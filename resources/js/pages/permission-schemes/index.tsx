import { PageHeader } from '@/components/page-header';
import { SoftCard, SoftCardBody } from '@/components/soft-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Settings2, Star } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Administration', href: '/dashboard' },
    { title: 'Permission schemes', href: '/permission-schemes' },
];

interface SchemeRow {
    id: number;
    name: string;
    description: string | null;
    is_default: boolean;
    is_system: boolean;
    grants_count: number;
    projects_count: number;
}

interface Props {
    schemes: SchemeRow[];
    can: { manage: boolean };
}

export default function PermissionSchemesIndex({ schemes, can }: Props) {
    const [creating, setCreating] = useState(false);
    const [name, setName] = useState('');
    const [saving, setSaving] = useState(false);

    const create = () => {
        if (!name.trim()) return;
        setSaving(true);
        router.post(
            route('permission-schemes.store'),
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
            <Head title="Permission schemes" />

            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    eyebrow="Administration"
                    title="Permission schemes"
                    description="Decide who, inside each project, may actually use the permissions their role grants them."
                    actions={
                        can.manage && !creating ? (
                            <Button size="sm" onClick={() => setCreating(true)}>
                                <Plus className="size-4" />
                                New scheme
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
                                placeholder="Scheme name, e.g. Client projects"
                                onChange={(e) => setName(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && create()}
                                className="sm:max-w-sm"
                            />
                            <div className="flex items-center gap-2">
                                <Button size="sm" onClick={create} disabled={saving || !name.trim()}>
                                    Create
                                </Button>
                                <Button size="sm" variant="ghost" onClick={() => setCreating(false)}>
                                    Cancel
                                </Button>
                            </div>
                            <p className="text-muted-foreground text-xs sm:ml-auto">Starts as a copy of the default scheme.</p>
                        </SoftCardBody>
                    </SoftCard>
                )}

                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    {schemes.map((scheme) => (
                        <SoftCard key={scheme.id} className="flex flex-col">
                            <SoftCardBody className="flex flex-1 flex-col gap-3">
                                <div className="flex items-start gap-2">
                                    <div className="min-w-0 flex-1">
                                        <h3 className="flex items-center gap-1.5 truncate text-sm font-semibold">
                                            {scheme.name}
                                            {scheme.is_default && <Star className="size-3.5 shrink-0 fill-amber-400 text-amber-500" />}
                                        </h3>
                                        <p className="text-muted-foreground mt-0.5 line-clamp-2 text-xs">{scheme.description ?? 'No description.'}</p>
                                    </div>
                                </div>

                                <dl className="grid grid-cols-2 gap-2 text-xs">
                                    <div className="bg-muted/40 rounded-lg px-2.5 py-2">
                                        <dt className="text-muted-foreground">Rules</dt>
                                        <dd className="font-semibold">{scheme.grants_count}</dd>
                                    </div>
                                    <div className="bg-muted/40 rounded-lg px-2.5 py-2">
                                        <dt className="text-muted-foreground">Projects</dt>
                                        <dd className="font-semibold">{scheme.projects_count}</dd>
                                    </div>
                                </dl>

                                <div className={cn('mt-auto flex items-center gap-2 pt-1', !can.manage && 'hidden')}>
                                    <Button size="sm" variant="outline" asChild>
                                        <Link href={route('permission-schemes.edit', scheme.id)}>
                                            <Settings2 className="size-3.5" />
                                            Configure
                                        </Link>
                                    </Button>
                                    {!scheme.is_default && (
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() => router.post(route('permission-schemes.default', scheme.id), {}, { preserveScroll: true })}
                                        >
                                            Make default
                                        </Button>
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
