import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import AppLogoIcon from './app-logo-icon';

export default function AppLogo() {
    const { name } = usePage<SharedData>().props;

    return (
        <>
            <div className="from-violet-600 via-indigo-600 to-blue-600 shadow-glow flex aspect-square size-10 items-center justify-center rounded-2xl bg-gradient-to-br ring-1 ring-white/30">
                <AppLogoIcon className="size-5 fill-current text-white" />
            </div>
            <div className="ml-2 grid flex-1 text-left">
                <span className="font-display truncate text-[15px] font-bold leading-tight tracking-tight">{name || 'Raqtan'}</span>
                <span className="text-muted-foreground truncate text-[11px]">Project workspace</span>
            </div>
        </>
    );
}
