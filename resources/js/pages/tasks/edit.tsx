import { PageHeader } from '@/components/page-header';
import { TaskForm, minutesToHm, type AssigneeOption, type ProjectOption } from '@/components/task-form';
import AppLayout from '@/layouts/app-layout';
import { type TaskPriority, type TaskStatus } from '@/lib/tasks';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

interface TasksEditProps {
    task: {
        id: number;
        project_id: number;
        title: string;
        description: string | null;
        status: TaskStatus;
        priority: TaskPriority;
        due_date: string | null;
        estimate_minutes: number | null;
        assignee_id: number | null;
        subtasks: Array<{ title: string; description: string | null; due_date: string | null; completed: boolean; assignee_id: number | null }>;
    };
    projects: ProjectOption[];
    assignees: AssigneeOption[];
    statuses: string[];
    priorities: string[];
}

export default function TasksEdit({ task, projects, assignees, statuses, priorities }: TasksEditProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Workspace', href: '/dashboard' },
        { title: 'Tasks', href: '/tasks' },
        { title: task.title, href: route('tasks.show', task.id) },
        { title: 'Edit', href: route('tasks.edit', task.id) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${task.title}`} />
            <div className="flex w-full flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader eyebrow="Task management" title={`Edit ${task.title}`} description="Tweak the title, status, priority, assignee, or breakdown." />
                <TaskForm
                    initial={{
                        project_id: task.project_id,
                        title: task.title,
                        description: task.description ?? '',
                        status: task.status,
                        priority: task.priority,
                        due_date: task.due_date ?? '',
                        estimate_hm: minutesToHm(task.estimate_minutes),
                        assignee_id: task.assignee_id,
                        subtasks: (task.subtasks ?? []).map((s) => ({
                            title: s.title,
                            description: s.description ?? '',
                            due_date: s.due_date ?? '',
                            completed: !!s.completed,
                            assignee_id: s.assignee_id,
                        })),
                    }}
                    projects={projects}
                    assignees={assignees}
                    statuses={statuses}
                    priorities={priorities}
                    submitUrl={route('tasks.update', task.id)}
                    submitMethod="patch"
                    submitLabel="Save changes"
                    cancelUrl={route('tasks.show', task.id)}
                />
            </div>
        </AppLayout>
    );
}
