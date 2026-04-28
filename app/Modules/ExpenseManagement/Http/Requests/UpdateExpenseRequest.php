<?php

namespace App\Modules\ExpenseManagement\Http\Requests;

use App\Modules\ExpenseManagement\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expense = $this->route('expense');
        $user = $this->user();
        if (! $expense || ! $user) {
            return false;
        }

        // Submitter can edit own pending/rejected expense; approvers can always edit.
        if ($user->hasPermission('expenses.approve')) {
            return true;
        }

        return $expense->user_id === $user->id && $expense->status !== 'approved';
    }

    public function rules(): array
    {
        return [
            'project_id' => ['sometimes', 'required', 'integer', 'exists:projects,id'],
            'title' => ['sometimes', 'required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01', 'max:9999999.99'],
            'currency' => ['sometimes', 'nullable', Rule::in(Expense::CURRENCIES)],
            'category' => ['sometimes', 'required', Rule::in(Expense::CATEGORIES)],
            'expense_date' => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'receipt' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,gif,pdf,webp'],
        ];
    }
}
