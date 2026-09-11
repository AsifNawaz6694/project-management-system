import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { type BreadcrumbItem } from '@/types';

export default function AppSidebarLayout({ children, breadcrumbs = [] }: { children: React.ReactNode; breadcrumbs?: BreadcrumbItem[] }) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="bg-transparent">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <div className="scrollbar-soft min-w-0 flex-1 overflow-x-hidden overflow-y-auto">{children}</div>
            </AppContent>
        </AppShell>
    );
}
