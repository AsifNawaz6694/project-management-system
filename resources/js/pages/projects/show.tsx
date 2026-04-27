import { PageHeader } from '@/components/page-header';
import { SoftCard, SoftCardBody, SoftCardTitle } from '@/components/soft-card';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/hooks/use-initials';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import {
    COLOR_GRADIENT,
    formatBudget,
    formatDate,
    PRIORITY_META,
    STATUS_META,
    type ProjectColor,
    type ProjectPriority,
    type ProjectStatus,
} from '@/lib/projects';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarDays,
    CheckCircle2,
    Circle,
    DollarSign,
    Pencil,
    Trash2,
    Users,
} from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

interface MemberRow {
    id: number;
    name: string;
    avatar: string | null;
    job_title: string | null;
    department: string | null;
    pivot?: { role: string };
}

interface MilestoneRow {
    id: number;
    title: string;
    description: string | null;
    due_date: string | null;
    completed_at: string | null;
    position: number;
}

interface ProjectShowProps {
    project: {
        id: number;
        slug: string;
        title: string;
        description: string | null;
        status: ProjectStatus;
        priority: ProjectPriority;
        color: ProjectColor;
        progress: number;
        budget: string | number | null;
        start_date: string | null;
        end_date: string | null;
        owner: MemberRow | null;
        members: MemberRow[];
        milestones: MilestoneRow[];
    };
    activities: Array<{ id: number; description: string | null; action: string; created_at: string }>;
    milestoneStats: { total: number; completed: number };
}

type Tab = 'overview' | 'milestones' | 'team' | 'activity';

export default function ProjectShow({ project, activities, milestoneStats }: ProjectShowProps) {
    const { can } = usePermissions();
    const getInitials = useInitials();
    const [tab, setTab] = useState<Tab>('overview');
    const [pendingId, setPendingId] = useState<number | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Workspace', href: '/dashboard' },
        { title: 'Projects', href: '/projects' },
        { title: project.title, href: route('projects.show', project.slug) },
    ];

    const status = STATUS_META[project.status] ?? STATUS_META.planning;
    const priority = PRIORITY_META[project.priority] ?? PRIORITY_META.medium;
    const gradient = COLOR_GRADIENT[project.color] ?? COLOR_GRADIENT.violet;

    const toggleMilestone: (m: MilestoneRow) => FormEventHandler = (m) => (e) => {
        e.preventDefault();
        setPendingId(m.id);
        router.patch(
            route('projects.milestones.toggle', { project: project.slug, milestone: m.id }),
            {},
            {
                preserveScroll: true,
                onFinish: () => setPendingId(null),
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={project.title} />
            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-8">
                <Link
                    href={route('projects.index')}
                    className="text-muted-foreground hover:text-foreground inline-flex w-fit items-center gap-1.5 text-xs font-medium transition-colors"
                >
                    <ArrowLeft className="size-3.5" /> Back to projects
                </Link>

                <SoftCard className="overflow-visible">
                    <div className={cn('relative h-44 overflow-hidden rounded-t-2xl bg-gradient-to-br', gradient)}>
                        <div className="absolute -right-24 -top-24 size-80 rounded-full bg-white/15 blur-3xl" />
                        <div className="absolute -bottom-12 left-1/4 size-60 rounded-full bg-white/15 blur-3xl" />
                        <div
                            className="absolute inset-0"
                            style={{
                                backgroundImage: 'radial-gradient(rgba(255,255,255,0.18) 1px, transparent 1px)',
                                backgroundSize: '28px 28px',
                            }}
                        />
                        <div className="relative flex items-start justify-between p-6">
                            <div className="flex flex-col items-start gap-3">
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.14em] text-white ring-1 ring-white/30 backdrop-blur">
                                    <span className={cn('size-1.5 rounded-full', status.dot)} /> {status.label}
                                </span>
                                <span className="inline-flex items-center rounded-full bg-white/20 px-2.5 py-0.5 text-[11px] font-semibold text-white ring-1 ring-white/30 backdrop-blur">
                                    {priority.label} priority
                                </span>
                            </div>
                            <div className="flex items-center gap-2">
                                {can('projects.update') && (
                                    <Button asChild variant="secondary" size="sm" className="gap-1.5 bg-white text-foreground hover:bg-white/90">
                                        <Link href={route('projects.edit', project.slug)}>
                                            <Pencil className="size-3.5" /> Edit
                                        </Link>
                                    </Button>
                                )}
                                {can('projects.delete') && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="bg-white/15 text-white ring-1 ring-white/25 hover:bg-white/25"
                                        onClick={() => {
                                            if (confirm(`Delete ${project.title}? This cannot be undone.`)) {
                                                router.delete(route('projects.destroy', project.slug));
                                            }
                                        }}
                                    >
                                        <Trash2 className="size-3.5" />
                                    </Button>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="relative -mt-12 px-6 pb-6">
                        <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                            <div className="flex items-end gap-4">
                                <div className={cn('flex size-20 items-center justify-center rounded-3xl bg-gradient-to-br text-2xl font-bold text-white shadow-soft-lg ring-4 ring-card', gradient)}>
                                    {project.title.slice(0, 2).toUpperCase()}
                                </div>
                                <div className="space-y-1.5 pb-1">
                                    <h2 className="font-display text-2xl font-bold tracking-tight md:text-3xl">{project.title}</h2>
                                    {project.description && (
                                        <p className="text-muted-foreground max-w-2xl text-sm leading-relaxed">{project.description}</p>
                                    )}
                                </div>
                            </div>
                        </div>

                        <div className="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <Stat icon={CalendarDays} label="Start date" value={formatDate(project.start_date)} tone="from-violet-500 to-indigo-600" />
                            <Stat icon={CalendarDays} label="Target end" value={formatDate(project.end_date)} tone="from-blue-500 to-cyan-500" />
                            <Stat icon={DollarSign} label="Budget" value={formatBudget(project.budget)} tone="from-emerald-500 to-teal-600" />
                            <Stat icon={Users} label="Members" value={project.members.length.toString()} tone="from-pink-500 to-fuchsia-600" />
                        </div>

                        <div className="mt-5 space-y-2">
                            <div className="flex items-center justify-between text-xs">
                                <span className="text-muted-foreground font-bold uppercase tracking-[0.14em]">Overall progress</span>
                                <span className="font-bold tabular-nums">
                                    {project.progress}%
                                    {milestoneStats.total > 0 && (
                                        <span className="text-muted-foreground ml-2 font-medium">
                                            · {milestoneStats.completed} / {milestoneStats.total} milestones
                                        </span>
                                    )}
                                </span>
                            </div>
                            <div className="bg-muted h-2.5 overflow-hidden rounded-full">
                                <div
                                    className={cn('h-full rounded-full bg-gradient-to-r transition-[width] duration-700', gradient)}
                                    style={{ width: `${Math.max(2, project.progress)}%` }}
                                />
                            </div>
                        </div>
                    </div>
                </SoftCard>

                <div className="bg-card ring-border/60 ring-1 shadow-soft-xs flex w-fit flex-wrap gap-1 rounded-2xl p-1">
                    {(['overview', 'milestones', 'team', 'activity'] as Tab[]).map((t) => (
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
                    <div className="grid gap-5 lg:grid-cols-3">
                        <SoftCard className="lg:col-span-2">
                            <SoftCardTitle eyebrow="Roadmap">Upcoming milestones</SoftCardTitle>
                            <SoftCardBody>
                                <ol className="relative space-y-4 pl-6 before:absolute before:bottom-1.5 before:left-2 before:top-1.5 before:w-px before:bg-border">
                                    {project.milestones.length === 0 && (
                                        <p className="text-muted-foreground text-xs">No milestones yet.</p>
                                    )}
                                    {project.milestones.slice(0, 5).map((m) => {
                                        const done = !!m.completed_at;
                                        return (
                                            <li key={m.id} className="relative">
                                                <span
                                                    className={cn(
                                                        'absolute -left-6 top-1 size-3 rounded-full ring-2 ring-card',
                                                        done ? 'bg-gradient-to-br from-emerald-500 to-teal-600' : 'bg-muted-foreground/30',
                                                    )}
                                                />
                                                <p className={cn('text-sm font-medium', done && 'text-muted-foreground line-through')}>{m.title}</p>
                                                <p className="text-muted-foreground mt-0.5 text-[11px]">{formatDate(m.due_date)}</p>
                                            </li>
                                        );
                                    })}
                                </ol>
                            </SoftCardBody>
                        </SoftCard>

                        <SoftCard>
                            <SoftCardTitle eyebrow="Project lead">Owner & top members</SoftCardTitle>
                            <SoftCardBody className="space-y-3">
                                {project.owner && (
                                    <div className="bg-muted/40 ring-border/50 ring-1 flex items-center gap-3 rounded-xl p-3">
                                        <div className="flex size-10 items-center justify-center rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 text-sm font-bold text-white shadow-soft-sm ring-2 ring-card">
                                            {getInitials(project.owner.name)}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-semibold">{project.owner.name}</p>
                                            <p className="text-muted-foreground truncate text-xs">{project.owner.job_title ?? 'Project owner'}</p>
                                        </div>
                                        <span className="bg-amber-50 text-amber-700 ring-amber-200/70 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30 inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.14em] ring-1">
                                            Owner
                                        </span>
                                    </div>
                                )}
                                {project.members.filter((m) => m.id !== project.owner?.id).slice(0, 4).map((m) => (
                                    <div key={m.id} className="flex items-center gap-3 px-1">
                                        <div className="flex size-9 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500 to-indigo-600 text-xs font-bold text-white shadow-soft-xs ring-2 ring-card">
                                            {getInitials(m.name)}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium">{m.name}</p>
                                            <p className="text-muted-foreground truncate text-[11px]">{m.job_title ?? '—'}</p>
                                        </div>
                                    </div>
                                ))}
                            </SoftCardBody>
                        </SoftCard>
                    </div>
                )}

                {tab === 'milestones' && (
                    <SoftCard>
                        <SoftCardTitle
                            eyebrow="Roadmap"
                            action={
                                <span className="text-muted-foreground text-xs font-semibold">
                                    {milestoneStats.completed} / {milestoneStats.total} done
                                </span>
                            }
                        >
                            Milestones
                        </SoftCardTitle>
                        <SoftCardBody>
                            <ol className="relative space-y-4 pl-7 before:absolute before:bottom-2 before:left-3 before:top-2 before:w-px before:bg-border">
                                {project.milestones.length === 0 && <p className="text-muted-foreground text-xs">No milestones yet.</p>}
                                {project.milestones.map((m) => {
                                    const done = !!m.completed_at;
                                    const canToggle = can('projects.update') || can('tasks.update-status');
                                    return (
                                        <li key={m.id} className="relative">
                                            <button
                                                type="button"
                                                disabled={!canToggle || pendingId === m.id}
                                                onClick={(e) => toggleMilestone(m)(e as unknown as React.FormEvent)}
                                                className={cn(
                                                    'absolute -left-7 top-0.5 flex size-6 items-center justify-center rounded-full ring-2 ring-card transition-all',
                                                    done ? 'shadow-soft-sm bg-gradient-to-br from-emerald-500 to-teal-600 text-white' : 'bg-card text-muted-foreground ring-1 ring-border hover:text-foreground',
                                                    !canToggle && 'cursor-not-allowed opacity-80',
                                                )}
                                                aria-label={done ? 'Mark incomplete' : 'Mark complete'}
                                            >
                                                {done ? <CheckCircle2 className="size-3.5" /> : <Circle className="size-3.5" />}
                                            </button>
                                            <div
                                                className={cn(
                                                    'bg-muted/40 ring-border/50 ring-1 rounded-xl p-3 transition-all',
                                                    done && 'opacity-70',
                                                )}
                                            >
                                                <p className={cn('text-sm font-semibold', done && 'line-through')}>{m.title}</p>
                                                <p className="text-muted-foreground mt-0.5 flex items-center gap-1 text-[11px]">
                                                    <CalendarDays className="size-3" /> {formatDate(m.due_date)}
                                                </p>
                                                {m.description && <p className="text-muted-foreground mt-1.5 text-xs">{m.description}</p>}
                                            </div>
                                        </li>
                                    );
                                })}
                            </ol>
                        </SoftCardBody>
                    </SoftCard>
                )}

                {tab === 'team' && (
                    <SoftCard>
                        <SoftCardTitle eyebrow="Members">Project team</SoftCardTitle>
                        <SoftCardBody>
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                {project.members.map((m) => {
                                    const isOwner = m.id === project.owner?.id;
                                    return (
                                        <Link
                                            key={m.id}
                                            href={route('users.show', m.id)}
                                            className="bg-muted/40 ring-border/50 hover:ring-foreground/20 hover:shadow-soft-sm group flex items-center gap-3 rounded-xl p-3 ring-1 transition-all"
                                        >
                                            <div
                                                className={cn(
                                                    'flex size-11 items-center justify-center rounded-xl bg-gradient-to-br text-sm font-bold text-white shadow-soft-xs ring-2 ring-card',
                                                    isOwner ? 'from-amber-400 to-orange-500' : 'from-violet-500 to-indigo-600',
                                                )}
                                            >
                                                {getInitials(m.name)}
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-semibold">{m.name}</p>
                                                <p className="text-muted-foreground truncate text-[11px]">{m.job_title ?? m.department ?? '—'}</p>
                                            </div>
                                            {isOwner && (
                                                <span className="bg-amber-50 text-amber-700 ring-amber-200/70 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30 inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.14em] ring-1">
                                                    Owner
                                                </span>
                                            )}
                                        </Link>
                                    );
                                })}
                            </div>
                        </SoftCardBody>
                    </SoftCard>
                )}

                {tab === 'activity' && (
                    <SoftCard>
                        <SoftCardTitle eyebrow="Timeline">Recent project activity</SoftCardTitle>
                        <SoftCardBody>
                            <ol className="relative space-y-4 pl-6 before:absolute before:bottom-1.5 before:left-2 before:top-1.5 before:w-px before:bg-border">
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
                                        <p className="text-muted-foreground mt-0.5 text-[11px]">{new Date(entry.created_at).toLocaleString()}</p>
                                    </li>
                                ))}
                            </ol>
                        </SoftCardBody>
                    </SoftCard>
                )}
            </div>
        </AppLayout>
    );
}

function Stat({ icon: Icon, label, value, tone }: { icon: typeof Users; label: string; value: string; tone: string }) {
    return (
        <div className="bg-card ring-border/50 ring-1 shadow-soft-xs flex items-center gap-3 rounded-2xl p-3">
            <div className={cn('flex size-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br text-white shadow-soft-sm', tone)}>
                <Icon className="size-4" />
            </div>
            <div className="min-w-0">
                <p className="text-muted-foreground text-[10px] font-bold uppercase tracking-[0.14em]">{label}</p>
                <p className="truncate text-sm font-semibold">{value}</p>
            </div>
        </div>
    );
}
