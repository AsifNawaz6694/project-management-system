import { PageHeader } from '@/components/page-header';
import { UserForm, type UserFormRole } from '@/components/user-form';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type RoleSummary, type User } from '@/types';
import { Head } from '@inertiajs/react';

interface UsersEditProps {
    user: User & { roles: RoleSummary[] };
    roles: UserFormRole[];
}

export default function UsersEdit({ user, roles }: UsersEditProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Workspace', href: '/dashboard' },
        { title: 'Users', href: '/users' },
        { title: user.name, href: route('users.show', user.id) },
        { title: 'Edit', href: route('users.edit', user.id) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${user.name}`} />
            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-8">
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
                        department: user.department ?? '',
                        status: (user.status as 'active' | 'invited' | 'suspended') ?? 'active',
                        two_factor_enabled: !!user.two_factor_enabled,
                        roles: user.roles?.map((r) => r.slug) ?? [],
                        password: '',
                    }}
                    roles={roles}
                    submitUrl={route('users.update', user.id)}
                    submitMethod="patch"
                    submitLabel="Save changes"
                    cancelUrl={route('users.show', user.id)}
                />
            </div>
        </AppLayout>
    );
}
