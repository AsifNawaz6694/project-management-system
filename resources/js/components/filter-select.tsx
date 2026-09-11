import { cn } from '@/lib/utils';
import { Check, ChevronDown, Search, X } from 'lucide-react';
import { useEffect, useId, useMemo, useRef, useState } from 'react';

export interface FilterOption {
    value: string;
    label: string;
    /** Secondary line, e.g. a job title or project key. */
    hint?: string;
    /** Palette name rendered as a leading dot. */
    color?: string;
}

interface FilterSelectProps {
    label: string;
    options: FilterOption[];
    /** Selected values. Always an array, even in single-select mode. */
    value: string[];
    onChange: (value: string[]) => void;
    multiple?: boolean;
    /** Hide the search box for very short lists. */
    searchable?: boolean;
    className?: string;
    /** Shown when nothing is selected. Defaults to "All {label}". */
    allLabel?: string;
    disabled?: boolean;
}

const DOT: Record<string, string> = {
    slate: 'bg-slate-500',
    blue: 'bg-blue-600',
    sky: 'bg-sky-600',
    violet: 'bg-indigo-600',
    emerald: 'bg-emerald-600',
    amber: 'bg-amber-500',
    rose: 'bg-red-600',
    pink: 'bg-pink-500',
};

/**
 * Searchable, multi-select filter control.
 *
 * Built directly on a button plus a positioned panel rather than a native
 * <select>, because a native control cannot host a search box or checkboxes.
 * Selection is always modelled as an array so the caller and the query string
 * have one shape regardless of `multiple`.
 */
export function FilterSelect({ label, options, value, onChange, multiple = true, searchable, className, allLabel, disabled }: FilterSelectProps) {
    const [open, setOpen] = useState(false);
    const [term, setTerm] = useState('');
    const [active, setActive] = useState(0);

    const rootRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const listRef = useRef<HTMLDivElement>(null);
    const listId = useId();

    // Search appears automatically once a list is long enough to need it.
    const showSearch = searchable ?? options.length > 7;

    const filtered = useMemo(() => {
        const q = term.trim().toLowerCase();
        if (!q) return options;
        return options.filter((o) => o.label.toLowerCase().includes(q) || o.hint?.toLowerCase().includes(q) || o.value.toLowerCase().includes(q));
    }, [options, term]);

    useEffect(() => {
        if (!open) return;

        const onPointerDown = (e: MouseEvent) => {
            if (!rootRef.current?.contains(e.target as Node)) setOpen(false);
        };
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') setOpen(false);
        };

        document.addEventListener('mousedown', onPointerDown);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('mousedown', onPointerDown);
            document.removeEventListener('keydown', onKey);
        };
    }, [open]);

    useEffect(() => {
        if (open) {
            setTerm('');
            setActive(0);
            if (showSearch) {
                const id = setTimeout(() => inputRef.current?.focus(), 20);
                return () => clearTimeout(id);
            }
        }
    }, [open, showSearch]);

    // Keep the highlighted row in view while arrowing through a long list.
    useEffect(() => {
        if (!open) return;
        listRef.current?.querySelector<HTMLElement>(`[data-index="${active}"]`)?.scrollIntoView({ block: 'nearest' });
    }, [active, open]);

    const toggle = (v: string) => {
        if (!multiple) {
            onChange(value.includes(v) ? [] : [v]);
            setOpen(false);
            return;
        }
        onChange(value.includes(v) ? value.filter((x) => x !== v) : [...value, v]);
    };

    const selectedLabels = value.map((v) => options.find((o) => o.value === v)?.label).filter(Boolean) as string[];

    const triggerText =
        selectedLabels.length === 0
            ? (allLabel ?? `All ${label.toLowerCase()}`)
            : selectedLabels.length === 1
              ? selectedLabels[0]
              : `${selectedLabels.length} selected`;

    const onTriggerKey = (e: React.KeyboardEvent) => {
        if (['ArrowDown', 'Enter', ' '].includes(e.key)) {
            e.preventDefault();
            setOpen(true);
        }
    };

    const onListKey = (e: React.KeyboardEvent) => {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActive((i) => Math.min(i + 1, filtered.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActive((i) => Math.max(i - 1, 0));
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const option = filtered[active];
            if (option) toggle(option.value);
        }
    };

    return (
        <div ref={rootRef} className={cn('relative', className)}>
            <button
                type="button"
                disabled={disabled}
                onClick={() => setOpen((v) => !v)}
                onKeyDown={onTriggerKey}
                aria-haspopup="listbox"
                aria-expanded={open}
                aria-controls={open ? listId : undefined}
                title={selectedLabels.length > 1 ? selectedLabels.join(', ') : undefined}
                className={cn(
                    'bg-card ring-border flex h-8 w-full items-center gap-1.5 rounded-lg px-2.5 text-xs ring-1 transition-all sm:h-9',
                    'hover:ring-foreground/25 focus-visible:ring-primary/40 focus-visible:ring-2 focus-visible:outline-none',
                    disabled && 'cursor-not-allowed opacity-50',
                    value.length > 0 && 'ring-primary/60 ring-2',
                )}
            >
                <span className={cn('min-w-0 flex-1 truncate text-left', value.length === 0 && 'text-muted-foreground')}>{triggerText}</span>

                {value.length > 0 && (
                    <span
                        role="button"
                        tabIndex={-1}
                        aria-label={`Clear ${label} filter`}
                        onClick={(e) => {
                            e.stopPropagation();
                            onChange([]);
                        }}
                        className="text-muted-foreground hover:text-foreground shrink-0"
                    >
                        <X className="size-3" />
                    </span>
                )}

                <ChevronDown className={cn('text-muted-foreground size-3.5 shrink-0 transition-transform', open && 'rotate-180')} />
            </button>

            {open && (
                <div
                    id={listId}
                    role="listbox"
                    aria-multiselectable={multiple}
                    aria-label={label}
                    onKeyDown={onListKey}
                    className="bg-card ring-border shadow-soft-lg absolute z-50 mt-1 max-h-72 w-full min-w-[13rem] overflow-hidden rounded-xl ring-1"
                >
                    {showSearch && (
                        <div className="border-border/60 flex items-center gap-1.5 border-b px-2.5 py-1.5">
                            <Search className="text-muted-foreground size-3.5 shrink-0" />
                            <input
                                ref={inputRef}
                                value={term}
                                onChange={(e) => {
                                    setTerm(e.target.value);
                                    setActive(0);
                                }}
                                placeholder={`Search ${label.toLowerCase()}…`}
                                aria-label={`Search ${label.toLowerCase()}`}
                                className="placeholder:text-muted-foreground min-w-0 flex-1 bg-transparent py-0.5 text-xs outline-none"
                            />
                            {term && (
                                <button
                                    type="button"
                                    onClick={() => setTerm('')}
                                    aria-label="Clear search"
                                    className="text-muted-foreground hover:text-foreground"
                                >
                                    <X className="size-3" />
                                </button>
                            )}
                        </div>
                    )}

                    <div ref={listRef} className="scrollbar-soft max-h-52 overflow-y-auto p-1">
                        {filtered.length === 0 && <p className="text-muted-foreground px-2 py-3 text-center text-xs">No matches.</p>}

                        {filtered.map((option, i) => {
                            const selected = value.includes(option.value);

                            return (
                                <button
                                    key={option.value}
                                    type="button"
                                    role="option"
                                    aria-selected={selected}
                                    data-index={i}
                                    onMouseEnter={() => setActive(i)}
                                    onClick={() => toggle(option.value)}
                                    className={cn(
                                        'flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-xs transition-colors',
                                        i === active && 'bg-muted',
                                        selected && 'font-semibold',
                                    )}
                                >
                                    {multiple ? (
                                        <span
                                            className={cn(
                                                'ring-border flex size-3.5 shrink-0 items-center justify-center rounded ring-1',
                                                selected && 'bg-primary ring-primary text-primary-foreground',
                                            )}
                                        >
                                            {selected && <Check className="size-2.5" />}
                                        </span>
                                    ) : (
                                        <span className="w-3.5 shrink-0">{selected && <Check className="text-primary size-3" />}</span>
                                    )}

                                    {option.color && <span className={cn('size-1.5 shrink-0 rounded-full', DOT[option.color] ?? DOT.slate)} />}

                                    <span className="min-w-0 flex-1 truncate">
                                        {option.label}
                                        {option.hint && <span className="text-muted-foreground ml-1 font-normal">· {option.hint}</span>}
                                    </span>
                                </button>
                            );
                        })}
                    </div>

                    {multiple && (
                        <div className="border-border/60 flex items-center justify-between gap-2 border-t px-2 py-1.5">
                            <span className="text-muted-foreground text-[10px] tabular-nums">
                                {value.length} of {options.length}
                            </span>
                            <div className="flex gap-1">
                                <button
                                    type="button"
                                    onClick={() => onChange(filtered.map((o) => o.value))}
                                    className="hover:text-primary text-[10px] font-semibold underline underline-offset-2"
                                >
                                    Select {term ? 'matching' : 'all'}
                                </button>
                                <button
                                    type="button"
                                    onClick={() => onChange([])}
                                    className="text-muted-foreground hover:text-foreground text-[10px] font-semibold underline underline-offset-2"
                                >
                                    Clear
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

/**
 * Normalises a filter value from the query string into an array — Inertia gives
 * back a string for one value and an array for several.
 */
export function toArray(value: unknown): string[] {
    if (value == null || value === '') return [];
    if (Array.isArray(value)) return value.map(String);
    return [String(value)];
}
