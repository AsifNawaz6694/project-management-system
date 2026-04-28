import { ConfirmDialog } from '@/components/confirm-dialog';
import { PageHeader } from '@/components/page-header';
import { SoftCard, SoftCardBody } from '@/components/soft-card';
import { StatCard } from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/hooks/use-initials';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type PaginatedResponse } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    AtSign,
    Bell,
    Calendar,
    CalendarClock,
    CheckCheck,
    CheckCircle2,
    FolderKanban,
    ListChecks,
    Sparkles,
    Trash2,
    Wallet,
    XCircle,
    type LucideIcon,
} from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Workspace', href: '/dashboard' },
    { title: 'Notifications', href: '/notifications' },
];

interface NotificationItem {
    id: number;
    type: string;
    group: string;
    title: string;
    body: string | null;
    icon: string | null;
    tone: string;
    link: string | null;
    read_at: string | null;
    created_at: string;
    actor: { id: number; name: string; avatar?: string | null } | null;
}

interface NotificationsIndexProps {
    notifications: PaginatedResponse<NotificationItem>;
    stats: {
        total: number;
        unread: number;
        by_group: Array<{ group: string; total: number; unread: number }>;
    };
}

const ICONS: Record<string, LucideIcon> = {
    'list-checks': ListChecks,
    'wallet': Wallet,
    'check-circle-2': CheckCircle2,
    'x-circle': XCircle,
    'at-sign': AtSign,
    'folder-kanban': FolderKanban,
    'calendar-clock': CalendarClock,
    'sparkles': Sparkles,
};

const TONES: Record<string, string> = {
    violet: 'from-violet-500 to-indigo-600',
    blue: 'from-blue-500 to-cyan-600',
    emerald: 'from-emerald-500 to-teal-600',
    amber: 'from-amber-400 to-orange-500',
    rose: 'from-rose-500 to-pink-600',
    pink: 'from-pink-500 to-fuchsia-600',
    slate: 'from-slate-500 to-slate-700',
};

export default function NotificationsIndex({ notifications, stats }: NotificationsIndexProps) {
    const getInitials = useInitials();
    const [confirmClear, setConfirmClear] = useState(false);

    const markAll = () => {
        router.patch(route('notifications.read-all'), {}, { preserveScroll: true });
    };

    const clearAll = () => setConfirmClear(true);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Notifications" />
            <div className="flex w-full flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Workspace"
                    title="Notifications"
                    description="Every signal from across the workspace, grouped and time-stamped."
                    actions={
                        <>
                            <Button onClick={markAll} disabled={stats.unread === 0} variant="secondary" size="sm" className="gap-1.5">
                                <CheckCheck className="size-3.5" /> Mark all read
                            </Button>
                            <Button onClick={clearAll} variant="outline" size="sm" className="text-rose-600 ring-rose-200 hover:bg-rose-50 hover:ring-rose-300 gap-1.5">
                                <Trash2 className="size-3.5" /> Clear
                            </Button>
                        </>
                    }
                />

                <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="All notifications" value={stats.total} icon={Bell} accent="violet" />
                    <StatCard label="Unread" value={stats.unread} icon={Sparkles} accent="rose" />
                    <StatCard label="Mentions" value={stats.by_group.find((g) => g.group === 'mentions')?.total ?? 0} icon={AtSign} accent="pink" />
                    <StatCard label="Tasks" value={stats.by_group.find((g) => g.group === 'tasks')?.total ?? 0} icon={ListChecks} accent="amber" />
                </section>

                <SoftCard>
                    <SoftCardBody className="p-0">
                        {notifications.data.length === 0 ? (
                            <div className="p-16 text-center">
                                <div className="from-violet-500 to-indigo-600 shadow-glow mx-auto flex size-14 items-center justify-center rounded-2xl bg-gradient-to-br text-white">
                                    <Bell className="size-6" />
                                </div>
                                <p className="font-display mt-3 text-lg font-bold">All caught up</p>
                                <p className="text-muted-foreground mt-1 text-sm">No notifications yet.</p>
                            </div>
                        ) : (
                            <ul className="divide-y divide-border/60">
                                {notifications.data.map((item) => {
                                    const Icon = ICONS[item.icon ?? ''] ?? Sparkles;
                                    const tone = TONES[item.tone] ?? TONES.violet;
                                    const Wrap: 'div' | typeof Link = item.link ? Link : 'div';
                                    const props = item.link ? { href: item.link } : {};
                                    return (
                                        <li
                                            key={item.id}
                                            className={cn(
                                                'group/item relative animate-[fade-in-up_0.3s_ease-out]',
                                                !item.read_at && 'bg-violet-50/40 dark:bg-violet-500/[0.05]',
                                            )}
                                        >
                                            <Wrap
                                                {...(props as Record<string, string>)}
                                                onClick={() => {
                                                    if (!item.read_at) {
                                                        router.patch(route('notifications.read', item.id), {}, { preserveScroll: true, preserveState: true });
                                                    }
                                                }}
                                                className="hover:bg-muted/40 flex items-start gap-4 px-5 py-4 transition-colors"
                                            >
                                                <div className={cn('flex size-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br text-white shadow-soft-sm', tone)}>
                                                    <Icon className="size-4" />
                                                </div>
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex items-center gap-2">
                                                        <p className="text-sm font-semibold">{item.title}</p>
                                                        <span className="bg-muted text-muted-foreground inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider">
                                                            {item.group}
                                                        </span>
                                                        {!item.read_at && <span className="bg-violet-500 size-1.5 rounded-full" />}
                                                    </div>
                                                    {item.body && <p className="text-muted-foreground mt-1 text-xs leading-relaxed">{item.body}</p>}
                                                    <p className="text-muted-foreground mt-1.5 inline-flex items-center gap-1.5 text-[11px]">
                                                        {item.actor && (
                                                            <span className="from-violet-500 to-indigo-600 ring-card flex size-4 items-center justify-center rounded-full bg-gradient-to-br text-[8px] font-bold text-white ring-2">
                                                                {getInitials(item.actor.name)}
                                                            </span>
                                                        )}
                                                        {item.actor?.name ?? 'System'}
                                                        <span>·</span>
                                                        <Calendar className="size-3" />
                                                        {new Date(item.created_at).toLocaleString()}
                                                    </p>
                                                </div>
                                                <button
                                                    onClick={(e) => {
                                                        e.preventDefault();
                                                        e.stopPropagation();
                                                        router.delete(route('notifications.destroy', item.id), { preserveScroll: true });
                                                    }}
                                                    className="text-muted-foreground/40 hover:text-rose-500 inline-flex size-7 shrink-0 items-center justify-center rounded-lg opacity-0 transition-all group-hover/item:opacity-100"
                                                >
                                                    <Trash2 className="size-3.5" />
                                                </button>
                                            </Wrap>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </SoftCardBody>
                </SoftCard>

                {notifications.last_page > 1 && (
                    <nav className="flex items-center justify-center gap-1.5">
                        {notifications.links.map((link, i) => (
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

            <ConfirmDialog
                open={confirmClear}
                onOpenChange={setConfirmClear}
                title="Clear all notifications?"
                description="This permanently removes every notification from your inbox. This cannot be undone."
                confirmLabel="Clear all"
                tone="warning"
                icon={Trash2}
                onConfirm={() => {
                    router.delete(route('notifications.clear-all'), { preserveScroll: true });
                    setConfirmClear(false);
                }}
            />
        </AppLayout>
    );
}
