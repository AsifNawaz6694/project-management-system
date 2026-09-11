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
        className: 'bg-amber-50 text-amber-800 ring-amber-300/60 dark:bg-amber-500/12 dark:text-amber-200 dark:ring-amber-500/30',
        icon: Crown,
    },
    manager: {
        label: 'Manager',
        className: 'bg-indigo-50 text-indigo-800 ring-indigo-300/60 dark:bg-indigo-500/12 dark:text-indigo-200 dark:ring-indigo-500/30',
        icon: Shield,
    },
    employee: {
        label: 'Employee',
        className: 'bg-emerald-50 text-emerald-800 ring-emerald-300/60 dark:bg-emerald-500/12 dark:text-emerald-200 dark:ring-emerald-500/30',
        icon: UserIcon,
    },
};

export function RoleBadge({ role, className, size = 'sm' }: RoleBadgeProps) {
    const style = (role && STYLES[role as string]) || STYLES.employee;
    const Icon = style.icon;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-full font-semibold ring-1 transition-all ring-inset',
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
