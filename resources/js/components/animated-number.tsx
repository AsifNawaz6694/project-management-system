import { useEffect, useRef, useState } from 'react';

interface AnimatedNumberProps {
    value: number | string;
    duration?: number;
    formatter?: (n: number) => string;
    className?: string;
}

const easeOutCubic = (t: number) => 1 - Math.pow(1 - t, 3);

export function AnimatedNumber({ value, duration = 900, formatter, className }: AnimatedNumberProps) {
    const numericTarget = typeof value === 'number' ? value : parseFloat(String(value).replace(/[^\d.-]/g, ''));
    const isNumeric = !Number.isNaN(numericTarget) && Number.isFinite(numericTarget) && typeof value === 'number';

    const [display, setDisplay] = useState(isNumeric ? 0 : value);
    const startRef = useRef<number | null>(null);
    const fromRef = useRef(0);

    useEffect(() => {
        if (!isNumeric) {
            setDisplay(value);
            return;
        }
        if (typeof window === 'undefined' || typeof window.requestAnimationFrame !== 'function') {
            setDisplay(numericTarget);
            return;
        }

        const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
        if (reduceMotion) {
            setDisplay(numericTarget);
            return;
        }

        startRef.current = null;
        const initial = typeof display === 'number' ? display : 0;
        fromRef.current = initial;

        let frameId = 0;
        const step = (ts: number) => {
            if (startRef.current === null) startRef.current = ts;
            const progress = Math.min(1, (ts - startRef.current) / duration);
            const eased = easeOutCubic(progress);
            const next = fromRef.current + (numericTarget - fromRef.current) * eased;
            setDisplay(progress >= 1 ? numericTarget : next);
            if (progress < 1) frameId = requestAnimationFrame(step);
        };
        frameId = requestAnimationFrame(step);
        return () => cancelAnimationFrame(frameId);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [numericTarget, isNumeric, duration]);

    if (!isNumeric) return <span className={className}>{value}</span>;

    const rounded = Number.isInteger(numericTarget) ? Math.round(display as number) : Math.round((display as number) * 100) / 100;
    return <span className={className}>{formatter ? formatter(rounded) : rounded.toLocaleString()}</span>;
}
