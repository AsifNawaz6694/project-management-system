import InputError from '@/components/input-error';
import { RoleBadge } from '@/components/role-badge';
import { SoftCard, SoftCardBody, SoftCardTitle } from '@/components/soft-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { type FormEventHandler } from 'react';

export interface UserFormRole {
    id: number;
    slug: string;
    name: string;
    description?: string | null;
}

export interface UserFormInitial {
    name: string;
    email: string;
    phone: string;
    job_title: string;
    department: string;
    status: 'active' | 'invited' | 'suspended';
    two_factor_enabled: boolean;
    roles: string[];
    password: string;
}

interface UserFormProps {
    initial: UserFormInitial;
    roles: UserFormRole[];
    submitUrl: string;
    submitMethod: 'post' | 'patch';
    submitLabel: string;
    cancelUrl: string;
    requirePassword?: boolean;
}

export function UserForm({ initial, roles, submitUrl, submitMethod, submitLabel, cancelUrl, requirePassword = false }: UserFormProps) {
    const { data, setData, post, patch, processing, errors } = useForm<UserFormInitial>({ ...initial });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const fn = submitMethod === 'post' ? post : patch;
        fn(submitUrl, { preserveScroll: true });
    };

    const toggleRole = (slug: string) => {
        setData(
            'roles',
            data.roles.includes(slug) ? data.roles.filter((s) => s !== slug) : [...data.roles, slug],
        );
    };

    return (
        <form onSubmit={submit} className="space-y-5">
            <SoftCard>
                <SoftCardTitle eyebrow="Profile">Identity</SoftCardTitle>
                <SoftCardBody className="grid gap-4 md:grid-cols-2">
                    <Field label="Full name" error={errors.name}>
                        <Input value={data.name} onChange={(e) => setData('name', e.target.value)} required autoFocus />
                    </Field>
                    <Field label="Email address" error={errors.email}>
                        <Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} required />
                    </Field>
                    <Field label="Phone" error={errors.phone}>
                        <Input value={data.phone} onChange={(e) => setData('phone', e.target.value)} placeholder="+966 …" />
                    </Field>
                    <Field label="Job title" error={errors.job_title}>
                        <Input value={data.job_title} onChange={(e) => setData('job_title', e.target.value)} />
                    </Field>
                    <Field label="Department" error={errors.department}>
                        <Input value={data.department} onChange={(e) => setData('department', e.target.value)} />
                    </Field>
                    <Field label="Status" error={errors.status}>
                        <Select value={data.status} onValueChange={(v) => setData('status', v as UserFormInitial['status'])}>
                            <SelectTrigger className="h-11 rounded-xl">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent className="rounded-xl">
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="invited">Invited</SelectItem>
                                <SelectItem value="suspended">Suspended</SelectItem>
                            </SelectContent>
                        </Select>
                    </Field>
                </SoftCardBody>
            </SoftCard>

            <SoftCard>
                <SoftCardTitle eyebrow="Access">Roles</SoftCardTitle>
                <SoftCardBody>
                    <div className="grid gap-3 md:grid-cols-3">
                        {roles.map((role) => {
                            const checked = data.roles.includes(role.slug);
                            return (
                                <button
                                    type="button"
                                    key={role.slug}
                                    onClick={() => toggleRole(role.slug)}
                                    className={cn(
                                        'group relative overflow-hidden rounded-2xl p-4 text-left transition-all duration-200',
                                        checked
                                            ? 'shadow-soft-md from-violet-50 via-card to-card ring-violet-300 dark:from-violet-500/10 dark:ring-violet-500/30 bg-gradient-to-br ring-2'
                                            : 'bg-card ring-border ring-1 hover:ring-foreground/30 hover:shadow-soft-sm',
                                    )}
                                >
                                    <div className="flex items-center justify-between">
                                        <RoleBadge role={role.slug} />
                                        <div
                                            className={cn(
                                                'flex size-5 items-center justify-center rounded-full border-2 transition-colors',
                                                checked ? 'border-violet-600 bg-gradient-to-br from-violet-600 to-indigo-600' : 'border-muted-foreground/30',
                                            )}
                                        >
                                            {checked && <span className="size-2 rounded-full bg-white" />}
                                        </div>
                                    </div>
                                    <p className="font-display mt-3 text-sm font-bold">{role.name}</p>
                                    {role.description && <p className="text-muted-foreground mt-1 text-xs">{role.description}</p>}
                                </button>
                            );
                        })}
                    </div>
                    <InputError message={errors.roles} className="mt-3" />
                </SoftCardBody>
            </SoftCard>

            <SoftCard>
                <SoftCardTitle eyebrow="Security">Password & 2FA</SoftCardTitle>
                <SoftCardBody className="grid gap-4 md:grid-cols-2">
                    <Field
                        label={requirePassword ? 'Initial password' : 'Reset password (leave blank to keep current)'}
                        error={errors.password}
                    >
                        <Input
                            type="password"
                            autoComplete="new-password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            placeholder="••••••••"
                            required={requirePassword}
                        />
                    </Field>
                    <Field label="Two-factor authentication">
                        <label className="bg-muted/40 ring-border/60 hover:ring-foreground/20 flex cursor-pointer items-center justify-between rounded-xl p-3 text-sm font-medium ring-1 transition-all">
                            <span>Require email 2FA on login</span>
                            <input
                                type="checkbox"
                                checked={data.two_factor_enabled}
                                onChange={(e) => setData('two_factor_enabled', e.target.checked)}
                                className="size-4 accent-violet-600"
                            />
                        </label>
                    </Field>
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

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <div className="space-y-1.5">
            <Label className="text-muted-foreground text-[10px] font-bold uppercase tracking-[0.14em]">{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
