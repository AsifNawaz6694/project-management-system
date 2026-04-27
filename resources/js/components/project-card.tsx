import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import { COLOR_GRADIENT, formatDate, PRIORITY_META, STATUS_META, type ProjectColor, type ProjectPriority, type ProjectStatus } from '@/lib/projects';
import { Link } from '@inertiajs/react';
import { CalendarDays, CircleCheckBig, Users } from 'lucide-react';

export interface ProjectCardData {
    id: number;
    slug: string;
    title: string;
    description: string | null;
    status: ProjectStatus;
    priority: ProjectPriority;
    color: ProjectColor;
    progress: number;
    end_date: string | null;
    members_count?: number;
    milestones_count?: number;
    members?: { id: number; name: string; avatar?: string | null }[];
}

interface ProjectCardProps {
    project: ProjectCardData;
}

export function ProjectCard({ project }: ProjectCardProps) {
    const getInitials = useInitials();
    const status = STATUS_META[project.status] ?? STATUS_META.planning;
    const priority = PRIORITY_META[project.priority] ?? PRIORITY_META.medium;
    const gradient = COLOR_GRADIENT[project.color] ?? COLOR_GRADIENT.violet;
    const visible = (project.members ?? []).slice(0, 4);
    const overflow = (project.members_count ?? project.members?.length ?? 0) - visible.length;

    return (
        <Link
            href={route('projects.show', project.slug)}
            className="group bg-card shadow-soft-sm hover:shadow-soft-xl ring-border/60 hover:ring-foreground/20 ring-1 relative flex flex-col overflow-hidden rounded-2xl transition-all duration-300 hover:-translate-y-1"
        >
            <div className={cn('relative h-28 overflow-hidden bg-gradient-to-br', gradient)}>
                <div className="absolute inset-0 bg-[radial-gradient(circle_at_30%_30%,rgba(255,255,255,0.35),transparent)]" />
                <div className="absolute -bottom-10 -right-10 size-40 rounded-full bg-white/15 blur-2xl" />
                <div className="relative flex items-start justify-between p-4">
                    <span
                        className={cn(
                            'inline-flex items-center gap-1.5 rounded-full bg-white/20 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-[0.14em] text-white ring-1 ring-white/30 backdrop-blur',
                        )}
                    >
                        <span className={cn('size-1.5 rounded-full', status.dot)} /> {status.label}
                    </span>
                    <span
                        className={cn(
                            'inline-flex items-center rounded-full bg-white/20 px-2 py-0.5 text-[10px] font-semibold text-white ring-1 ring-white/30 backdrop-blur',
                        )}
                    >
                        {priority.label}
                    </span>
                </div>
            </div>

            <div className="-mt-6 flex flex-1 flex-col gap-3 p-5 pt-0">
                <div className="bg-card ring-card flex size-12 items-center justify-center rounded-2xl ring-4 shadow-soft-sm">
                    <div className={cn('flex size-9 items-center justify-center rounded-xl bg-gradient-to-br text-sm font-bold text-white shadow-soft-xs', gradient)}>
                        {project.title.slice(0, 2).toUpperCase()}
                    </div>
                </div>

                <div className="space-y-1">
                    <h3 className="font-display line-clamp-1 text-base font-bold tracking-tight">{project.title}</h3>
                    {project.description && (
                        <p className="text-muted-foreground line-clamp-2 text-xs leading-relaxed">{project.description}</p>
                    )}
                </div>

                <div className="space-y-1.5">
                    <div className="flex items-center justify-between text-[11px]">
                        <span className="text-muted-foreground font-semibold uppercase tracking-[0.12em]">Progress</span>
                        <span className="font-bold tabular-nums">{project.progress}%</span>
                    </div>
                    <div className="bg-muted h-2 overflow-hidden rounded-full">
                        <div
                            className={cn('h-full rounded-full bg-gradient-to-r transition-[width] duration-500', gradient)}
                            style={{ width: `${Math.max(2, project.progress)}%` }}
                        />
                    </div>
                </div>

                <div className="text-muted-foreground flex items-center gap-3 pt-1 text-[11px] font-medium">
                    {project.end_date && (
                        <span className="inline-flex items-center gap-1">
                            <CalendarDays className="size-3" />
                            {formatDate(project.end_date)}
                        </span>
                    )}
                    {(project.milestones_count ?? 0) > 0 && (
                        <span className="inline-flex items-center gap-1">
                            <CircleCheckBig className="size-3" />
                            {project.milestones_count} milestones
                        </span>
                    )}
                    <span className="ml-auto inline-flex items-center gap-1">
                        <Users className="size-3" />
                        {project.members_count ?? project.members?.length ?? 0}
                    </span>
                </div>

                {visible.length > 0 && (
                    <div className="border-border/60 mt-1 flex items-center justify-between border-t pt-3">
                        <div className="flex -space-x-2">
                            {visible.map((m) => (
                                <div
                                    key={m.id}
                                    className="ring-card flex size-7 items-center justify-center rounded-full bg-gradient-to-br from-violet-500 to-indigo-600 text-[10px] font-bold text-white ring-2"
                                    title={m.name}
                                >
                                    {getInitials(m.name)}
                                </div>
                            ))}
                            {overflow > 0 && (
                                <div className="ring-card bg-muted text-muted-foreground inline-flex size-7 items-center justify-center rounded-full text-[10px] font-bold ring-2">
                                    +{overflow}
                                </div>
                            )}
                        </div>
                        <span className="text-[10px] font-semibold uppercase tracking-[0.14em] text-muted-foreground transition-colors group-hover:text-foreground">
                            View →
                        </span>
                    </div>
                )}
            </div>
        </Link>
    );
}
