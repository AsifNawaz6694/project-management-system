import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { RoleBadge } from '@/components/role-badge';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
import AppLogo from './app-logo';

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;

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

                {auth.user && (
                    <div className="mt-auto px-3 pb-2 group-data-[collapsible=icon]:hidden">
                        <div className="from-violet-600 via-indigo-600 to-blue-600 shadow-soft-md relative overflow-hidden rounded-2xl bg-gradient-to-br p-4 text-white">
                            <div className="absolute -right-6 -top-6 size-20 rounded-full bg-white/15 blur-2xl" />
                            <div className="absolute right-2 top-2 rounded-full bg-white/15 p-1.5 ring-1 ring-white/25 backdrop-blur">
                                <Sparkles className="size-3.5" />
                            </div>
                            <p className="text-[10px] font-semibold uppercase tracking-[0.18em] text-white/70">Workspace</p>
                            <p className="font-display mt-1 text-base font-bold leading-tight">Need help?</p>
                            <p className="mt-1 text-[11px] text-white/80">Browse docs or reach support.</p>
                            <div className="mt-3 flex items-center gap-2">
                                <RoleBadge role={auth.user.primary_role} className="bg-white/20 text-white ring-white/30" />
                            </div>
                        </div>
                    </div>
                )}
            </SidebarContent>

            <SidebarFooter className="border-t border-sidebar-border/60 p-2">
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
