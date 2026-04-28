<?php

namespace App\Modules\ExpenseManagement\Http\Requests;

use App\Modules\ExpenseManagement\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('expenses.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999.99'],
            'currency' => ['nullable', Rule::in(Expense::CURRENCIES)],
            'category' => ['required', Rule::in(Expense::CATEGORIES)],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'receipt' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,gif,pdf,webp'],
        ];
    }
}
