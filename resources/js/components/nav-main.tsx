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
                    <SidebarGroupLabel className="text-muted-foreground/70 mb-1 px-2 text-[10px] font-bold tracking-[0.14em] uppercase">
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
                                                ? 'shadow-soft-md bg-gradient-to-r from-blue-600 to-blue-700 font-semibold text-white hover:bg-gradient-to-r hover:from-blue-600 hover:to-blue-700 hover:text-white'
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
                                                            : 'bg-blue-50 text-blue-600 group-hover/item:bg-blue-100 dark:bg-blue-500/10 dark:text-blue-300 dark:group-hover/item:bg-blue-500/20',
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
