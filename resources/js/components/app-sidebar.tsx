import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { Link } from '@inertiajs/react';
import AppLogo from './app-logo';

export function AppSidebar() {
    // Padding, not margin. The layout only reserves `--sidebar-width` for the
    // sidebar, so a margin pushed the fixed panel that far past its column and
    // slid it under the header — the card has to breathe *inside* the column,
    // and the same margin overflowed `h-svh` past the bottom of the viewport.
    // Collapsed to icons it keeps the stock p-2 so the 3rem rail still fits an
    // icon button.
    return (
        <Sidebar
            collapsible="icon"
            variant="inset"
            className="[&_[data-sidebar=sidebar]]:border-sidebar-border/60 [&_[data-sidebar=sidebar]]:shadow-soft-md group-data-[state=expanded]:p-3 [&_[data-sidebar=sidebar]]:rounded-2xl [&_[data-sidebar=sidebar]]:border"
        >
            <SidebarHeader className="px-3 pt-4 pb-3">
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

            <SidebarFooter className="border-sidebar-border/60 border-t p-2">
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
