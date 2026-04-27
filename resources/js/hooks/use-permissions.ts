import { type AuthUser, type RoleSlug, type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

interface PermissionsApi {
    user: AuthUser | null;
    is: (...roles: RoleSlug[]) => boolean;
    can: (permission?: string) => boolean;
    canAny: (...permissions: string[]) => boolean;
    primaryRole: RoleSlug | string | null;
}

export function usePermissions(): PermissionsApi {
    const { auth } = usePage<SharedData>().props;
    const user = auth.user;

    const is = (...roles: RoleSlug[]): boolean => {
        if (!user) return false;
        return roles.some((role) => user.roles.some((r) => r.slug === role));
    };

    const can = (permission?: string): boolean => {
        if (!permission) return true;
        if (!user) return false;
        if (user.is_admin) return true;
        return user.permissions.includes(permission);
    };

    const canAny = (...permissions: string[]): boolean => permissions.some((p) => can(p));

    return { user, is, can, canAny, primaryRole: user?.primary_role ?? null };
}
