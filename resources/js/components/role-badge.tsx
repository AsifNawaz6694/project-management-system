import { cn } from '@/lib/utils';
import { type RoleSlug } from '@/types';
import { Crown, Shield, User as UserIcon } from 'lucide-react';

interface RoleBadgeProps {
    role: RoleSlug | string | null | undefined;
    className?: string;
    size?: 'sm' | 'md';
}

const STYLES: Record<string, { label: string; className: string; icon: typeof Crown }> = {
    admin: {
        label: 'Admin',
        className: 'bg-gradient-to-r from-amber-100 to-orange-100 text-amber-800 ring-amber-300/60 dark:from-amber-500/15 dark:to-orange-500/15 dark:text-amber-200 dark:ring-amber-500/30',
        icon: Crown,
    },
    manager: {
        label: 'Manager',
        className: 'bg-gradient-to-r from-violet-100 to-indigo-100 text-violet-800 ring-violet-300/60 dark:from-violet-500/15 dark:to-indigo-500/15 dark:text-violet-200 dark:ring-violet-500/30',
        icon: Shield,
    },
    employee: {
        label: 'Employee',
        className: 'bg-gradient-to-r from-emerald-100 to-teal-100 text-emerald-800 ring-emerald-300/60 dark:from-emerald-500/15 dark:to-teal-500/15 dark:text-emerald-200 dark:ring-emerald-500/30',
        icon: UserIcon,
    },
};

export function RoleBadge({ role, className, size = 'sm' }: RoleBadgeProps) {
    const style = (role && STYLES[role as string]) || STYLES.employee;
    const Icon = style.icon;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-full font-semibold ring-1 ring-inset transition-all',
                size === 'sm' ? 'px-2 py-0.5 text-[11px]' : 'px-2.5 py-1 text-xs',
                style.className,
                className,
            )}
        >
            <Icon className={cn('shrink-0', size === 'sm' ? 'size-3' : 'size-3.5')} />
            {style.label}
        </span>
    );
}
