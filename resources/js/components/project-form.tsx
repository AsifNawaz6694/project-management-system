import InputError from '@/components/input-error';
import { SoftCard, SoftCardBody, SoftCardTitle } from '@/components/soft-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useInitials } from '@/hooks/use-initials';
import {
    COLOR_GRADIENT,
    PRIORITY_META,
    STATUS_META,
    type ProjectColor,
    type ProjectPriority,
    type ProjectStatus,
} from '@/lib/projects';
import { CURRENCY_META, SUPPORTED_CURRENCIES } from '@/lib/currency';
import { cn } from '@/lib/utils';
import { Link, useForm } from '@inertiajs/react';
import { Calendar, Check, CheckCircle2, Circle, LoaderCircle, Plus, Trash2 } from 'lucide-react';
import { type FormEventHandler } from 'react';

export interface AssignableUser {
    id: number;
    name: string;
    email: string;
    avatar?: string | null;
    initials: string;
    job_title?: string | null;
    department?: string | null;
    primary_role?: string | null;
}

export interface MilestoneInput {
    title: string;
    description: string;
    due_date: string;
    completed: boolean;
}

export interface ProjectFormState {
    title: string;
    description: string;
    status: ProjectStatus;
    priority: ProjectPriority;
    color: ProjectColor;
    start_date: string;
    end_date: string;
    budget: string;
    currency: string;
    progress: number;
    owner_id: number | null;
    member_ids: number[];
    milestones: MilestoneInput[];
}

interface ProjectFormProps {
    initial: ProjectFormState;
    users: AssignableUser[];
    statuses: string[];
    priorities: string[];
    colors: string[];
    currencies?: string[];
    submitUrl: string;
    submitMethod: 'post' | 'patch';
    submitLabel: string;
    cancelUrl: string;
}

export function ProjectForm({ initial, users, statuses, priorities, colors, currencies, submitUrl, submitMethod, submitLabel, cancelUrl }: ProjectFormProps) {
    const currencyList = (currencies && currencies.length > 0 ? currencies : SUPPORTED_CURRENCIES) as string[];
    const getInitials = useInitials();
    const { data, setData, post, patch, processing, errors } = useForm<ProjectFormState>({ ...initial });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const fn = submitMethod === 'post' ? post : patch;
        fn(submitUrl, { preserveScroll: true });
    };

    const toggleMember = (userId: number) => {
        setData('member_ids', data.member_ids.includes(userId) ? data.member_ids.filter((id) => id !== userId) : [...data.member_ids, userId]);
    };

    const setMilestone = (i: number, patch: Partial<MilestoneInput>) => {
        setData(
            'milestones',
            data.milestones.map((m, idx) => (idx === i ? { ...m, ...patch } : m)),
        );
    };

    const addMilestone = () => {
        setData('milestones', [...data.milestones, { title: '', description: '', due_date: '', completed: false }]);
    };

    const removeMilestone = (i: number) => {
        setData(
            'milestones',
            data.milestones.filter((_, idx) => idx !== i),
        );
    };

    return (
        <form onSubmit={submit} className="space-y-5">
            <SoftCard>
                <SoftCardTitle eyebrow="Project">Basics</SoftCardTitle>
                <SoftCardBody className="grid gap-4 md:grid-cols-2">
                    <Field label="Title" error={errors.title} className="md:col-span-2">
                        <Input value={data.title} onChange={(e) => setData('title', e.target.value)} required autoFocus placeholder="e.g. Mobile App Redesign" />
                    </Field>
                    <Field label="Description" error={errors.description} className="md:col-span-2">
                        <textarea
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            rows={3}
                            className="bg-card shadow-soft-xs ring-border focus-visible:border-foreground/30 focus-visible:ring-violet-200/60 dark:focus-visible:ring-violet-500/20 hover:border-foreground/20 ring-1 w-full rounded-xl px-3.5 py-2.5 text-sm transition-all focus-visible:outline-none focus-visible:ring-4"
                            placeholder="What does this project deliver, who is it for, and how will success be measured?"
                        />
                    </Field>

                    <Field label="Status" error={errors.status}>
                        <Select value={data.status} onValueChange={(v) => setData('status', v as ProjectStatus)}>
                            <SelectTrigger className="h-11 rounded-xl">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent className="rounded-xl">
                                {statuses.map((s) => (
                                    <SelectItem key={s} value={s}>
                                        {STATUS_META[s as ProjectStatus]?.label ?? s}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                    <Field label="Priority" error={errors.priority}>
                        <Select value={data.priority} onValueChange={(v) => setData('priority', v as ProjectPriority)}>
                            <SelectTrigger className="h-11 rounded-xl">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent className="rounded-xl">
                                {priorities.map((p) => (
                                    <SelectItem key={p} value={p}>
                                        {PRIORITY_META[p as ProjectPriority]?.label ?? p}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>

                    <Field label="Start date" error={errors.start_date}>
                        <Input type="date" value={data.start_date} onChange={(e) => setData('start_date', e.target.value)} />
                    </Field>
                    <Field label="End date" error={errors.end_date}>
                        <Input type="date" value={data.end_date} onChange={(e) => setData('end_date', e.target.value)} />
                    </Field>

                    <Field label="Budget" error={errors.budget}>
                        <div className="flex gap-2">
                            <Select value={data.currency} onValueChange={(v) => setData('currency', v)}>
                                <SelectTrigger className="h-11 w-[120px] rounded-xl"><SelectValue /></SelectTrigger>
                                <SelectContent className="rounded-xl">
                                    {currencyList.map((c) => (
                                        <SelectItem key={c} value={c}>
                                            {c} · {CURRENCY_META[c as keyof typeof CURRENCY_META]?.label ?? c}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Input
                                type="number"
                                step="0.01"
                                min="0"
                                value={data.budget}
                                onChange={(e) => setData('budget', e.target.value)}
                                placeholder="50000"
                                className="flex-1"
                            />
                        </div>
                    </Field>
                    <Field label="Progress %" error={errors.progress}>
                        <Input
                            type="number"
                            min="0"
                            max="100"
                            value={data.progress}
                            onChange={(e) => setData('progress', Math.max(0, Math.min(100, Number(e.target.value))))}
                        />
                    </Field>

                    <Field label="Visual color" className="md:col-span-2">
                        <div className="flex flex-wrap gap-2">
                            {colors.map((c) => (
                                <button
                                    type="button"
                                    key={c}
                                    onClick={() => setData('color', c as ProjectColor)}
                                    className={cn(
                                        'group/swatch relative size-10 overflow-hidden rounded-xl bg-gradient-to-br transition-all',
                                        COLOR_GRADIENT[c as ProjectColor],
                                        data.color === c ? 'shadow-glow ring-foreground ring-2 scale-110' : 'ring-border ring-1 hover:scale-105',
                                    )}
                                    aria-label={c}
                                >
                                    {data.color === c && <Check className="absolute inset-0 m-auto size-4 text-white" />}
                                </button>
                            ))}
                        </div>
                    </Field>
                </SoftCardBody>
            </SoftCard>

            <SoftCard>
                <SoftCardTitle eyebrow="Team">
                    {data.member_ids.length === 0 ? 'Assign members' : `${data.member_ids.length} member${data.member_ids.length === 1 ? '' : 's'} assigned`}
                </SoftCardTitle>
                <SoftCardBody>
                    <Label className="text-muted-foreground mb-2 block text-[10px] font-bold uppercase tracking-[0.14em]">Project owner</Label>
                    <Select
                        value={data.owner_id ? String(data.owner_id) : ''}
                        onValueChange={(v) => setData('owner_id', v ? Number(v) : null)}
                    >
                        <SelectTrigger className="h-11 rounded-xl">
                            <SelectValue placeholder="Choose an owner (manager or admin)" />
                        </SelectTrigger>
                        <SelectContent className="rounded-xl">
                            {users
                                .filter((u) => u.primary_role === 'admin' || u.primary_role === 'manager')
                                .map((u) => (
                                    <SelectItem key={u.id} value={String(u.id)}>
                                        {u.name} · {u.job_title ?? u.email}
                                    </SelectItem>
                                ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.owner_id} className="mt-1" />

                    <Label className="text-muted-foreground mb-2 mt-5 block text-[10px] font-bold uppercase tracking-[0.14em]">Members</Label>
                    <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        {users.map((u) => {
                            const checked = data.member_ids.includes(u.id);
                            return (
                                <button
                                    type="button"
                                    key={u.id}
                                    onClick={() => toggleMember(u.id)}
                                    className={cn(
                                        'group flex items-center gap-3 rounded-xl p-3 text-left ring-1 transition-all',
                                        checked
                                            ? 'shadow-soft-sm bg-gradient-to-br from-violet-50 to-indigo-50 ring-violet-300 dark:from-violet-500/10 dark:to-indigo-500/10 dark:ring-violet-500/30'
                                            : 'bg-muted/30 ring-border/60 hover:ring-foreground/20',
                                    )}
                                >
                                    <div className="flex size-10 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500 to-indigo-600 text-xs font-bold text-white shadow-soft-xs ring-2 ring-card">
                                        {u.initials || getInitials(u.name)}
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-semibold">{u.name}</p>
                                        <p className="text-muted-foreground truncate text-[11px]">{u.job_title ?? u.email}</p>
                                    </div>
                                    <div
                                        className={cn(
                                            'flex size-5 shrink-0 items-center justify-center rounded-full border-2 transition-colors',
                                            checked ? 'border-violet-600 bg-gradient-to-br from-violet-600 to-indigo-600' : 'border-muted-foreground/30',
                                        )}
                                    >
                                        {checked && <Check className="size-3 text-white" />}
                                    </div>
                                </button>
                            );
                        })}
                    </div>
                    <InputError message={errors.member_ids} className="mt-2" />
                </SoftCardBody>
            </SoftCard>

            <SoftCard>
                <SoftCardTitle
                    eyebrow="Roadmap"
                    action={
                        <Button type="button" variant="soft" size="sm" onClick={addMilestone} className="gap-1.5">
                            <Plus className="size-3.5" /> Add milestone
                        </Button>
                    }
                >
                    Milestones
                </SoftCardTitle>
                <SoftCardBody>
                    {data.milestones.length === 0 ? (
                        <div className="bg-muted/40 ring-border/60 ring-1 rounded-xl p-8 text-center">
                            <Calendar className="text-muted-foreground mx-auto size-5" />
                            <p className="mt-2 text-sm font-semibold">No milestones yet</p>
                            <p className="text-muted-foreground mt-1 text-xs">Break the project into checkpoints to track progress.</p>
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {data.milestones.map((m, i) => (
                                <div key={i} className="bg-muted/30 ring-border/60 ring-1 grid gap-3 rounded-2xl p-4 md:grid-cols-[auto_1fr_180px_auto]">
                                    <button
                                        type="button"
                                        onClick={() => setMilestone(i, { completed: !m.completed })}
                                        className={cn(
                                            'flex size-10 items-center justify-center self-center rounded-xl transition-all',
                                            m.completed
                                                ? 'shadow-soft-sm bg-gradient-to-br from-emerald-500 to-teal-600 text-white'
                                                : 'bg-card text-muted-foreground ring-border ring-1 hover:text-foreground',
                                        )}
                                        aria-label="Toggle complete"
                                    >
                                        {m.completed ? <CheckCircle2 className="size-5" /> : <Circle className="size-5" />}
                                    </button>
                                    <Input
                                        value={m.title}
                                        onChange={(e) => setMilestone(i, { title: e.target.value })}
                                        placeholder="Milestone title"
                                    />
                                    <Input
                                        type="date"
                                        value={m.due_date}
                                        onChange={(e) => setMilestone(i, { due_date: e.target.value })}
                                    />
                                    <Button type="button" variant="ghost" size="icon" className="text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10" onClick={() => removeMilestone(i)}>
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>
                            ))}
                        </div>
                    )}
                </SoftCardBody>
            </SoftCard>

            <div className="flex items-center justify-end gap-2">
                <Button asChild variant="ghost">
                    <Link href={cancelUrl}>Cancel</Link>
                </Button>
                <Button type="submit" disabled={processing} className="gap-2">
                    {processing && <LoaderCircle className="size-4 animate-spin" />}
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}

function Field({ label, error, children, className }: { label: string; error?: string; children: React.ReactNode; className?: string }) {
    return (
        <div className={cn('space-y-1.5', className)}>
            <Label className="text-muted-foreground text-[10px] font-bold uppercase tracking-[0.14em]">{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
