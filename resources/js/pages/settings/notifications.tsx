import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { AtSign, Bell, CalendarClock, FolderKanban, ListChecks, Mail, Settings, type LucideIcon } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Notification settings', href: '/settings/notifications' }];

interface Preference {
    group: string;
    in_app: boolean;
    email: boolean;
}

interface Props {
    preferences: Preference[];
    digest: string;
    digestModes: string[];
}

const GROUP_META: Record<string, { label: string; description: string; icon: LucideIcon }> = {
    tasks: { label: 'Tasks', description: 'Assignments, status changes, comments and completions.', icon: ListChecks },
    projects: { label: 'Projects', description: 'Membership changes and milestones.', icon: FolderKanban },
    mentions: { label: 'Mentions', description: 'When someone @mentions you in a comment.', icon: AtSign },
    deadlines: { label: 'Deadlines', description: 'Due-date reminders and overdue alerts.', icon: CalendarClock },
    system: { label: 'System', description: 'Workspace announcements and everything else.', icon: Settings },
};

const DIGEST_META: Record<string, { label: string; description: string }> = {
    immediate: { label: 'Send immediately', description: 'An email as each notification happens.' },
    daily: { label: 'Daily digest', description: 'One round-up each morning at 07:30.' },
    off: { label: 'No email', description: 'In-app notifications only.' },
};

export default function NotificationSettings({ preferences, digest, digestModes }: Props) {
    // Managed as plain state: Inertia's useForm cannot type an array of objects.
    const [mode, setMode] = useState(digest);
    const [rows, setRows] = useState<Preference[]>(preferences);
    const [processing, setProcessing] = useState(false);
    const [saved, setSaved] = useState(false);

    const toggle = (group: string, channel: 'in_app' | 'email') => {
        setRows((prev) => prev.map((p) => (p.group === group ? { ...p, [channel]: !p[channel] } : p)));
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        router.patch(route('notifications.preferences.update'), { digest: mode, preferences: rows } as never, {
            preserveScroll: true,
            onSuccess: () => {
                setSaved(true);
                setTimeout(() => setSaved(false), 2500);
            },
            onFinish: () => setProcessing(false),
        });
    };

    const emailOff = mode === 'off';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Notification settings" />

            <SettingsLayout>
                <form onSubmit={submit} className="space-y-6">
                    <HeadingSmall title="Email delivery" description="How often notifications reach your inbox." />

                    <div className="grid gap-2 sm:grid-cols-3">
                        {digestModes.map((option) => (
                            <button
                                key={option}
                                type="button"
                                onClick={() => setMode(option)}
                                className={cn(
                                    'ring-border/60 hover:ring-foreground/20 rounded-xl p-3 text-left ring-1 transition-all',
                                    mode === option && 'ring-primary bg-primary/5 ring-2',
                                )}
                            >
                                <span className="flex items-center gap-1.5 text-sm font-semibold">
                                    <Mail className="size-3.5" />
                                    {DIGEST_META[option]?.label ?? option}
                                </span>
                                <span className="text-muted-foreground mt-1 block text-xs">{DIGEST_META[option]?.description}</span>
                            </button>
                        ))}
                    </div>

                    <HeadingSmall title="What you receive" description="Turn off a category to stop it reaching you on that channel." />

                    <div className="ring-border/60 overflow-hidden rounded-xl ring-1">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-border/60 text-muted-foreground border-b text-left text-[11px] font-bold tracking-[0.12em] uppercase">
                                    <th className="px-3 py-2.5">Category</th>
                                    <th className="w-24 px-3 py-2.5 text-center">In-app</th>
                                    <th className="w-24 px-3 py-2.5 text-center">Email</th>
                                </tr>
                            </thead>
                            <tbody className="divide-border/60 divide-y">
                                {rows.map((pref) => {
                                    const meta = GROUP_META[pref.group] ?? {
                                        label: pref.group,
                                        description: '',
                                        icon: Bell,
                                    };
                                    const Icon = meta.icon;

                                    return (
                                        <tr key={pref.group}>
                                            <td className="px-3 py-3">
                                                <span className="flex items-center gap-2 font-medium">
                                                    <Icon className="text-muted-foreground size-4" />
                                                    {meta.label}
                                                </span>
                                                <span className="text-muted-foreground mt-0.5 block text-xs">{meta.description}</span>
                                            </td>
                                            <td className="px-3 py-3 text-center">
                                                <input
                                                    type="checkbox"
                                                    checked={pref.in_app}
                                                    onChange={() => toggle(pref.group, 'in_app')}
                                                    aria-label={`In-app notifications for ${meta.label}`}
                                                    className="accent-primary size-4"
                                                />
                                            </td>
                                            <td className="px-3 py-3 text-center">
                                                <input
                                                    type="checkbox"
                                                    checked={pref.email && !emailOff}
                                                    disabled={emailOff}
                                                    onChange={() => toggle(pref.group, 'email')}
                                                    aria-label={`Email notifications for ${meta.label}`}
                                                    className="accent-primary size-4 disabled:opacity-40"
                                                />
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    {emailOff && (
                        <p className="text-muted-foreground text-xs">
                            Email is switched off entirely, so the per-category email settings are inactive.
                        </p>
                    )}

                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={processing}>
                            Save preferences
                        </Button>
                        {saved && <p className="text-muted-foreground text-sm">Saved.</p>}
                    </div>
                </form>
            </SettingsLayout>
        </AppLayout>
    );
}
