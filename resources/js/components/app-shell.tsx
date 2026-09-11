import { SidebarProvider } from '@/components/ui/sidebar';
import { useCallback, useEffect, useRef, useState } from 'react';

interface AppShellProps {
    children: React.ReactNode;
    variant?: 'header' | 'sidebar';
}

/**
 * Below this width the sidebar folds itself away so content — the task board in
 * particular — gets the full viewport. Above it, the user's own preference is
 * restored. The shadcn sidebar already swaps to an overlay sheet under 768px;
 * this covers the tablet and small-laptop range in between.
 */
const AUTO_FOLD_BELOW = 1280;

const STORAGE_KEY = 'sidebar';

function storedPreference(): boolean {
    if (typeof window === 'undefined') return true;
    return localStorage.getItem(STORAGE_KEY) !== 'false';
}

export function AppShell({ children, variant = 'header' }: AppShellProps) {
    const [isOpen, setIsOpen] = useState(storedPreference);

    // Distinguishes an automatic fold from a deliberate toggle, so resizing the
    // window never overwrites what the user chose.
    const auto = useRef(false);

    useEffect(() => {
        if (variant !== 'sidebar' || typeof window === 'undefined') return;

        const mql = window.matchMedia(`(max-width: ${AUTO_FOLD_BELOW - 1}px)`);

        const apply = (narrow: boolean) => {
            auto.current = true;
            setIsOpen(narrow ? false : storedPreference());
            // Release on the next tick so a real toggle is not misread as auto.
            requestAnimationFrame(() => {
                auto.current = false;
            });
        };

        apply(mql.matches);

        const onChange = (e: MediaQueryListEvent) => apply(e.matches);
        mql.addEventListener('change', onChange);

        return () => mql.removeEventListener('change', onChange);
    }, [variant]);

    const handleSidebarChange = useCallback((open: boolean) => {
        setIsOpen(open);

        // Only a deliberate toggle updates the remembered preference.
        if (!auto.current && typeof window !== 'undefined') {
            localStorage.setItem(STORAGE_KEY, String(open));
        }
    }, []);

    if (variant === 'header') {
        return <div className="flex min-h-screen w-full flex-col">{children}</div>;
    }

    return (
        <SidebarProvider defaultOpen={isOpen} open={isOpen} onOpenChange={handleSidebarChange}>
            {children}
        </SidebarProvider>
    );
}
