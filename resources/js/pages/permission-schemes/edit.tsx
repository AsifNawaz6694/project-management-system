import { FilterSelect, type FilterOption } from '@/components/filter-select';
import { PageHeader } from '@/components/page-header';
import { SoftCard, SoftCardBody, SoftCardTitle } from '@/components/soft-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Plus, Trash2, TriangleAlert } from 'lucide-react';
import { useMemo, useState } from 'react';

interface Grant {
    permission: string;
    grant_type: string;
    grant_value: string | null;
}

interface PermissionRow {
    slug: string;
    label: string;
    module: string;
}

interface Option {
    value: string;
    label: string;
}

interface Props {
    scheme: { id: number; name: string; description: string | null; is_default: boolean; is_system: boolean };
    permissions: PermissionRow[];
    grants: Grant[];
    grantTypes: string[];
    typesWithValue: string[];
    projectRoles: string[];
    workspaceRoles: Option[];
    teams: Option[];
    users: Option[];
    projects: Array<{ id: number; title: string; assigned: boolean }>;
}

const TYPE_LABEL: Record<string, string> = {
    everyone: 'Anyone with the permission',
    project_owner: 'Project owner',
    any_member: 'Any project member',
    project_role: 'Project role',
    assignee: 'Task assignee',
    reporter: 'Task reporter',
    team: 'Team',
    user: 'User',
    workspace_role: 'Workspace role',
};

const MODULE_LABEL: Record<string, string> = {
    projects: 'Projects',
    tasks: 'Tasks',
    reports: 'Reports',
};

export default function PermissionSchemeEdit({
    scheme,
    permissions,
    grants: initialGrants,
    grantTypes,
    typesWithValue,
    projectRoles,
    workspaceRoles,
    teams,
    users,
    projects,
}: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Permission schemes', href: '/permission-schemes' },
        { title: scheme.name, href: `/permission-schemes/${scheme.id}/edit` },
    ];

    const [grants, setGrants] = useState<Grant[]>(initialGrants);
    const [saving, setSaving] = useState(false);
    const [dirty, setDirty] = useState(false);

    // Which permission row currently has the "add rule" form open.
    const [adding, setAdding] = useState<string | null>(null);
    const [newType, setNewType] = useState('everyone');
    const [newValue, setNewValue] = useState('');

    const [name, setName] = useState(scheme.name);
    const [description, setDescription] = useState(scheme.description ?? '');

    const [assigned, setAssigned] = useState<string[]>(projects.filter((p) => p.assigned).map((p) => String(p.id)));

    const byModule = useMemo(() => {
        const groups: Record<string, PermissionRow[]> = {};
        permissions.forEach((p) => {
            (groups[p.module] ??= []).push(p);
        });
        return groups;
    }, [permissions]);

    const valueOptions = (type: string): Option[] => {
        if (type === 'project_role') return projectRoles.map((r) => ({ value: r, label: r[0].toUpperCase() + r.slice(1) }));
        if (type === 'workspace_role') return workspaceRoles;
        if (type === 'team') return teams;
        if (type === 'user') return users;
        return [];
    };

    const describe = (grant: Grant) => {
        const base = TYPE_LABEL[grant.grant_type] ?? grant.grant_type;
        if (!grant.grant_value) return base;
        const match = valueOptions(grant.grant_type).find((o) => o.value === grant.grant_value);
        return `${base}: ${match?.label ?? grant.grant_value}`;
    };

    const openAdd = (permission: string) => {
        setAdding(permission);
        setNewType('everyone');
        setNewValue('');
    };

    const confirmAdd = () => {
        if (!adding) return;
        const needsValue = typesWithValue.includes(newType);
        if (needsValue && !newValue) return;

        const value = needsValue ? newValue : null;
        const exists = grants.some((g) => g.permission === adding && g.grant_type === newType && g.grant_value === value);

        if (!exists) {
            setGrants((prev) => [...prev, { permission: adding, grant_type: newType, grant_value: value }]);
            setDirty(true);
        }

        setAdding(null);
    };

    const removeGrant = (grant: Grant) => {
        setGrants((prev) =>
            prev.filter((g) => !(g.permission === grant.permission && g.grant_type === grant.grant_type && g.grant_value === grant.grant_value)),
        );
        setDirty(true);
    };

    const saveGrants = () => {
        setSaving(true);
        router.put(route('permission-schemes.grants.update', scheme.id), { grants } as never, {
            preserveScroll: true,
            onSuccess: () => setDirty(false),
            onFinish: () => setSaving(false),
        });
    };

    const saveDetails = () => {
        router.patch(route('permission-schemes.update', scheme.id), { name, description }, { preserveScroll: true });
    };

    const saveProjects = () => {
        router.post(route('permission-schemes.projects.assign', scheme.id), { project_ids: assigned.map(Number) } as never, { preserveScroll: true });
    };

    const projectOptions: FilterOption[] = projects.map((p) => ({ value: String(p.id), label: p.title }));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${scheme.name} — permission scheme`} />

            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    eyebrow="Permission scheme"
                    title={scheme.name}
                    description="A permission with no rules is unrestricted — everyone whose role holds it may use it. Add rules only to the permissions you want to narrow."
                    actions={
                        <Button size="sm" onClick={saveGrants} disabled={saving || !dirty}>
                            {dirty ? 'Save rules' : 'Saved'}
                        </Button>
                    }
                />

                <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_20rem]">
                    <div className="flex flex-col gap-4">
                        {Object.entries(byModule).map(([module, rows]) => (
                            <SoftCard key={module}>
                                <SoftCardTitle>{MODULE_LABEL[module] ?? module}</SoftCardTitle>
                                <SoftCardBody className="flex flex-col gap-0 pt-0">
                                    {rows.map((row) => {
                                        const rowGrants = grants.filter((g) => g.permission === row.slug);
                                        const open = adding === row.slug;
                                        const needsValue = typesWithValue.includes(newType);

                                        return (
                                            <div key={row.slug} className="border-border/60 flex flex-col gap-2 border-b py-3 last:border-0">
                                                <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                                                    <span className="text-sm font-medium">{row.label}</span>
                                                    <code className="text-muted-foreground text-[11px]">{row.slug}</code>
                                                    {rowGrants.length === 0 && (
                                                        <span className="text-muted-foreground ml-auto flex items-center gap-1 text-[11px]">
                                                            <TriangleAlert className="size-3" />
                                                            Unrestricted
                                                        </span>
                                                    )}
                                                </div>

                                                <div className="flex flex-wrap items-center gap-1.5">
                                                    {rowGrants.map((grant, i) => (
                                                        <span
                                                            key={`${grant.grant_type}-${grant.grant_value}-${i}`}
                                                            className="bg-muted/60 ring-border/60 inline-flex items-center gap-1 rounded-full py-1 pr-1 pl-2.5 text-xs ring-1"
                                                        >
                                                            {describe(grant)}
                                                            <button
                                                                type="button"
                                                                onClick={() => removeGrant(grant)}
                                                                aria-label={`Remove ${describe(grant)}`}
                                                                className="hover:bg-destructive/10 hover:text-destructive rounded-full p-1 transition-colors"
                                                            >
                                                                <Trash2 className="size-3" />
                                                            </button>
                                                        </span>
                                                    ))}

                                                    {!open && (
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            className="h-7 px-2 text-xs"
                                                            onClick={() => openAdd(row.slug)}
                                                        >
                                                            <Plus className="size-3" />
                                                            Add rule
                                                        </Button>
                                                    )}
                                                </div>

                                                {open && (
                                                    <div className="bg-muted/30 flex flex-wrap items-center gap-2 rounded-lg p-2">
                                                        <select
                                                            autoFocus
                                                            value={newType}
                                                            onChange={(e) => {
                                                                setNewType(e.target.value);
                                                                setNewValue('');
                                                            }}
                                                            className="border-border/60 bg-background h-8 rounded-md border px-2 text-xs"
                                                        >
                                                            {grantTypes.map((t) => (
                                                                <option key={t} value={t}>
                                                                    {TYPE_LABEL[t] ?? t}
                                                                </option>
                                                            ))}
                                                        </select>

                                                        {needsValue && (
                                                            <select
                                                                value={newValue}
                                                                onChange={(e) => setNewValue(e.target.value)}
                                                                className="border-border/60 bg-background h-8 max-w-48 rounded-md border px-2 text-xs"
                                                            >
                                                                <option value="">Choose…</option>
                                                                {valueOptions(newType).map((o) => (
                                                                    <option key={o.value} value={o.value}>
                                                                        {o.label}
                                                                    </option>
                                                                ))}
                                                            </select>
                                                        )}

                                                        <Button
                                                            size="sm"
                                                            className="h-8 text-xs"
                                                            onClick={confirmAdd}
                                                            disabled={needsValue && !newValue}
                                                        >
                                                            Add
                                                        </Button>
                                                        <Button size="sm" variant="ghost" className="h-8 text-xs" onClick={() => setAdding(null)}>
                                                            Cancel
                                                        </Button>
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })}
                                </SoftCardBody>
                            </SoftCard>
                        ))}
                    </div>

                    <div className="flex flex-col gap-4">
                        <SoftCard>
                            <SoftCardTitle>Details</SoftCardTitle>
                            <SoftCardBody className="flex flex-col gap-2 pt-0">
                                <Input value={name} onChange={(e) => setName(e.target.value)} placeholder="Name" />
                                <Input value={description} onChange={(e) => setDescription(e.target.value)} placeholder="Description" />
                                <Button size="sm" variant="outline" onClick={saveDetails} className="self-start">
                                    Save details
                                </Button>
                                {scheme.is_default && (
                                    <p className="text-muted-foreground text-xs">
                                        This is the default scheme — every project without one of its own follows these rules.
                                    </p>
                                )}
                            </SoftCardBody>
                        </SoftCard>

                        <SoftCard>
                            <SoftCardTitle>Projects using this scheme</SoftCardTitle>
                            <SoftCardBody className="flex flex-col gap-2 pt-0">
                                <FilterSelect
                                    label="Projects"
                                    options={projectOptions}
                                    value={assigned}
                                    onChange={setAssigned}
                                    allLabel="No projects"
                                />
                                <Button size="sm" variant="outline" onClick={saveProjects} className="self-start">
                                    Save projects
                                </Button>
                            </SoftCardBody>
                        </SoftCard>

                        <SoftCard className={cn(scheme.is_default && 'hidden')}>
                            <SoftCardTitle>Danger zone</SoftCardTitle>
                            <SoftCardBody className="pt-0">
                                <Button size="sm" variant="destructive" onClick={() => router.delete(route('permission-schemes.destroy', scheme.id))}>
                                    <Trash2 className="size-3.5" />
                                    Delete scheme
                                </Button>
                            </SoftCardBody>
                        </SoftCard>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
