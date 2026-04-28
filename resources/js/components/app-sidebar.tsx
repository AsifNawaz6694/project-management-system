import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { Link } from '@inertiajs/react';
import AppLogo from './app-logo';

export function AppSidebar() {
    return (
        <Sidebar
            collapsible="icon"
            variant="inset"
            className="bg-sidebar shadow-soft-md group-data-[variant=inset]:m-3 group-data-[variant=inset]:rounded-2xl group-data-[variant=inset]:border group-data-[variant=inset]:border-sidebar-border/60"
        >
            <SidebarHeader className="px-3 pb-3 pt-4">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild className="h-auto rounded-xl py-2 hover:bg-transparent">
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="scrollbar-soft gap-2 py-2">
                <NavMain />
            </SidebarContent>

            <SidebarFooter className="border-t border-sidebar-border/60 p-2">
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
