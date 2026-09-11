import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { Link, router } from '@inertiajs/react';
import { Ban, Check, Link2, Plus, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export interface TaskLinkItem {
    link_id: number;
    id: number;
    key: string;
    title: string;
    status: string;
    priority: string;
    is_done: boolean;
}

export type TaskLinkGroups = Record<string, TaskLinkItem[]>;

interface SearchResult {
    id: number;
    key: string;
    title: string;
    status: string;
}

interface TaskLinksPanelProps {
    taskId: number;
    links: TaskLinkGroups;
    linkTypes: Array<{ value: string; label: string }>;
    canLink: boolean;
}

/**
 * Dependency and relationship links. Creating one writes both directions on the
 * server, so the other task shows the mirrored relationship automatically.
 */
export function TaskLinksPanel({ taskId, links, linkTypes, canLink }: TaskLinksPanelProps) {
    const [adding, setAdding] = useState(false);
    const [type, setType] = useState(linkTypes[0]?.value ?? 'relates_to');
    const [term, setTerm] = useState('');
    const [results, setResults] = useState<SearchResult[]>([]);
    const [searching, setSearching] = useState(false);
    const debounce = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        if (!adding) return;

        if (debounce.current) clearTimeout(debounce.current);

        debounce.current = setTimeout(async () => {
            setSearching(true);
            try {
                const res = await fetch(route('tasks.search', { q: term, exclude: taskId }), {
                    headers: { Accept: 'application/json' },
                });
                setResults(res.ok ? await res.json() : []);
            } catch {
                setResults([]);
            } finally {
                setSearching(false);
            }
        }, 250);

        return () => {
            if (debounce.current) clearTimeout(debounce.current);
        };
    }, [term, adding, taskId]);

    const createLink = (targetId: number) => {
        router.post(
            route('tasks.links.store', taskId),
            { target_task_id: targetId, type },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setAdding(false);
                    setTerm('');
                    setResults([]);
                },
            },
        );
    };

    const groups = Object.entries(links).filter(([, items]) => items.length > 0);

    return (
        <div className="space-y-3">
            {groups.length === 0 && !adding && <p className="text-muted-foreground text-xs">No linked tasks.</p>}

            {groups.map(([linkType, items]) => (
                <div key={linkType}>
                    <p className="text-muted-foreground mb-1.5 text-[10px] font-bold tracking-[0.12em] uppercase">
                        {linkTypes.find((t) => t.value === linkType)?.label ?? linkType.replace(/_/g, ' ')}
                    </p>
                    <ul className="space-y-1.5">
                        {items.map((item) => (
                            <li key={item.link_id} className="bg-muted/30 ring-border/60 flex items-center gap-2 rounded-lg px-2.5 py-1.5 ring-1">
                                {linkType === 'blocked_by' && !item.is_done ? (
                                    <Ban className="size-3.5 shrink-0 text-red-600 dark:text-red-400" />
                                ) : item.is_done ? (
                                    <Check className="size-3.5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                ) : (
                                    <Link2 className="text-muted-foreground size-3.5 shrink-0" />
                                )}

                                <Link href={route('tasks.show', item.id)} className="hover:text-primary min-w-0 flex-1 truncate text-xs font-medium">
                                    <span className="text-muted-foreground mr-1.5 font-mono text-[10px]">{item.key}</span>
                                    <span className={cn(item.is_done && 'text-muted-foreground line-through')}>{item.title}</span>
                                </Link>

                                {canLink && (
                                    <button
                                        type="button"
                                        aria-label="Remove link"
                                        onClick={() =>
                                            router.delete(route('tasks.links.destroy', [taskId, item.link_id]), {
                                                preserveScroll: true,
                                            })
                                        }
                                        className="text-muted-foreground hover:text-red-600"
                                    >
                                        <X className="size-3" />
                                    </button>
                                )}
                            </li>
                        ))}
                    </ul>
                </div>
            ))}

            {canLink &&
                (adding ? (
                    <div className="space-y-2">
                        <Select value={type} onValueChange={setType}>
                            <SelectTrigger className="h-9 text-xs">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {linkTypes.map((t) => (
                                    <SelectItem key={t.value} value={t.value}>
                                        {t.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Input
                            value={term}
                            onChange={(e) => setTerm(e.target.value)}
                            placeholder="Search tasks by title or number…"
                            className="h-9 text-xs"
                            autoFocus
                        />

                        <div className="max-h-44 space-y-1 overflow-y-auto">
                            {searching && <p className="text-muted-foreground text-[11px]">Searching…</p>}
                            {!searching && results.length === 0 && <p className="text-muted-foreground text-[11px]">No matching tasks.</p>}
                            {results.map((r) => (
                                <button
                                    key={r.id}
                                    type="button"
                                    onClick={() => createLink(r.id)}
                                    className="hover:bg-muted flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-xs transition-colors"
                                >
                                    <span className="text-muted-foreground font-mono text-[10px]">{r.key}</span>
                                    <span className="truncate">{r.title}</span>
                                </button>
                            ))}
                        </div>

                        <Button size="sm" variant="ghost" onClick={() => setAdding(false)} className="w-full">
                            Cancel
                        </Button>
                    </div>
                ) : (
                    <Button size="sm" variant="soft" onClick={() => setAdding(true)} className="w-full">
                        <Plus className="size-3.5" /> Link a task
                    </Button>
                ))}
        </div>
    );
}
