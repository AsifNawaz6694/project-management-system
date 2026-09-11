import { ConfirmDialog } from '@/components/confirm-dialog';
import { PageHeader } from '@/components/page-header';
import { RoleBadge } from '@/components/role-badge';
import { SoftCard } from '@/components/soft-card';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Crown, LoaderCircle, Lock, Pencil, Plus, ShieldCheck, Trash2, Users } from 'lucide-react';
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
    const [activeRoleId, setActiveRoleId] = useState<number>(roles.find((r) => r.slug !== 'admin')?.id ?? roles[0]?.id);
    const activeRole = roles.find((r) => r.id === activeRoleId)!;
    const isAdminRole = activeRole?.slug === 'admin';

    const [selected, setSelected] = useState<Set<string>>(new Set(activeRole?.permission_slugs ?? []));
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    const [showCreate, setShowCreate] = useState(false);
    const [showRename, setShowRename] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);

    useEffect(() => {
        if (!activeRole) return;
        setSelected(new Set(activeRole.permission_slugs));
        setDirty(false);
    }, [activeRoleId, activeRole?.permission_slugs]);

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

    if (!activeRole) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Roles & Permissions" />
                <div className="flex w-full flex-1 flex-col gap-6 p-4 md:p-6">
                    <p className="text-muted-foreground text-sm">No roles yet.</p>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Roles & Permissions" />
            <div className="flex w-full flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Administration"
                    title="Roles & Permissions"
                    description="Define exactly what each role can access. Add custom roles for your team's structure."
                    actions={
                        can('roles.create') && (
                            <Button onClick={() => setShowCreate(true)} className="gap-2">
                                <Plus className="size-4" /> New role
                            </Button>
                        )
                    }
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
                                        ? 'shadow-soft-md ring-2 ring-blue-400 dark:ring-blue-500/40'
                                        : 'ring-border/60 hover:ring-foreground/20 hover:shadow-soft-md',
                                )}
                            >
                                {activeRoleId === role.id && (
                                    <div className="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-blue-600 to-blue-700" />
                                )}
                                <div
                                    className={cn(
                                        'shadow-soft-sm flex size-11 items-center justify-center rounded-xl',
                                        role.slug === 'admin' && 'bg-gradient-to-br from-amber-400 to-orange-500',
                                        role.slug === 'manager' && 'bg-gradient-to-br from-blue-500 to-blue-700',
                                        role.slug === 'employee' && 'bg-gradient-to-br from-emerald-500 to-teal-600',
                                        !['admin', 'manager', 'employee'].includes(role.slug) && 'bg-gradient-to-br from-slate-500 to-slate-700',
                                    )}
                                >
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
                                        <Users className="size-3" /> {role.users_count} member{role.users_count === 1 ? '' : 's'} ·{' '}
                                        {role.permission_slugs.length} perms
                                    </p>
                                </div>
                                <RoleBadge role={role.slug} />
                            </button>
                        ))}
                    </aside>

                    <SoftCard>
                        <div className="border-border/60 flex flex-col items-start gap-3 border-b p-6 md:flex-row md:items-center md:justify-between">
                            <div>
                                <div className="flex items-center gap-2">
                                    <h3 className="font-display text-lg font-bold">{activeRole.name}</h3>
                                    <RoleBadge role={activeRole.slug} />
                                </div>
                                {activeRole.description && <p className="text-muted-foreground mt-1 max-w-xl text-xs">{activeRole.description}</p>}
                            </div>
                            <div className="flex items-center gap-2">
                                {!activeRole.is_system && can('roles.update') && (
                                    <Button onClick={() => setShowRename(true)} variant="secondary" size="sm" className="gap-1.5">
                                        <Pencil className="size-3.5" /> Rename
                                    </Button>
                                )}
                                {!activeRole.is_system && can('roles.delete') && (
                                    <Button
                                        onClick={() => setConfirmDelete(true)}
                                        variant="outline"
                                        size="sm"
                                        className="gap-1.5 text-rose-600 ring-rose-200 hover:bg-rose-50 hover:ring-rose-300 dark:text-rose-400 dark:ring-rose-500/30"
                                    >
                                        <Trash2 className="size-3.5" /> Delete
                                    </Button>
                                )}
                                {isAdminRole ? (
                                    <span className="inline-flex items-center gap-1.5 rounded-full bg-gradient-to-r from-amber-50 to-orange-50 px-3 py-1 text-[11px] font-semibold text-amber-800 ring-1 ring-amber-300/60 dark:from-amber-500/10 dark:to-orange-500/10 dark:text-amber-200 dark:ring-amber-500/30">
                                        <Lock className="size-3" /> Admins always have full access
                                    </span>
                                ) : (
                                    <Button onClick={save} disabled={!dirty || saving || !can('roles.update')} size="sm">
                                        {saving ? 'Saving…' : 'Save changes'}
                                    </Button>
                                )}
                            </div>
                        </div>

                        <div className="divide-border/60 divide-y">
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
                                                            ? 'shadow-soft-sm bg-gradient-to-br from-blue-600 to-blue-700 text-white'
                                                            : someChecked
                                                              ? 'bg-gradient-to-br from-blue-600/30 to-blue-700/30'
                                                              : 'ring-muted-foreground/30 bg-muted ring-1',
                                                    )}
                                                >
                                                    {allChecked && <span className="text-[10px]">✓</span>}
                                                    {someChecked && <span className="size-2 rounded-sm bg-blue-600" />}
                                                </span>
                                                <span className="font-display text-sm font-bold">{mod.label}</span>
                                            </button>
                                            <p className="text-muted-foreground mt-1 ml-7 text-[11px]">
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
                                                                ? 'bg-gradient-to-br from-blue-50 to-blue-100 ring-1 ring-blue-200 dark:from-blue-500/10 dark:to-blue-500/15 dark:ring-blue-500/30'
                                                                : 'bg-muted/40 ring-border/60 hover:ring-foreground/20 ring-1',
                                                            isAdminRole && 'cursor-not-allowed opacity-70',
                                                        )}
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            disabled={isAdminRole}
                                                            checked={isAdminRole || checked}
                                                            onChange={() => togglePermission(perm.slug)}
                                                            className="mt-0.5 size-3.5 accent-blue-600"
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

            <CreateRoleDialog open={showCreate} onOpenChange={setShowCreate} modules={modules} />
            <RenameRoleDialog open={showRename} onOpenChange={setShowRename} role={activeRole} />
            <ConfirmDialog
                open={confirmDelete}
                onOpenChange={setConfirmDelete}
                title={`Delete role "${activeRole.name}"?`}
                description={
                    activeRole.users_count > 0
                        ? `This role is currently assigned to ${activeRole.users_count} user(s). Those users will lose this role's permissions. This cannot be undone.`
                        : 'This role will be removed permanently. This cannot be undone.'
                }
                confirmLabel="Delete role"
                tone="destructive"
                icon={Trash2}
                onConfirm={() => {
                    router.delete(route('roles.destroy', activeRole.id));
                    setConfirmDelete(false);
                }}
            />
        </AppLayout>
    );
}

function CreateRoleDialog({ open, onOpenChange, modules }: { open: boolean; onOpenChange: (o: boolean) => void; modules: ModuleConfig[] }) {
    const form = useForm<{ name: string; description: string; permissions: string[] }>({ name: '', description: '', permissions: [] });

    const togglePerm = (slug: string) => {
        const set = new Set(form.data.permissions);
        if (set.has(slug)) set.delete(slug);
        else set.add(slug);
        form.setData('permissions', Array.from(set));
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="rounded-2xl sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle className="font-display text-xl font-bold">Create a new role</DialogTitle>
                    <DialogDescription>Name the role and choose which permissions members should have.</DialogDescription>
                </DialogHeader>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(route('roles.store'), {
                            onSuccess: () => {
                                form.reset();
                                onOpenChange(false);
                            },
                        });
                    }}
                    className="space-y-4"
                >
                    <div className="grid gap-3 md:grid-cols-2">
                        <div className="space-y-1.5">
                            <Label className="text-muted-foreground text-[10px] font-bold tracking-[0.14em] uppercase">Name</Label>
                            <Input
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                placeholder="e.g. Project Lead"
                                required
                                autoFocus
                            />
                            {form.errors.name && <p className="text-xs text-rose-600">{form.errors.name}</p>}
                        </div>
                        <div className="space-y-1.5">
                            <Label className="text-muted-foreground text-[10px] font-bold tracking-[0.14em] uppercase">Description</Label>
                            <Input
                                value={form.data.description}
                                onChange={(e) => form.setData('description', e.target.value)}
                                placeholder="Short summary of what this role does"
                            />
                        </div>
                    </div>

                    <div className="border-border/60 bg-muted/20 max-h-[40vh] space-y-3 overflow-y-auto rounded-xl border p-3">
                        {modules.map((mod) => (
                            <div key={mod.key} className="space-y-1.5">
                                <p className="text-muted-foreground text-[10px] font-bold tracking-[0.14em] uppercase">{mod.label}</p>
                                <div className="grid gap-1.5 sm:grid-cols-2">
                                    {mod.permissions.map((p) => {
                                        const checked = form.data.permissions.includes(p.slug);
                                        return (
                                            <label
                                                key={p.slug}
                                                className={cn(
                                                    'flex cursor-pointer items-start gap-2 rounded-lg p-2 text-xs ring-1 transition-all',
                                                    checked
                                                        ? 'bg-blue-50 ring-blue-200 dark:bg-blue-500/10 dark:ring-blue-500/30'
                                                        : 'bg-card ring-border/50 hover:ring-foreground/20',
                                                )}
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={checked}
                                                    onChange={() => togglePerm(p.slug)}
                                                    className="mt-0.5 size-3.5 accent-blue-600"
                                                />
                                                <div className="min-w-0">
                                                    <p className="font-semibold">{p.name}</p>
                                                    <p className="text-muted-foreground truncate font-mono text-[10px]">{p.slug}</p>
                                                </div>
                                            </label>
                                        );
                                    })}
                                </div>
                            </div>
                        ))}
                    </div>

                    <DialogFooter className="gap-2">
                        <Button type="button" variant="ghost" onClick={() => onOpenChange(false)} disabled={form.processing}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing || !form.data.name.trim()} className="gap-2">
                            {form.processing && <LoaderCircle className="size-4 animate-spin" />} Create role
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function RenameRoleDialog({ open, onOpenChange, role }: { open: boolean; onOpenChange: (o: boolean) => void; role: RoleSummary }) {
    const form = useForm<{ name: string; description: string }>({ name: role.name, description: role.description ?? '' });

    useEffect(() => {
        form.setData({ name: role.name, description: role.description ?? '' });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [role.id]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="rounded-2xl sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="font-display text-lg font-bold">Rename role</DialogTitle>
                    <DialogDescription>Update the display name and description for this role.</DialogDescription>
                </DialogHeader>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.patch(route('roles.rename', role.id), { onSuccess: () => onOpenChange(false), preserveScroll: true });
                    }}
                    className="space-y-3"
                >
                    <div className="space-y-1.5">
                        <Label className="text-muted-foreground text-[10px] font-bold tracking-[0.14em] uppercase">Name</Label>
                        <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required autoFocus />
                        {form.errors.name && <p className="text-xs text-rose-600">{form.errors.name}</p>}
                    </div>
                    <div className="space-y-1.5">
                        <Label className="text-muted-foreground text-[10px] font-bold tracking-[0.14em] uppercase">Description</Label>
                        <Input value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                    </div>
                    <DialogFooter className="gap-2">
                        <Button type="button" variant="ghost" onClick={() => onOpenChange(false)} disabled={form.processing}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing || !form.data.name.trim()} className="gap-2">
                            {form.processing && <LoaderCircle className="size-4 animate-spin" />} Save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
