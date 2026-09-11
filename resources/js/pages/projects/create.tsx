import { PageHeader } from '@/components/page-header';
import { ProjectForm, type AssignableUser } from '@/components/project-form';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Workspace', href: '/dashboard' },
    { title: 'Projects', href: '/projects' },
    { title: 'New', href: '/projects/create' },
];

interface ProjectsCreateProps {
    users: AssignableUser[];
    statuses: string[];
    priorities: string[];
    colors: string[];
}

export default function ProjectsCreate({ users, statuses, priorities, colors }: ProjectsCreateProps) {
    const { user } = usePermissions();

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New project" />
            <div className="flex w-full flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Project management"
                    title="Create a new project"
                    description="Set the basics, pick an owner, invite members, and stake out the roadmap."
                />
                <ProjectForm
                    initial={{
                        title: '',
                        description: '',
                        status: 'planning',
                        priority: 'medium',
                        color: 'violet',
                        start_date: '',
                        end_date: '',
                        progress: 0,
                        owner_id: user?.id ?? null,
                        member_ids: user?.id ? [user.id] : [],
                        milestones: [],
                    }}
                    users={users}
                    statuses={statuses}
                    priorities={priorities}
                    colors={colors}
                    submitUrl={route('projects.store')}
                    submitMethod="post"
                    submitLabel="Create project"
                    cancelUrl={route('projects.index')}
                />
            </div>
        </AppLayout>
    );
}
