import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, ShieldCheck, Sparkles, Users } from 'lucide-react';

export default function Welcome() {
    const { auth, name } = usePage<SharedData>().props;

    return (
        <>
            <Head title="Welcome" />
            <div className="relative min-h-dvh overflow-hidden">
                <div className="bg-mesh pointer-events-none absolute inset-0 -z-10 opacity-90" />
                <header className="mx-auto flex max-w-6xl items-center justify-between px-6 py-6">
                    <div className="flex items-center gap-2.5">
                        <div className="shadow-glow flex size-10 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 to-blue-700 ring-1 ring-white/30">
                            <AppLogoIcon className="size-5 fill-current text-white" />
                        </div>
                        <span className="font-display text-base font-bold">{name || 'Raqtan TMS'}</span>
                    </div>
                    <nav className="flex items-center gap-2">
                        {auth.user ? (
                            <Button asChild size="sm">
                                <Link href={route('dashboard')}>
                                    Open dashboard <ArrowRight className="ml-1 size-3.5" />
                                </Link>
                            </Button>
                        ) : (
                            <Button asChild size="sm">
                                <Link href={route('login')}>
                                    Sign in <ArrowRight className="ml-1 size-3.5" />
                                </Link>
                            </Button>
                        )}
                    </nav>
                </header>

                <main className="mx-auto max-w-4xl px-6 py-20 text-center">
                    <span className="text-muted-foreground bg-card shadow-soft-xs ring-border/60 inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium ring-1">
                        <Sparkles className="size-3 text-blue-500" /> Enterprise task management, beautifully crafted
                    </span>
                    <h1 className="font-display mt-6 text-5xl leading-[1.05] font-bold tracking-tight md:text-6xl">
                        One workspace for your <span className="text-gradient-brand">team</span>,
                        <br className="hidden md:block" /> their tasks, and their people.
                    </h1>
                    <p className="text-muted-foreground mx-auto mt-6 max-w-xl text-base leading-relaxed">
                        Built with strict role-based access, two-factor authentication, and a premium experience inspired by Linear, Notion, and
                        Asana.
                    </p>
                    <div className="mt-8 flex justify-center gap-2">
                        <Button asChild size="lg" className="gap-2">
                            <Link href={auth.user ? route('dashboard') : route('login')}>
                                {auth.user ? 'Go to dashboard' : 'Sign in'} <ArrowRight className="size-4" />
                            </Link>
                        </Button>
                    </div>

                    <div className="mt-20 grid gap-4 text-left md:grid-cols-3">
                        {[
                            {
                                icon: ShieldCheck,
                                title: 'Email 2FA',
                                body: 'Every sign-in is verified with a 6-digit code, expiring after 10 minutes.',
                                tone: 'from-blue-500 to-blue-700',
                            },
                            {
                                icon: Users,
                                title: 'Role-based access',
                                body: 'Admin, Manager, Employee — each tuned to the exact permissions they need.',
                                tone: 'from-emerald-500 to-teal-600',
                            },
                            {
                                icon: Sparkles,
                                title: 'Modular by design',
                                body: 'Drop new modules in without rewiring permissions or navigation.',
                                tone: 'from-slate-500 to-slate-700',
                            },
                        ].map((f) => (
                            <div
                                key={f.title}
                                className="bg-card shadow-soft-sm ring-border/60 hover:shadow-soft-md rounded-2xl p-5 ring-1 transition-all"
                            >
                                <div
                                    className={`flex size-10 items-center justify-center rounded-xl bg-gradient-to-br ${f.tone} shadow-soft-sm text-white`}
                                >
                                    <f.icon className="size-5" />
                                </div>
                                <h3 className="font-display mt-3 text-sm font-bold">{f.title}</h3>
                                <p className="text-muted-foreground mt-1 text-xs leading-relaxed">{f.body}</p>
                            </div>
                        ))}
                    </div>
                </main>
            </div>
        </>
    );
}
