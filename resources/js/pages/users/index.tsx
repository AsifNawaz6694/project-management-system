import { PageHeader } from '@/components/page-header';
import { RoleBadge } from '@/components/role-badge';
import { StatCard } from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useInitials } from '@/hooks/use-initials';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type PaginatedResponse, type RoleSummary, type User } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Crown, Mail, Phone, Search, Shield, UserPlus, Users } from 'lucide-react';
import { useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Workspace', href: '/dashboard' },
    { title: 'Users', href: '/users' },
];

interface UsersIndexProps {
    users: PaginatedResponse<User & { roles: RoleSummary[] }>;
    filters: { search?: string; role?: string; status?: string };
    roles: { id: number; slug: string; name: string }[];
    stats: { total: number; active: number; admins: number; managers: number };
}

const AVATAR_TONES = [
    'from-violet-500 to-indigo-600',
    'from-blue-500 to-cyan-600',
    'from-emerald-500 to-teal-600',
    'from-amber-500 to-orange-600',
    'from-pink-500 to-fuchsia-600',
    'from-rose-500 to-red-600',
];

const tone = (id: number) => AVATAR_TONES[id % AVATAR_TONES.length];

const STATUS_CHIP: Record<string, string> = {
    active: 'bg-emerald-50 text-emerald-700 ring-emerald-200/70 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/30',
    invited: 'bg-amber-50 text-amber-700 ring-amber-200/70 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30',
    suspended: 'bg-rose-50 text-rose-700 ring-rose-200/70 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/30',
};

export default function UsersIndex({ users, filters, roles, stats }: UsersIndexProps) {
    const { can } = usePermissions();
    const getInitials = useInitials();
    const [search, setSearch] = useState(filters.search ?? '');
    const [role, setRole] = useState(filters.role ?? 'all');
    const [status, setStatus] = useState(filters.status ?? 'all');

    useEffect(() => {
        const handle = setTimeout(() => {
            router.get(
                route('users.index'),
                {
                    search: search || undefined,
                    role: role === 'all' ? undefined : role,
                    status: status === 'all' ? undefined : status,
                },
                { preserveState: true, replace: true, preserveScroll: true },
            );
        }, 250);
        return () => clearTimeout(handle);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search, role, status]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Users" />
            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-8">
                <PageHeader
                    eyebrow="Workspace"
                    title="User directory"
                    description="A complete view of every member in the workspace, their role, status, and how to reach them."
                    actions={
                        can('users.create') && (
                            <Button asChild className="gap-2">
                                <Link href={route('users.create')}>
                                    <UserPlus className="size-4" /> Invite user
                                </Link>
                            </Button>
                        )
                    }
                />

                <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="Total members" value={stats.total} icon={Users} accent="violet" />
                    <StatCard label="Active" value={stats.active} icon={Users} accent="emerald" />
                    <StatCard label="Admins" value={stats.admins} icon={Crown} accent="amber" />
                    <StatCard label="Managers" value={stats.managers} icon={Shield} accent="blue" />
                </section>

                <section className="bg-card shadow-soft-sm ring-border/60 ring-1 flex flex-col gap-3 rounded-2xl p-3 md:flex-row md:items-center">
                    <div className="relative flex-1">
                        <Search className="text-muted-foreground absolute left-3.5 top-1/2 size-4 -translate-y-1/2" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search by name, email, job title…"
                            className="h-11 pl-10"
                        />
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Select value={role} onValueChange={setRole}>
                            <SelectTrigger className="h-11 w-[160px] rounded-xl">
                                <SelectValue placeholder="All roles" />
                            </SelectTrigger>
                            <SelectContent className="rounded-xl">
                                <SelectItem value="all">All roles</SelectItem>
                                {roles.map((r) => (
                                    <SelectItem key={r.slug} value={r.slug}>
                                        {r.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select value={status} onValueChange={setStatus}>
                            <SelectTrigger className="h-11 w-[160px] rounded-xl">
                                <SelectValue placeholder="All statuses" />
                            </SelectTrigger>
                            <SelectContent className="rounded-xl">
                                <SelectItem value="all">All statuses</SelectItem>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="invited">Invited</SelectItem>
                                <SelectItem value="suspended">Suspended</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </section>

                {users.data.length === 0 ? (
                    <div className="bg-card shadow-soft-sm ring-border/60 ring-1 flex flex-col items-center justify-center gap-3 rounded-2xl p-12 text-center">
                        <div className="bg-violet-50 dark:bg-violet-500/10 rounded-2xl p-3">
                            <Users className="size-5 text-violet-600 dark:text-violet-300" />
                        </div>
                        <div>
                            <p className="font-display font-bold">No users match those filters</p>
                            <p className="text-muted-foreground mt-1 text-sm">Try clearing the search or role filter.</p>
                        </div>
                    </div>
                ) : (
                    <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {users.data.map((u) => (
                            <Link
                                key={u.id}
                                href={route('users.show', u.id)}
                                className="group bg-card shadow-soft-sm hover:shadow-soft-lg ring-border/60 hover:ring-foreground/20 ring-1 relative flex flex-col overflow-hidden rounded-2xl transition-all duration-300 hover:-translate-y-1"
                            >
                                <div className={`relative h-20 bg-gradient-to-br ${tone(u.id)} overflow-hidden`}>
                                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_30%_50%,rgba(255,255,255,0.25),transparent)]" />
                                </div>
                                <div className="relative -mt-9 flex flex-col items-center px-5 pb-5 text-center">
                                    <div className={`flex size-16 items-center justify-center rounded-2xl bg-gradient-to-br ${tone(u.id)} text-lg font-bold text-white shadow-soft-md ring-4 ring-card`}>
                                        {u.initials || getInitials(u.name)}
                                    </div>
                                    <p className="font-display mt-3 truncate text-base font-bold">{u.name}</p>
                                    <p className="text-muted-foreground truncate text-xs">{u.job_title || u.department || '—'}</p>
                                    {u.roles?.[0] && <div className="mt-2"><RoleBadge role={u.roles[0].slug} /></div>}
                                </div>
                                <div className="text-muted-foreground bg-muted/40 border-border/50 mt-auto space-y-1 border-t px-5 py-3 text-[11px]">
                                    <div className="flex items-center gap-2 truncate">
                                        <Mail className="size-3 shrink-0" />
                                        <span className="truncate">{u.email}</span>
                                    </div>
                                    {u.phone && (
                                        <div className="flex items-center gap-2 truncate">
                                            <Phone className="size-3 shrink-0" />
                                            <span className="truncate">{u.phone}</span>
                                        </div>
                                    )}
                                </div>
                                {u.status && (
                                    <div className="border-border/50 absolute right-3 top-3">
                                        <span
                                            className={
                                                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold ring-1 ring-inset capitalize ' +
                                                (STATUS_CHIP[u.status as string] ?? STATUS_CHIP.active)
                                            }
                                        >
                                            <span className="size-1 rounded-full bg-current" />
                                            {u.status}
                                        </span>
                                    </div>
                                )}
                            </Link>
                        ))}
                    </section>
                )}

                {users.last_page > 1 && (
                    <nav className="flex items-center justify-center gap-1.5">
                        {users.links.map((link, i) => (
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
