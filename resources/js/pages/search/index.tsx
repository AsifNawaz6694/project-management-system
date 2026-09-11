import { PageHeader } from '@/components/page-header';
import { SoftCard, SoftCardBody, SoftCardTitle } from '@/components/soft-card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { CalendarClock, FolderKanban, ListChecks, MessageSquareText, Search, Target, Users, UsersRound, type LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Workspace', href: '/dashboard' },
    { title: 'Search', href: '/search' },
];

interface Hit {
    id: number;
    title: string;
    subtitle: string;
    url: string;
}

interface Props {
    results: {
        term: string;
        sections: Array<{ key: string; label: string; icon: string; items: Hit[] }>;
        total: number;
    };
}

const ICONS: Record<string, LucideIcon> = {
    'list-checks': ListChecks,
    'folder-kanban': FolderKanban,
    'message-square-text': MessageSquareText,
    users: Users,
    'users-round': UsersRound,
    'calendar-clock': CalendarClock,
    target: Target,
};

export default function SearchIndex({ results }: Props) {
    const [term, setTerm] = useState(results.term);

    // Keep the box in step when the user arrives from the palette or a back button.
    useEffect(() => setTerm(results.term), [results.term]);

    useEffect(() => {
        if (term === results.term) return;

        const handle = setTimeout(() => {
            router.get(route('search.index'), { q: term || undefined }, { preserveState: true, preserveScroll: true, replace: true });
        }, 300);

        return () => clearTimeout(handle);
    }, [term, results.term]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={results.term ? `Search — ${results.term}` : 'Search'} />

            <div className="flex flex-col gap-4 p-4">
                <PageHeader eyebrow="Workspace" title="Search" description="Everything you have access to, in one place." />

                <SoftCard>
                    <SoftCardBody className="relative">
                        <Search className="text-muted-foreground absolute top-1/2 left-7 size-4 -translate-y-1/2" />
                        <Input
                            autoFocus
                            value={term}
                            onChange={(e) => setTerm(e.target.value)}
                            placeholder="Search tasks, projects, people…"
                            aria-label="Search the workspace"
                            className="h-11 pl-10"
                        />
                    </SoftCardBody>
                </SoftCard>

                {results.term.length >= 2 && results.total === 0 && (
                    <SoftCard>
                        <SoftCardBody className="text-muted-foreground py-12 text-center text-sm">
                            Nothing matches <strong className="text-foreground">{results.term}</strong>.
                        </SoftCardBody>
                    </SoftCard>
                )}

                {results.term.length < 2 && (
                    <SoftCard>
                        <SoftCardBody className="text-muted-foreground py-12 text-center text-sm">
                            Type at least two characters. Press <kbd className="border-border/70 bg-muted rounded border px-1">⌘K</kbd> anywhere to
                            search without leaving the page.
                        </SoftCardBody>
                    </SoftCard>
                )}

                <div className="grid gap-3 lg:grid-cols-2">
                    {results.sections.map((section) => {
                        const Icon = ICONS[section.icon] ?? ListChecks;

                        return (
                            <SoftCard key={section.key}>
                                <SoftCardTitle>
                                    {section.label} ({section.items.length})
                                </SoftCardTitle>
                                <SoftCardBody className="flex flex-col gap-0 pt-0">
                                    {section.items.map((hit) => (
                                        <Link
                                            key={hit.id}
                                            href={hit.url}
                                            className="border-border/60 hover:bg-muted/40 flex items-center gap-2.5 border-b py-2.5 transition-colors last:border-0"
                                        >
                                            <Icon className="text-muted-foreground size-4 shrink-0" />
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate text-sm font-medium">{hit.title}</span>
                                                {hit.subtitle && <span className="text-muted-foreground block truncate text-xs">{hit.subtitle}</span>}
                                            </span>
                                        </Link>
                                    ))}
                                </SoftCardBody>
                            </SoftCard>
                        );
                    })}
                </div>
            </div>
        </AppLayout>
    );
}
