import { ConfirmDialog } from '@/components/confirm-dialog';
import { PageHeader } from '@/components/page-header';
import { SoftCard, SoftCardBody, SoftCardTitle } from '@/components/soft-card';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { ClipboardList, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface Template {
    id: number;
    name: string;
    kind: string;
    description: string | null;
    agenda_items: Array<{ title: string; time_allocation_minutes?: number | null }> | null;
    is_shared: boolean;
    creator: { id: number; name: string } | null;
}

interface Props {
    templates: Template[];
    kinds: string[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Workspace', href: '/dashboard' },
    { title: 'Meetings', href: '/meetings' },
    { title: 'Templates', href: '/meetings/templates' },
];

export default function TemplatesIndex({ templates, kinds }: Props) {
    const [showCreate, setShowCreate] = useState(false);
    const [confirmDeleteId, setConfirmDeleteId] = useState<number | null>(null);

    const form = useForm<{
        name: string;
        kind: string;
        description: string;
        agenda_items: Array<{ title: string; time_allocation_minutes: number | null }>;
        is_shared: boolean;
    }>({
        name: '',
        kind: 'team',
        description: '',
        agenda_items: [{ title: '', time_allocation_minutes: 5 }],
        is_shared: true,
    });

    const submitNew = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(route('meetings.templates.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setShowCreate(false);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Meeting templates" />
            <div className="flex w-full flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Meetings"
                    title="Templates"
                    description="Reusable meeting blueprints for 1:1s, retros, planning, kickoffs, and standups."
                    actions={
                        <Button className="gap-2" onClick={() => setShowCreate(true)}>
                            <Plus className="size-4" /> New template
                        </Button>
                    }
                />

                <SoftCard>
                    <SoftCardTitle eyebrow="Library">Available templates</SoftCardTitle>
                    <SoftCardBody>
                        {templates.length === 0 ? (
                            <p className="text-muted-foreground text-sm">No templates yet — create one to skip retyping the same agenda each week.</p>
                        ) : (
                            <ul className="grid gap-3 md:grid-cols-2">
                                {templates.map((t) => (
                                    <li key={t.id} className="bg-muted/30 ring-border/60 rounded-xl p-3.5 ring-1">
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="min-w-0 flex-1">
                                                <p className="inline-flex items-center gap-1.5 text-sm font-bold">
                                                    <ClipboardList className="size-3.5 text-blue-500" /> {t.name}
                                                </p>
                                                <p className="text-muted-foreground text-[11px] capitalize">
                                                    {t.kind.replace('_', ' ')} {t.creator ? `· by ${t.creator.name}` : ''}
                                                </p>
                                            </div>
                                            <button
                                                onClick={() => setConfirmDeleteId(t.id)}
                                                className="inline-flex size-7 items-center justify-center rounded-lg text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10"
                                            >
                                                <Trash2 className="size-3.5" />
                                            </button>
                                        </div>
                                        {t.description && <p className="text-muted-foreground mt-2 text-xs">{t.description}</p>}
                                        {t.agenda_items && t.agenda_items.length > 0 && (
                                            <ul className="text-muted-foreground mt-2 space-y-0.5 text-xs">
                                                {t.agenda_items.slice(0, 4).map((a, i) => (
                                                    <li key={i}>
                                                        • {a.title}
                                                        {a.time_allocation_minutes ? ` (${a.time_allocation_minutes}m)` : ''}
                                                    </li>
                                                ))}
                                                {t.agenda_items.length > 4 && <li>+ {t.agenda_items.length - 4} more</li>}
                                            </ul>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </SoftCardBody>
                </SoftCard>
            </div>

            <Dialog open={showCreate} onOpenChange={setShowCreate}>
                <DialogContent className="rounded-2xl">
                    <DialogHeader>
                        <DialogTitle>New meeting template</DialogTitle>
                        <DialogDescription>Capture the recurring agenda once, reuse it whenever you schedule.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitNew} className="space-y-3">
                        <div className="grid gap-2">
                            <Label className="text-muted-foreground text-[10px] font-bold tracking-[0.14em] uppercase">Name</Label>
                            <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                        </div>
                        <div className="grid gap-2">
                            <Label className="text-muted-foreground text-[10px] font-bold tracking-[0.14em] uppercase">Kind</Label>
                            <Select value={form.data.kind} onValueChange={(v) => form.setData('kind', v)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {kinds.map((k) => (
                                        <SelectItem key={k} value={k}>
                                            {k.replace('_', ' ')}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-2">
                            <Label className="text-muted-foreground text-[10px] font-bold tracking-[0.14em] uppercase">Description</Label>
                            <textarea
                                value={form.data.description}
                                onChange={(e) => form.setData('description', e.target.value)}
                                rows={2}
                                className="bg-card ring-border rounded-lg px-3 py-2 text-sm ring-1"
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label className="text-muted-foreground text-[10px] font-bold tracking-[0.14em] uppercase">Agenda items</Label>
                            {form.data.agenda_items.map((a, i) => (
                                <div key={i} className="grid gap-2 sm:grid-cols-[1fr_100px_auto]">
                                    <Input
                                        value={a.title}
                                        onChange={(e) => {
                                            const next = [...form.data.agenda_items];
                                            next[i] = { ...next[i], title: e.target.value };
                                            form.setData('agenda_items', next);
                                        }}
                                        placeholder={`Topic ${i + 1}`}
                                    />
                                    <Input
                                        type="number"
                                        value={a.time_allocation_minutes ?? ''}
                                        onChange={(e) => {
                                            const next = [...form.data.agenda_items];
                                            next[i] = { ...next[i], time_allocation_minutes: e.target.value ? Number(e.target.value) : null };
                                            form.setData('agenda_items', next);
                                        }}
                                        placeholder="Min"
                                    />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                            form.setData(
                                                'agenda_items',
                                                form.data.agenda_items.filter((_, idx) => idx !== i),
                                            )
                                        }
                                    >
                                        <Trash2 className="size-3.5" />
                                    </Button>
                                </div>
                            ))}
                            <Button
                                type="button"
                                variant="soft"
                                size="sm"
                                className="gap-1.5 justify-self-start"
                                onClick={() => form.setData('agenda_items', [...form.data.agenda_items, { title: '', time_allocation_minutes: 5 }])}
                            >
                                <Plus className="size-3.5" /> Add item
                            </Button>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="ghost" onClick={() => setShowCreate(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                Save template
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={confirmDeleteId !== null}
                onOpenChange={(o) => !o && setConfirmDeleteId(null)}
                title="Delete this template?"
                description="Existing meetings created from it are not affected."
                confirmLabel="Delete"
                tone="destructive"
                icon={Trash2}
                onConfirm={() => {
                    if (confirmDeleteId) router.delete(route('meetings.templates.destroy', confirmDeleteId), { preserveScroll: true });
                    setConfirmDeleteId(null);
                }}
            />
        </AppLayout>
    );
}
