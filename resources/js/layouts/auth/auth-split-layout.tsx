import AppLogoIcon from '@/components/app-logo-icon';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

interface AuthLayoutProps {
    children: React.ReactNode;
    title?: string;
    description?: string;
}

export default function AuthSplitLayout({ children, title, description }: AuthLayoutProps) {
    const { name } = usePage<SharedData>().props;

    return (
        <div className="bg-canvas relative flex min-h-dvh items-center justify-center px-4 py-10">
            <div className="bg-mesh pointer-events-none absolute inset-0 -z-10 opacity-50" />
            <div
                aria-hidden
                className="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[420px] bg-[radial-gradient(ellipse_at_top,theme(colors.violet.200/.45),transparent_60%)] dark:bg-[radial-gradient(ellipse_at_top,theme(colors.violet.500/.18),transparent_60%)]"
            />

            <div className="flex w-full max-w-[420px] flex-col items-center gap-8">
                <div className="flex flex-col items-center gap-3">
                    <div className="from-violet-600 via-indigo-600 to-blue-600 shadow-glow flex size-14 items-center justify-center rounded-2xl bg-gradient-to-br ring-1 ring-white/30">
                        <AppLogoIcon className="size-7 fill-current text-white" />
                    </div>
                    <span className="font-display text-base font-bold tracking-tight">{name || 'Raqtan PMS'}</span>
                </div>

                <div className="bg-card shadow-soft-lg ring-border/60 w-full rounded-2xl p-8 ring-1">
                    <div className="mb-6 space-y-1.5 text-center">
                        {title && <h1 className="font-display text-2xl font-bold tracking-tight">{title}</h1>}
                        {description && <p className="text-muted-foreground text-sm leading-relaxed">{description}</p>}
                    </div>
                    {children}
                </div>

                <p className="text-muted-foreground text-center text-[11px]">
                    &copy; {new Date().getFullYear()} {name || 'Raqtan PMS'}. All rights reserved.
                </p>
            </div>
        </div>
    );
}
