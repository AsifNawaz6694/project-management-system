import { PageHeader } from '@/components/page-header';
import { RoleBadge } from '@/components/role-badge';
import { SoftCard, SoftCardBody, SoftCardTitle } from '@/components/soft-card';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Crown, Lock, ShieldCheck, Users } from 'lucide-react';
import { useEffect, useState } from 'react';

interface RoleSummary {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    is_system: boolean;
    users_count: number;
    permission_slugs: string[];
}

interface ModuleConfig {
    key: string;
    label: string;
    permissions: { slug: string; name: string }[];
}

interface RolesIndexProps {
    roles: RoleSummary[];
    modules: ModuleConfig[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Administration', href: '/dashboard' },
    { title: 'Roles & Permissions', href: '/roles' },
];

export default function RolesIndex({ roles, modules }: RolesIndexProps) {
    const { can } = usePermissions();
    const editableRoles = roles.filter((r) => r.slug !== 'admin');
    const [activeRoleId, setActiveRoleId] = useState<number>(editableRoles[0]?.id ?? roles[0]?.id);
    const activeRole = roles.find((r) => r.id === activeRoleId)!;
    const isAdminRole = activeRole.slug === 'admin';

    const [selected, setSelected] = useState<Set<string>>(new Set(activeRole.permission_slugs));
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        setSelected(new Set(activeRole.permission_slugs));
        setDirty(false);
    }, [activeRoleId, activeRole.permission_slugs]);

    const togglePermission = (slug: string) => {
        if (isAdminRole) return;
        const next = new Set(selected);
        if (next.has(slug)) next.delete(slug);
        else next.add(slug);
        setSelected(next);
        setDirty(true);
    };

    const toggleModule = (mod: ModuleConfig) => {
        if (isAdminRole) return;
        const slugs = mod.permissions.map((p) => p.slug);
        const allChecked = slugs.every((s) => selected.has(s));
        const next = new Set(selected);
        if (allChecked) slugs.forEach((s) => next.delete(s));
        else slugs.forEach((s) => next.add(s));
        setSelected(next);
        setDirty(true);
    };

    const save = () => {
        if (!can('roles.update') || isAdminRole) return;
        setSaving(true);
        router.patch(
            route('roles.update', activeRole.id),
            { permissions: Array.from(selected) },
            {
                preserveScroll: true,
                onFinish: () => {
                    setSaving(false);
                    setDirty(false);
                },
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Roles & Permissions" />
            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-8">
                <PageHeader
                    eyebrow="Administration"
                    title="Roles & Permissions"
                    description="Define exactly what each role can access across every module of the workspace."
                />

                <div className="grid gap-6 lg:grid-cols-[300px_1fr]">
                    <aside className="space-y-3">
                        {roles.map((role) => (
                            <button
                                key={role.id}
                                onClick={() => setActiveRoleId(role.id)}
                                className={cn(
                                    'group bg-card shadow-soft-sm relative flex w-full items-center gap-3 overflow-hidden rounded-2xl p-4 text-left ring-1 transition-all duration-200',
                                    activeRoleId === role.id
                                        ? 'ring-violet-400 dark:ring-violet-500/40 shadow-soft-md ring-2'
                                        : 'ring-border/60 hover:ring-foreground/20 hover:shadow-soft-md',
                                )}
                            >
                                {activeRoleId === role.id && (
                                    <div className="from-violet-600 via-indigo-600 to-blue-600 absolute inset-x-0 top-0 h-1 bg-gradient-to-r" />
                                )}
                                <div className={cn(
                                    'flex size-11 items-center justify-center rounded-xl shadow-soft-sm',
                                    role.slug === 'admin' && 'bg-gradient-to-br from-amber-400 to-orange-500',
                                    role.slug === 'manager' && 'bg-gradient-to-br from-violet-500 to-indigo-600',
                                    role.slug === 'employee' && 'bg-gradient-to-br from-emerald-500 to-teal-600',
                                )}>
                                    {role.slug === 'admin' ? <Crown className="size-5 text-white" /> : <ShieldCheck className="size-5 text-white" />}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        <p className="font-display text-sm font-bold">{role.name}</p>
                                        {role.is_system && (
                                            <span className="text-muted-foreground inline-flex items-center gap-1 text-[10px] font-semibold">
                                                <Lock className="size-3" /> system
                                            </span>
                                        )}
                                    </div>
                                    <p className="text-muted-foreground mt-0.5 flex items-center gap-1 text-[11px]">
                                        <Users className="size-3" /> {role.users_count} member{role.users_count === 1 ? '' : 's'} · {role.permission_slugs.length} perms
                                    </p>
                                </div>
                                <RoleBadge role={role.slug} />
                            </button>
                        ))}
                    </aside>

                    <SoftCard>
                        <div className="flex flex-col items-start gap-3 border-b border-border/60 p-6 md:flex-row md:items-center md:justify-between">
                            <div>
                                <div className="flex items-center gap-2">
                                    <h3 className="font-display text-lg font-bold">{activeRole.name}</h3>
                                    <RoleBadge role={activeRole.slug} />
                                </div>
                                {activeRole.description && (
                                    <p className="text-muted-foreground mt-1 max-w-xl text-xs">{activeRole.description}</p>
                                )}
                            </div>
                            <div className="flex items-center gap-2">
                                {isAdminRole ? (
                                    <span className="bg-gradient-to-r from-amber-50 to-orange-50 text-amber-800 ring-amber-300/60 dark:from-amber-500/10 dark:to-orange-500/10 dark:text-amber-200 dark:ring-amber-500/30 inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-semibold ring-1">
                                        <Lock className="size-3" /> Admins always have full access
                                    </span>
                                ) : (
                                    <Button onClick={save} disabled={!dirty || saving || !can('roles.update')} size="sm">
                                        {saving ? 'Saving…' : 'Save changes'}
                                    </Button>
                                )}
                            </div>
                        </div>

                        <div className="divide-y divide-border/60">
                            {modules.map((mod) => {
                                const slugs = mod.permissions.map((p) => p.slug);
                                const checkedCount = slugs.filter((s) => selected.has(s)).length;
                                const allChecked = checkedCount === slugs.length;
                                const someChecked = checkedCount > 0 && !allChecked;
                                return (
                                    <div key={mod.key} className="grid gap-4 p-6 md:grid-cols-[220px_1fr]">
                                        <div>
                                            <button
                                                type="button"
                                                onClick={() => toggleModule(mod)}
                                                disabled={isAdminRole}
                                                className="flex items-center gap-2.5 text-left disabled:cursor-not-allowed"
                                            >
                                                <span
                                                    className={cn(
                                                        'flex size-5 shrink-0 items-center justify-center rounded-md transition-all',
                                                        allChecked
                                                            ? 'shadow-soft-sm from-violet-600 to-indigo-600 bg-gradient-to-br text-white'
                                                            : someChecked
                                                              ? 'from-violet-600/30 to-indigo-600/30 bg-gradient-to-br'
                                                              : 'ring-muted-foreground/30 bg-muted ring-1',
                                                    )}
                                                >
                                                    {allChecked && <span className="text-[10px]">✓</span>}
                                                    {someChecked && <span className="size-2 rounded-sm bg-violet-600" />}
                                                </span>
                                                <span className="font-display text-sm font-bold">{mod.label}</span>
                                            </button>
                                            <p className="text-muted-foreground ml-7 mt-1 text-[11px]">
                                                {checkedCount} / {slugs.length} enabled
                                            </p>
                                        </div>
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            {mod.permissions.map((perm) => {
                                                const checked = selected.has(perm.slug);
                                                return (
                                                    <label
                                                        key={perm.slug}
                                                        className={cn(
                                                            'group flex cursor-pointer items-start gap-2.5 rounded-xl p-3 text-xs transition-all',
                                                            checked
                                                                ? 'bg-gradient-to-br from-violet-50 to-indigo-50 ring-violet-200 dark:from-violet-500/10 dark:to-indigo-500/10 dark:ring-violet-500/30 ring-1'
                                                                : 'bg-muted/40 ring-border/60 hover:ring-foreground/20 ring-1',
                                                            isAdminRole && 'cursor-not-allowed opacity-70',
                                                        )}
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            disabled={isAdminRole}
                                                            checked={isAdminRole || checked}
                                                            onChange={() => togglePermission(perm.slug)}
                                                            className="mt-0.5 size-3.5 accent-violet-600"
                                                        />
                                                        <div className="min-w-0">
                                                            <p className="font-semibold">{perm.name}</p>
                                                            <p className="text-muted-foreground mt-0.5 truncate font-mono text-[10px]">{perm.slug}</p>
                                                        </div>
                                                    </label>
                                                );
                                            })}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </SoftCard>
                </div>
            </div>
        </AppLayout>
    );
}
