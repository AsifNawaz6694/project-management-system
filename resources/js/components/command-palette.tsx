import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import {
    CalendarClock,
    FolderKanban,
    ListChecks,
    Loader2,
    MessageSquareText,
    Search,
    Target,
    Users,
    UsersRound,
    type LucideIcon,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

interface Hit {
    id: number;
    title: string;
    subtitle: string;
    url: string;
}

interface Section {
    key: string;
    label: string;
    icon: string;
    items: Hit[];
}

interface Results {
    term: string;
    sections: Section[];
    total: number;
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

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

/**
 * Workspace-wide search.
 *
 * Results arrive as JSON rather than as an Inertia visit — a palette that
 * re-rendered the page on every keystroke would be unusable. Every request is
 * debounced and the stale ones are dropped, so a fast typist never sees results
 * for a term they have already moved past.
 */
export function CommandPalette({ open, onOpenChange }: Props) {
    const [term, setTerm] = useState('');
    const [results, setResults] = useState<Results | null>(null);
    const [loading, setLoading] = useState(false);
    const [active, setActive] = useState(0);

    const inputRef = useRef<HTMLInputElement>(null);
    const requestId = useRef(0);

    // A single flat list, so the arrow keys can walk across section boundaries.
    const flat = useMemo(() => (results?.sections ?? []).flatMap((section) => section.items), [results]);

    useEffect(() => {
        if (open) {
            setActive(0);
            requestAnimationFrame(() => inputRef.current?.focus());
        } else {
            setTerm('');
            setResults(null);
        }
    }, [open]);

    useEffect(() => {
        if (!open) return;

        if (term.trim().length < 2) {
            setResults(null);
            setLoading(false);
            return;
        }

        setLoading(true);
        const id = ++requestId.current;

        const handle = setTimeout(() => {
            fetch(`${route('search.quick')}?q=${encodeURIComponent(term)}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            })
                .then((r) => (r.ok ? r.json() : null))
                .then((data: Results | null) => {
                    // Drop anything that is no longer the current keystroke.
                    if (id !== requestId.current) return;
                    setResults(data);
                    setActive(0);
                })
                .catch(() => {
                    if (id === requestId.current) setResults(null);
                })
                .finally(() => {
                    if (id === requestId.current) setLoading(false);
                });
        }, 200);

        return () => clearTimeout(handle);
    }, [term, open]);

    const go = useCallback(
        (hit: Hit) => {
            onOpenChange(false);
            router.visit(hit.url);
        },
        [onOpenChange],
    );

    const onKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Escape') {
            onOpenChange(false);
            return;
        }

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActive((i) => (flat.length === 0 ? 0 : (i + 1) % flat.length));
            return;
        }

        if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActive((i) => (flat.length === 0 ? 0 : (i - 1 + flat.length) % flat.length));
            return;
        }

        if (e.key === 'Enter') {
            e.preventDefault();
            if (flat[active]) {
                go(flat[active]);
            } else if (term.trim().length >= 2) {
                onOpenChange(false);
                router.visit(`${route('search.index')}?q=${encodeURIComponent(term)}`);
            }
        }
    };

    if (!open) return null;

    let cursor = -1;

    return (
        <div
            className="fixed inset-0 z-50 flex items-start justify-center bg-black/30 p-4 pt-[12vh] backdrop-blur-sm"
            role="presentation"
            onMouseDown={(e) => e.target === e.currentTarget && onOpenChange(false)}
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-label="Search the workspace"
                className="bg-card ring-border/60 shadow-soft-lg w-full max-w-2xl overflow-hidden rounded-2xl ring-1"
            >
                <div className="border-border/60 flex items-center gap-2.5 border-b px-4">
                    <Search className="text-muted-foreground size-4 shrink-0" />
                    <input
                        ref={inputRef}
                        value={term}
                        onChange={(e) => setTerm(e.target.value)}
                        onKeyDown={onKeyDown}
                        placeholder="Search tasks, projects, people…"
                        aria-label="Search the workspace"
                        className="h-14 flex-1 bg-transparent text-sm outline-none"
                    />
                    {loading && <Loader2 className="text-muted-foreground size-4 animate-spin" />}
                    <kbd className="border-border/70 bg-muted rounded-md border px-1.5 py-0.5 text-[10px] font-semibold">esc</kbd>
                </div>

                <div className="scrollbar-soft max-h-[60vh] overflow-y-auto p-2">
                    {term.trim().length < 2 && <p className="text-muted-foreground px-2 py-6 text-center text-xs">Type at least two characters.</p>}

                    {term.trim().length >= 2 && !loading && (results?.total ?? 0) === 0 && (
                        <p className="text-muted-foreground px-2 py-6 text-center text-xs">
                            Nothing matches <strong className="text-foreground">{term}</strong>.
                        </p>
                    )}

                    {(results?.sections ?? []).map((section) => {
                        const Icon = ICONS[section.icon] ?? ListChecks;

                        return (
                            <div key={section.key} className="mb-1">
                                <p className="text-muted-foreground px-2 py-1.5 text-[10px] font-bold tracking-[0.14em] uppercase">{section.label}</p>

                                {section.items.map((hit) => {
                                    cursor += 1;
                                    const isActive = cursor === active;
                                    const index = cursor;

                                    return (
                                        <button
                                            key={`${section.key}-${hit.id}`}
                                            type="button"
                                            onMouseEnter={() => setActive(index)}
                                            onClick={() => go(hit)}
                                            className={cn(
                                                'flex w-full items-center gap-2.5 rounded-lg px-2 py-2 text-left transition-colors',
                                                isActive ? 'bg-primary/10' : 'hover:bg-muted/60',
                                            )}
                                        >
                                            <Icon className="text-muted-foreground size-4 shrink-0" />
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate text-sm">{hit.title}</span>
                                                {hit.subtitle && <span className="text-muted-foreground block truncate text-xs">{hit.subtitle}</span>}
                                            </span>
                                        </button>
                                    );
                                })}
                            </div>
                        );
                    })}

                    {(results?.total ?? 0) > 0 && (
                        <button
                            type="button"
                            onClick={() => {
                                onOpenChange(false);
                                router.visit(`${route('search.index')}?q=${encodeURIComponent(term)}`);
                            }}
                            className="hover:bg-muted/60 text-muted-foreground mt-1 w-full rounded-lg px-2 py-2 text-center text-xs transition-colors"
                        >
                            See all results for “{term}”
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}

/**
 * Owns the Cmd/Ctrl+K shortcut so the header does not have to.
 */
export function useCommandPalette() {
    const [open, setOpen] = useState(false);

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                setOpen((v) => !v);
            }
        };

        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

    return { open, setOpen };
}
