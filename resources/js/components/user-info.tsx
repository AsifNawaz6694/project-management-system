import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';

interface UserInfoUser {
    name: string;
    email?: string;
    avatar?: string | null;
    initials?: string;
}

export function UserInfo({ user, showEmail = false, className }: { user: UserInfoUser; showEmail?: boolean; className?: string }) {
    const getInitials = useInitials();

    return (
        <div className={cn('flex min-w-0 items-center gap-2.5', className)}>
            <div className="relative shrink-0">
                <Avatar className="dark:ring-card shadow-soft-sm size-9 overflow-hidden rounded-xl ring-2 ring-white">
                    <AvatarImage src={user.avatar ?? undefined} alt={user.name} />
                    <AvatarFallback className="bg-gradient-to-br from-blue-600 to-blue-700 text-xs font-bold text-white">
                        {user.initials || getInitials(user.name)}
                    </AvatarFallback>
                </Avatar>
                <span className="ring-card absolute -right-0.5 -bottom-0.5 block size-2.5 rounded-full bg-emerald-500 ring-2" />
            </div>
            <div className="grid min-w-0 flex-1 text-left leading-tight">
                <span className="truncate text-[13px] font-semibold">{user.name}</span>
                {showEmail && user.email && <span className="text-muted-foreground truncate text-[11px]">{user.email}</span>}
            </div>
        </div>
    );
}
