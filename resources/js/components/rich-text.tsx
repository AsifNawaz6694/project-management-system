import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { Bold, Code, Italic, Link2, List, ListOrdered, Quote, Strikethrough } from 'lucide-react';
import { useRef, type ReactNode } from 'react';

/*
|------------------------------------------------------------------------------
| Rendering
|------------------------------------------------------------------------------
|
| A deliberately small Markdown subset, rendered to React elements rather than
| to an HTML string. Nothing here ever reaches dangerouslySetInnerHTML, so a
| comment containing markup is text, not a script — the renderer is XSS-safe by
| construction rather than by sanitising after the fact.
*/

/** Inline: **bold**, *italic*, ~~strike~~, `code`, [text](url), bare URLs, @mentions. */
function renderInline(text: string, keyPrefix: string): ReactNode[] {
    const pattern =
        /(\*\*[^*]+\*\*)|(\*[^*\n]+\*)|(~~[^~]+~~)|(`[^`\n]+`)|(\[[^\]]+\]\((?:https?:\/\/|\/)[^\s)]+\))|((?:https?:\/\/)[^\s<]+)|(@[\w.-]+)/g;

    const out: ReactNode[] = [];
    let last = 0;
    let match: RegExpExecArray | null;
    let i = 0;

    while ((match = pattern.exec(text)) !== null) {
        if (match.index > last) out.push(text.slice(last, match.index));

        const token = match[0];
        const key = `${keyPrefix}-i${i++}`;

        if (token.startsWith('**')) {
            out.push(<strong key={key}>{token.slice(2, -2)}</strong>);
        } else if (token.startsWith('~~')) {
            out.push(
                <span key={key} className="line-through opacity-70">
                    {token.slice(2, -2)}
                </span>,
            );
        } else if (token.startsWith('`')) {
            out.push(
                <code key={key} className="bg-muted rounded px-1 py-0.5 font-mono text-[0.9em]">
                    {token.slice(1, -1)}
                </code>,
            );
        } else if (token.startsWith('[')) {
            const split = token.indexOf('](');
            const label = token.slice(1, split);
            const href = token.slice(split + 2, -1);
            out.push(
                <a key={key} href={href} target="_blank" rel="noopener noreferrer" className="text-primary underline underline-offset-2">
                    {label}
                </a>,
            );
        } else if (token.startsWith('http')) {
            out.push(
                <a key={key} href={token} target="_blank" rel="noopener noreferrer" className="text-primary underline underline-offset-2">
                    {token}
                </a>,
            );
        } else if (token.startsWith('@')) {
            out.push(
                <span key={key} className="bg-primary/10 text-primary rounded px-1 font-medium">
                    {token}
                </span>,
            );
        } else {
            out.push(
                <em key={key} className="italic">
                    {token.slice(1, -1)}
                </em>,
            );
        }

        last = match.index + token.length;
    }

    if (last < text.length) out.push(text.slice(last));

    return out;
}

interface RichTextProps {
    value: string | null | undefined;
    className?: string;
    /** Shown when there is nothing to render. */
    empty?: string;
}

/**
 * Renders the stored text. Safe to point at anything a user typed.
 */
export function RichText({ value, className, empty }: RichTextProps) {
    if (!value || value.trim() === '') {
        return empty ? <p className="text-muted-foreground text-sm">{empty}</p> : null;
    }

    const lines = value.replace(/\r\n/g, '\n').split('\n');
    const blocks: ReactNode[] = [];

    let listBuffer: string[] = [];
    let listOrdered = false;
    let codeBuffer: string[] = [];
    let inCode = false;
    let key = 0;

    const flushList = () => {
        if (listBuffer.length === 0) return;

        const items = listBuffer.map((item, i) => <li key={i}>{renderInline(item, `l${key}-${i}`)}</li>);
        blocks.push(
            listOrdered ? (
                <ol key={`b${key++}`} className="ml-5 list-decimal space-y-0.5">
                    {items}
                </ol>
            ) : (
                <ul key={`b${key++}`} className="ml-5 list-disc space-y-0.5">
                    {items}
                </ul>
            ),
        );
        listBuffer = [];
    };

    for (const line of lines) {
        // Fenced code blocks swallow everything until the closing fence.
        if (line.trimStart().startsWith('```')) {
            if (inCode) {
                blocks.push(
                    <pre key={`b${key++}`} className="bg-muted scrollbar-soft overflow-x-auto rounded-lg p-3 font-mono text-xs">
                        <code>{codeBuffer.join('\n')}</code>
                    </pre>,
                );
                codeBuffer = [];
                inCode = false;
            } else {
                flushList();
                inCode = true;
            }
            continue;
        }

        if (inCode) {
            codeBuffer.push(line);
            continue;
        }

        const bullet = line.match(/^\s*[-*]\s+(.*)$/);
        const numbered = line.match(/^\s*\d+[.)]\s+(.*)$/);

        if (bullet || numbered) {
            const ordered = Boolean(numbered);
            if (listBuffer.length > 0 && ordered !== listOrdered) flushList();
            listOrdered = ordered;
            listBuffer.push((bullet?.[1] ?? numbered?.[1]) as string);
            continue;
        }

        flushList();

        if (line.trim() === '') continue;

        const heading = line.match(/^(#{1,3})\s+(.*)$/);
        if (heading) {
            const level = heading[1].length;
            const size = level === 1 ? 'text-base' : level === 2 ? 'text-sm' : 'text-xs';
            blocks.push(
                <p key={`b${key++}`} className={cn('font-semibold', size)}>
                    {renderInline(heading[2], `h${key}`)}
                </p>,
            );
            continue;
        }

        if (line.trimStart().startsWith('> ')) {
            blocks.push(
                <blockquote key={`b${key++}`} className="border-border text-muted-foreground border-l-2 pl-3 italic">
                    {renderInline(line.trimStart().slice(2), `q${key}`)}
                </blockquote>,
            );
            continue;
        }

        if (/^\s*(---|\*\*\*)\s*$/.test(line)) {
            blocks.push(<hr key={`b${key++}`} className="border-border/60" />);
            continue;
        }

        blocks.push(<p key={`b${key++}`}>{renderInline(line, `p${key}`)}</p>);
    }

    flushList();

    if (inCode && codeBuffer.length > 0) {
        blocks.push(
            <pre key={`b${key++}`} className="bg-muted scrollbar-soft overflow-x-auto rounded-lg p-3 font-mono text-xs">
                <code>{codeBuffer.join('\n')}</code>
            </pre>,
        );
    }

    return <div className={cn('space-y-2 text-sm leading-relaxed break-words', className)}>{blocks}</div>;
}

/*
|------------------------------------------------------------------------------
| Editing
|------------------------------------------------------------------------------
*/

interface EditorProps {
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    rows?: number;
    disabled?: boolean;
    className?: string;
    id?: string;
}

const TOOLS = [
    { icon: Bold, label: 'Bold', wrap: '**' },
    { icon: Italic, label: 'Italic', wrap: '*' },
    { icon: Strikethrough, label: 'Strikethrough', wrap: '~~' },
    { icon: Code, label: 'Code', wrap: '`' },
] as const;

const PREFIXES = [
    { icon: List, label: 'Bulleted list', prefix: '- ' },
    { icon: ListOrdered, label: 'Numbered list', prefix: '1. ' },
    { icon: Quote, label: 'Quote', prefix: '> ' },
] as const;

/**
 * A textarea with a formatting toolbar.
 *
 * Deliberately not a contenteditable WYSIWYG: what is stored stays plain text,
 * so existing descriptions and comments keep working, nothing needs migrating,
 * and the value degrades to something readable anywhere it is not rendered.
 */
export function RichTextEditor({ value, onChange, placeholder, rows = 5, disabled, className, id }: EditorProps) {
    const ref = useRef<HTMLTextAreaElement>(null);

    const surround = (token: string) => {
        const el = ref.current;
        if (!el) return;

        const { selectionStart: start, selectionEnd: end } = el;
        const selected = value.slice(start, end) || 'text';
        const next = `${value.slice(0, start)}${token}${selected}${token}${value.slice(end)}`;

        onChange(next);

        requestAnimationFrame(() => {
            el.focus();
            el.setSelectionRange(start + token.length, start + token.length + selected.length);
        });
    };

    const prefixLines = (prefix: string) => {
        const el = ref.current;
        if (!el) return;

        const { selectionStart: start, selectionEnd: end } = el;
        const lineStart = value.lastIndexOf('\n', start - 1) + 1;
        const lineEnd = value.indexOf('\n', end) === -1 ? value.length : value.indexOf('\n', end);

        const block = value
            .slice(lineStart, lineEnd)
            .split('\n')
            .map((line) => (line.startsWith(prefix) ? line.slice(prefix.length) : prefix + line))
            .join('\n');

        onChange(value.slice(0, lineStart) + block + value.slice(lineEnd));
        requestAnimationFrame(() => el.focus());
    };

    const link = () => {
        const el = ref.current;
        if (!el) return;

        const { selectionStart: start, selectionEnd: end } = el;
        const selected = value.slice(start, end) || 'link text';
        onChange(`${value.slice(0, start)}[${selected}](https://)${value.slice(end)}`);
        requestAnimationFrame(() => el.focus());
    };

    return (
        <div className={cn('ring-border/60 focus-within:ring-primary/40 overflow-hidden rounded-lg ring-1', className)}>
            <div className="border-border/60 bg-muted/30 flex flex-wrap items-center gap-0.5 border-b px-1.5 py-1">
                {TOOLS.map((tool) => (
                    <Button
                        key={tool.label}
                        type="button"
                        size="sm"
                        variant="ghost"
                        disabled={disabled}
                        aria-label={tool.label}
                        title={tool.label}
                        className="size-7 p-0"
                        onClick={() => surround(tool.wrap)}
                    >
                        <tool.icon className="size-3.5" />
                    </Button>
                ))}

                {PREFIXES.map((tool) => (
                    <Button
                        key={tool.label}
                        type="button"
                        size="sm"
                        variant="ghost"
                        disabled={disabled}
                        aria-label={tool.label}
                        title={tool.label}
                        className="size-7 p-0"
                        onClick={() => prefixLines(tool.prefix)}
                    >
                        <tool.icon className="size-3.5" />
                    </Button>
                ))}

                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    disabled={disabled}
                    aria-label="Link"
                    title="Link"
                    className="size-7 p-0"
                    onClick={link}
                >
                    <Link2 className="size-3.5" />
                </Button>

                <span className="text-muted-foreground ml-auto pr-1 text-[10px]">Markdown supported</span>
            </div>

            <textarea
                id={id}
                ref={ref}
                value={value}
                rows={rows}
                disabled={disabled}
                placeholder={placeholder}
                onChange={(e) => onChange(e.target.value)}
                className="bg-background w-full resize-y px-3 py-2 text-sm outline-none"
            />
        </div>
    );
}
