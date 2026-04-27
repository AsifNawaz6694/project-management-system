import { PageHeader } from '@/components/page-header';
import { UserForm, type UserFormRole } from '@/components/user-form';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Workspace', href: '/dashboard' },
    { title: 'Users', href: '/users' },
    { title: 'Invite', href: '/users/create' },
];

export default function UsersCreate({ roles }: { roles: UserFormRole[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Invite user" />
            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-8">
                <PageHeader
                    eyebrow="User management"
                    title="Invite a new user"
                    description="Create the account, assign roles, and they'll receive a sign-in code by email."
                />
                <UserForm
                    initial={{
                        name: '',
                        email: '',
                        phone: '',
                        job_title: '',
                        department: '',
                        status: 'active',
                        two_factor_enabled: true,
                        roles: ['employee'],
                        password: '',
                    }}
                    roles={roles}
                    submitUrl={route('users.store')}
                    submitMethod="post"
                    submitLabel="Create user"
                    cancelUrl={route('users.index')}
                    requirePassword
                />
            </div>
        </AppLayout>
    );
}
