import { cn } from '@/lib/utils';
import { forwardRef, type HTMLAttributes } from 'react';

export const SoftCard = forwardRef<HTMLDivElement, HTMLAttributes<HTMLDivElement>>(({ className, ...props }, ref) => (
    <div
        ref={ref}
        className={cn(
            'bg-card shadow-soft-sm hover:shadow-soft-lg ring-1 ring-border/60 hover:ring-foreground/15 relative overflow-hidden rounded-2xl transition-all duration-300 animate-fade-up',
            className,
        )}
        {...props}
    />
));
SoftCard.displayName = 'SoftCard';

export function SoftCardHeader({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('flex items-start justify-between gap-3 p-5 pb-3', className)} {...props} />;
}

export function SoftCardTitle({ className, children, eyebrow, action }: { className?: string; children: React.ReactNode; eyebrow?: string; action?: React.ReactNode }) {
    return (
        <div className={cn('flex items-start justify-between gap-3 p-5 pb-3', className)}>
            <div>
                {eyebrow && <p className="text-muted-foreground text-[11px] font-bold uppercase tracking-[0.14em]">{eyebrow}</p>}
                <h3 className="font-display mt-1 text-base font-bold tracking-tight">{children}</h3>
            </div>
            {action}
        </div>
    );
}

export function SoftCardBody({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('px-5 pb-5', className)} {...props} />;
}
