import { Head } from '@inertiajs/react';
import { useEffect } from 'react';

interface Row {
    key: string;
    title: string;
    project: string;
    status: string;
    priority: string;
    assignee: string;
    due_date: string | null;
    story_points: number | null;
    is_done: boolean;
}

interface Props {
    report: {
        generated_at: string;
        generated_by: string;
        filters: Record<string, unknown>;
        summary: { total: number; done: number; overdue: number; points: number; logged_hours: number };
        rows: Row[];
    };
}

/**
 * A print-ready report.
 *
 * Rendered without the app layout on purpose — no sidebar, no header, nothing
 * that would waste a page. The browser's own "Save as PDF" produces a better
 * document than a server-side renderer would, and it stays selectable.
 */
export default function PrintableReport({ report }: Props) {
    const { summary, rows } = report;

    // Offer the print dialog straight away; the page has no other purpose.
    useEffect(() => {
        const handle = setTimeout(() => window.print(), 400);
        return () => clearTimeout(handle);
    }, []);

    const activeFilters = Object.entries(report.filters).filter(([, v]) => v !== null && v !== '' && (!Array.isArray(v) || v.length > 0));

    return (
        <>
            <Head title="Task report" />

            <style>{`
                @page { size: A4 landscape; margin: 14mm; }
                @media print {
                    .no-print { display: none !important; }
                    body { background: #fff; }
                    thead { display: table-header-group; }
                    tr { break-inside: avoid; }
                }
            `}</style>

            <div className="mx-auto max-w-[1100px] bg-white p-8 text-[13px] text-slate-900">
                <header className="mb-6 flex items-start justify-between border-b border-slate-300 pb-4">
                    <div>
                        <h1 className="text-xl font-bold">Task report</h1>
                        <p className="mt-1 text-xs text-slate-500">
                            Generated {report.generated_at} by {report.generated_by}
                        </p>
                        {activeFilters.length > 0 && (
                            <p className="mt-1 text-xs text-slate-500">
                                Filters: {activeFilters.map(([k, v]) => `${k} = ${Array.isArray(v) ? v.join(', ') : String(v)}`).join(' · ')}
                            </p>
                        )}
                    </div>

                    <button
                        type="button"
                        onClick={() => window.print()}
                        className="no-print rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium hover:bg-slate-50"
                    >
                        Print / Save as PDF
                    </button>
                </header>

                <section className="mb-6 grid grid-cols-5 gap-3">
                    {[
                        { label: 'Total', value: summary.total },
                        { label: 'Done', value: summary.done },
                        { label: 'Overdue', value: summary.overdue },
                        { label: 'Story points', value: summary.points },
                        { label: 'Logged hours', value: summary.logged_hours },
                    ].map((tile) => (
                        <div key={tile.label} className="rounded-lg border border-slate-200 px-3 py-2">
                            <p className="text-[10px] font-bold tracking-wider text-slate-500 uppercase">{tile.label}</p>
                            <p className="text-lg font-bold tabular-nums">{tile.value}</p>
                        </div>
                    ))}
                </section>

                <table className="w-full border-collapse">
                    <thead>
                        <tr className="border-b-2 border-slate-300 text-left text-[10px] font-bold tracking-wider text-slate-500 uppercase">
                            <th className="py-2 pr-2">Key</th>
                            <th className="py-2 pr-2">Title</th>
                            <th className="py-2 pr-2">Project</th>
                            <th className="py-2 pr-2">Stage</th>
                            <th className="py-2 pr-2">Priority</th>
                            <th className="py-2 pr-2">Assignee</th>
                            <th className="py-2 pr-2">Due</th>
                            <th className="py-2 text-right">Pts</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr key={row.key} className="border-b border-slate-200">
                                <td className="py-1.5 pr-2 font-mono text-[11px] whitespace-nowrap">{row.key}</td>
                                <td className={`py-1.5 pr-2 ${row.is_done ? 'text-slate-400 line-through' : ''}`}>{row.title}</td>
                                <td className="py-1.5 pr-2 text-slate-600">{row.project}</td>
                                <td className="py-1.5 pr-2">{row.status}</td>
                                <td className="py-1.5 pr-2">{row.priority}</td>
                                <td className="py-1.5 pr-2 text-slate-600">{row.assignee}</td>
                                <td className="py-1.5 pr-2 whitespace-nowrap">{row.due_date ?? '—'}</td>
                                <td className="py-1.5 text-right tabular-nums">{row.story_points ?? '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                {rows.length === 0 && <p className="py-10 text-center text-slate-500">Nothing matches the current filters.</p>}

                <footer className="mt-6 border-t border-slate-200 pt-3 text-[10px] text-slate-400">
                    {rows.length} row{rows.length === 1 ? '' : 's'} · Raqtan TMS
                </footer>
            </div>
        </>
    );
}
