import { ConfirmDialog } from '@/components/confirm-dialog';
import { SoftCard, SoftCardBody, SoftCardTitle } from '@/components/soft-card';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useInitials } from '@/hooks/use-initials';
import AppLayout from '@/layouts/app-layout';
import { COLOR_GRADIENT, type ProjectColor } from '@/lib/projects';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type DepartmentSummary } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, Crown, LoaderCircle, Mail, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface MemberMini {
    id: number;
    name: string;
    avatar?: string | null;
    initials?: string;
    job_title?: string | null;
    department?: DepartmentSummary | null;
    email?: string;
}

interface TeamShow {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    color: ProjectColor;
    lead_id: number | null;
    lead: MemberMini | null;
    members: MemberMini[];
}

interface ShowProps {
    team: TeamShow;
    activities: Array<{ id: number; description: string | null; action: string; created_at: string; user?: MemberMini | null }>;
    canManage: boolean;
    users: MemberMini[];
    colors: string[];
}

export default function TeamShow({ team, activities, canManage, users, colors }: ShowProps) {
    const getInitials = useInitials();
    const [showEdit, setShowEdit] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Workspace', href: '/dashboard' },
        { title: 'Teams', href: '/teams' },
        { title: team.name, href: route('teams.show', team.slug) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={team.name} />
            <div className="flex w-full flex-1 flex-col gap-6 p-4 md:p-6">
                <Link
                    href={route('teams.index')}
                    className="text-muted-foreground hover:text-foreground inline-flex w-fit items-center gap-1.5 text-xs font-medium transition-colors"
                >
                    <ArrowLeft className="size-3.5" /> Back to teams
                </Link>

                <SoftCard className="overflow-visible">
                    <div
                        className={cn(
                            'relative h-32 overflow-hidden rounded-t-2xl bg-gradient-to-br',
                            COLOR_GRADIENT[team.color] ?? COLOR_GRADIENT.violet,
                        )}
                    >
                        <div className="absolute inset-0 bg-[radial-gradient(circle_at_30%_30%,rgba(255,255,255,0.3),transparent)]" />
                    </div>
                    <div className="-mt-10 flex flex-col gap-4 px-6 pb-6 md:flex-row md:items-end md:justify-between">
                        <div className="flex items-end gap-4">
                            <div
                                className={cn(
                                    'shadow-soft-lg ring-card flex size-20 items-center justify-center rounded-2xl bg-gradient-to-br text-2xl font-bold text-white ring-4',
                                    COLOR_GRADIENT[team.color] ?? COLOR_GRADIENT.violet,
                                )}
                            >
                                {team.name.slice(0, 2).toUpperCase()}
                            </div>
                            <div className="pb-1">
                                <h2 className="font-display text-2xl font-bold tracking-tight md:text-3xl">{team.name}</h2>
                                {team.description && <p className="text-muted-foreground mt-1 max-w-xl text-sm">{team.description}</p>}
                            </div>
                        </div>
                        {canManage && (
                            <div className="flex gap-2">
                                <Button onClick={() => setShowEdit(true)} variant="secondary" size="sm" className="gap-1.5">
                                    <Pencil className="size-3.5" /> Edit
                                </Button>
                                <Button
                                    onClick={() => setConfirmDelete(true)}
                                    variant="outline"
                                    size="sm"
                                    className="gap-1.5 text-rose-600 ring-rose-200 hover:bg-rose-50 hover:ring-rose-300 dark:text-rose-400 dark:ring-rose-500/30"
                                >
                                    <Trash2 className="size-3.5" />
                                </Button>
                            </div>
                        )}
                    </div>
                </SoftCard>

                <div className="grid gap-5 lg:grid-cols-[1fr_320px]">
                    <SoftCard>
                        <SoftCardTitle
                            eyebrow="Members"
                            action={<span className="text-muted-foreground text-xs font-semibold">{team.members.length} total</span>}
                        >
                            Roster
                        </SoftCardTitle>
                        <SoftCardBody>
                            {team.members.length === 0 ? (
                                <p className="text-muted-foreground text-xs">No members yet. Edit the team to add some.</p>
                            ) : (
                                <ul className="divide-border/60 divide-y">
                                    {team.members.map((m) => {
                                        const isLead = m.id === team.lead_id;
                                        return (
                                            <li key={m.id} className="flex items-center gap-3 py-3">
                                                <Link
                                                    href={route('users.show', m.id)}
                                                    className="ring-card shadow-soft-xs flex size-10 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-blue-700 text-sm font-bold text-white ring-2"
                                                >
                                                    {getInitials(m.name)}
                                                </Link>
                                                <div className="min-w-0 flex-1">
                                                    <Link
                                                        href={route('users.show', m.id)}
                                                        className="text-sm font-semibold hover:text-blue-600 dark:hover:text-blue-300"
                                                    >
                                                        {m.name}
                                                    </Link>
                                                    <p className="text-muted-foreground truncate text-[11px]">
                                                        {m.job_title ?? m.department?.name ?? '—'}
                                                    </p>
                                                </div>
                                                {isLead && (
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-800 ring-1 ring-amber-200/70 ring-inset dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-500/30">
                                                        <Crown className="size-3" /> Lead
                                                    </span>
                                                )}
                                                {m.email && (
                                                    <a
                                                        href={`mailto:${m.email}`}
                                                        className="text-muted-foreground hover:text-foreground ring-border hover:ring-foreground/30 inline-flex size-8 items-center justify-center rounded-lg ring-1 transition-all"
                                                    >
                                                        <Mail className="size-3.5" />
                                                    </a>
                                                )}
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}
                        </SoftCardBody>
                    </SoftCard>

                    <aside className="space-y-4">
                        <SoftCard>
                            <SoftCardTitle eyebrow="Lead">Team lead</SoftCardTitle>
                            <SoftCardBody>
                                {team.lead ? (
                                    <Link
                                        href={route('users.show', team.lead.id)}
                                        className="bg-muted/30 ring-border/60 hover:ring-foreground/20 flex items-center gap-3 rounded-xl p-3 ring-1 transition-all"
                                    >
                                        <div className="ring-card shadow-soft-sm flex size-12 items-center justify-center rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 text-sm font-bold text-white ring-2">
                                            {getInitials(team.lead.name)}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-semibold">{team.lead.name}</p>
                                            <p className="text-muted-foreground truncate text-[11px]">{team.lead.job_title ?? '—'}</p>
                                        </div>
                                    </Link>
                                ) : (
                                    <p className="text-muted-foreground text-xs italic">No lead assigned.</p>
                                )}
                            </SoftCardBody>
                        </SoftCard>

                        <SoftCard>
                            <SoftCardTitle eyebrow="Audit">Activity</SoftCardTitle>
                            <SoftCardBody>
                                <ol className="before:bg-border relative space-y-3 pl-6 before:absolute before:top-1.5 before:bottom-1.5 before:left-2 before:w-px">
                                    {activities.length === 0 && <p className="text-muted-foreground text-xs">No activity yet.</p>}
                                    {activities.map((a, i) => (
                                        <li key={a.id} className="relative">
                                            <span
                                                className={cn(
                                                    'ring-card absolute top-1 -left-6 size-3 rounded-full ring-2',
                                                    i % 4 === 0 && 'bg-gradient-to-br from-blue-500 to-blue-700',
                                                    i % 4 === 1 && 'bg-gradient-to-br from-emerald-500 to-teal-600',
                                                    i % 4 === 2 && 'bg-gradient-to-br from-amber-500 to-orange-600',
                                                    i % 4 === 3 && 'bg-gradient-to-br from-slate-500 to-slate-700',
                                                )}
                                            />
                                            <p className="text-xs leading-snug">{a.description ?? a.action}</p>
                                            <p className="text-muted-foreground mt-0.5 text-[10px]">
                                                {a.user?.name ? `${a.user.name} · ` : ''}
                                                {new Date(a.created_at).toLocaleString()}
                                            </p>
                                        </li>
                                    ))}
                                </ol>
                            </SoftCardBody>
                        </SoftCard>
                    </aside>
                </div>
            </div>

            {canManage && <EditTeamDialog open={showEdit} onOpenChange={setShowEdit} team={team} users={users} colors={colors} />}

            <ConfirmDialog
                open={confirmDelete}
                onOpenChange={setConfirmDelete}
                title={`Delete team "${team.name}"?`}
                description={
                    team.members.length > 0
                        ? `This team has ${team.members.length} member(s). They will be removed from this team. This cannot be undone.`
                        : 'This team will be removed permanently. This cannot be undone.'
                }
                confirmLabel="Delete team"
                tone="destructive"
                icon={Trash2}
                onConfirm={() => {
                    router.delete(route('teams.destroy', team.slug));
                    setConfirmDelete(false);
                }}
            />
        </AppLayout>
    );
}

function EditTeamDialog({
    open,
    onOpenChange,
    team,
    users,
    colors,
}: {
    open: boolean;
    onOpenChange: (o: boolean) => void;
    team: TeamShow;
    users: MemberMini[];
    colors: string[];
}) {
    const getInitials = useInitials();
    const form = useForm<{ name: string; description: string; color: string; lead_id: number | null; member_ids: number[] }>({
        name: team.name,
        description: team.description ?? '',
        color: team.color,
        lead_id: team.lead_id,
        member_ids: team.members.map((m) => m.id),
    });

    const toggleMember = (id: number) => {
        const set = new Set(form.data.member_ids);
        if (set.has(id)) set.delete(id);
        else set.add(id);
        form.setData('member_ids', Array.from(set));
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="rounded-2xl sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle className="font-display text-xl font-bold">Edit team</DialogTitle>
                    <DialogDescription>Update name, lead, color, and members.</DialogDescription>
                </DialogHeader>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.patch(route('teams.update', team.slug), {
                            preserveScroll: true,
                            onSuccess: () => onOpenChange(false),
                        });
                    }}
                    className="space-y-4"
                >
                    <div className="grid gap-3 md:grid-cols-2">
                        <div className="space-y-1.5">
                            <Label className="text-muted-foreground text-[10px] font-bold tracking-[0.14em] uppercase">Name</Label>
                            <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                        </div>
                        <div className="space-y-1.5">
                            <Label className="text-muted-foreground text-[10px] font-bold tracking-[0.14em] uppercase">Lead</Label>
                            <Select
                                value={form.data.lead_id ? String(form.data.lead_id) : 'none'}
                                onValueChange={(v) => form.setData('lead_id', v === 'none' ? null : Number(v))}
                            >
                                <SelectTrigger className="h-11 rounded-xl">
                                    <SelectValue placeholder="No lead" />
                                </SelectTrigger>
                                <SelectContent className="rounded-xl">
                                    <SelectItem value="none">No lead</SelectItem>
                                    {users.map((u) => (
                                        <SelectItem key={u.id} value={String(u.id)}>
                                            {u.name}
                                            {u.job_title ? ` · ${u.job_title}` : ''}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label className="text-muted-foreground text-[10px] font-bold tracking-[0.14em] uppercase">Description</Label>
                        <Input value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                    </div>

                    <div className="space-y-1.5">
                        <Label className="text-muted-foreground text-[10px] font-bold tracking-[0.14em] uppercase">Color</Label>
                        <div className="flex flex-wrap gap-2">
                            {colors.map((c) => (
                                <button
                                    key={c}
                                    type="button"
                                    onClick={() => form.setData('color', c)}
                                    className={cn(
                                        'relative size-9 overflow-hidden rounded-xl bg-gradient-to-br transition-all',
                                        COLOR_GRADIENT[c as ProjectColor],
                                        form.data.color === c ? 'shadow-glow ring-foreground scale-110 ring-2' : 'ring-border ring-1 hover:scale-105',
                                    )}
                                    aria-label={c}
                                >
                                    {form.data.color === c && <Check className="absolute inset-0 m-auto size-4 text-white" />}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label className="text-muted-foreground text-[10px] font-bold tracking-[0.14em] uppercase">Members</Label>
                        <div className="border-border/60 bg-muted/20 grid max-h-[40vh] gap-1.5 overflow-y-auto rounded-xl border p-2 sm:grid-cols-2">
                            {users.map((u) => {
                                const checked = form.data.member_ids.includes(u.id);
                                return (
                                    <button
                                        key={u.id}
                                        type="button"
                                        onClick={() => toggleMember(u.id)}
                                        className={cn(
                                            'group flex items-center gap-2.5 rounded-lg p-2 text-left text-xs ring-1 transition-all',
                                            checked
                                                ? 'bg-blue-50 ring-blue-200 dark:bg-blue-500/10 dark:ring-blue-500/30'
                                                : 'bg-card ring-border/50 hover:ring-foreground/20',
                                        )}
                                    >
                                        <div className="ring-card flex size-7 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-blue-700 text-[10px] font-bold text-white ring-2">
                                            {getInitials(u.name)}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate font-semibold">{u.name}</p>
                                            <p className="text-muted-foreground truncate text-[10px]">{u.job_title ?? u.email}</p>
                                        </div>
                                        <div
                                            className={cn(
                                                'flex size-4 items-center justify-center rounded-full border-2',
                                                checked
                                                    ? 'border-blue-600 bg-gradient-to-br from-blue-600 to-blue-700'
                                                    : 'border-muted-foreground/30',
                                            )}
                                        >
                                            {checked && <Check className="size-2.5 text-white" />}
                                        </div>
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    <DialogFooter className="gap-2">
                        <Button type="button" variant="ghost" onClick={() => onOpenChange(false)} disabled={form.processing}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing || !form.data.name.trim()} className="gap-2">
                            {form.processing && <LoaderCircle className="size-4 animate-spin" />} Save changes
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
