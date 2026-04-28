import { ConfirmDialog } from '@/components/confirm-dialog';
import { PageHeader } from '@/components/page-header';
import { SoftCard, SoftCardBody, SoftCardTitle } from '@/components/soft-card';
import { StatCard } from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useInitials } from '@/hooks/use-initials';
import AppLayout from '@/layouts/app-layout';
import { COLOR_DOT, COLOR_GRADIENT, type ProjectColor } from '@/lib/projects';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Check, Crown, LoaderCircle, Plus, Trash2, Users, UsersRound } from 'lucide-react';
import { useState } from 'react';

interface MemberMini {
    id: number;
    name: string;
    avatar?: string | null;
    initials?: string;
    job_title?: string | null;
    email?: string;
    department?: string | null;
}

interface TeamRow {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    color: ProjectColor;
    lead: MemberMini | null;
    members: MemberMini[];
    members_count: number;
}

interface TeamsIndexProps {
    teams: TeamRow[];
    canManage: boolean;
    users: MemberMini[];
    colors: string[];
    stats: { total: number; with_lead: number; members_total: number };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Workspace', href: '/dashboard' },
    { title: 'Teams', href: '/teams' },
];

export default function TeamsIndex({ teams, canManage, users, colors, stats }: TeamsIndexProps) {
    const getInitials = useInitials();
    const [showCreate, setShowCreate] = useState(false);
    const [confirmDeleteSlug, setConfirmDeleteSlug] = useState<string | null>(null);

    const teamToDelete = teams.find((t) => t.slug === confirmDeleteSlug);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Teams" />
            <div className="flex w-full flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Workspace"
                    title="Teams"
                    description="Group people by squad, department, or initiative. Each team has a lead and members."
                    actions={
                        canManage && (
                            <Button onClick={() => setShowCreate(true)} className="gap-2">
                                <Plus className="size-4" /> New team
                            </Button>
                        )
                    }
                />

                <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <StatCard label="Teams" value={stats.total} icon={UsersRound} accent="violet" />
                    <StatCard label="With a lead" value={stats.with_lead} icon={Crown} accent="amber" />
                    <StatCard label="Total memberships" value={stats.members_total} icon={Users} accent="emerald" />
                </section>

                {teams.length === 0 ? (
                    <SoftCard>
                        <div className="flex flex-col items-center justify-center gap-3 p-16 text-center">
                            <div className="from-violet-500 to-indigo-600 shadow-glow flex size-14 items-center justify-center rounded-2xl bg-gradient-to-br text-white">
                                <UsersRound className="size-6" />
                            </div>
                            <div>
                                <p className="font-display text-lg font-bold">No teams yet</p>
                                <p className="text-muted-foreground mt-1 max-w-sm text-sm">
                                    {canManage ? 'Spin up your first team to organize people around projects or departments.' : 'A manager will set up teams here.'}
                                </p>
                            </div>
                            {canManage && (
                                <Button onClick={() => setShowCreate(true)} className="mt-2 gap-2">
                                    <Plus className="size-4" /> Create team
                                </Button>
                            )}
                        </div>
                    </SoftCard>
                ) : (
                    <section className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                        {teams.map((t) => (
                            <SoftCard key={t.id} className="group">
                                <div className={cn('relative h-20 overflow-hidden rounded-t-2xl bg-gradient-to-br', COLOR_GRADIENT[t.color] ?? COLOR_GRADIENT.violet)}>
                                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_30%_30%,rgba(255,255,255,0.3),transparent)]" />
                                </div>
                                <div className="-mt-7 flex flex-col gap-3 p-5 pt-0">
                                    <div className="bg-card ring-card flex size-12 items-center justify-center rounded-2xl ring-4 shadow-soft-sm">
                                        <div className={cn('flex size-9 items-center justify-center rounded-xl bg-gradient-to-br text-sm font-bold text-white shadow-soft-xs', COLOR_GRADIENT[t.color] ?? COLOR_GRADIENT.violet)}>
                                            {t.name.slice(0, 2).toUpperCase()}
                                        </div>
                                    </div>
                                    <div>
                                        <Link href={route('teams.show', t.slug)} className="font-display line-clamp-1 text-base font-bold tracking-tight hover:text-violet-600 dark:hover:text-violet-300">
                                            {t.name}
                                        </Link>
                                        {t.description && <p className="text-muted-foreground line-clamp-2 mt-1 text-xs leading-relaxed">{t.description}</p>}
                                    </div>
                                    <div className="text-muted-foreground flex items-center gap-3 text-[11px] font-medium">
                                        {t.lead && (
                                            <span className="inline-flex items-center gap-1.5">
                                                <Crown className="size-3 text-amber-500" />
                                                {t.lead.name}
                                            </span>
                                        )}
                                        <span className="inline-flex items-center gap-1">
                                            <Users className="size-3" />
                                            {t.members_count} member{t.members_count === 1 ? '' : 's'}
                                        </span>
                                    </div>
                                    <div className="border-border/60 flex items-center justify-between border-t pt-3">
                                        <div className="flex -space-x-2">
                                            {t.members.slice(0, 5).map((m) => (
                                                <div key={m.id} className="ring-card flex size-7 items-center justify-center rounded-full bg-gradient-to-br from-violet-500 to-indigo-600 text-[10px] font-bold text-white ring-2" title={m.name}>
                                                    {getInitials(m.name)}
                                                </div>
                                            ))}
                                            {t.members.length > 5 && (
                                                <div className="ring-card bg-muted text-muted-foreground inline-flex size-7 items-center justify-center rounded-full text-[10px] font-bold ring-2">
                                                    +{t.members.length - 5}
                                                </div>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-1">
                                            <Button asChild size="sm" variant="ghost"><Link href={route('teams.show', t.slug)}>View</Link></Button>
                                            {canManage && (
                                                <Button onClick={() => setConfirmDeleteSlug(t.slug)} variant="ghost" size="icon" className="text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10">
                                                    <Trash2 className="size-3.5" />
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </SoftCard>
                        ))}
                    </section>
                )}
            </div>

            <CreateTeamDialog open={showCreate} onOpenChange={setShowCreate} users={users} colors={colors} />

            <ConfirmDialog
                open={confirmDeleteSlug !== null}
                onOpenChange={(o) => !o && setConfirmDeleteSlug(null)}
                title={teamToDelete ? `Delete team "${teamToDelete.name}"?` : 'Delete team?'}
                description={teamToDelete && teamToDelete.members_count > 0
                    ? `This team has ${teamToDelete.members_count} member(s). They will be removed from this team. This cannot be undone.`
                    : 'This team will be removed permanently. This cannot be undone.'}
                confirmLabel="Delete team"
                tone="destructive"
                icon={Trash2}
                onConfirm={() => {
                    if (confirmDeleteSlug) router.delete(route('teams.destroy', confirmDeleteSlug));
                    setConfirmDeleteSlug(null);
                }}
            />
        </AppLayout>
    );
}

function CreateTeamDialog({ open, onOpenChange, users, colors }: { open: boolean; onOpenChange: (o: boolean) => void; users: MemberMini[]; colors: string[] }) {
    const getInitials = useInitials();
    const form = useForm<{ name: string; description: string; color: string; lead_id: number | null; member_ids: number[] }>({
        name: '', description: '', color: 'violet', lead_id: null, member_ids: [],
    });

    const toggleMember = (id: number) => {
        const set = new Set(form.data.member_ids);
        if (set.has(id)) set.delete(id); else set.add(id);
        form.setData('member_ids', Array.from(set));
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl rounded-2xl">
                <DialogHeader>
                    <DialogTitle className="font-display text-xl font-bold">Create a team</DialogTitle>
                    <DialogDescription>Pick a name, choose a lead, and add members.</DialogDescription>
                </DialogHeader>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(route('teams.store'), {
                            onSuccess: () => { form.reset(); onOpenChange(false); },
                        });
                    }}
                    className="space-y-4"
                >
                    <div className="grid gap-3 md:grid-cols-2">
                        <div className="space-y-1.5">
                            <Label className="text-muted-foreground text-[10px] font-bold uppercase tracking-[0.14em]">Name</Label>
                            <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="e.g. Mobile Engineering" required autoFocus />
                            {form.errors.name && <p className="text-rose-600 text-xs">{form.errors.name}</p>}
                        </div>
                        <div className="space-y-1.5">
                            <Label className="text-muted-foreground text-[10px] font-bold uppercase tracking-[0.14em]">Lead</Label>
                            <Select value={form.data.lead_id ? String(form.data.lead_id) : 'none'} onValueChange={(v) => form.setData('lead_id', v === 'none' ? null : Number(v))}>
                                <SelectTrigger className="h-11 rounded-xl"><SelectValue placeholder="No lead" /></SelectTrigger>
                                <SelectContent className="rounded-xl">
                                    <SelectItem value="none">No lead</SelectItem>
                                    {users.map((u) => <SelectItem key={u.id} value={String(u.id)}>{u.name}{u.job_title ? ` · ${u.job_title}` : ''}</SelectItem>)}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label className="text-muted-foreground text-[10px] font-bold uppercase tracking-[0.14em]">Description</Label>
                        <Input value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} placeholder="What does this team do?" />
                    </div>

                    <div className="space-y-1.5">
                        <Label className="text-muted-foreground text-[10px] font-bold uppercase tracking-[0.14em]">Color</Label>
                        <div className="flex flex-wrap gap-2">
                            {colors.map((c) => (
                                <button
                                    key={c}
                                    type="button"
                                    onClick={() => form.setData('color', c)}
                                    className={cn('relative size-9 overflow-hidden rounded-xl bg-gradient-to-br transition-all', COLOR_GRADIENT[c as ProjectColor],
                                        form.data.color === c ? 'shadow-glow ring-foreground ring-2 scale-110' : 'ring-border ring-1 hover:scale-105')}
                                    aria-label={c}
                                >
                                    {form.data.color === c && <Check className="absolute inset-0 m-auto size-4 text-white" />}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label className="text-muted-foreground text-[10px] font-bold uppercase tracking-[0.14em]">Members</Label>
                        <div className="grid max-h-[40vh] gap-1.5 overflow-y-auto rounded-xl border border-border/60 bg-muted/20 p-2 sm:grid-cols-2">
                            {users.map((u) => {
                                const checked = form.data.member_ids.includes(u.id);
                                return (
                                    <button
                                        key={u.id}
                                        type="button"
                                        onClick={() => toggleMember(u.id)}
                                        className={cn('group flex items-center gap-2.5 rounded-lg p-2 text-left text-xs ring-1 transition-all',
                                            checked ? 'bg-violet-50 ring-violet-200 dark:bg-violet-500/10 dark:ring-violet-500/30' : 'bg-card ring-border/50 hover:ring-foreground/20')}
                                    >
                                        <div className="from-violet-500 to-indigo-600 ring-card flex size-7 items-center justify-center rounded-full bg-gradient-to-br text-[10px] font-bold text-white ring-2 shrink-0">
                                            {getInitials(u.name)}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate font-semibold">{u.name}</p>
                                            <p className="text-muted-foreground truncate text-[10px]">{u.job_title ?? u.email}</p>
                                        </div>
                                        <div className={cn('flex size-4 items-center justify-center rounded-full border-2', checked ? 'border-violet-600 bg-gradient-to-br from-violet-600 to-indigo-600' : 'border-muted-foreground/30')}>
                                            {checked && <Check className="size-2.5 text-white" />}
                                        </div>
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    <DialogFooter className="gap-2">
                        <Button type="button" variant="ghost" onClick={() => onOpenChange(false)} disabled={form.processing}>Cancel</Button>
                        <Button type="submit" disabled={form.processing || !form.data.name.trim()} className="gap-2">
                            {form.processing && <LoaderCircle className="size-4 animate-spin" />} Create team
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
