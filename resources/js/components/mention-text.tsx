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
                        className="inline-flex items-center rounded-md bg-blue-100 px-1 font-semibold text-blue-700 ring-1 ring-blue-200 dark:bg-blue-500/15 dark:text-blue-300 dark:ring-blue-500/30"
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
