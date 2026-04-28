import { Breadcrumbs } from '@/components/breadcrumbs';
import { NotificationBell } from '@/components/notification-bell';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { type BreadcrumbItem } from '@/types';
import { Command, Search, Sun } from 'lucide-react';

export function AppSidebarHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItem[] }) {
    return (
        <header className="bg-background/70 sticky top-0 z-30 flex h-[68px] shrink-0 items-center gap-3 border-b border-border/60 px-4 backdrop-blur-xl md:px-6">
            <div className="flex flex-1 items-center gap-3">
                <SidebarTrigger className="ring-1 ring-border/70 shadow-soft-xs hover:shadow-soft-sm bg-card text-muted-foreground hover:text-foreground size-9 rounded-xl transition-all" />
                <div className="hidden md:block">
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </div>
            </div>

            <div className="flex items-center gap-2">
                <button className="bg-card ring-1 ring-border/70 shadow-soft-xs hover:shadow-soft-sm hover:ring-foreground/20 hidden h-10 min-w-[260px] items-center gap-2.5 rounded-xl px-3 text-sm font-medium text-muted-foreground transition-all md:inline-flex">
                    <Search className="size-4" />
                    <span>Search anything…</span>
                    <span className="ml-auto inline-flex items-center gap-1 rounded-md border border-border/70 bg-muted px-1.5 py-0.5 text-[10px] font-semibold">
                        <Command className="size-2.5" /> K
                    </span>
                </button>
                <Button variant="outline" size="icon" className="size-10 rounded-xl md:hidden">
                    <Search className="size-4" />
                </Button>
                <NotificationBell />
                <Button variant="outline" size="icon" className="hidden size-10 rounded-xl md:inline-flex">
                    <Sun className="size-4" />
                </Button>
            </div>
        </header>
    );
}
