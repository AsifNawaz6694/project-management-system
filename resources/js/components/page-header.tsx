import { cn } from '@/lib/utils';
import { type ReactNode } from 'react';

interface PageHeaderProps {
    title: string;
    description?: string;
    actions?: ReactNode;
    className?: string;
    eyebrow?: string;
}

export function PageHeader({ title, description, actions, className, eyebrow }: PageHeaderProps) {
    return (
        <div className={cn('flex flex-col gap-3 md:flex-row md:items-end md:justify-between', className)}>
            <div className="space-y-1.5">
                {eyebrow && <p className="text-muted-foreground text-[11px] font-bold uppercase tracking-[0.16em]">{eyebrow}</p>}
                <h1 className="font-display text-3xl font-bold tracking-tight md:text-[32px]">{title}</h1>
                {description && <p className="text-muted-foreground max-w-2xl text-sm leading-relaxed">{description}</p>}
            </div>
            {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
        </div>
    );
}
