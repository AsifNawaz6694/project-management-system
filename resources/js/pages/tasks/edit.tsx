import { PageHeader } from '@/components/page-header';
import { TaskForm, minutesToHm, type AssigneeOption, type ProjectOption, type SimpleOption } from '@/components/task-form';
import AppLayout from '@/layouts/app-layout';
import { type TaskPriority, type TaskStatus, type WorkflowStatus } from '@/lib/tasks';
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
        start_date: string | null;
        estimate_minutes: number | null;
        assignee_id: number | null;
        team_id: number | null;
        task_type_id: number | null;
        label_ids: number[];
        subtasks: Array<{
            id: number;
            title: string;
            description: string | null;
            due_date: string | null;
            completed: boolean;
            assignee_id: number | null;
        }>;
    };
    projects: ProjectOption[];
    assignees: AssigneeOption[];
    teams: SimpleOption[];
    labels: SimpleOption[];
    types: SimpleOption[];
    statuses: WorkflowStatus[];
    priorities: string[];
}

export default function TasksEdit({ task, projects, assignees, teams, labels, types, statuses, priorities }: TasksEditProps) {
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
                <PageHeader
                    eyebrow="Task management"
                    title={`Edit ${task.title}`}
                    description="Tweak the title, status, priority, owner, labels or breakdown."
                />
                <TaskForm
                    initial={{
                        project_id: task.project_id,
                        title: task.title,
                        description: task.description ?? '',
                        status: task.status,
                        priority: task.priority,
                        due_date: task.due_date ?? '',
                        start_date: task.start_date ?? '',
                        estimate_hm: minutesToHm(task.estimate_minutes),
                        assignee_id: task.assignee_id,
                        team_id: task.team_id,
                        task_type_id: task.task_type_id,
                        labels: task.label_ids ?? [],
                        // Carrying the id through is what lets the server update
                        // subtasks in place instead of recreating them.
                        subtasks: (task.subtasks ?? []).map((s) => ({
                            id: s.id,
                            title: s.title,
                            description: s.description ?? '',
                            due_date: s.due_date ?? '',
                            completed: !!s.completed,
                            assignee_id: s.assignee_id,
                        })),
                    }}
                    projects={projects}
                    assignees={assignees}
                    teams={teams}
                    labelOptions={labels}
                    types={types}
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
