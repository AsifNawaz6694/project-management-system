import { PageHeader } from '@/components/page-header';
import { SoftCard, SoftCardBody } from '@/components/soft-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { TRIGGER_LABEL } from '@/lib/automations';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Settings2, Zap } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Administration', href: '/dashboard' },
    { title: 'Automation', href: '/automations' },
];

interface RuleRow {
    id: number;
    name: string;
    description: string | null;
    trigger: string;
    is_active: boolean;
    project: string | null;
    conditions_count: number;
    actions_count: number;
    run_count: number;
    last_run_at: string | null;
}

interface Props {
    rules: RuleRow[];
    triggers: string[];
    can: { manage: boolean };
}

export default function AutomationsIndex({ rules, triggers, can }: Props) {
    const [creating, setCreating] = useState(false);
    const [name, setName] = useState('');
    const [trigger, setTrigger] = useState(triggers[0]);
    const [saving, setSaving] = useState(false);

    const create = () => {
        if (!name.trim()) return;
        setSaving(true);
        router.post(
            route('automations.store'),
            { name: name.trim(), trigger },
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
            <Head title="Automation" />

            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    eyebrow="Administration"
                    title="Automation"
                    description="When something happens, check a condition, then act — without anyone having to remember."
                    actions={
                        can.manage && !creating ? (
                            <Button size="sm" onClick={() => setCreating(true)}>
                                <Plus className="size-4" />
                                New rule
                            </Button>
                        ) : undefined
                    }
                />

                {creating && (
                    <SoftCard>
                        <SoftCardBody className="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <Input
                                autoFocus
                                value={name}
                                placeholder="Rule name, e.g. Notify QA when a task is ready"
                                onChange={(e) => setName(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && create()}
                                className="sm:max-w-sm"
                            />
                            <select
                                value={trigger}
                                onChange={(e) => setTrigger(e.target.value)}
                                className="border-border/60 bg-background h-9 rounded-md border px-2 text-sm"
                            >
                                {triggers.map((t) => (
                                    <option key={t} value={t}>
                                        {TRIGGER_LABEL[t] ?? t}
                                    </option>
                                ))}
                            </select>
                            <div className="flex items-center gap-2">
                                <Button size="sm" onClick={create} disabled={saving || !name.trim()}>
                                    Create
                                </Button>
                                <Button size="sm" variant="ghost" onClick={() => setCreating(false)}>
                                    Cancel
                                </Button>
                            </div>
                        </SoftCardBody>
                    </SoftCard>
                )}

                {rules.length === 0 && !creating && (
                    <SoftCard>
                        <SoftCardBody className="text-muted-foreground py-10 text-center text-sm">
                            <Zap className="mx-auto mb-2 size-6 opacity-40" />
                            No rules yet. A rule reacts to a task event and does something on your behalf.
                        </SoftCardBody>
                    </SoftCard>
                )}

                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    {rules.map((rule) => (
                        <SoftCard key={rule.id} className="flex flex-col">
                            <SoftCardBody className="flex flex-1 flex-col gap-3">
                                <div className="flex items-start gap-2">
                                    <span
                                        className={cn('mt-1 size-2 shrink-0 rounded-full', rule.is_active ? 'bg-emerald-500' : 'bg-slate-300')}
                                        aria-label={rule.is_active ? 'Active' : 'Paused'}
                                    />
                                    <div className="min-w-0 flex-1">
                                        <h3 className="truncate text-sm font-semibold">{rule.name}</h3>
                                        <p className="text-muted-foreground mt-0.5 text-xs">{TRIGGER_LABEL[rule.trigger] ?? rule.trigger}</p>
                                        <p className="text-muted-foreground mt-0.5 text-xs">{rule.project ?? 'Every project'}</p>
                                    </div>
                                </div>

                                <dl className="grid grid-cols-3 gap-2 text-xs">
                                    <div className="bg-muted/40 rounded-lg px-2.5 py-2">
                                        <dt className="text-muted-foreground">If</dt>
                                        <dd className="font-semibold">{rule.conditions_count}</dd>
                                    </div>
                                    <div className="bg-muted/40 rounded-lg px-2.5 py-2">
                                        <dt className="text-muted-foreground">Then</dt>
                                        <dd className="font-semibold">{rule.actions_count}</dd>
                                    </div>
                                    <div className="bg-muted/40 rounded-lg px-2.5 py-2">
                                        <dt className="text-muted-foreground">Runs</dt>
                                        <dd className="font-semibold">{rule.run_count}</dd>
                                    </div>
                                </dl>

                                {can.manage && (
                                    <div className="mt-auto flex items-center gap-2 pt-1">
                                        <Button size="sm" variant="outline" asChild>
                                            <Link href={route('automations.edit', rule.id)}>
                                                <Settings2 className="size-3.5" />
                                                Configure
                                            </Link>
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() => router.post(route('automations.toggle', rule.id), {}, { preserveScroll: true })}
                                        >
                                            {rule.is_active ? 'Pause' : 'Switch on'}
                                        </Button>
                                    </div>
                                )}
                            </SoftCardBody>
                        </SoftCard>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
