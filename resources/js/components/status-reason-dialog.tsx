import { Button } from '@/components/ui/button';
import { useEffect, useRef, useState } from 'react';

export interface PendingTransition {
    taskId: number;
    status: string;
    statusName: string;
    label: string;
    position?: number;
}

interface StatusReasonDialogProps {
    pending: PendingTransition | null;
    onCancel: () => void;
    onConfirm: (reason: string) => void;
    submitting?: boolean;
    error?: string | null;
}

/**
 * Prompts for the written reason a workflow transition requires — a QA verdict,
 * a block, a reopen. The same requirement is enforced server-side; this exists
 * so the user is asked before the request rather than rejected after it.
 */
export function StatusReasonDialog({ pending, onCancel, onConfirm, submitting, error }: StatusReasonDialogProps) {
    const [reason, setReason] = useState('');
    const inputRef = useRef<HTMLTextAreaElement>(null);

    useEffect(() => {
        if (pending) {
            setReason('');
            // Let the dialog mount before stealing focus.
            const id = setTimeout(() => inputRef.current?.focus(), 30);
            return () => clearTimeout(id);
        }
    }, [pending]);

    useEffect(() => {
        if (!pending) return;

        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') onCancel();
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [pending, onCancel]);

    if (!pending) return null;

    const empty = reason.trim().length === 0;

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            aria-labelledby="status-reason-title"
            onMouseDown={(e) => {
                if (e.target === e.currentTarget) onCancel();
            }}
        >
            <div className="bg-card ring-border shadow-soft-xl w-full max-w-lg rounded-2xl p-5 ring-1">
                <h2 id="status-reason-title" className="font-display text-lg font-bold">
                    Move to “{pending.statusName}”
                </h2>
                <p className="text-muted-foreground mt-1 text-sm">{pending.label}</p>

                <textarea
                    ref={inputRef}
                    value={reason}
                    onChange={(e) => setReason(e.target.value)}
                    rows={5}
                    maxLength={2000}
                    placeholder="Be specific — this is stored on the task history."
                    className="bg-card ring-border focus-visible:ring-primary/30 mt-3 w-full rounded-xl px-3.5 py-2.5 text-sm ring-1 transition-all focus-visible:ring-4 focus-visible:outline-none"
                    onKeyDown={(e) => {
                        if ((e.metaKey || e.ctrlKey) && e.key === 'Enter' && !empty) {
                            onConfirm(reason.trim());
                        }
                    }}
                />

                <div className="text-muted-foreground mt-1 flex items-center justify-between text-[11px]">
                    <span>{error ? <span className="text-red-600 dark:text-red-400">{error}</span> : 'Required'}</span>
                    <span className="tabular-nums">{reason.length}/2000</span>
                </div>

                <div className="mt-4 flex justify-end gap-2">
                    <Button size="sm" variant="ghost" onClick={onCancel} disabled={submitting}>
                        Cancel
                    </Button>
                    <Button size="sm" onClick={() => onConfirm(reason.trim())} disabled={empty || submitting}>
                        {submitting ? 'Saving…' : `Move to ${pending.statusName}`}
                    </Button>
                </div>
            </div>
        </div>
    );
}
