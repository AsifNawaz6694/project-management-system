import { PageHeader } from '@/components/page-header';
import { UserForm, type UserFormDepartment, type UserFormRole } from '@/components/user-form';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type RoleSummary, type User } from '@/types';
import { Head } from '@inertiajs/react';

interface UsersEditProps {
    user: User & { roles: RoleSummary[] };
    roles: UserFormRole[];
    departments: UserFormDepartment[];
}

export default function UsersEdit({ user, roles, departments }: UsersEditProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Workspace', href: '/dashboard' },
        { title: 'Users', href: '/users' },
        { title: user.name, href: route('users.show', user.id) },
        { title: 'Edit', href: route('users.edit', user.id) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${user.name}`} />
            <div className="flex w-full flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="User management"
                    title={`Edit ${user.name}`}
                    description="Update profile, roles, status, and security settings."
                />
                <UserForm
                    initial={{
                        name: user.name,
                        email: user.email,
                        phone: user.phone ?? '',
                        job_title: user.job_title ?? '',
                        department_id: user.department_id ? String(user.department_id) : '',
                        status: (user.status as 'active' | 'invited' | 'suspended') ?? 'active',
                        two_factor_enabled: !!user.two_factor_enabled,
                        roles: user.roles?.map((r) => r.slug) ?? [],
                        password: '',
                    }}
                    roles={roles}
                    departments={departments}
                    submitUrl={route('users.update', user.id)}
                    submitMethod="patch"
                    submitLabel="Save changes"
                    cancelUrl={route('users.show', user.id)}
                />
            </div>
        </AppLayout>
    );
}
