import { PageHeader } from '@/components/page-header';
import { ProjectForm, type AssignableUser, type ProjectFormState } from '@/components/project-form';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

interface ProjectsEditProps {
    project: ProjectFormState & {
        id: number;
        slug: string;
        title: string;
        member_ids: number[];
        milestones: Array<{ title: string; description: string | null; due_date: string | null; completed_at: string | null }>;
    };
    users: AssignableUser[];
    statuses: string[];
    priorities: string[];
    colors: string[];
}

export default function ProjectsEdit({ project, users, statuses, priorities, colors }: ProjectsEditProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Workspace', href: '/dashboard' },
        { title: 'Projects', href: '/projects' },
        { title: project.title, href: route('projects.show', project.slug) },
        { title: 'Edit', href: route('projects.edit', project.slug) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${project.title}`} />
            <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-8">
                <PageHeader eyebrow="Project management" title={`Edit ${project.title}`} description="Update the plan, members, milestones and progress." />
                <ProjectForm
                    initial={{
                        title: project.title,
                        description: project.description ?? '',
                        status: project.status,
                        priority: project.priority,
                        color: project.color,
                        start_date: (project.start_date as unknown as string) ?? '',
                        end_date: (project.end_date as unknown as string) ?? '',
                        budget: project.budget ? String(project.budget) : '',
                        progress: project.progress ?? 0,
                        owner_id: project.owner_id ?? null,
                        member_ids: project.member_ids ?? [],
                        milestones: (project.milestones ?? []).map((m) => ({
                            title: m.title,
                            description: m.description ?? '',
                            due_date: m.due_date ?? '',
                            completed: !!m.completed_at,
                        })),
                    }}
                    users={users}
                    statuses={statuses}
                    priorities={priorities}
                    colors={colors}
                    submitUrl={route('projects.update', project.slug)}
                    submitMethod="patch"
                    submitLabel="Save changes"
                    cancelUrl={route('projects.show', project.slug)}
                />
            </div>
        </AppLayout>
    );
}
