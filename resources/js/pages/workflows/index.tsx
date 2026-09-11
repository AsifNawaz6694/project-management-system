import { PageHeader } from '@/components/page-header';
import { SoftCard, SoftCardBody, SoftCardTitle } from '@/components/soft-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { statusChip, statusDot } from '@/lib/tasks';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { GitBranch, Plus, Settings2, Star } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Administration', href: '/dashboard' },
    { title: 'Workflows', href: '/workflows' },
];

interface WorkflowRow {
    id: number;
    name: string;
    description: string | null;
    is_default: boolean;
    is_system: boolean;
    projects_count: number;
    transitions_count: number;
    assignments_count: number;
    statuses: Array<{ key: string; name: string; color: string; category: string }>;
}

interface WorkflowsIndexProps {
    workflows: WorkflowRow[];
    can: { manage: boolean };
}

export default function WorkflowsIndex({ workflows, can }: WorkflowsIndexProps) {
    const [creating, setCreating] = useState(false);
    const [name, setName] = useState('');
    const [saving, setSaving] = useState(false);

    const create = () => {
        if (!name.trim()) return;
        setSaving(true);
        router.post(
            route('workflows.store'),
            { name: name.trim() },
            {
                onFinish: () => {
                    setSaving(false);
                    setName('');
                    setCreating(false);
                },
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Workflows" />

            <div className="flex w-full flex-1 flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    eyebrow="Administration"
                    title="Workflows"
                    description="Define the stages work moves through, and the rules for moving between them."
                    actions={
                        can.manage && (
                            <Button size="sm" onClick={() => setCreating((v) => !v)}>
                                <Plus className="size-4" /> New workflow
                            </Button>
                        )
                    }
                />

                {creating && (
                    <SoftCard>
                        <SoftCardBody className="flex flex-wrap items-end gap-2">
                            <div className="min-w-[220px] flex-1">
                                <label htmlFor="wf-name" className="mb-1 block text-xs font-semibold">
                                    Workflow name
                                </label>
                                <Input
                                    id="wf-name"
                                    value={name}
                                    onChange={(e) => setName(e.target.value)}
                                    placeholder="e.g. Marketing campaign"
                                    autoFocus
                                    onKeyDown={(e) => e.key === 'Enter' && create()}
                                />
                            </div>
                            <Button size="sm" onClick={create} disabled={!name.trim() || saving}>
                                Create
                            </Button>
                            <Button size="sm" variant="ghost" onClick={() => setCreating(false)}>
                                Cancel
                            </Button>
                            <p className="text-muted-foreground w-full text-[11px]">
                                A new workflow starts with To do / In progress / Done and no restrictions. You can add stages and rules next.
                            </p>
                        </SoftCardBody>
                    </SoftCard>
                )}

                <div className="grid gap-4 lg:grid-cols-2">
                    {workflows.map((wf) => (
                        <SoftCard key={wf.id}>
                            <SoftCardTitle
                                eyebrow={wf.is_default ? 'Default' : 'Workflow'}
                                action={
                                    can.manage && (
                                        <Button asChild size="sm" variant="soft">
                                            <Link href={route('workflows.edit', wf.id)}>
                                                <Settings2 className="size-3.5" /> Edit
                                            </Link>
                                        </Button>
                                    )
                                }
                            >
                                <span className="inline-flex items-center gap-1.5">
                                    {wf.is_default && <Star className="size-3.5 fill-amber-400 text-amber-500" />}
                                    {wf.name}
                                </span>
                            </SoftCardTitle>
                            <SoftCardBody className="space-y-3">
                                {wf.description && <p className="text-muted-foreground text-xs">{wf.description}</p>}

                                <div className="flex flex-wrap gap-1.5">
                                    {wf.statuses.map((s, i) => (
                                        <span key={s.key} className="inline-flex items-center gap-1.5">
                                            <span
                                                className={cn(
                                                    'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset',
                                                    statusChip(s.color),
                                                )}
                                            >
                                                <span className={cn('size-1.5 rounded-full', statusDot(s.color))} />
                                                {s.name}
                                            </span>
                                            {i < wf.statuses.length - 1 && <span className="text-muted-foreground text-[10px]">→</span>}
                                        </span>
                                    ))}
                                </div>

                                <div className="text-muted-foreground flex flex-wrap items-center gap-3 text-[11px]">
                                    <span className="inline-flex items-center gap-1">
                                        <GitBranch className="size-3" />
                                        {wf.transitions_count === 0
                                            ? 'No restrictions — any stage to any stage'
                                            : `${wf.transitions_count} transition rule(s)`}
                                    </span>
                                    <span>·</span>
                                    <span>
                                        {wf.projects_count} project{wf.projects_count === 1 ? '' : 's'}
                                    </span>
                                    {wf.assignments_count > 0 && (
                                        <>
                                            <span>·</span>
                                            <span>
                                                {wf.assignments_count} person/team override{wf.assignments_count === 1 ? '' : 's'}
                                            </span>
                                        </>
                                    )}
                                </div>
                            </SoftCardBody>
                        </SoftCard>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
