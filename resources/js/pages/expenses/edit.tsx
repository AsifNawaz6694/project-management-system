import { PageHeader } from '@/components/page-header';
import { ExpenseForm } from '@/components/expense-form';
import AppLayout from '@/layouts/app-layout';
import { type ExpenseCategory } from '@/lib/expenses';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

interface ExpenseEditProps {
    expense: {
        id: number;
        project_id: number;
        title: string;
        description: string | null;
        amount: string;
        currency: string;
        category: ExpenseCategory;
        expense_date: string;
        receipt_name: string | null;
    };
    projects: Array<{ id: number; slug: string; title: string; color: string; currency?: string | null }>;
    categories: string[];
    currencies: string[];
}

export default function ExpensesEdit({ expense, projects, categories, currencies }: ExpenseEditProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Finance', href: '/dashboard' },
        { title: 'Expenses', href: '/expenses' },
        { title: expense.title, href: route('expenses.show', expense.id) },
        { title: 'Edit', href: route('expenses.edit', expense.id) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${expense.title}`} />
            <div className="flex w-full flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader eyebrow="Finance" title={`Edit ${expense.title}`} description="Update the amount, category, or replace the receipt." />
                <ExpenseForm
                    initial={{
                        project_id: expense.project_id,
                        title: expense.title,
                        description: expense.description ?? '',
                        amount: String(expense.amount),
                        currency: expense.currency,
                        category: expense.category,
                        expense_date: (expense.expense_date as unknown as string)?.slice(0, 10) ?? '',
                        receipt: null,
                    }}
                    projects={projects}
                    categories={categories}
                    currencies={currencies}
                    submitUrl={route('expenses.update', expense.id)}
                    submitMethod="patch"
                    submitLabel="Save changes"
                    cancelUrl={route('expenses.show', expense.id)}
                    existingReceiptName={expense.receipt_name}
                />
            </div>
        </AppLayout>
    );
}
