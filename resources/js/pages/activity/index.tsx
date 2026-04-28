import { PageHeader } from '@/components/page-header';
import { SoftCard, SoftCardBody } from '@/components/soft-card';
import { StatCard } from '@/components/stat-card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useInitials } from '@/hooks/use-initials';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type PaginatedResponse } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Activity as ActivityIcon, AlertTriangle, CalendarClock, Search, ShieldAlert } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Actor {
    id: number;
    name: string;
    avatar?: string | null;
}

interface ActivityRow {
    id: number;
    action: string;
    module: string | null;
    description: string | null;
    properties: Record<string, unknown> | null;
    ip_address: string | null;
    created_at: string;
    user: Actor | null;
    subject_user: Actor | null;
}

interface ActivityIndexProps {
    activities: PaginatedResponse<ActivityRow>;
    filters: { module?: string; user_id?: string; action?: string; date_from?: string; date_to?: string; search?: string };
    modules: string[];
    actions: string[];
    users: Array<{ id: number; name: string }>;
    stats: { total: number; today: number; this_week: number; auth_failures: number };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Administration', href: '/dashboard' },
    { title: 'Activity log', href: '/activity' },
];

const MODULE_TONES: Record<string, string> = {
    auth: 'from-rose-500 to-pink-600',
    users: 'from-violet-500 to-indigo-600',
    roles: 'from-amber-400 to-orange-500',
    projects: 'from-blue-500 to-indigo-600',
    tasks: 'from-emerald-500 to-teal-600',
    expenses: 'from-amber-500 to-orange-600',
    files: 'from-pink-500 to-fuchsia-600',
    notifications: 'from-sky-400 to-cyan-500',
    reports: 'from-violet-500 to-purple-600',
};

export default function ActivityIndex({ activities, filters, modules, actions, users, stats }: ActivityIndexProps) {
    const getInitials = useInitials();
    const [search, setSearch] = useState(filters.search ?? '');
    const [module, setModule] = useState(filters.module ?? 'all');
    const [userId, setUserId] = useState(filters.user_id ?? 'all');
    const [action, setAction] = useState(filters.action ?? '');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');

    useEffect(() => {
        const handle = setTimeout(() => {
            router.get(
                route('activity.index'),
                {
                    search: search || undefined,
                    module: module === 'all' ? undefined : module,
                    user_id: userId === 'all' ? undefined : userId,
                    action: action || undefined,
                    date_from: dateFrom || undefined,
                    date_to: dateTo || undefined,
                },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 250);
        return () => clearTimeout(handle);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search, module, userId, action, dateFrom, dateTo]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Activity log" />
            <div className="flex w-full flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Administration"
                    title="Activity log"
                    description="A complete, chronological record of every action taken across the workspace."
                />

                <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="Total events" value={stats.total} icon={ActivityIcon} accent="violet" />
                    <StatCard label="Today" value={stats.today} icon={CalendarClock} accent="emerald" />
                    <StatCard label="This week" value={stats.this_week} icon={CalendarClock} accent="blue" />
                    <StatCard label="Auth failures" value={stats.auth_failures} icon={ShieldAlert} accent="rose" />
                </section>

                <SoftCard>
                    <div className="grid gap-3 p-4 md:grid-cols-6">
                        <div className="relative md:col-span-2">
                            <Search className="text-muted-foreground absolute left-3.5 top-1/2 size-4 -translate-y-1/2" />
                            <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search descriptions…" className="h-11 pl-10" />
                        </div>
                        <Select value={module} onValueChange={setModule}>
                            <SelectTrigger className="h-11 rounded-xl"><SelectValue placeholder="All modules" /></SelectTrigger>
                            <SelectContent className="rounded-xl">
                                <SelectItem value="all">All modules</SelectItem>
                                {modules.map((m) => <SelectItem key={m} value={m}>{m}</SelectItem>)}
                            </SelectContent>
                        </Select>
                        <Select value={userId} onValueChange={setUserId}>
                            <SelectTrigger className="h-11 rounded-xl"><SelectValue placeholder="Any user" /></SelectTrigger>
                            <SelectContent className="rounded-xl">
                                <SelectItem value="all">Any user</SelectItem>
                                {users.map((u) => <SelectItem key={u.id} value={String(u.id)}>{u.name}</SelectItem>)}
                            </SelectContent>
                        </Select>
                        <Input type="date" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} className="h-11" />
                        <Input type="date" value={dateTo} onChange={(e) => setDateTo(e.target.value)} className="h-11" />
                    </div>
                </SoftCard>

                <SoftCard>
                    <SoftCardBody className="overflow-x-auto">
                        <table className="w-full min-w-[760px] text-sm">
                            <thead>
                                <tr className="text-muted-foreground border-b border-border/60 text-left text-[10px] font-bold uppercase tracking-[0.14em]">
                                    <th className="py-2 pr-3">When</th>
                                    <th className="py-2 pr-3">Actor</th>
                                    <th className="py-2 pr-3">Module</th>
                                    <th className="py-2 pr-3">Action</th>
                                    <th className="py-2 pr-3">Description</th>
                                    <th className="py-2">IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                {activities.data.length === 0 && (
                                    <tr><td colSpan={6} className="py-8 text-center text-xs text-muted-foreground">No activity matches those filters.</td></tr>
                                )}
                                {activities.data.map((a) => {
                                    const tone = MODULE_TONES[a.module ?? ''] ?? 'from-slate-500 to-slate-700';
                                    const isAuthFailure = a.action.startsWith('auth.') && a.action.includes('failed');
                                    return (
                                        <tr key={a.id} className="border-b border-border/40 align-top last:border-0">
                                            <td className="py-3 pr-3 text-xs whitespace-nowrap">
                                                <p className="font-semibold tabular-nums">{new Date(a.created_at).toLocaleString()}</p>
                                                <p className="text-muted-foreground text-[10px]">{relTime(a.created_at)}</p>
                                            </td>
                                            <td className="py-3 pr-3">
                                                {a.user ? (
                                                    <Link href={route('users.show', a.user.id)} className="inline-flex items-center gap-2 hover:text-violet-600 dark:hover:text-violet-300">
                                                        <span className="from-violet-500 to-indigo-600 ring-card flex size-7 items-center justify-center rounded-full bg-gradient-to-br text-[10px] font-bold text-white ring-2">
                                                            {getInitials(a.user.name)}
                                                        </span>
                                                        <span className="text-sm font-semibold">{a.user.name}</span>
                                                    </Link>
                                                ) : <span className="text-muted-foreground text-xs">system</span>}
                                            </td>
                                            <td className="py-3 pr-3">
                                                {a.module && (
                                                    <span className={cn('inline-flex items-center gap-1.5 rounded-full bg-gradient-to-br px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.12em] text-white shadow-soft-xs', tone)}>
                                                        {a.module}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="py-3 pr-3">
                                                <span className={cn('inline-flex items-center gap-1 rounded-md bg-muted px-2 py-0.5 font-mono text-[11px]', isAuthFailure && 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300')}>
                                                    {isAuthFailure && <AlertTriangle className="size-3" />}
                                                    {a.action}
                                                </span>
                                            </td>
                                            <td className="py-3 pr-3 text-sm">{a.description ?? '—'}</td>
                                            <td className="py-3 text-xs font-mono text-muted-foreground">{a.ip_address ?? '—'}</td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </SoftCardBody>
                </SoftCard>

                {activities.last_page > 1 && (
                    <nav className="flex items-center justify-center gap-1.5">
                        {activities.links.map((link, i) => (
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

function relTime(iso: string): string {
    const diff = (Date.now() - new Date(iso).getTime()) / 1000;
    if (diff < 60) return 'just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;
    return new Date(iso).toLocaleDateString();
}
