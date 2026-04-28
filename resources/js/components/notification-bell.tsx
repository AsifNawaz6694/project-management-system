import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import {
    AtSign,
    Bell,
    CalendarClock,
    CheckCircle2,
    CheckCheck,
    FolderKanban,
    ListChecks,
    Sparkles,
    Trash2,
    Wallet,
    XCircle,
    type LucideIcon,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

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

interface DropdownPayload {
    items: NotificationItem[];
    unread: number;
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

export function NotificationBell() {
    const page = usePage<SharedData>();
    const initialUnread = (page.props.notifications as { unread?: number } | null)?.unread ?? 0;
    const [unread, setUnread] = useState(initialUnread);
    const [items, setItems] = useState<NotificationItem[]>([]);
    const [loaded, setLoaded] = useState(false);
    const [open, setOpen] = useState(false);
    const getInitials = useInitials();

    const fetchItems = async () => {
        try {
            const res = await fetch('/notifications/dropdown', {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) return;
            const data: DropdownPayload = await res.json();
            setItems(data.items);
            setUnread(data.unread);
            setLoaded(true);
        } catch {
            // ignore
        }
    };

    useEffect(() => {
        setUnread(initialUnread);
    }, [initialUnread]);

    useEffect(() => {
        if (open && !loaded) fetchItems();
    }, [open, loaded]);

    useEffect(() => {
        const id = window.setInterval(() => {
            fetchItems();
        }, 30_000);
        return () => window.clearInterval(id);
    }, []);

    const grouped = useMemo(() => {
        const map = new Map<string, NotificationItem[]>();
        items.forEach((item) => {
            const list = map.get(item.group) ?? [];
            list.push(item);
            map.set(item.group, list);
        });
        return Array.from(map.entries());
    }, [items]);

    const markAll = () => {
        router.patch(route('notifications.read-all'), {}, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                setUnread(0);
                setItems((prev) => prev.map((i) => ({ ...i, read_at: i.read_at ?? new Date().toISOString() })));
            },
        });
    };

    const onItemClick = (item: NotificationItem) => {
        if (!item.read_at) {
            router.patch(route('notifications.read', item.id), {}, {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setItems((prev) => prev.map((i) => (i.id === item.id ? { ...i, read_at: new Date().toISOString() } : i)));
                    setUnread((u) => Math.max(0, u - 1));
                },
            });
        }
        setOpen(false);
    };

    return (
        <DropdownMenu open={open} onOpenChange={setOpen}>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    className="bg-card ring-border/70 shadow-soft-xs hover:shadow-soft-sm hover:ring-foreground/20 relative inline-flex size-10 items-center justify-center rounded-xl ring-1 transition-all"
                    aria-label="Notifications"
                >
                    <Bell className="size-4" />
                    {unread > 0 && (
                        <span
                            key={unread}
                            className="ring-card from-rose-500 to-pink-600 absolute -right-1 -top-1 inline-flex h-5 min-w-5 animate-[fade-in-up_0.25s_ease-out] items-center justify-center rounded-full bg-gradient-to-br px-1 text-[10px] font-bold text-white ring-2"
                        >
                            {unread > 99 ? '99+' : unread}
                        </span>
                    )}
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-[380px] rounded-2xl border-border/60 shadow-soft-lg p-0">
                <div className="flex items-center justify-between border-b border-border/60 px-4 py-3">
                    <div>
                        <p className="font-display text-sm font-bold tracking-tight">Notifications</p>
                        <p className="text-muted-foreground text-[11px]">{unread} unread</p>
                    </div>
                    <div className="flex items-center gap-1">
                        <button
                            onClick={markAll}
                            disabled={unread === 0}
                            className="text-muted-foreground hover:text-foreground hover:bg-muted/60 inline-flex items-center gap-1 rounded-lg px-2 py-1 text-[11px] font-semibold transition-all disabled:opacity-50"
                        >
                            <CheckCheck className="size-3.5" /> Mark all
                        </button>
                    </div>
                </div>
                <div className="scrollbar-soft max-h-[420px] overflow-y-auto">
                    {!loaded ? (
                        <div className="space-y-2 p-4">
                            {[0, 1, 2].map((i) => (
                                <div key={i} className="bg-muted/40 h-14 animate-pulse rounded-xl" />
                            ))}
                        </div>
                    ) : items.length === 0 ? (
                        <div className="p-10 text-center">
                            <div className="from-violet-500 to-indigo-600 mx-auto flex size-12 items-center justify-center rounded-2xl bg-gradient-to-br text-white shadow-soft-md">
                                <Bell className="size-5" />
                            </div>
                            <p className="font-display mt-3 text-sm font-bold">All caught up</p>
                            <p className="text-muted-foreground mt-1 text-xs">You'll see new alerts here as they come in.</p>
                        </div>
                    ) : (
                        grouped.map(([group, groupItems]) => (
                            <div key={group} className="border-b border-border/40 last:border-b-0">
                                <p className="text-muted-foreground bg-muted/30 px-4 py-1.5 text-[10px] font-bold uppercase tracking-[0.16em]">{group}</p>
                                <ul className="divide-y divide-border/40">
                                    {groupItems.map((item) => {
                                        const Icon = ICONS[item.icon ?? ''] ?? Sparkles;
                                        const tone = TONES[item.tone] ?? TONES.violet;
                                        const Wrap = item.link ? Link : 'div';
                                        const props = item.link ? { href: item.link } : {};
                                        return (
                                            <li
                                                key={item.id}
                                                className={cn(
                                                    'group/item relative animate-[fade-in-up_0.3s_ease-out] transition-colors',
                                                    !item.read_at && 'bg-violet-50/40 dark:bg-violet-500/[0.04]',
                                                )}
                                            >
                                                <Wrap
                                                    {...(props as Record<string, string>)}
                                                    onClick={() => onItemClick(item)}
                                                    className="hover:bg-muted/40 flex items-start gap-3 px-4 py-3 transition-colors"
                                                >
                                                    <div className={cn('flex size-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br text-white shadow-soft-xs', tone)}>
                                                        <Icon className="size-4" />
                                                    </div>
                                                    <div className="min-w-0 flex-1">
                                                        <p className="line-clamp-1 text-sm font-semibold">{item.title}</p>
                                                        {item.body && <p className="text-muted-foreground line-clamp-2 mt-0.5 text-[11px]">{item.body}</p>}
                                                        <p className="text-muted-foreground mt-1 inline-flex items-center gap-1.5 text-[10px]">
                                                            {item.actor && (
                                                                <span className="from-violet-500 to-indigo-600 ring-card flex size-4 items-center justify-center rounded-full bg-gradient-to-br text-[8px] font-bold text-white ring-2">
                                                                    {getInitials(item.actor.name)}
                                                                </span>
                                                            )}
                                                            {relativeTime(item.created_at)}
                                                        </p>
                                                    </div>
                                                    {!item.read_at && <span className="bg-violet-500 mt-1.5 size-2 shrink-0 rounded-full" />}
                                                </Wrap>
                                            </li>
                                        );
                                    })}
                                </ul>
                            </div>
                        ))
                    )}
                </div>
                <div className="border-t border-border/60 p-2">
                    <Link href={route('notifications.index')} className="text-muted-foreground hover:text-foreground hover:bg-muted/60 block rounded-lg px-3 py-2 text-center text-[12px] font-semibold transition-colors" onClick={() => setOpen(false)}>
                        View all notifications →
                    </Link>
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function relativeTime(iso: string): string {
    const diff = (Date.now() - new Date(iso).getTime()) / 1000;
    if (diff < 60) return 'just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;
    return new Date(iso).toLocaleDateString();
}
