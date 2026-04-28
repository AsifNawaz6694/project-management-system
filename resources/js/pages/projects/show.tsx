import { BudgetBar } from '@/components/budget-bar';
import { CommentThread, type CommentNode } from '@/components/comment-thread';
import { ConfirmDialog } from '@/components/confirm-dialog';
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
import { formatBytes, formatMoney } from '@/lib/expenses';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarDays,
    CheckCircle2,
    Circle,
    DollarSign,
    Download,
    File,
    Pencil,
    Plus,
    Trash2,
    UploadCloud,
    Users,
    Wallet,
} from 'lucide-react';
import { useRef, useState, type ChangeEvent, type DragEvent } from 'react';

interface MemberRow {
    id: number;
    name: string;
    avatar: string | null;
    job_title: string | null;
    department: string | null;
}

interface MilestoneRow {
    id: number;
    title: string;
    description: string | null;
    due_date: string | null;
    completed_at: string | null;
    position: number;
}

interface AttachmentRow {
    id: number;
    file_name: string;
    file_size: number;
    mime_type: string | null;
    created_at: string;
    uploader: { id: number; name: string } | null;
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
        currency: string | null;
        start_date: string | null;
        end_date: string | null;
        owner: MemberRow | null;
        members: MemberRow[];
        milestones: MilestoneRow[];
        attachments: AttachmentRow[];
    };
    activities: Array<{ id: number; description: string | null; action: string; created_at: string }>;
    milestoneStats: { total: number; completed: number };
    comments: CommentNode[];
    budget: { budget: number; spent: number; pending: number; remaining: number; utilization: number; over_budget: boolean; currency: string };
    canUpload: boolean;
    canSubmitExpense: boolean;
}

type Tab = 'overview' | 'milestones' | 'team' | 'discussion' | 'files' | 'budget' | 'activity';

export default function ProjectShow({ project, activities, milestoneStats, comments, budget, canUpload, canSubmitExpense }: ProjectShowProps) {
    const { can, user } = usePermissions();
    const getInitials = useInitials();
    const [tab, setTab] = useState<Tab>('overview');
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [dragActive, setDragActive] = useState(false);
    const [confirmDeleteFileId, setConfirmDeleteFileId] = useState<number | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Workspace', href: '/dashboard' },
        { title: 'Projects', href: '/projects' },
        { title: project.title, href: route('projects.show', project.slug) },
    ];

    const status = STATUS_META[project.status] ?? STATUS_META.planning;
    const priority = PRIORITY_META[project.priority] ?? PRIORITY_META.medium;
    const gradient = COLOR_GRADIENT[project.color] ?? COLOR_GRADIENT.violet;

    const tabs: Array<{ key: Tab; label: string; count?: number }> = [
        { key: 'overview', label: 'Overview' },
        { key: 'milestones', label: 'Milestones', count: milestoneStats.total },
        { key: 'team', label: 'Team', count: project.members.length },
        { key: 'discussion', label: 'Discussion', count: comments.length },
        { key: 'files', label: 'Files', count: project.attachments.length },
        { key: 'budget', label: 'Budget' },
        { key: 'activity', label: 'Activity' },
    ];

    const uploadFile = (file: File) => {
        router.post(route('projects.files.store', project.slug), { file }, { forceFormData: true, preserveScroll: true });
    };

    const onUploadChosen = (e: ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) uploadFile(file);
        if (fileInputRef.current) fileInputRef.current.value = '';
    };

    const onDrop = (e: DragEvent<HTMLDivElement>) => {
        e.preventDefault();
        setDragActive(false);
        const file = e.dataTransfer.files?.[0];
        if (file) uploadFile(file);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={project.title} />
            <div className="flex w-full flex-1 flex-col gap-6 p-4 md:p-6">
                <Link href={route('projects.index')} className="text-muted-foreground hover:text-foreground inline-flex w-fit items-center gap-1.5 text-xs font-medium transition-colors">
                    <ArrowLeft className="size-3.5" /> Back to projects
                </Link>

                <SoftCard className="overflow-visible">
                    <div className={cn('relative h-44 overflow-hidden rounded-t-2xl bg-gradient-to-br', gradient)}>
                        <div className="absolute -right-24 -top-24 size-80 rounded-full bg-white/15 blur-3xl" />
                        <div className="absolute -bottom-12 left-1/4 size-60 rounded-full bg-white/15 blur-3xl" />
                        <div className="absolute inset-0" style={{ backgroundImage: 'radial-gradient(rgba(255,255,255,0.18) 1px, transparent 1px)', backgroundSize: '28px 28px' }} />
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
                                {canSubmitExpense && (
                                    <Button asChild variant="secondary" size="sm" className="gap-1.5 bg-white text-foreground hover:bg-white/90">
                                        <Link href={route('expenses.create', { project_id: project.id })}>
                                            <Wallet className="size-3.5" /> New expense
                                        </Link>
                                    </Button>
                                )}
                                {can('projects.update') && (
                                    <Button asChild variant="ghost" size="sm" className="bg-white/15 text-white ring-1 ring-white/25 hover:bg-white/25 gap-1.5">
                                        <Link href={route('projects.edit', project.slug)}>
                                            <Pencil className="size-3.5" /> Edit
                                        </Link>
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
                                    {project.description && <p className="text-muted-foreground max-w-2xl text-sm leading-relaxed">{project.description}</p>}
                                </div>
                            </div>
                        </div>

                        <div className="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <Stat icon={CalendarDays} label="Start" value={formatDate(project.start_date)} tone="from-violet-500 to-indigo-600" />
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
                                <div className={cn('h-full rounded-full bg-gradient-to-r transition-[width] duration-700', gradient)} style={{ width: `${Math.max(2, project.progress)}%` }} />
                            </div>
                        </div>
                    </div>
                </SoftCard>

                <div className="bg-card ring-border/60 ring-1 shadow-soft-xs flex w-full flex-wrap gap-1 rounded-2xl p-1">
                    {tabs.map((t) => (
                        <button
                            key={t.key}
                            onClick={() => setTab(t.key)}
                            className={cn(
                                'relative inline-flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-xs font-semibold capitalize transition-all',
                                tab === t.key ? 'shadow-soft-md from-violet-600 to-indigo-600 bg-gradient-to-br text-white' : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {t.label}
                            {typeof t.count === 'number' && (
                                <span className={cn('inline-flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] font-bold', tab === t.key ? 'bg-white/20 text-white' : 'bg-muted text-muted-foreground')}>{t.count}</span>
                            )}
                        </button>
                    ))}
                </div>

                {tab === 'overview' && (
                    <div className="grid gap-5 lg:grid-cols-3">
                        <SoftCard className="lg:col-span-2">
                            <SoftCardTitle eyebrow="Roadmap">Upcoming milestones</SoftCardTitle>
                            <SoftCardBody>
                                <ol className="relative space-y-4 pl-6 before:absolute before:bottom-1.5 before:left-2 before:top-1.5 before:w-px before:bg-border">
                                    {project.milestones.length === 0 && <p className="text-muted-foreground text-xs">No milestones yet.</p>}
                                    {project.milestones.slice(0, 5).map((m) => {
                                        const done = !!m.completed_at;
                                        return (
                                            <li key={m.id} className="relative">
                                                <span className={cn('absolute -left-6 top-1 size-3 rounded-full ring-2 ring-card', done ? 'bg-gradient-to-br from-emerald-500 to-teal-600' : 'bg-muted-foreground/30')} />
                                                <p className={cn('text-sm font-medium', done && 'text-muted-foreground line-through')}>{m.title}</p>
                                                <p className="text-muted-foreground mt-0.5 text-[11px]">{formatDate(m.due_date)}</p>
                                            </li>
                                        );
                                    })}
                                </ol>
                            </SoftCardBody>
                        </SoftCard>

                        <SoftCard>
                            <SoftCardTitle eyebrow="Live budget">Spending</SoftCardTitle>
                            <SoftCardBody>
                                <BudgetBar budget={budget.budget} spent={budget.spent} pending={budget.pending} utilization={budget.utilization} overBudget={budget.over_budget} currency={budget.currency} />
                                <p className="text-muted-foreground mt-3 text-xs">{formatMoney(budget.pending, budget.currency)} pending · {formatMoney(budget.remaining, budget.currency)} remaining</p>
                            </SoftCardBody>
                        </SoftCard>
                    </div>
                )}

                {tab === 'milestones' && (
                    <SoftCard>
                        <SoftCardTitle eyebrow="Roadmap" action={<span className="text-muted-foreground text-xs font-semibold">{milestoneStats.completed} / {milestoneStats.total} done</span>}>
                            Milestones
                        </SoftCardTitle>
                        <SoftCardBody>
                            <ol className="relative space-y-4 pl-7 before:absolute before:bottom-2 before:left-3 before:top-2 before:w-px before:bg-border">
                                {project.milestones.length === 0 && <p className="text-muted-foreground text-xs">No milestones yet.</p>}
                                {project.milestones.map((m) => {
                                    const done = !!m.completed_at;
                                    return (
                                        <li key={m.id} className="relative">
                                            <span className={cn('absolute -left-7 top-0.5 flex size-6 items-center justify-center rounded-full ring-2 ring-card', done ? 'shadow-soft-sm bg-gradient-to-br from-emerald-500 to-teal-600 text-white' : 'bg-card text-muted-foreground ring-1 ring-border')}>
                                                {done ? <CheckCircle2 className="size-3.5" /> : <Circle className="size-3.5" />}
                                            </span>
                                            <div className={cn('bg-muted/40 ring-border/50 ring-1 rounded-xl p-3 transition-all', done && 'opacity-70')}>
                                                <p className={cn('text-sm font-semibold', done && 'line-through')}>{m.title}</p>
                                                <p className="text-muted-foreground mt-0.5 text-[11px]">{formatDate(m.due_date)}</p>
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
                                        <Link key={m.id} href={route('users.show', m.id)} className="bg-muted/40 ring-border/50 hover:ring-foreground/20 hover:shadow-soft-sm group flex items-center gap-3 rounded-xl p-3 ring-1 transition-all">
                                            <div className={cn('flex size-11 items-center justify-center rounded-xl bg-gradient-to-br text-sm font-bold text-white shadow-soft-xs ring-2 ring-card', isOwner ? 'from-amber-400 to-orange-500' : 'from-violet-500 to-indigo-600')}>
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

                {tab === 'discussion' && (
                    <SoftCard>
                        <SoftCardTitle eyebrow="Comments">Project discussion</SoftCardTitle>
                        <SoftCardBody>
                            <CommentThread
                                comments={comments}
                                storeUrl={route('projects.comments.store', project.slug)}
                                destroyUrlFor={(id) => route('projects.comments.destroy', { project: project.slug, comment: id })}
                                currentUserId={user?.id}
                                canDeleteAny={can('projects.update')}
                                placeholder="Talk to the team — use @name to mention…"
                            />
                        </SoftCardBody>
                    </SoftCard>
                )}

                {tab === 'files' && (
                    <SoftCard>
                        <SoftCardTitle
                            eyebrow="Storage"
                            action={
                                canUpload && (
                                    <>
                                        <input ref={fileInputRef} type="file" className="hidden" onChange={onUploadChosen} />
                                        <Button type="button" variant="soft" size="sm" className="gap-1.5" onClick={() => fileInputRef.current?.click()}>
                                            <Plus className="size-3.5" /> Add file
                                        </Button>
                                    </>
                                )
                            }
                        >
                            Project files
                        </SoftCardTitle>
                        <SoftCardBody>
                            {canUpload && (
                                <div
                                    onDragOver={(e) => {
                                        e.preventDefault();
                                        setDragActive(true);
                                    }}
                                    onDragLeave={() => setDragActive(false)}
                                    onDrop={onDrop}
                                    className={cn('mb-4 rounded-2xl border-2 border-dashed p-6 text-center transition-all', dragActive ? 'bg-violet-50/60 dark:bg-violet-500/10 border-violet-400 dark:border-violet-500/40' : 'border-border')}
                                >
                                    <UploadCloud className={cn('mx-auto size-7', dragActive ? 'text-violet-500' : 'text-muted-foreground')} />
                                    <p className="mt-2 text-sm font-semibold">{dragActive ? 'Release to upload' : 'Drag & drop a file here'}</p>
                                    <p className="text-muted-foreground mt-1 text-xs">Up to 50 MB</p>
                                </div>
                            )}
                            {project.attachments.length === 0 ? (
                                <p className="text-muted-foreground text-xs">No files uploaded yet.</p>
                            ) : (
                                <ul className="divide-y divide-border/60">
                                    {project.attachments.map((a) => (
                                        <li key={a.id} className="flex items-center gap-3 py-3">
                                            <div className="from-violet-500 to-indigo-600 flex size-10 items-center justify-center rounded-xl bg-gradient-to-br text-white shadow-soft-xs">
                                                <File className="size-4" />
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-semibold">{a.file_name}</p>
                                                <p className="text-muted-foreground truncate text-[11px]">{formatBytes(a.file_size)} · {a.uploader?.name ?? '—'} · {new Date(a.created_at).toLocaleString()}</p>
                                            </div>
                                            <a href={route('projects.files.download', { project: project.slug, attachment: a.id })} className="text-muted-foreground hover:text-foreground inline-flex size-8 items-center justify-center rounded-lg ring-1 ring-border hover:ring-foreground/30 transition-all">
                                                <Download className="size-3.5" />
                                            </a>
                                            {(can('projects.update') || a.uploader?.id === user?.id) && (
                                                <button
                                                    onClick={() => setConfirmDeleteFileId(a.id)}
                                                    className="text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 inline-flex size-8 items-center justify-center rounded-lg transition-all"
                                                >
                                                    <Trash2 className="size-3.5" />
                                                </button>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </SoftCardBody>
                    </SoftCard>
                )}

                {tab === 'budget' && (
                    <div className="grid gap-5 lg:grid-cols-3">
                        <SoftCard className="lg:col-span-2">
                            <SoftCardTitle
                                eyebrow="Budget"
                                action={
                                    canSubmitExpense && (
                                        <Button asChild size="sm" variant="soft" className="gap-1.5">
                                            <Link href={route('expenses.create', { project_id: project.id })}>
                                                <Plus className="size-3.5" /> Submit expense
                                            </Link>
                                        </Button>
                                    )
                                }
                            >
                                Spending overview
                            </SoftCardTitle>
                            <SoftCardBody className="space-y-5">
                                <BudgetBar budget={budget.budget} spent={budget.spent} pending={budget.pending} utilization={budget.utilization} overBudget={budget.over_budget} currency={budget.currency} />
                                <div className="grid gap-3 sm:grid-cols-3">
                                    {[
                                        { label: 'Total budget', value: formatMoney(budget.budget, budget.currency), tone: 'text-violet-600' },
                                        { label: 'Approved spend', value: formatMoney(budget.spent, budget.currency), tone: 'text-emerald-600' },
                                        { label: 'Remaining', value: formatMoney(budget.remaining, budget.currency), tone: 'text-blue-600' },
                                    ].map((s) => (
                                        <div key={s.label} className="bg-muted/40 ring-border/50 ring-1 rounded-xl p-4">
                                            <p className="text-muted-foreground text-[10px] font-bold uppercase tracking-[0.14em]">{s.label}</p>
                                            <p className={'font-display mt-1 text-xl font-bold tabular-nums ' + s.tone}>{s.value}</p>
                                        </div>
                                    ))}
                                </div>
                                {budget.pending > 0 && (
                                    <p className="text-muted-foreground text-xs">
                                        <span className="bg-amber-100 text-amber-700 ring-amber-200/70 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30 mr-1.5 inline-flex items-center rounded-full px-2 py-0.5 font-semibold ring-1">
                                            {formatMoney(budget.pending, budget.currency)} pending
                                        </span>
                                        awaiting approval and not yet counted toward spend.
                                    </p>
                                )}
                            </SoftCardBody>
                        </SoftCard>

                        <SoftCard>
                            <SoftCardTitle eyebrow="Quick actions">Expense links</SoftCardTitle>
                            <SoftCardBody className="space-y-2 text-sm">
                                <Link href={route('expenses.index', { project: project.slug })} className="bg-muted/40 ring-border/60 hover:ring-foreground/20 hover:shadow-soft-sm flex items-center justify-between rounded-xl p-3 ring-1 transition-all">
                                    <span className="font-semibold">All expenses for this project</span>
                                    <ArrowLeft className="size-3.5 rotate-180" />
                                </Link>
                                {can('expenses.approve') && (
                                    <Link href={route('expenses.approvals')} className="bg-muted/40 ring-border/60 hover:ring-foreground/20 hover:shadow-soft-sm flex items-center justify-between rounded-xl p-3 ring-1 transition-all">
                                        <span className="font-semibold">Approval queue</span>
                                        <ArrowLeft className="size-3.5 rotate-180" />
                                    </Link>
                                )}
                            </SoftCardBody>
                        </SoftCard>
                    </div>
                )}

                {tab === 'activity' && (
                    <SoftCard>
                        <SoftCardTitle eyebrow="Timeline">Recent project activity</SoftCardTitle>
                        <SoftCardBody>
                            <ol className="relative space-y-4 pl-6 before:absolute before:bottom-1.5 before:left-2 before:top-1.5 before:w-px before:bg-border">
                                {activities.length === 0 && <p className="text-muted-foreground text-xs">No recorded activity yet.</p>}
                                {activities.map((entry, i) => (
                                    <li key={entry.id} className="relative">
                                        <span className={cn('shadow-soft-xs ring-card absolute -left-6 top-0.5 size-3 rounded-full ring-2', i % 4 === 0 && 'bg-gradient-to-br from-violet-500 to-indigo-600', i % 4 === 1 && 'bg-gradient-to-br from-emerald-500 to-teal-600', i % 4 === 2 && 'bg-gradient-to-br from-amber-500 to-orange-600', i % 4 === 3 && 'bg-gradient-to-br from-pink-500 to-fuchsia-600')} />
                                        <p className="text-sm">{entry.description ?? entry.action}</p>
                                        <p className="text-muted-foreground mt-0.5 text-[11px]">{new Date(entry.created_at).toLocaleString()}</p>
                                    </li>
                                ))}
                            </ol>
                        </SoftCardBody>
                    </SoftCard>
                )}
            </div>
            <ConfirmDialog
                open={confirmDeleteFileId !== null}
                onOpenChange={(o) => !o && setConfirmDeleteFileId(null)}
                title="Delete this file?"
                description="The attachment will be removed from this project's files. This cannot be undone."
                confirmLabel="Delete file"
                tone="destructive"
                icon={Trash2}
                onConfirm={() => {
                    if (confirmDeleteFileId !== null) {
                        router.delete(route('projects.files.destroy', { project: project.slug, attachment: confirmDeleteFileId }), { preserveScroll: true });
                    }
                    setConfirmDeleteFileId(null);
                }}
            />
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
