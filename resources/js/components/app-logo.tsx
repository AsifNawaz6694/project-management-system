import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import AppLogoIcon from './app-logo-icon';

export default function AppLogo() {
    const { name } = usePage<SharedData>().props;

    return (
        <>
            <div className="shadow-glow flex aspect-square size-10 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 to-blue-700 ring-1 ring-white/30">
                <AppLogoIcon className="size-5 fill-current text-white" />
            </div>
            <div className="ml-2 grid flex-1 text-left">
                <span className="font-display truncate text-[15px] leading-tight font-bold tracking-tight">{name || 'Raqtan TMS'}</span>
                <span className="text-muted-foreground truncate text-[11px]">Project workspace</span>
            </div>
        </>
    );
}
