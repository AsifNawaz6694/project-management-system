import { Breadcrumbs } from '@/components/breadcrumbs';
import { CommandPalette, useCommandPalette } from '@/components/command-palette';
import { NotificationBell } from '@/components/notification-bell';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useAppearance } from '@/hooks/use-appearance';
import { type BreadcrumbItem } from '@/types';
import { Command, Moon, Search, Sun } from 'lucide-react';

/**
 * Two groups on one row: the fold control and the trail on the left, the
 * controls on the right. The left group is the only one allowed to shrink
 * (`min-w-0` + a truncating trail), so a long breadcrumb can never push the
 * controls out of the header and over the sidebar. Every control keeps its
 * place at every width — the search field is the one thing that degrades, to
 * an icon, once there is no room for the full field.
 */
export function AppSidebarHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItem[] }) {
    const { open, setOpen } = useCommandPalette();
    const { appearance, updateAppearance } = useAppearance();

    const dark = appearance === 'dark';

    return (
        <header className="bg-background/70 border-border/60 sticky top-0 z-30 flex h-16 w-full min-w-0 shrink-0 items-center gap-2 border-b px-3 backdrop-blur-xl sm:h-[68px] sm:gap-3 sm:px-4 lg:px-6">
            <div className="flex min-w-0 flex-1 items-center gap-2 sm:gap-3">
                <SidebarTrigger className="ring-border/70 shadow-soft-xs hover:shadow-soft-sm bg-card text-muted-foreground hover:text-foreground size-9 shrink-0 rounded-xl ring-1 transition-all" />
                <div className="hidden min-w-0 flex-1 md:block">
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </div>
            </div>

            <div className="flex shrink-0 items-center gap-1.5 sm:gap-2">
                <button
                    type="button"
                    onClick={() => setOpen(true)}
                    className="bg-card ring-border/70 shadow-soft-xs hover:shadow-soft-sm hover:ring-foreground/20 text-muted-foreground hidden h-10 w-[220px] items-center gap-2.5 rounded-xl px-3 text-sm font-medium ring-1 transition-all lg:inline-flex xl:w-[260px]"
                >
                    <Search className="size-4 shrink-0" />
                    <span className="truncate">Search anything…</span>
                    <span className="border-border/70 bg-muted ml-auto inline-flex shrink-0 items-center gap-1 rounded-md border px-1.5 py-0.5 text-[10px] font-semibold">
                        <Command className="size-2.5" /> K
                    </span>
                </button>

                <Button
                    variant="outline"
                    size="icon"
                    onClick={() => setOpen(true)}
                    aria-label="Search"
                    className="size-9 rounded-xl sm:size-10 lg:hidden"
                >
                    <Search className="size-4" />
                </Button>

                <NotificationBell />

                <Button
                    variant="outline"
                    size="icon"
                    onClick={() => updateAppearance(dark ? 'light' : 'dark')}
                    aria-label={dark ? 'Switch to light mode' : 'Switch to dark mode'}
                    className="size-9 rounded-xl sm:size-10"
                >
                    {dark ? <Sun className="size-4" /> : <Moon className="size-4" />}
                </Button>
            </div>

            <CommandPalette open={open} onOpenChange={setOpen} />
        </header>
    );
}
