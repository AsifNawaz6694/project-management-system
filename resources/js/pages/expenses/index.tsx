import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useInitials } from '@/hooks/use-initials';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { COLOR_DOT, type ProjectColor } from '@/lib/projects';
import { EXPENSE_CATEGORY_META, EXPENSE_STATUS_META, formatMoney, type ExpenseCategory, type ExpenseStatus } from '@/lib/expenses';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type PaginatedResponse } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Banknote, CheckCircle2, Hourglass, Plus, Receipt, Search } from 'lucide-react';
import { useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Finance', href: '/dashboard' },
    { title: 'Expenses', href: '/expenses' },
];

interface ExpenseRow {
    id: number;
    reference: string;
    title: string;
    amount: string;
    currency: string;
    category: ExpenseCategory;
    status: ExpenseStatus;
    expense_date: string;
    project: { id: number; slug: string; title: string; color: ProjectColor };
    submitter: { id: number; name: string; avatar?: string | null };
    approver?: { id: number; name: string; avatar?: string | null } | null;
}

interface ExpensesIndexProps {
    expenses: PaginatedResponse<ExpenseRow>;
    filters: { search?: string; project?: string; status?: string; category?: string };
    stats: { total: number; pending: number; approved_amount: number; pending_amount: number };
    projects: Array<{ id: number; slug: string; title: string; color: ProjectColor }>;
    statuses: string[];
    categories: string[];
}

export default function ExpensesIndex({ expenses, filters, stats, projects, statuses, categories }: ExpensesIndexProps) {
    const { can } = usePermissions();
    const getInitials = useInitials();
    const [search, setSearch] = useState(filters.search ?? '');
    const [project, setProject] = useState(filters.project ?? 'all');
    const [status, setStatus] = useState(filters.status ?? 'all');
    const [category, setCategory] = useState(filters.category ?? 'all');

    useEffect(() => {
        const handle = setTimeout(() => {
            router.get(
                route('expenses.index'),
                {
                    search: search || undefined,
                    project: project === 'all' ? undefined : project,
                    status: status === 'all' ? undefined : status,
                    category: category === 'all' ? undefined : category,
                },
                { preserveState: true, replace: true, preserveScroll: true },
            );
        }, 250);
        return () => clearTimeout(handle);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search, project, status, category]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Expenses" />
            <div className="flex w-full flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Finance"
                    title="Expenses"
                    description={can('expenses.approve') ? 'Every expense across the workspace, with quick filters and approval state.' : 'Track every expense you have submitted.'}
                    actions={
                        <>
                            {can('expenses.approve') && (
                                <Button asChild variant="secondary" className="gap-2">
                                    <Link href={route('expenses.approvals')}>
                                        <Receipt className="size-4" /> Approvals
                                    </Link>
                                </Button>
                            )}
                            {can('expenses.create') && (
                                <Button asChild className="gap-2">
                                    <Link href={route('expenses.create')}>
                                        <Plus className="size-4" /> New expense
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

                <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="All expenses" value={stats.total} icon={Receipt} accent="violet" />
                    <StatCard label="Pending" value={stats.pending} sub={formatMoney(stats.pending_amount)} icon={Hourglass} accent="amber" />
                    <StatCard label="Approved (visible)" value={formatMoney(stats.approved_amount)} icon={CheckCircle2} accent="emerald" />
                    <StatCard label="Total submitted" value={formatMoney(stats.approved_amount + stats.pending_amount)} icon={Banknote} accent="blue" />
                </section>

                <section className="bg-card shadow-soft-sm ring-border/60 ring-1 flex flex-col gap-3 rounded-2xl p-3 md:flex-row md:items-center">
                    <div className="relative flex-1">
                        <Search className="text-muted-foreground absolute left-3.5 top-1/2 size-4 -translate-y-1/2" />
                        <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search expenses…" className="h-11 pl-10" />
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Select value={project} onValueChange={setProject}>
                            <SelectTrigger className="h-11 w-[180px] rounded-xl"><SelectValue placeholder="All projects" /></SelectTrigger>
                            <SelectContent className="rounded-xl">
                                <SelectItem value="all">All projects</SelectItem>
                                {projects.map((p) => <SelectItem key={p.slug} value={p.slug}>{p.title}</SelectItem>)}
                            </SelectContent>
                        </Select>
                        <Select value={status} onValueChange={setStatus}>
                            <SelectTrigger className="h-11 w-[160px] rounded-xl"><SelectValue placeholder="All statuses" /></SelectTrigger>
                            <SelectContent className="rounded-xl">
                                <SelectItem value="all">All statuses</SelectItem>
                                {statuses.map((s) => <SelectItem key={s} value={s}>{EXPENSE_STATUS_META[s as ExpenseStatus]?.label ?? s}</SelectItem>)}
                            </SelectContent>
                        </Select>
                        <Select value={category} onValueChange={setCategory}>
                            <SelectTrigger className="h-11 w-[160px] rounded-xl"><SelectValue placeholder="All categories" /></SelectTrigger>
                            <SelectContent className="rounded-xl">
                                <SelectItem value="all">All categories</SelectItem>
                                {categories.map((c) => <SelectItem key={c} value={c}>{EXPENSE_CATEGORY_META[c as ExpenseCategory]?.label ?? c}</SelectItem>)}
                            </SelectContent>
                        </Select>
                    </div>
                </section>

                <section className="bg-card shadow-soft-sm ring-border/60 ring-1 overflow-hidden rounded-2xl">
                    <div className="bg-muted/40 hidden grid-cols-[120px_1fr_180px_140px_140px_120px_140px] gap-3 px-5 py-3 text-[10px] font-bold uppercase tracking-[0.14em] text-muted-foreground md:grid">
                        <span>Reference</span>
                        <span>Title</span>
                        <span>Project</span>
                        <span>Category</span>
                        <span>Amount</span>
                        <span>Status</span>
                        <span className="text-right">Submitter</span>
                    </div>
                    <ul className="divide-y divide-border/60">
                        {expenses.data.length === 0 && (
                            <li className="p-12 text-center text-sm text-muted-foreground">No expenses found.</li>
                        )}
                        {expenses.data.map((e) => {
                            const sMeta = EXPENSE_STATUS_META[e.status];
                            const cMeta = EXPENSE_CATEGORY_META[e.category];
                            return (
                                <li key={e.id}>
                                    <Link
                                        href={route('expenses.show', e.id)}
                                        className="group hover:bg-muted/40 grid grid-cols-1 gap-2 px-5 py-3.5 transition-colors md:grid-cols-[120px_1fr_180px_140px_140px_120px_140px] md:items-center md:gap-3"
                                    >
                                        <span className="text-muted-foreground font-mono text-[11px]">{e.reference}</span>
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-semibold group-hover:text-violet-600 dark:group-hover:text-violet-300">{e.title}</p>
                                            <p className="text-muted-foreground text-[11px]">{new Date(e.expense_date).toLocaleDateString()}</p>
                                        </div>
                                        <div className="text-muted-foreground inline-flex items-center gap-2 text-xs">
                                            <span className={cn('size-1.5 rounded-full', COLOR_DOT[e.project?.color] ?? 'bg-violet-500')} />
                                            <span className="truncate">{e.project?.title}</span>
                                        </div>
                                        <span className={cn('inline-flex w-fit items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset', cMeta?.chip)}>
                                            {cMeta?.label}
                                        </span>
                                        <span className="font-display text-sm font-bold tabular-nums">{formatMoney(e.amount, e.currency)}</span>
                                        <span className={cn('inline-flex w-fit items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset', sMeta?.chip)}>
                                            <span className={cn('size-1 rounded-full', sMeta?.dot)} />
                                            {sMeta?.label}
                                        </span>
                                        <div className="flex items-center justify-end gap-2">
                                            <div className="ring-card flex size-7 items-center justify-center rounded-full bg-gradient-to-br from-violet-500 to-indigo-600 text-[10px] font-bold text-white ring-2" title={e.submitter.name}>
                                                {getInitials(e.submitter.name)}
                                            </div>
                                            <span className="hidden text-xs md:inline">{e.submitter.name.split(' ')[0]}</span>
                                        </div>
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                </section>

                {expenses.last_page > 1 && (
                    <nav className="flex items-center justify-center gap-1.5">
                        {expenses.links.map((link, i) => (
                            <Link
                                key={i}
                                href={link.url ?? '#'}
                                preserveScroll
                                preserveState
                                disabled={!link.url}
                                className={
                                    'inline-flex h-9 min-w-9 items-center justify-center rounded-lg px-3 text-xs font-semibold transition-all ' +
                                    (link.active
                                        ? 'shadow-soft-md from-violet-600 to-indigo-600 bg-gradient-to-br text-white'
                                        : 'bg-card ring-border ring-1 text-muted-foreground hover:text-foreground hover:shadow-soft-sm')
                                }
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </nav>
                )}
            </div>
        </AppLayout>
    );
}
