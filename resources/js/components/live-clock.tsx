import { useEffect, useState } from 'react';

export function LiveClock({ className }: { className?: string }) {
    const [now, setNow] = useState<Date | null>(null);

    useEffect(() => {
        setNow(new Date());
        const id = setInterval(() => setNow(new Date()), 1000);
        return () => clearInterval(id);
    }, []);

    if (!now) return null;
    const time = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    const date = now.toLocaleDateString([], { weekday: 'long', month: 'short', day: 'numeric' });

    return (
        <div className={className}>
            <p className="font-display text-2xl font-bold tracking-tight tabular-nums">{time}</p>
            <p className="text-[11px] font-semibold tracking-[0.18em] uppercase opacity-80">{date}</p>
        </div>
    );
}
