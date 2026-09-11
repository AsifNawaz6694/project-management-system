import * as React from 'react';

import { cn } from '@/lib/utils';

const Input = React.forwardRef<HTMLInputElement, React.ComponentProps<'input'>>(({ className, type, ...props }, ref) => {
    return (
        <input
            type={type}
            className={cn(
                'flex h-11 w-full rounded-xl border border-input bg-card px-3.5 py-2 text-sm ring-offset-background shadow-soft-xs transition-all file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground/70 hover:border-foreground/20 focus-visible:border-foreground/30 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-blue-200/60 dark:focus-visible:ring-blue-500/20 disabled:cursor-not-allowed disabled:opacity-50',
                className,
            )}
            ref={ref}
            {...props}
        />
    );
});

Input.displayName = 'Input';

export { Input };
