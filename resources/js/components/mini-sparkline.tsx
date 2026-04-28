import { cn } from '@/lib/utils';

interface MiniSparklineProps {
    points: number[];
    tone?: 'violet' | 'emerald' | 'amber' | 'rose' | 'blue' | 'slate' | 'sky' | 'pink';
    className?: string;
    height?: number;
}

const TONE: Record<NonNullable<MiniSparklineProps['tone']>, string> = {
    violet: '#7c3aed',
    emerald: '#10b981',
    amber: '#f59e0b',
    rose: '#f43f5e',
    blue: '#3b82f6',
    slate: '#64748b',
    sky: '#0ea5e9',
    pink: '#ec4899',
};

export function MiniSparkline({ points, tone = 'violet', className, height = 36 }: MiniSparklineProps) {
    const data = points.length === 0 ? [0, 0] : points;
    const w = 120;
    const h = height;
    const max = Math.max(...data, 1);
    const min = Math.min(...data);
    const range = Math.max(1, max - min);
    const xFor = (i: number) => (i / Math.max(1, data.length - 1)) * w;
    const yFor = (v: number) => h - ((v - min) / range) * (h - 4) - 2;
    const line = data.map((v, i) => `${xFor(i)},${yFor(v)}`).join(' ');
    const area = `${xFor(0)},${h} ${line} ${xFor(data.length - 1)},${h}`;
    const lastX = xFor(data.length - 1);
    const lastY = yFor(data[data.length - 1]);
    const color = TONE[tone];
    const lineLen = w + h;

    return (
        <svg viewBox={`0 0 ${w} ${h}`} className={cn('w-full', className)} preserveAspectRatio="none">
            <defs>
                <linearGradient id={`spark-${tone}`} x1="0" x2="0" y1="0" y2="1">
                    <stop offset="0%" stopColor={color} stopOpacity="0.4" />
                    <stop offset="100%" stopColor={color} stopOpacity="0" />
                </linearGradient>
            </defs>
            <polygon points={area} fill={`url(#spark-${tone})`} style={{ opacity: 0, animation: 'fade-in-up 0.7s cubic-bezier(0.22, 1, 0.36, 1) forwards', animationDelay: '300ms' }} />
            <polyline
                points={line}
                fill="none"
                stroke={color}
                strokeWidth={1.75}
                strokeLinecap="round"
                strokeLinejoin="round"
                style={{
                    strokeDasharray: lineLen,
                    strokeDashoffset: lineLen,
                    animation: 'draw-stroke 1.1s cubic-bezier(0.22, 1, 0.36, 1) forwards',
                    ['--dash-len' as string]: lineLen,
                }}
            />
            <circle cx={lastX} cy={lastY} r={2.5} fill={color} style={{ opacity: 0, animation: 'pop-in 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) forwards', animationDelay: '900ms' }} />
            <circle cx={lastX} cy={lastY} r={5} fill={color} opacity="0.35" className="animate-pulse-ring" />
        </svg>
    );
}
