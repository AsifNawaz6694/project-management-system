import { PageHeader } from '@/components/page-header';
import { ExpenseForm } from '@/components/expense-form';
import AppLayout from '@/layouts/app-layout';
import { type ExpenseCategory } from '@/lib/expenses';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Finance', href: '/dashboard' },
    { title: 'Expenses', href: '/expenses' },
    { title: 'New', href: '/expenses/create' },
];

interface ExpenseCreateProps {
    projects: Array<{ id: number; slug: string; title: string; color: string; currency?: string | null }>;
    categories: string[];
    currencies: string[];
    preselect_project_id?: number | null;
}

export default function ExpensesCreate({ projects, categories, currencies, preselect_project_id }: ExpenseCreateProps) {
    const preselected = projects.find((p) => p.id === (preselect_project_id ?? projects[0]?.id));
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New expense" />
            <div className="flex w-full flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader eyebrow="Finance" title="Submit a new expense" description="Pick a project, attach a receipt, and we'll route it for approval." />
                <ExpenseForm
                    initial={{
                        project_id: preselect_project_id ?? projects[0]?.id ?? null,
                        title: '',
                        description: '',
                        amount: '',
                        currency: preselected?.currency ?? 'SAR',
                        category: 'other' as ExpenseCategory,
                        expense_date: new Date().toISOString().slice(0, 10),
                        receipt: null,
                    }}
                    projects={projects}
                    categories={categories}
                    currencies={currencies}
                    submitUrl={route('expenses.store')}
                    submitMethod="post"
                    submitLabel="Submit expense"
                    cancelUrl={route('expenses.index')}
                />
            </div>
        </AppLayout>
    );
}
