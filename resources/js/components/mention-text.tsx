import { Fragment } from 'react';

const MENTION_RE = /@([A-Za-z][A-Za-z0-9_.-]*(?:\s[A-Za-z]+)?)/g;

export function MentionText({ children }: { children: string }) {
    const parts: Array<{ type: 'text' | 'mention'; value: string }> = [];
    let lastIndex = 0;
    let match;
    while ((match = MENTION_RE.exec(children)) !== null) {
        if (match.index > lastIndex) parts.push({ type: 'text', value: children.slice(lastIndex, match.index) });
        parts.push({ type: 'mention', value: match[1] });
        lastIndex = match.index + match[0].length;
    }
    if (lastIndex < children.length) parts.push({ type: 'text', value: children.slice(lastIndex) });

    return (
        <span>
            {parts.map((p, i) =>
                p.type === 'mention' ? (
                    <span
                        key={i}
                        className="bg-violet-100 text-violet-700 ring-violet-200 dark:bg-violet-500/15 dark:text-violet-300 dark:ring-violet-500/30 inline-flex items-center rounded-md px-1 font-semibold ring-1"
                    >
                        @{p.value}
                    </span>
                ) : (
                    <Fragment key={i}>{p.value}</Fragment>
                ),
            )}
        </span>
    );
}
