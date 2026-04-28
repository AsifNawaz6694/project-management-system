<?php

namespace App\Modules\ExpenseManagement\Models;

use App\Models\User;
use App\Modules\ProjectManagement\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Expense extends Model
{
    use SoftDeletes;

    public const STATUSES = ['pending', 'approved', 'rejected'];

    public const CATEGORIES = ['travel', 'meals', 'supplies', 'software', 'services', 'hardware', 'subscriptions', 'other'];

    public const CURRENCIES = ['SAR', 'PKR', 'USD'];

    public const DEFAULT_CURRENCY = 'SAR';

    protected $fillable = [
        'project_id',
        'user_id',
        'approver_id',
        'reference',
        'title',
        'description',
        'amount',
        'currency',
        'category',
        'status',
        'expense_date',
        'receipt_path',
        'receipt_disk',
        'receipt_name',
        'decision_note',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date',
            'decided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Expense $expense) {
            if (! $expense->reference) {
                $expense->reference = 'EXP-'.strtoupper(Str::random(8));
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin() || $user->hasPermission('expenses.approve')) {
            return $query;
        }

        return $query->where('user_id', $user->id);
    }
}
