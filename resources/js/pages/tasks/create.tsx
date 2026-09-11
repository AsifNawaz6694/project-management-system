import { PageHeader } from '@/components/page-header';
import { TaskForm, type AssigneeOption, type ProjectOption, type SimpleOption } from '@/components/task-form';
import AppLayout from '@/layouts/app-layout';
import { type TaskPriority, type WorkflowStatus } from '@/lib/tasks';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Workspace', href: '/dashboard' },
    { title: 'Tasks', href: '/tasks' },
    { title: 'New', href: '/tasks/create' },
];

interface TasksCreateProps {
    projects: ProjectOption[];
    assignees: AssigneeOption[];
    teams: SimpleOption[];
    labels: SimpleOption[];
    types: SimpleOption[];
    statuses: WorkflowStatus[];
    priorities: string[];
    preselect_project_id?: number | null;
}

export default function TasksCreate({ projects, assignees, teams, labels, types, statuses, priorities, preselect_project_id }: TasksCreateProps) {
    const params = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : null;

    // Fall back to the workflow's initial status rather than a hardcoded key.
    const initialStatus = params?.get('status') ?? statuses.find((s) => s.is_initial)?.key ?? statuses[0]?.key ?? 'todo';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New task" />
            <div className="flex w-full flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Task management"
                    title="Create a new task"
                    description="Pick the project, set type, priority and owner, and break it into subtasks if it helps."
                />
                <TaskForm
                    enableAttachments
                    initial={{
                        project_id: preselect_project_id ?? projects[0]?.id ?? null,
                        title: '',
                        description: '',
                        status: initialStatus,
                        priority: 'medium' as TaskPriority,
                        due_date: '',
                        start_date: '',
                        estimate_hm: '',
                        assignee_id: null,
                        team_id: null,
                        task_type_id: types[0]?.id ?? null,
                        labels: [],
                        subtasks: [],
                    }}
                    projects={projects}
                    assignees={assignees}
                    teams={teams}
                    labelOptions={labels}
                    types={types}
                    statuses={statuses}
                    priorities={priorities}
                    submitUrl={route('tasks.store')}
                    submitMethod="post"
                    submitLabel="Create task"
                    cancelUrl={route('tasks.index')}
                />
            </div>
        </AppLayout>
    );
}
