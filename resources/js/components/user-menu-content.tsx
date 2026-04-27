import { RoleBadge } from '@/components/role-badge';
import { DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { type AuthUser } from '@/types';
import { Link } from '@inertiajs/react';
import { LogOut, Settings, User } from 'lucide-react';

interface UserMenuContentProps {
    user: AuthUser;
}

export function UserMenuContent({ user }: UserMenuContentProps) {
    const cleanup = useMobileNavigation();

    return (
        <>
            <DropdownMenuLabel className="p-2 font-normal">
                <div className="flex flex-col gap-2">
                    <div className="text-sm font-semibold leading-tight">{user.name}</div>
                    <div className="text-muted-foreground truncate text-xs">{user.email}</div>
                    <div>
                        <RoleBadge role={user.primary_role} />
                    </div>
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <DropdownMenuItem asChild className="rounded-lg">
                    <Link className="block w-full" href={route('users.show', user.id)} as="button" prefetch onClick={cleanup}>
                        <User className="mr-2 size-4" />
                        My profile
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild className="rounded-lg">
                    <Link className="block w-full" href={route('profile.edit')} as="button" prefetch onClick={cleanup}>
                        <Settings className="mr-2 size-4" />
                        Settings
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild className="rounded-lg text-rose-600 focus:text-rose-700 dark:text-rose-400">
                <Link className="block w-full" method="post" href={route('logout')} as="button" onClick={cleanup}>
                    <LogOut className="mr-2 size-4" />
                    Log out
                </Link>
            </DropdownMenuItem>
        </>
    );
}
