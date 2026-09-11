import { PageHeader } from '@/components/page-header';
import { SoftCard, SoftCardBody, SoftCardTitle } from '@/components/soft-card';
import { StatCard } from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/hooks/use-initials';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { CalendarClock, CalendarPlus, ClipboardList, History, Users } from 'lucide-react';

type View = 'upcoming' | 'past' | 'mine';

interface UserMini {
    id: number;
    name: string;
    avatar?: string | null;
}

interface MeetingRow {
    id: number;
    title: string;
    kind: string;
    starts_at: string;
    ends_at: string | null;
    status: string;
    location: string | null;
    organizer: UserMini | null;
    project: { id: number; slug: string; title: string; color: string } | null;
    participants: UserMini[];
    agenda_items_count: number;
    action_items_count: number;
}

interface Props {
    meetings: MeetingRow[];
    view: View;
    kinds: string[];
    stats: { upcoming: number; past: number; mine: number };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Workspace', href: '/dashboard' },
    { title: 'Meetings', href: '/meetings' },
];

export default function MeetingsIndex({ meetings, view, stats }: Props) {
    const getInitials = useInitials();
    const setView = (v: View) => router.get(route('meetings.index'), { view: v }, { preserveScroll: true, preserveState: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Meetings" />
            <div className="flex w-full flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Communication"
                    title="Meetings"
                    description="Schedule meetings, run agendas, capture notes, and convert action items into work."
                    actions={
                        <>
                            <Button asChild variant="secondary" size="sm" className="gap-1.5">
                                <Link href={route('meetings.templates.index')}>
                                    <ClipboardList className="size-3.5" /> Templates
                                </Link>
                            </Button>
                            <Button asChild className="gap-2">
                                <Link href={route('meetings.create')}>
                                    <CalendarPlus className="size-4" /> New meeting
                                </Link>
                            </Button>
                        </>
                    }
                />

                <section className="grid gap-4 sm:grid-cols-3">
                    <StatCard label="Upcoming" value={stats.upcoming} icon={CalendarClock} accent="violet" />
                    <StatCard label="Past" value={stats.past} icon={History} accent="slate" />
                    <StatCard label="Mine" value={stats.mine} icon={Users} accent="emerald" />
                </section>

                <div className="bg-muted/40 ring-border/60 inline-flex w-fit gap-1 rounded-xl p-1 ring-1">
                    {(['upcoming', 'past', 'mine'] as View[]).map((v) => (
                        <button
                            key={v}
                            type="button"
                            onClick={() => setView(v)}
                            className={cn(
                                'rounded-lg px-3 py-1.5 text-xs font-semibold capitalize transition-all',
                                view === v ? 'bg-card shadow-soft-xs text-foreground' : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {v}
                        </button>
                    ))}
                </div>

                <SoftCard>
                    <SoftCardTitle eyebrow="List">
                        {view === 'upcoming' ? 'Upcoming meetings' : view === 'past' ? 'Past meetings' : 'My meetings'}
                    </SoftCardTitle>
                    <SoftCardBody>
                        {meetings.length === 0 ? (
                            <p className="text-muted-foreground text-sm">Nothing here yet.</p>
                        ) : (
                            <ul className="divide-border/40 -mx-3 divide-y">
                                {meetings.map((m) => (
                                    <li key={m.id}>
                                        <Link
                                            href={route('meetings.show', m.id)}
                                            className="hover:bg-muted/40 flex flex-col gap-2 rounded-lg px-3 py-3 transition-colors sm:flex-row sm:items-center sm:gap-4"
                                        >
                                            <div className="shadow-soft-xs flex size-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-blue-700 text-white">
                                                <CalendarClock className="size-5" />
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-semibold">{m.title}</p>
                                                <p className="text-muted-foreground truncate text-xs">
                                                    {new Date(m.starts_at).toLocaleString()} · {m.kind.replace('_', ' ')}
                                                    {m.location ? ` · ${m.location}` : ''}
                                                    {m.project ? ` · ${m.project.title}` : ''}
                                                </p>
                                            </div>
                                            <div className="text-muted-foreground flex items-center gap-3 text-[11px]">
                                                <span>{m.agenda_items_count} agenda</span>
                                                <span>{m.action_items_count} actions</span>
                                                <div className="flex -space-x-1.5">
                                                    {m.participants.slice(0, 4).map((p) => (
                                                        <span
                                                            key={p.id}
                                                            className="ring-card flex size-6 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-blue-700 text-[10px] font-bold text-white ring-2"
                                                        >
                                                            {getInitials(p.name)}
                                                        </span>
                                                    ))}
                                                    {m.participants.length > 4 && (
                                                        <span className="bg-muted ring-card text-muted-foreground flex size-6 items-center justify-center rounded-full text-[10px] font-bold ring-2">
                                                            +{m.participants.length - 4}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </SoftCardBody>
                </SoftCard>
            </div>
        </AppLayout>
    );
}
