<?php

namespace App\Modules\ExpenseManagement\Services;

use App\Models\User;
use App\Modules\ExpenseManagement\Models\Expense;
use App\Modules\NotificationCenter\Models\Notification;
use App\Modules\NotificationCenter\Services\NotificationService;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\UserManagement\Models\Activity;
use App\Modules\UserManagement\Models\Role;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ExpenseService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function create(array $data, User $actor, ?UploadedFile $receipt = null): Expense
    {
        return DB::transaction(function () use ($data, $actor, $receipt) {
            $expense = Expense::create([
                'project_id' => $data['project_id'],
                'user_id' => $actor->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? Expense::DEFAULT_CURRENCY,
                'category' => $data['category'],
                'expense_date' => $data['expense_date'],
                'status' => 'pending',
            ]);

            if ($receipt) {
                $this->storeReceipt($expense, $receipt);
            }

            Activity::log('expense.submitted', [
                'module' => 'expenses',
                'description' => "Submitted {$expense->reference} for \"{$expense->title}\" ({$expense->currency} {$expense->amount})",
                'properties' => ['expense_id' => $expense->id, 'project_id' => $expense->project_id],
            ]);

            $approverIds = User::query()
                ->whereHas('roles', fn ($q) => $q->whereIn('slug', [Role::ADMIN, Role::MANAGER]))
                ->pluck('id')
                ->all();

            $this->notifications->push($approverIds, [
                'group' => Notification::GROUP_EXPENSES,
                'type' => 'expense.submitted',
                'title' => "{$actor->name} submitted an expense",
                'body' => "{$expense->reference} · {$expense->title} · {$expense->currency} {$expense->amount}",
                'icon' => 'wallet',
                'tone' => 'amber',
                'link' => route('expenses.show', $expense->id, false),
                'data' => ['expense_id' => $expense->id, 'project_id' => $expense->project_id],
            ], $actor->id);

            return $expense->load(['project', 'submitter']);
        });
    }

    public function update(Expense $expense, array $data, ?UploadedFile $receipt = null): Expense
    {
        return DB::transaction(function () use ($expense, $data, $receipt) {
            $expense->fill(array_filter([
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'amount' => $data['amount'] ?? null,
                'category' => $data['category'] ?? null,
                'expense_date' => $data['expense_date'] ?? null,
                'project_id' => $data['project_id'] ?? null,
            ], fn ($v) => $v !== null));

            if (isset($data['amount']) || isset($data['title'])) {
                if ($expense->status === 'rejected') {
                    $expense->status = 'pending';
                    $expense->decided_at = null;
                    $expense->approver_id = null;
                    $expense->decision_note = null;
                }
            }

            $expense->save();

            if ($receipt) {
                $this->deleteReceipt($expense);
                $this->storeReceipt($expense, $receipt);
            }

            Activity::log('expense.updated', [
                'module' => 'expenses',
                'description' => "Updated expense {$expense->reference}",
                'properties' => ['expense_id' => $expense->id, 'project_id' => $expense->project_id],
            ]);

            return $expense->fresh(['project', 'submitter']);
        });
    }

    public function approve(Expense $expense, User $approver, ?string $note = null): Expense
    {
        $expense->update([
            'status' => 'approved',
            'approver_id' => $approver->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ]);

        Activity::log('expense.approved', [
            'module' => 'expenses',
            'subject_user_id' => $expense->user_id,
            'description' => "Approved expense {$expense->reference}",
            'properties' => ['expense_id' => $expense->id, 'project_id' => $expense->project_id],
        ]);

        $this->notifications->push((int) $expense->user_id, [
            'group' => Notification::GROUP_EXPENSES,
            'type' => 'expense.approved',
            'title' => "{$approver->name} approved your expense",
            'body' => "{$expense->reference} · {$expense->title}",
            'icon' => 'check-circle-2',
            'tone' => 'emerald',
            'link' => route('expenses.show', $expense->id, false),
            'data' => ['expense_id' => $expense->id],
        ], $approver->id);

        return $expense;
    }

    public function reject(Expense $expense, User $approver, string $note): Expense
    {
        $expense->update([
            'status' => 'rejected',
            'approver_id' => $approver->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ]);

        Activity::log('expense.rejected', [
            'module' => 'expenses',
            'subject_user_id' => $expense->user_id,
            'description' => "Rejected expense {$expense->reference}",
            'properties' => ['expense_id' => $expense->id, 'project_id' => $expense->project_id],
        ]);

        $this->notifications->push((int) $expense->user_id, [
            'group' => Notification::GROUP_EXPENSES,
            'type' => 'expense.rejected',
            'title' => "{$approver->name} rejected your expense",
            'body' => $note,
            'icon' => 'x-circle',
            'tone' => 'rose',
            'link' => route('expenses.show', $expense->id, false),
            'data' => ['expense_id' => $expense->id],
        ], $approver->id);

        return $expense;
    }

    public function delete(Expense $expense): void
    {
        $this->deleteReceipt($expense);
        $expense->delete();
    }

    public function downloadReceipt(Expense $expense)
    {
        return Storage::disk($expense->receipt_disk)->download($expense->receipt_path, $expense->receipt_name ?? 'receipt');
    }

    public static function projectBudgetSummary(Project $project): array
    {
        $approved = (float) Expense::query()
            ->where('project_id', $project->id)
            ->where('status', 'approved')
            ->sum('amount');

        $pending = (float) Expense::query()
            ->where('project_id', $project->id)
            ->where('status', 'pending')
            ->sum('amount');

        $budget = (float) ($project->budget ?? 0);
        $remaining = max($budget - $approved, 0);
        $utilization = $budget > 0 ? min(100, (int) round(($approved / $budget) * 100)) : 0;

        return [
            'budget' => $budget,
            'spent' => $approved,
            'pending' => $pending,
            'remaining' => $remaining,
            'utilization' => $utilization,
            'over_budget' => $budget > 0 && $approved > $budget,
            'currency' => $project->currency ?? Project::DEFAULT_CURRENCY,
        ];
    }

    private function storeReceipt(Expense $expense, UploadedFile $file): void
    {
        $path = $file->store("expense-receipts/{$expense->id}", 'local');
        $expense->update([
            'receipt_path' => $path,
            'receipt_disk' => 'local',
            'receipt_name' => $file->getClientOriginalName(),
        ]);
    }

    private function deleteReceipt(Expense $expense): void
    {
        if ($expense->receipt_path) {
            Storage::disk($expense->receipt_disk)->delete($expense->receipt_path);
        }
    }
}
