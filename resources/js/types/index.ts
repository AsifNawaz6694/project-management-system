import { LucideIcon } from 'lucide-react';

export type RoleSlug = 'admin' | 'manager' | 'employee';

export interface DepartmentSummary {
    id: number;
    slug: string;
    name: string;
}

export interface RoleSummary {
    slug: RoleSlug | string;
    name: string;
}

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string | null;
    avatar?: string | null;
    phone?: string | null;
    job_title?: string | null;
    department?: string | null;
    status?: 'active' | 'invited' | 'suspended';
    department_id?: number | null;
    initials: string;
    roles: RoleSummary[];
    primary_role: RoleSlug | string | null;
    permissions: string[];
    is_admin: boolean;
    is_manager: boolean;
    is_employee: boolean;
}

export interface Auth {
    user: AuthUser | null;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
    permission?: string;
    roles?: RoleSlug[];
}

export interface SharedData {
    name: string;
    auth: Auth;
    flash: {
        status?: string | null;
        error?: string | null;
    };
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string | null;
    phone?: string | null;
    job_title?: string | null;
    department_id?: number | null;
    department?: DepartmentSummary | null;
    status?: 'active' | 'invited' | 'suspended';
    two_factor_enabled?: boolean;
    last_login_at?: string | null;
    initials?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    roles?: RoleSummary[];
    [key: string]: unknown;
}

export interface PaginatedResponse<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}
