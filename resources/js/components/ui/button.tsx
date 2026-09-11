import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import * as React from 'react';

import { cn } from '@/lib/utils';

const buttonVariants = cva(
    'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-xl text-sm font-semibold tracking-tight ring-offset-background transition-all duration-200 ease-out focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-60 [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0 active:translate-y-px',
    {
        variants: {
            variant: {
                default:
                    'bg-gradient-to-br from-blue-600 to-blue-700 text-white shadow-soft-md hover:shadow-glow hover:brightness-[1.05]',
                secondary:
                    'bg-card text-foreground shadow-soft-sm ring-1 ring-border hover:ring-foreground/30 hover:shadow-soft-md',
                outline:
                    'bg-card/60 text-foreground ring-1 ring-border backdrop-blur hover:bg-card hover:ring-foreground/30 hover:shadow-soft-sm',
                ghost: 'text-foreground hover:bg-accent/70',
                destructive:
                    'bg-gradient-to-br from-red-500 to-red-600 text-white shadow-soft-md hover:brightness-110',
                soft: 'bg-blue-50 text-blue-700 ring-1 ring-blue-100 hover:bg-blue-100 dark:bg-blue-500/10 dark:text-blue-200 dark:ring-blue-500/20',
                link: 'text-primary underline-offset-4 hover:underline',
            },
            size: {
                default: 'h-10 px-4 py-2',
                sm: 'h-9 px-3.5 text-[13px]',
                lg: 'h-12 px-6 text-[15px]',
                icon: 'h-10 w-10',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    },
);

export interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement>, VariantProps<typeof buttonVariants> {
    asChild?: boolean;
}

const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(({ className, variant, size, asChild = false, ...props }, ref) => {
    const Comp = asChild ? Slot : 'button';
    return <Comp className={cn(buttonVariants({ variant, size, className }))} ref={ref} {...props} />;
});
Button.displayName = 'Button';

export { Button, buttonVariants };
