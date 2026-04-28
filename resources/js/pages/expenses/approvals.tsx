import { BudgetBar } from '@/components/budget-bar';
import { PageHeader } from '@/components/page-header';
import { SoftCard, SoftCardBody, SoftCardTitle } from '@/components/soft-card';
import { StatCard } from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/hooks/use-initials';
import AppLayout from '@/layouts/app-layout';
import { COLOR_DOT, type ProjectColor } from '@/lib/projects';
import { EXPENSE_CATEGORY_META, formatMoney, type ExpenseCategory } from '@/lib/expenses';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Banknote, Check, CheckCircle2, Hourglass, X, XCircle } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Finance', href: '/dashboard' },
    { title: 'Expenses', href: '/expenses' },
    { title: 'Approvals', href: '/expenses/approvals' },
];

interface PendingExpense {
    id: number;
    reference: string;
    title: string;
    description: string | null;
    amount: string;
    currency: string;
    category: ExpenseCategory;
    expense_date: string;
    project: { id: number; slug: string; title: string; color: ProjectColor; budget: string | null };
    submitter: { id: number; name: string; avatar?: string | null; job_title?: string | null };
}

interface ProjectBudget {
    id: number;
    slug: string;
    title: string;
    color: ProjectColor;
    budget: number;
    spent: number;
    pending: number;
    remaining: number;
    utilization: number;
    over_budget: boolean;
    currency: string;
}

interface ApprovalsProps {
    pending: PendingExpense[];
    projectBudgets: ProjectBudget[];
    stats: { pending_count: number; pending_amount: number; approved_this_month: number; rejected_this_month: number };
}

export default function ExpenseApprovals({ pending, projectBudgets, stats }: ApprovalsProps) {
    const getInitials = useInitials();

    const decide = (id: number, decision: 'approved' | 'rejected') => {
        if (decision === 'rejected') {
            const note = window.prompt('Reason for rejection?');
            if (!note) return;
            router.patch(route('expenses.decide', id), { decision, note }, { preserveScroll: true });
        } else {
            router.patch(route('expenses.decide', id), { decision }, { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Expense approvals" />
            <div className="flex w-full flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Finance"
                    title="Approval queue"
                    description="Triage pending expenses and keep a live read on each project's budget."
                />

                <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="Pending requests" value={stats.pending_count} icon={Hourglass} accent="amber" />
                    <StatCard label="Pending amount" value={formatMoney(stats.pending_amount)} icon={Banknote} accent="violet" />
                    <StatCard label="Approved this month" value={formatMoney(stats.approved_this_month)} icon={CheckCircle2} accent="emerald" />
                    <StatCard label="Rejected this month" value={stats.rejected_this_month} icon={XCircle} accent="rose" />
                </section>

                <div className="grid gap-5 lg:grid-cols-[1fr_360px]">
                    <SoftCard>
                        <SoftCardTitle eyebrow="Queue">{pending.length} expense{pending.length === 1 ? '' : 's'} awaiting decision</SoftCardTitle>
                        <SoftCardBody className="space-y-3">
                            {pending.length === 0 && (
                                <div className="bg-muted/40 ring-border/60 ring-1 rounded-xl py-12 text-center">
                                    <CheckCircle2 className="text-emerald-500 mx-auto size-8" />
                                    <p className="font-display mt-2 text-base font-bold">All caught up</p>
                                    <p className="text-muted-foreground mt-1 text-xs">No pending expenses to review.</p>
                                </div>
                            )}
                            {pending.map((e) => {
                                const cat = EXPENSE_CATEGORY_META[e.category];
                                return (
                                    <div key={e.id} className="bg-muted/30 ring-border/60 hover:ring-foreground/20 ring-1 hover:shadow-soft-sm rounded-2xl p-4 transition-all">
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0 flex-1">
                                                <div className="text-muted-foreground flex items-center gap-2 text-[11px]">
                                                    <span className="font-mono">{e.reference}</span>
                                                    <span>·</span>
                                                    <span className="inline-flex items-center gap-1">
                                                        <span className={cn('size-1.5 rounded-full', COLOR_DOT[e.project.color] ?? 'bg-violet-500')} />
                                                        {e.project.title}
                                                    </span>
                                                </div>
                                                <Link href={route('expenses.show', e.id)} className="mt-1 block text-sm font-semibold hover:text-violet-600 dark:hover:text-violet-300">
                                                    {e.title}
                                                </Link>
                                                {e.description && <p className="text-muted-foreground mt-1 line-clamp-2 text-xs">{e.description}</p>}
                                                <div className="mt-2 flex flex-wrap items-center gap-2">
                                                    <span className={cn('inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold ring-1 ring-inset', cat?.chip)}>
                                                        {cat?.label}
                                                    </span>
                                                    <span className="text-muted-foreground text-[11px]">{new Date(e.expense_date).toLocaleDateString()}</span>
                                                    <span className="text-muted-foreground inline-flex items-center gap-1.5 text-[11px]">
                                                        <span className="from-violet-500 to-indigo-600 ring-card flex size-5 items-center justify-center rounded-full bg-gradient-to-br text-[9px] font-bold text-white ring-2">
                                                            {getInitials(e.submitter.name)}
                                                        </span>
                                                        {e.submitter.name}
                                                    </span>
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <p className="font-display text-lg font-bold tabular-nums">{formatMoney(e.amount, e.currency)}</p>
                                            </div>
                                        </div>
                                        <div className="mt-3 flex items-center gap-2">
                                            <Button
                                                size="sm"
                                                onClick={() => decide(e.id, 'approved')}
                                                className="from-emerald-500 to-teal-600 gap-1.5 bg-gradient-to-br"
                                            >
                                                <Check className="size-3.5" /> Approve
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => decide(e.id, 'rejected')}
                                                className="text-rose-600 ring-rose-200 hover:bg-rose-50 hover:ring-rose-300 gap-1.5"
                                            >
                                                <X className="size-3.5" /> Reject
                                            </Button>
                                            <Button asChild size="sm" variant="ghost" className="ml-auto">
                                                <Link href={route('expenses.show', e.id)}>Review</Link>
                                            </Button>
                                        </div>
                                    </div>
                                );
                            })}
                        </SoftCardBody>
                    </SoftCard>

                    <aside className="space-y-4">
                        <SoftCard>
                            <SoftCardTitle eyebrow="Live budgets">Affected projects</SoftCardTitle>
                            <SoftCardBody className="space-y-4">
                                {projectBudgets.length === 0 && <p className="text-muted-foreground text-xs">Nothing pending.</p>}
                                {projectBudgets.map((p) => (
                                    <div key={p.id} className="bg-muted/30 ring-border/60 ring-1 rounded-xl p-3">
                                        <Link href={route('projects.show', p.slug)} className="text-sm font-semibold hover:text-violet-600 dark:hover:text-violet-300">{p.title}</Link>
                                        <div className="mt-2"><BudgetBar budget={p.budget} spent={p.spent} pending={p.pending} utilization={p.utilization} overBudget={p.over_budget} currency={p.currency} compact /></div>
                                    </div>
                                ))}
                            </SoftCardBody>
                        </SoftCard>
                    </aside>
                </div>
            </div>
        </AppLayout>
    );
}
