<?php

namespace App\Modules\ExpenseManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ExpenseManagement\Http\Requests\DecideExpenseRequest;
use App\Modules\ExpenseManagement\Http\Requests\StoreExpenseRequest;
use App\Modules\ExpenseManagement\Http\Requests\UpdateExpenseRequest;
use App\Modules\ExpenseManagement\Models\Expense;
use App\Modules\ExpenseManagement\Services\ExpenseService;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\UserManagement\Models\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    public function __construct(private readonly ExpenseService $expenses) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->only(['search', 'project', 'status', 'category', 'view']);

        $base = Expense::query()
            ->visibleTo($user)
            ->with(['project:id,slug,title,color,budget', 'submitter:id,name,avatar', 'approver:id,name,avatar']);

        $base = $this->applyFilters($base, $filters);

        $stats = [
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->where('status', 'pending')->count(),
            'approved_amount' => (float) (clone $base)->where('status', 'approved')->sum('amount'),
            'pending_amount' => (float) (clone $base)->where('status', 'pending')->sum('amount'),
        ];

        $expenses = $base->latest('expense_date')->latest('id')->paginate(20)->withQueryString();

        $projects = Project::visibleTo($user)
            ->orderBy('title')
            ->get(['id', 'slug', 'title', 'color', 'budget']);

        return Inertia::render('expenses/index', [
            'expenses' => $expenses,
            'filters' => $filters,
            'stats' => $stats,
            'projects' => $projects,
            'statuses' => Expense::STATUSES,
            'categories' => Expense::CATEGORIES,
        ]);
    }

    public function approvals(Request $request): Response
    {
        if (! $request->user()->hasPermission('expenses.approve')) {
            abort(403);
        }

        $pending = Expense::query()
            ->where('status', 'pending')
            ->with(['project:id,slug,title,color,budget', 'submitter:id,name,avatar,job_title'])
            ->orderBy('expense_date')
            ->get();

        $byProject = Project::query()
            ->whereIn('id', $pending->pluck('project_id')->unique())
            ->get(['id', 'slug', 'title', 'color', 'budget'])
            ->map(function (Project $project) {
                return array_merge(
                    $project->only(['id', 'slug', 'title', 'color', 'budget']),
                    ExpenseService::projectBudgetSummary($project),
                );
            });

        $stats = [
            'pending_count' => $pending->count(),
            'pending_amount' => (float) $pending->sum('amount'),
            'approved_this_month' => (float) Expense::where('status', 'approved')
                ->whereMonth('decided_at', now()->month)
                ->whereYear('decided_at', now()->year)
                ->sum('amount'),
            'rejected_this_month' => Expense::where('status', 'rejected')
                ->whereMonth('decided_at', now()->month)
                ->whereYear('decided_at', now()->year)
                ->count(),
        ];

        return Inertia::render('expenses/approvals', [
            'pending' => $pending,
            'projectBudgets' => $byProject,
            'stats' => $stats,
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('expenses/create', [
            'projects' => Project::visibleTo($user)->orderBy('title')->get(['id', 'slug', 'title', 'color', 'currency']),
            'categories' => Expense::CATEGORIES,
            'currencies' => Expense::CURRENCIES,
            'preselect_project_id' => $request->integer('project_id') ?: null,
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $expense = $this->expenses->create($request->validated(), $request->user(), $request->file('receipt'));

        return redirect()
            ->route('expenses.show', $expense)
            ->with('status', 'Expense submitted.');
    }

    public function show(Request $request, Expense $expense): Response
    {
        $user = $request->user();
        if (! Expense::visibleTo($user)->whereKey($expense->id)->exists()) {
            abort(403);
        }

        $expense->load(['project:id,slug,title,color,budget', 'submitter:id,name,avatar,job_title', 'approver:id,name,avatar,job_title']);

        $activities = Activity::query()
            ->where('module', 'expenses')
            ->whereJsonContains('properties->expense_id', $expense->id)
            ->latest()
            ->limit(40)
            ->get()
            ->load('user:id,name,avatar');

        return Inertia::render('expenses/show', [
            'expense' => $expense,
            'activities' => $activities,
            'budget' => ExpenseService::projectBudgetSummary($expense->project),
            'canEdit' => $user->hasPermission('expenses.approve') || ($expense->user_id === $user->id && $expense->status !== 'approved'),
            'canApprove' => $user->hasPermission('expenses.approve') && $expense->status === 'pending',
            'canDelete' => $user->hasPermission('expenses.delete') || ($expense->user_id === $user->id && $expense->status === 'pending'),
        ]);
    }

    public function edit(Request $request, Expense $expense): Response
    {
        $user = $request->user();
        if (! ($user->hasPermission('expenses.approve') || ($expense->user_id === $user->id && $expense->status !== 'approved'))) {
            abort(403);
        }

        return Inertia::render('expenses/edit', [
            'expense' => $expense,
            'projects' => Project::visibleTo($user)->orderBy('title')->get(['id', 'slug', 'title', 'color', 'currency']),
            'categories' => Expense::CATEGORIES,
            'currencies' => Expense::CURRENCIES,
        ]);
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $this->expenses->update($expense, $request->validated(), $request->file('receipt'));

        return redirect()
            ->route('expenses.show', $expense)
            ->with('status', 'Expense updated.');
    }

    public function decide(DecideExpenseRequest $request, Expense $expense): RedirectResponse
    {
        if ($expense->status !== 'pending') {
            return back()->with('status', 'This expense already has a decision.');
        }

        $note = $request->input('note');
        if ($request->input('decision') === 'approved') {
            $this->expenses->approve($expense, $request->user(), $note);
        } else {
            $this->expenses->reject($expense, $request->user(), $note ?? 'Rejected');
        }

        return back()->with('status', 'Decision recorded.');
    }

    public function destroy(Request $request, Expense $expense): RedirectResponse
    {
        $user = $request->user();
        if (! ($user->hasPermission('expenses.delete') || ($expense->user_id === $user->id && $expense->status === 'pending'))) {
            abort(403);
        }

        $this->expenses->delete($expense);

        return redirect()->route('expenses.index')->with('status', 'Expense deleted.');
    }

    public function downloadReceipt(Request $request, Expense $expense): StreamedResponse
    {
        $user = $request->user();
        if (! Expense::visibleTo($user)->whereKey($expense->id)->exists()) {
            abort(403);
        }
        if (! $expense->receipt_path) {
            abort(404);
        }

        return $this->expenses->downloadReceipt($expense);
    }

    private function applyFilters($query, array $filters)
    {
        return $query
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%");
                });
            })
            ->when($filters['project'] ?? null, fn ($q, $slug) => $q->whereHas('project', fn ($p) => $p->where('slug', $slug)))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['category'] ?? null, fn ($q, $c) => $q->where('category', $c));
    }
}
