import { ConfirmDialog } from '@/components/confirm-dialog';
import { PageHeader } from '@/components/page-header';
import { RoleBadge } from '@/components/role-badge';
import { SoftCard, SoftCardBody, SoftCardTitle } from '@/components/soft-card';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/hooks/use-initials';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type RoleSummary, type User } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Briefcase, Building2, CheckCircle2, Mail, Pencil, Phone, ShieldCheck, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface ActivityRecord {
    id: number;
    action: string;
    module: string | null;
    description: string | null;
    created_at: string;
}

interface UserShowProps {
    user: User & { roles: (RoleSummary & { permissions: { slug: string; name: string; module: string }[] })[] };
    activities: ActivityRecord[];
    permissionSlugs: string[];
}

type Tab = 'overview' | 'permissions' | 'activity';

export default function UserShow({ user, activities, permissionSlugs }: UserShowProps) {
    const { can, user: me } = usePermissions();
    const getInitials = useInitials();
    const [tab, setTab] = useState<Tab>('overview');
    const [confirmDelete, setConfirmDelete] = useState(false);
    const isMe = me?.id === user.id;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Workspace', href: '/dashboard' },
        { title: 'Users', href: '/users' },
        { title: user.name, href: route('users.show', user.id) },
    ];

    const grouped = permissionSlugs.reduce<Record<string, string[]>>((acc, slug) => {
        const [module] = slug.split('.');
        acc[module] = acc[module] || [];
        acc[module].push(slug);
        return acc;
    }, {});

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={user.name} />
            <div className="flex w-full flex-1 flex-col gap-6 p-4 md:p-6">
                <Link
                    href={route('users.index')}
                    className="text-muted-foreground hover:text-foreground inline-flex w-fit items-center gap-1.5 text-xs font-medium transition-colors"
                >
                    <ArrowLeft className="size-3.5" /> Back to directory
                </Link>

                <SoftCard className="overflow-visible">
                    <div className="from-violet-600 via-indigo-600 to-blue-600 relative h-40 overflow-hidden rounded-t-2xl bg-gradient-to-br">
                        <div className="absolute -right-20 -top-20 size-72 rounded-full bg-white/15 blur-3xl" />
                        <div className="absolute -bottom-10 left-1/3 size-56 rounded-full bg-pink-400/30 blur-3xl" />
                        <div className="absolute inset-0" style={{ backgroundImage: 'radial-gradient(rgba(255,255,255,0.18) 1px, transparent 1px)', backgroundSize: '24px 24px' }} />
                    </div>
                    <div className="relative -mt-16 flex flex-col gap-5 px-6 pb-6 md:flex-row md:items-end md:justify-between">
                        <div className="flex flex-col items-start gap-4 md:flex-row md:items-end">
                            <div className="from-violet-500 to-indigo-600 shadow-soft-lg ring-card flex size-28 items-center justify-center rounded-3xl bg-gradient-to-br text-3xl font-bold text-white ring-4">
                                {user.initials || getInitials(user.name)}
                            </div>
                            <div className="space-y-1.5 pb-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <h2 className="font-display text-2xl font-bold tracking-tight md:text-3xl">{user.name}</h2>
                                    {user.roles?.map((r) => <RoleBadge key={r.slug} role={r.slug} />)}
                                </div>
                                <p className="text-muted-foreground text-sm">
                                    {user.job_title}
                                    {user.department && <span> · {user.department}</span>}
                                </p>
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {can('users.update') && (
                                <Button asChild variant="secondary" size="sm" className="gap-1.5">
                                    <Link href={route('users.edit', user.id)}>
                                        <Pencil className="size-3.5" /> Edit
                                    </Link>
                                </Button>
                            )}
                            {can('users.delete') && !isMe && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="text-rose-600 ring-rose-200 hover:bg-rose-50 dark:text-rose-400 dark:ring-rose-500/30 hover:ring-rose-300 gap-1.5"
                                    onClick={() => setConfirmDelete(true)}
                                >
                                    <Trash2 className="size-3.5" /> Delete
                                </Button>
                            )}
                        </div>
                    </div>

                    <div className="grid gap-3 border-t border-border/60 px-6 py-5 sm:grid-cols-2 lg:grid-cols-4">
                        <InfoRow icon={Mail} label="Email" value={user.email} tone="from-violet-500 to-indigo-600" />
                        <InfoRow icon={Phone} label="Phone" value={user.phone ?? '—'} tone="from-emerald-500 to-teal-600" />
                        <InfoRow icon={Briefcase} label="Role" value={user.roles?.[0]?.name ?? '—'} tone="from-amber-500 to-orange-600" />
                        <InfoRow icon={Building2} label="Department" value={user.department ?? '—'} tone="from-pink-500 to-fuchsia-600" />
                    </div>
                </SoftCard>

                <div className="bg-card ring-border/60 ring-1 shadow-soft-xs flex w-fit gap-1 rounded-2xl p-1">
                    {(['overview', 'permissions', 'activity'] as Tab[]).map((t) => (
                        <button
                            key={t}
                            onClick={() => setTab(t)}
                            className={cn(
                                'relative rounded-xl px-4 py-2 text-xs font-semibold capitalize transition-all',
                                tab === t
                                    ? 'shadow-soft-md from-violet-600 to-indigo-600 bg-gradient-to-br text-white'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {t}
                        </button>
                    ))}
                </div>

                {tab === 'overview' && (
                    <div className="grid gap-4 lg:grid-cols-3">
                        <SoftCard className="lg:col-span-2">
                            <SoftCardTitle eyebrow="Performance">Workload overview</SoftCardTitle>
                            <SoftCardBody className="grid gap-3 sm:grid-cols-3">
                                {[
                                    { label: 'Active projects', value: '—', tone: 'text-violet-600' },
                                    { label: 'Open tasks', value: '—', tone: 'text-blue-600' },
                                    { label: 'Completed (30d)', value: '—', tone: 'text-emerald-600' },
                                ].map((stat) => (
                                    <div key={stat.label} className="bg-muted/40 ring-border/50 ring-1 rounded-xl p-4">
                                        <p className="text-muted-foreground text-[10px] font-bold uppercase tracking-[0.14em]">{stat.label}</p>
                                        <p className={'font-display mt-1.5 text-2xl font-bold ' + stat.tone}>{stat.value}</p>
                                    </div>
                                ))}
                            </SoftCardBody>
                        </SoftCard>

                        <SoftCard>
                            <SoftCardTitle eyebrow="Account">Status & security</SoftCardTitle>
                            <SoftCardBody>
                                <dl className="space-y-3 text-sm">
                                    <Row label="Status"><span className="capitalize">{user.status ?? '—'}</span></Row>
                                    <Row label="2FA">{user.two_factor_enabled ? 'Enabled' : 'Disabled'}</Row>
                                    <Row label="Joined">{new Date(user.created_at).toLocaleDateString()}</Row>
                                </dl>
                            </SoftCardBody>
                        </SoftCard>
                    </div>
                )}

                {tab === 'permissions' && (
                    <SoftCard>
                        <SoftCardTitle
                            eyebrow="Access"
                            action={
                                <span className="text-muted-foreground inline-flex items-center gap-1.5 text-xs">
                                    <ShieldCheck className="size-3.5 text-violet-500" />
                                    {permissionSlugs.length} granted
                                </span>
                            }
                        >
                            Effective permissions
                        </SoftCardTitle>
                        <SoftCardBody className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {Object.entries(grouped).map(([module, slugs]) => (
                                <div key={module} className="bg-muted/40 ring-border/50 ring-1 rounded-xl p-4">
                                    <p className="text-muted-foreground text-[10px] font-bold uppercase tracking-[0.14em]">{module}</p>
                                    <ul className="mt-2.5 space-y-1.5">
                                        {slugs.map((slug) => (
                                            <li key={slug} className="flex items-center gap-1.5 text-xs">
                                                <CheckCircle2 className="size-3.5 text-emerald-500" />
                                                <span className="font-mono">{slug}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            ))}
                            {Object.keys(grouped).length === 0 && <p className="text-muted-foreground text-xs">No permissions granted.</p>}
                        </SoftCardBody>
                    </SoftCard>
                )}

                {tab === 'activity' && (
                    <SoftCard>
                        <SoftCardTitle eyebrow="Timeline">Recent activity</SoftCardTitle>
                        <SoftCardBody>
                            <ol className="relative space-y-5 pl-6 before:absolute before:left-2 before:top-1.5 before:bottom-1.5 before:w-px before:bg-border">
                                {activities.length === 0 && <p className="text-muted-foreground text-xs">No recorded activity yet.</p>}
                                {activities.map((entry, i) => (
                                    <li key={entry.id} className="relative">
                                        <span
                                            className={cn(
                                                'shadow-soft-xs ring-card absolute -left-6 top-0.5 size-3 rounded-full ring-2',
                                                i % 4 === 0 && 'bg-gradient-to-br from-violet-500 to-indigo-600',
                                                i % 4 === 1 && 'bg-gradient-to-br from-emerald-500 to-teal-600',
                                                i % 4 === 2 && 'bg-gradient-to-br from-amber-500 to-orange-600',
                                                i % 4 === 3 && 'bg-gradient-to-br from-pink-500 to-fuchsia-600',
                                            )}
                                        />
                                        <p className="text-sm">{entry.description ?? entry.action}</p>
                                        <p className="text-muted-foreground mt-0.5 text-xs">
                                            {entry.module && <span className="font-mono">{entry.module}</span>}
                                            {entry.module && ' · '}
                                            {new Date(entry.created_at).toLocaleString()}
                                        </p>
                                    </li>
                                ))}
                            </ol>
                        </SoftCardBody>
                    </SoftCard>
                )}
            </div>

            <ConfirmDialog
                open={confirmDelete}
                onOpenChange={setConfirmDelete}
                title={`Delete ${user.name}?`}
                description={`This permanently removes ${user.name} from the workspace, along with their role assignments. Their authored work (tasks, projects, comments) will remain. This cannot be undone.`}
                confirmLabel="Delete user"
                tone="destructive"
                icon={Trash2}
                onConfirm={() => {
                    router.delete(route('users.destroy', user.id));
                    setConfirmDelete(false);
                }}
            />
        </AppLayout>
    );
}

function InfoRow({ icon: Icon, label, value, tone }: { icon: typeof Mail; label: string; value: string; tone: string }) {
    return (
        <div className="flex items-center gap-3">
            <div className={`flex size-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br ${tone} text-white shadow-soft-sm`}>
                <Icon className="size-4" />
            </div>
            <div className="min-w-0">
                <p className="text-muted-foreground text-[10px] font-bold uppercase tracking-[0.14em]">{label}</p>
                <p className="truncate text-sm font-medium">{value}</p>
            </div>
        </div>
    );
}

function Row({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="flex items-center justify-between">
            <dt className="text-muted-foreground text-[10px] font-bold uppercase tracking-[0.14em]">{label}</dt>
            <dd className="text-sm font-medium">{children}</dd>
        </div>
    );
}
