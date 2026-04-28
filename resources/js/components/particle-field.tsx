import { useMemo } from 'react';

interface ParticleFieldProps {
    count?: number;
    className?: string;
}

export function ParticleField({ count = 22, className }: ParticleFieldProps) {
    const particles = useMemo(() =>
        Array.from({ length: count }).map((_, i) => ({
            id: i,
            cx: Math.random() * 100,
            cy: Math.random() * 100,
            r: Math.random() * 2 + 0.6,
            delay: Math.random() * 4,
            duration: 3 + Math.random() * 4,
            opacity: 0.3 + Math.random() * 0.5,
        })),
    [count]);

    return (
        <svg className={className} viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden>
            {particles.map((p) => (
                <circle
                    key={p.id}
                    cx={p.cx}
                    cy={p.cy}
                    r={p.r}
                    fill="white"
                    opacity={p.opacity}
                    style={{ animation: `sparkle ${p.duration}s ease-in-out infinite`, animationDelay: `${p.delay}s` }}
                />
            ))}
        </svg>
    );
}
