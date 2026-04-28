import { ConfirmDialog } from '@/components/confirm-dialog';
import { MentionText } from '@/components/mention-text';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import { Link, router, useForm } from '@inertiajs/react';
import { LoaderCircle, MessageSquare, Send, Trash2 } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

export interface CommentNode {
    id: number;
    body: string;
    created_at: string;
    user: { id: number; name: string; avatar?: string | null };
    replies?: CommentNode[];
}

interface CommentThreadProps {
    comments: CommentNode[];
    storeUrl: string;
    destroyUrlFor: (commentId: number) => string;
    currentUserId?: number | null;
    canDeleteAny?: boolean;
    placeholder?: string;
}

export function CommentThread({ comments, storeUrl, destroyUrlFor, currentUserId, canDeleteAny, placeholder = 'Drop a comment, use @name to mention…' }: CommentThreadProps) {
    return (
        <div className="space-y-4">
            <CommentComposer storeUrl={storeUrl} placeholder={placeholder} />
            <ul className="space-y-4">
                {comments.length === 0 && (
                    <li className="bg-muted/30 ring-border/60 ring-1 rounded-2xl py-10 text-center">
                        <MessageSquare className="text-muted-foreground mx-auto size-5" />
                        <p className="text-muted-foreground mt-2 text-sm">No comments yet — start the conversation.</p>
                    </li>
                )}
                {comments.map((c) => (
                    <CommentNodeView
                        key={c.id}
                        node={c}
                        storeUrl={storeUrl}
                        destroyUrlFor={destroyUrlFor}
                        currentUserId={currentUserId}
                        canDeleteAny={canDeleteAny}
                    />
                ))}
            </ul>
        </div>
    );
}

function CommentNodeView({
    node,
    storeUrl,
    destroyUrlFor,
    currentUserId,
    canDeleteAny,
    depth = 0,
}: {
    node: CommentNode;
    storeUrl: string;
    destroyUrlFor: (id: number) => string;
    currentUserId?: number | null;
    canDeleteAny?: boolean;
    depth?: number;
}) {
    const getInitials = useInitials();
    const [replying, setReplying] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const canDelete = canDeleteAny || node.user.id === currentUserId;

    return (
        <li className={cn('group animate-[fade-in-up_0.3s_ease-out]', depth > 0 && 'pl-10')}>
            <div className="relative bg-card ring-border/60 shadow-soft-xs hover:shadow-soft-sm ring-1 rounded-2xl p-4 transition-all">
                {depth > 0 && (
                    <span className="absolute -left-6 top-6 h-px w-6 bg-border" />
                )}
                <div className="flex items-start gap-3">
                    <Link href={route('users.show', node.user.id)} className="from-violet-500 to-indigo-600 ring-card shadow-soft-xs flex size-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br text-xs font-bold text-white ring-2">
                        {getInitials(node.user.name)}
                    </Link>
                    <div className="min-w-0 flex-1">
                        <div className="flex items-baseline gap-2">
                            <Link href={route('users.show', node.user.id)} className="text-sm font-semibold hover:text-violet-600 dark:hover:text-violet-300">{node.user.name}</Link>
                            <span className="text-muted-foreground text-[11px]">{relativeTime(node.created_at)}</span>
                        </div>
                        <p className="mt-1 whitespace-pre-line text-sm leading-relaxed">
                            <MentionText>{node.body}</MentionText>
                        </p>
                        <div className="mt-2 flex items-center gap-3">
                            <button onClick={() => setReplying((v) => !v)} className="text-muted-foreground hover:text-foreground text-[11px] font-semibold transition-colors">
                                {replying ? 'Cancel' : 'Reply'}
                            </button>
                            {canDelete && (
                                <button
                                    onClick={() => setConfirmDelete(true)}
                                    className="text-muted-foreground/60 hover:text-rose-500 text-[11px] inline-flex items-center gap-1 font-semibold transition-colors"
                                >
                                    <Trash2 className="size-3" /> Delete
                                </button>
                            )}
                        </div>
                        {replying && (
                            <div className="mt-3">
                                <CommentComposer storeUrl={storeUrl} parentId={node.id} placeholder={`Reply to ${node.user.name}…`} compact onPosted={() => setReplying(false)} />
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {(node.replies?.length ?? 0) > 0 && (
                <ul className="mt-3 space-y-3">
                    {node.replies!.map((r) => (
                        <CommentNodeView
                            key={r.id}
                            node={r}
                            storeUrl={storeUrl}
                            destroyUrlFor={destroyUrlFor}
                            currentUserId={currentUserId}
                            canDeleteAny={canDeleteAny}
                            depth={depth + 1}
                        />
                    ))}
                </ul>
            )}

            <ConfirmDialog
                open={confirmDelete}
                onOpenChange={setConfirmDelete}
                title="Delete this comment?"
                description="The comment will be removed for everyone. This cannot be undone."
                confirmLabel="Delete comment"
                tone="destructive"
                icon={Trash2}
                onConfirm={() => {
                    router.delete(destroyUrlFor(node.id), { preserveScroll: true });
                    setConfirmDelete(false);
                }}
            />
        </li>
    );
}

function CommentComposer({ storeUrl, parentId, placeholder, compact, onPosted }: { storeUrl: string; parentId?: number; placeholder?: string; compact?: boolean; onPosted?: () => void }) {
    const form = useForm<{ body: string; parent_id?: number }>({ body: '', parent_id: parentId });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (!form.data.body.trim()) return;
        form.post(storeUrl, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('body');
                onPosted?.();
            },
        });
    };

    return (
        <form onSubmit={submit} className={cn('bg-card ring-border/60 ring-1 shadow-soft-xs flex flex-col gap-2 rounded-2xl p-3', compact && 'rounded-xl')}>
            <textarea
                value={form.data.body}
                onChange={(e) => form.setData('body', e.target.value)}
                rows={compact ? 2 : 3}
                placeholder={placeholder}
                className="bg-muted/30 ring-border/60 focus-visible:border-foreground/30 focus-visible:ring-violet-200/60 dark:focus-visible:ring-violet-500/20 ring-1 w-full resize-none rounded-xl px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-4"
            />
            <div className="flex items-center justify-end">
                <Button type="submit" size="sm" disabled={form.processing || !form.data.body.trim()} className="gap-1.5">
                    {form.processing ? <LoaderCircle className="size-3.5 animate-spin" /> : <Send className="size-3.5" />}
                    Post
                </Button>
            </div>
        </form>
    );
}

function relativeTime(iso: string): string {
    const diff = (Date.now() - new Date(iso).getTime()) / 1000;
    if (diff < 60) return 'just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;
    return new Date(iso).toLocaleDateString();
}
