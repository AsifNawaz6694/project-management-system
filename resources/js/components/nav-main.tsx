import { Icon } from '@/components/icon';
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { buildSidebarSections } from '@/lib/navigation';
import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';

export function NavMain() {
    const page = usePage<SharedData>();
    const sections = buildSidebarSections(page.props.auth.user);

    return (
        <>
            {sections.map((section) => (
                <SidebarGroup key={section.label} className="px-3 py-1.5">
                    <SidebarGroupLabel className="text-muted-foreground/70 mb-1 px-2 text-[10px] font-bold uppercase tracking-[0.14em]">
                        {section.label}
                    </SidebarGroupLabel>
                    <SidebarMenu className="gap-1">
                        {section.items.map((item) => {
                            const isActive = page.url === item.url || page.url.startsWith(item.url + '/');
                            return (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton
                                        asChild
                                        isActive={isActive}
                                        className={cn(
                                            'group/item relative h-11 overflow-hidden rounded-xl px-3 transition-all duration-200',
                                            isActive
                                                ? 'shadow-soft-md bg-gradient-to-r from-violet-600 via-indigo-600 to-blue-600 font-semibold text-white hover:bg-gradient-to-r hover:from-violet-600 hover:to-blue-600 hover:text-white'
                                                : 'hover:bg-sidebar-accent text-sidebar-foreground hover:text-sidebar-accent-foreground',
                                        )}
                                    >
                                        <Link href={item.url} prefetch>
                                            {item.icon && (
                                                <span
                                                    className={cn(
                                                        'flex size-7 items-center justify-center rounded-lg transition-all',
                                                        isActive
                                                            ? 'bg-white/20 text-white'
                                                            : 'text-violet-600 dark:text-violet-300 bg-violet-50 dark:bg-violet-500/10 group-hover/item:bg-violet-100 dark:group-hover/item:bg-violet-500/20',
                                                    )}
                                                >
                                                    <Icon iconNode={item.icon} className="size-4" />
                                                </span>
                                            )}
                                            <span className="text-[13px]">{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            );
                        })}
                    </SidebarMenu>
                </SidebarGroup>
            ))}
        </>
    );
}
