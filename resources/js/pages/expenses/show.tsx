import { BudgetBar } from '@/components/budget-bar';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { PageHeader } from '@/components/page-header';
import { SoftCard, SoftCardBody, SoftCardTitle } from '@/components/soft-card';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/hooks/use-initials';
import AppLayout from '@/layouts/app-layout';
import { COLOR_DOT, type ProjectColor } from '@/lib/projects';
import { EXPENSE_CATEGORY_META, EXPENSE_STATUS_META, formatMoney, type ExpenseCategory, type ExpenseStatus } from '@/lib/expenses';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, CalendarDays, Check, Download, Pencil, Trash2, X } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

interface ExpenseShowProps {
    expense: {
        id: number;
        reference: string;
        title: string;
        description: string | null;
        amount: string;
        currency: string;
        category: ExpenseCategory;
        status: ExpenseStatus;
        expense_date: string;
        decided_at: string | null;
        decision_note: string | null;
        receipt_path: string | null;
        receipt_name: string | null;
        project: { id: number; slug: string; title: string; color: ProjectColor; budget: string | null };
        submitter: { id: number; name: string; avatar?: string | null; job_title?: string | null };
        approver: { id: number; name: string; avatar?: string | null; job_title?: string | null } | null;
        created_at: string;
    };
    activities: Array<{ id: number; description: string | null; action: string; created_at: string; user?: { id: number; name: string } | null }>;
    budget: { budget: number; spent: number; pending: number; remaining: number; utilization: number; over_budget: boolean; currency: string };
    canEdit: boolean;
    canApprove: boolean;
    canDelete: boolean;
}

export default function ExpenseShow({ expense, activities, budget, canEdit, canApprove, canDelete }: ExpenseShowProps) {
    const getInitials = useInitials();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Finance', href: '/dashboard' },
        { title: 'Expenses', href: '/expenses' },
        { title: expense.reference, href: route('expenses.show', expense.id) },
    ];

    const status = EXPENSE_STATUS_META[expense.status];
    const cat = EXPENSE_CATEGORY_META[expense.category];
    const [decisionMode, setDecisionMode] = useState<'approved' | 'rejected' | null>(null);
    const [confirmDelete, setConfirmDelete] = useState(false);

    const decideForm = useForm<{ decision: 'approved' | 'rejected'; note: string }>({ decision: 'approved', note: '' });
    const submitDecision: FormEventHandler = (e) => {
        e.preventDefault();
        decideForm.patch(route('expenses.decide', expense.id), {
            preserveScroll: true,
            onSuccess: () => {
                setDecisionMode(null);
                decideForm.reset('note');
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={expense.title} />
            <div className="flex w-full flex-1 flex-col gap-6 p-4 md:p-6">
                <Link href={route('expenses.index')} className="text-muted-foreground hover:text-foreground inline-flex w-fit items-center gap-1.5 text-xs font-medium transition-colors">
                    <ArrowLeft className="size-3.5" /> Back to expenses
                </Link>

                <PageHeader
                    eyebrow={
                        <span className="inline-flex items-center gap-2">
                            <span className="font-mono">{expense.reference}</span>
                            <span className="text-muted-foreground">·</span>
                            <Link href={route('projects.show', expense.project.slug)} className="hover:text-foreground inline-flex items-center gap-1.5 transition-colors">
                                <span className={cn('size-1.5 rounded-full', COLOR_DOT[expense.project.color] ?? 'bg-violet-500')} />
                                {expense.project.title}
                            </Link>
                        </span> as unknown as string
                    }
                    title={expense.title}
                    description={expense.description ?? undefined}
                    actions={
                        <>
                            {canEdit && (
                                <Button asChild variant="secondary" size="sm" className="gap-1.5">
                                    <Link href={route('expenses.edit', expense.id)}><Pencil className="size-3.5" /> Edit</Link>
                                </Button>
                            )}
                            {canDelete && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="text-rose-600 ring-rose-200 hover:bg-rose-50 dark:text-rose-400 dark:ring-rose-500/30 hover:ring-rose-300 gap-1.5"
                                    onClick={() => setConfirmDelete(true)}
                                >
                                    <Trash2 className="size-3.5" />
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="grid gap-5 lg:grid-cols-[1fr_320px]">
                    <div className="space-y-5">
                        <SoftCard>
                            <div className="grid gap-5 p-6 md:grid-cols-3">
                                <div className="md:col-span-2 space-y-3">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className={cn('inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-[0.14em] ring-1 ring-inset', status?.chip)}>
                                            <span className={cn('size-1.5 rounded-full', status?.dot)} />
                                            {status?.label}
                                        </span>
                                        <span className={cn('inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold ring-1 ring-inset', cat?.chip)}>
                                            {cat?.label}
                                        </span>
                                        <span className="text-muted-foreground inline-flex items-center gap-1.5 text-xs">
                                            <CalendarDays className="size-3.5" /> {new Date(expense.expense_date).toLocaleDateString()}
                                        </span>
                                    </div>
                                    {expense.description && <p className="text-sm leading-relaxed">{expense.description}</p>}
                                </div>
                                <div className="from-violet-600 via-indigo-600 to-blue-600 shadow-soft-md flex flex-col items-end justify-center rounded-2xl bg-gradient-to-br p-5 text-white">
                                    <p className="text-[10px] font-bold uppercase tracking-[0.14em] text-white/80">Amount</p>
                                    <p className="font-display mt-1 text-3xl font-bold tabular-nums">{formatMoney(expense.amount, expense.currency)}</p>
                                </div>
                            </div>

                            {expense.decision_note && (
                                <div className="border-t border-border/60 px-6 py-4">
                                    <p className="text-muted-foreground text-[10px] font-bold uppercase tracking-[0.14em]">Decision note</p>
                                    <p className="mt-1 text-sm">{expense.decision_note}</p>
                                </div>
                            )}
                        </SoftCard>

                        {canApprove && (
                            <SoftCard>
                                <SoftCardTitle eyebrow="Approval">Decide on this expense</SoftCardTitle>
                                <SoftCardBody className="space-y-4">
                                    {decisionMode === null ? (
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            <Button
                                                onClick={() => {
                                                    decideForm.setData('decision', 'approved');
                                                    setDecisionMode('approved');
                                                }}
                                                className="from-emerald-500 to-teal-600 gap-2 bg-gradient-to-br h-12"
                                            >
                                                <Check className="size-4" /> Approve {formatMoney(expense.amount, expense.currency)}
                                            </Button>
                                            <Button
                                                onClick={() => {
                                                    decideForm.setData('decision', 'rejected');
                                                    setDecisionMode('rejected');
                                                }}
                                                variant="destructive"
                                                className="gap-2 h-12"
                                            >
                                                <X className="size-4" /> Reject
                                            </Button>
                                        </div>
                                    ) : (
                                        <form onSubmit={submitDecision} className="space-y-3">
                                            <textarea
                                                value={decideForm.data.note}
                                                onChange={(e) => decideForm.setData('note', e.target.value)}
                                                placeholder={decisionMode === 'rejected' ? 'Reason (required for rejection)…' : 'Optional note…'}
                                                rows={3}
                                                className="bg-card ring-border focus-visible:border-foreground/30 focus-visible:ring-violet-200/60 dark:focus-visible:ring-violet-500/20 ring-1 w-full rounded-xl px-3.5 py-2.5 text-sm focus-visible:outline-none focus-visible:ring-4"
                                            />
                                            <div className="flex items-center justify-end gap-2">
                                                <Button type="button" variant="ghost" onClick={() => setDecisionMode(null)}>Cancel</Button>
                                                <Button
                                                    type="submit"
                                                    disabled={decideForm.processing || (decisionMode === 'rejected' && !decideForm.data.note.trim())}
                                                    className={cn(decisionMode === 'rejected' ? '' : 'from-emerald-500 to-teal-600 bg-gradient-to-br', 'gap-2')}
                                                    variant={decisionMode === 'rejected' ? 'destructive' : 'default'}
                                                >
                                                    {decisionMode === 'approved' ? <Check className="size-4" /> : <X className="size-4" />}
                                                    Confirm {decisionMode === 'approved' ? 'approval' : 'rejection'}
                                                </Button>
                                            </div>
                                        </form>
                                    )}
                                </SoftCardBody>
                            </SoftCard>
                        )}

                        <SoftCard>
                            <SoftCardTitle eyebrow="Project">Live budget</SoftCardTitle>
                            <SoftCardBody>
                                <BudgetBar
                                    budget={budget.budget}
                                    spent={budget.spent}
                                    pending={budget.pending}
                                    utilization={budget.utilization}
                                    overBudget={budget.over_budget}
                                    currency={budget.currency}
                                />
                                <p className="text-muted-foreground mt-3 text-xs">
                                    {formatMoney(budget.pending, budget.currency)} pending · {formatMoney(budget.remaining, budget.currency)} remaining
                                </p>
                            </SoftCardBody>
                        </SoftCard>

                        <SoftCard>
                            <SoftCardTitle
                                eyebrow="Receipt"
                                action={
                                    expense.receipt_path && (
                                        <Button asChild variant="soft" size="sm" className="gap-1.5">
                                            <a href={route('expenses.receipt', expense.id)}>
                                                <Download className="size-3.5" /> Download
                                            </a>
                                        </Button>
                                    )
                                }
                            >
                                Attached file
                            </SoftCardTitle>
                            <SoftCardBody>
                                {expense.receipt_path ? (
                                    <p className="text-muted-foreground text-sm">{expense.receipt_name ?? 'receipt'}</p>
                                ) : (
                                    <p className="text-muted-foreground text-xs italic">No receipt attached.</p>
                                )}
                            </SoftCardBody>
                        </SoftCard>
                    </div>

                    <aside className="space-y-4">
                        <SoftCard>
                            <SoftCardTitle eyebrow="People">Submission</SoftCardTitle>
                            <SoftCardBody className="space-y-3 text-sm">
                                <Person label="Submitted by" user={expense.submitter} />
                                {expense.approver && <Person label={expense.status === 'approved' ? 'Approved by' : 'Decided by'} user={expense.approver} />}
                                <Row label="Submitted">{new Date(expense.created_at).toLocaleString()}</Row>
                                {expense.decided_at && <Row label="Decided">{new Date(expense.decided_at).toLocaleString()}</Row>}
                            </SoftCardBody>
                        </SoftCard>

                        <SoftCard>
                            <SoftCardTitle eyebrow="Timeline">Activity</SoftCardTitle>
                            <SoftCardBody>
                                <ol className="relative space-y-4 pl-6 before:absolute before:bottom-1.5 before:left-2 before:top-1.5 before:w-px before:bg-border">
                                    {activities.length === 0 && <p className="text-muted-foreground text-xs">No activity yet.</p>}
                                    {activities.map((a, i) => (
                                        <li key={a.id} className="relative">
                                            <span
                                                className={cn(
                                                    'shadow-soft-xs ring-card absolute -left-6 top-0.5 size-3 rounded-full ring-2',
                                                    i % 4 === 0 && 'bg-gradient-to-br from-violet-500 to-indigo-600',
                                                    i % 4 === 1 && 'bg-gradient-to-br from-emerald-500 to-teal-600',
                                                    i % 4 === 2 && 'bg-gradient-to-br from-amber-500 to-orange-600',
                                                    i % 4 === 3 && 'bg-gradient-to-br from-pink-500 to-fuchsia-600',
                                                )}
                                            />
                                            <p className="text-sm">{a.description ?? a.action}</p>
                                            <p className="text-muted-foreground mt-0.5 text-[11px]">{new Date(a.created_at).toLocaleString()}</p>
                                        </li>
                                    ))}
                                </ol>
                            </SoftCardBody>
                        </SoftCard>
                    </aside>
                </div>
            </div>

            <ConfirmDialog
                open={confirmDelete}
                onOpenChange={setConfirmDelete}
                title={`Delete expense ${expense.reference}?`}
                description={`This permanently removes "${expense.title}" and its receipt. This cannot be undone.`}
                confirmLabel="Delete expense"
                tone="destructive"
                icon={Trash2}
                onConfirm={() => {
                    router.delete(route('expenses.destroy', expense.id));
                    setConfirmDelete(false);
                }}
            />
        </AppLayout>
    );

    function Person({ label, user }: { label: string; user: { id: number; name: string; job_title?: string | null } }) {
        return (
            <div className="flex items-center gap-3">
                <div className="from-violet-500 to-indigo-600 ring-card flex size-10 items-center justify-center rounded-xl bg-gradient-to-br text-xs font-bold text-white shadow-soft-xs ring-2">
                    {getInitials(user.name)}
                </div>
                <div className="min-w-0 flex-1">
                    <p className="text-muted-foreground text-[10px] font-bold uppercase tracking-[0.14em]">{label}</p>
                    <Link href={route('users.show', user.id)} className="block truncate text-sm font-semibold hover:text-violet-600 dark:hover:text-violet-300">
                        {user.name}
                    </Link>
                    {user.job_title && <p className="text-muted-foreground truncate text-[11px]">{user.job_title}</p>}
                </div>
            </div>
        );
    }

    function Row({ label, children }: { label: string; children: React.ReactNode }) {
        return (
            <div className="flex items-center justify-between">
                <dt className="text-muted-foreground text-[10px] font-bold uppercase tracking-[0.14em]">{label}</dt>
                <dd>{children}</dd>
            </div>
        );
    }
}
