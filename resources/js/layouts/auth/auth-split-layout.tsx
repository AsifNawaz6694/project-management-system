import AppLogoIcon from '@/components/app-logo-icon';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';

interface AuthLayoutProps {
    children: React.ReactNode;
    title?: string;
    description?: string;
}

const HIGHLIGHTS = [
    'Email-based two-factor authentication built in',
    'Granular permissions across every module',
    'Premium experience inspired by Linear, Notion & Asana',
];

export default function AuthSplitLayout({ children, title, description }: AuthLayoutProps) {
    const { name } = usePage<SharedData>().props;

    return (
        <div className="relative grid min-h-dvh lg:grid-cols-2">
            <div className="relative hidden h-full flex-col overflow-hidden p-10 text-white lg:flex">
                <div className="from-violet-700 via-indigo-700 to-blue-900 absolute inset-0 bg-gradient-to-br" />
                <div className="absolute -left-40 -top-20 size-[460px] rounded-full bg-white/10 blur-3xl" />
                <div className="absolute -right-32 bottom-0 size-[420px] rounded-full bg-pink-400/30 blur-3xl" />
                <div
                    className="absolute inset-0 opacity-[0.07]"
                    style={{ backgroundImage: 'radial-gradient(white 1px, transparent 1px)', backgroundSize: '28px 28px' }}
                />

                <Link href={route('login')} className="relative z-20 flex items-center text-lg font-semibold">
                    <div className="mr-2.5 flex size-10 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/20 backdrop-blur">
                        <AppLogoIcon className="size-5 fill-current text-white" />
                    </div>
                    <span className="font-display text-lg tracking-tight">{name || 'Raqtan'}</span>
                </Link>

                <div className="relative z-20 mt-auto space-y-6">
                    <h2 className="font-display text-4xl font-bold leading-[1.1] tracking-tight">
                        Run your projects, your team, and your workspace from one premium platform.
                    </h2>
                    <ul className="space-y-3 text-sm text-white/85">
                        {HIGHLIGHTS.map((line) => (
                            <li key={line} className="flex items-start gap-2.5">
                                <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-emerald-300" />
                                <span>{line}</span>
                            </li>
                        ))}
                    </ul>
                    <div className="flex items-center gap-3 pt-4 text-xs text-white/60">
                        <div className="flex -space-x-2">
                            {['from-amber-400 to-orange-500', 'from-violet-400 to-indigo-500', 'from-emerald-400 to-teal-500'].map((g, i) => (
                                <div
                                    key={i}
                                    className={`ring-violet-900 size-7 rounded-full bg-gradient-to-br ${g} ring-2`}
                                />
                            ))}
                        </div>
                        Trusted by teams that ship.
                    </div>
                </div>
            </div>

            <div className="bg-canvas relative flex w-full items-center justify-center p-6 lg:p-10">
                <div className="absolute inset-0 -z-10 bg-mesh opacity-50" />
                <div className="mx-auto flex w-full max-w-[420px] flex-col gap-8">
                    <Link href={route('login')} className="flex items-center justify-center lg:hidden">
                        <AppLogoIcon className="h-10 fill-current" />
                    </Link>
                    <div className="space-y-2 text-center">
                        <h1 className="font-display text-3xl font-bold tracking-tight">{title}</h1>
                        <p className="text-muted-foreground mx-auto max-w-sm text-sm leading-relaxed">{description}</p>
                    </div>
                    <div className="bg-card shadow-soft-lg ring-border/60 ring-1 rounded-2xl p-6">{children}</div>
                </div>
            </div>
        </div>
    );
}
