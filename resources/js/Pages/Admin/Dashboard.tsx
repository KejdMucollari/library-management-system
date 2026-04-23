import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, useForm } from '@inertiajs/react';

type AiResult = {
    columns: string[];
    rows: Record<string, unknown>[];
    summary: string;
    debug?: { spec?: unknown };
};

export default function AdminDashboard({
    stats,
    ai,
    insights,
}: {
    stats: { totalBooks: number; totalUsers: number; mostPopularGenre: string };
    ai: { result: AiResult | null };
    insights: {
        completed: number;
        reading: number;
        planToRead: number;
        completionRate: number;
    };
}) {
    const form = useForm<{ question: string }>({
        question: '',
    });

    const result = ai.result;
    const hasAiResult = result != null;
    const isEmptyAiResult =
        hasAiResult &&
        result.columns.length === 0 &&
        result.rows.length === 0 &&
        result.summary.trim().length > 0;
    const hasAiTableRows = hasAiResult && result.rows.length > 0;
    return (
        <AuthenticatedLayout
            header={
                <h2 className="font-display text-2xl font-semibold leading-tight tracking-tight text-[#f5f0e8]">
                    Dashboard
                </h2>
            }
        >
            <Head title="Admin Dashboard" />

            <div className="py-10 sm:py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <div className="grid gap-4 md:grid-cols-3">
                        <div className="library-glass-panel p-6">
                            <div className="text-sm text-[rgba(255,255,255,0.6)]">
                                Total books
                            </div>
                            <div className="library-stat-mega mt-1">{stats.totalBooks}</div>
                        </div>
                        <div className="library-glass-panel p-6">
                            <div className="text-sm text-[rgba(255,255,255,0.6)]">
                                Total users
                            </div>
                            <div className="library-stat-mega mt-1">{stats.totalUsers}</div>
                        </div>
                        <div className="library-glass-panel p-6">
                            <div className="text-sm text-[rgba(255,255,255,0.6)]">
                                Most popular genre
                            </div>
                            <div className="font-display mt-1 text-2xl font-semibold leading-snug text-[#c9a84c]">
                                {stats.mostPopularGenre}
                            </div>
                        </div>
                    </div>

                    <div className="library-glass-panel p-6">
                        <div className="font-display text-lg font-semibold text-[#f5f0e8]">
                            AI Query Agent
                        </div>
                        <div className="mt-1 text-sm text-[rgba(245,240,232,0.65)]">
                            Ask natural language questions about library data.
                        </div>

                        <form
                            className="mt-4 space-y-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.post(route('admin.ai.query'));
                            }}
                        >
                            <TextInput
                                className="block w-full"
                                value={form.data.question}
                                onChange={(e) => form.setData('question', e.target.value)}
                                placeholder="e.g. Who owns the most books?"
                            />
                            <InputError className="mt-2" message={form.errors.question} />
                            <PrimaryButton disabled={form.processing}>Run query</PrimaryButton>
                        </form>

                        {hasAiResult && (
                            <div className="mt-6">
                                <div className="font-display text-lg font-semibold text-[#f5f0e8]">
                                    Result
                                </div>

                                {isEmptyAiResult ? (
                                    <div
                                        className="mt-4 border border-white/10 backdrop-blur-xl"
                                        style={{
                                            borderLeft: '3px solid #c9a84c',
                                            background: 'rgba(201, 168, 76, 0.08)',
                                            padding: '12px 16px',
                                            borderRadius: '8px',
                                            color: 'rgba(255,255,255,0.8)',
                                            fontSize: '14px',
                                        }}
                                    >
                                        {result.summary}
                                    </div>
                                ) : hasAiTableRows ? (
                                    <>
                                        <div className="mt-1 text-sm text-[rgba(245,240,232,0.75)]">
                                            {result.summary}
                                        </div>
                                        <div className="library-table-wrap mt-4 overflow-x-auto rounded-xl border border-white/10">
                                            <table className="min-w-full">
                                                <thead>
                                                    <tr>
                                                        {result.columns.map((c) => (
                                                            <th
                                                                key={c}
                                                                className="bg-black/20 px-4 py-2 text-left"
                                                            >
                                                                {c}
                                                            </th>
                                                        ))}
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {result.rows.map((r, idx) => (
                                                        <tr key={idx}>
                                                            {result.columns.map((c) => (
                                                                <td
                                                                    key={c}
                                                                    className="px-4 py-2 text-sm"
                                                                >
                                                                    {String(
                                                                        (r as Record<string, unknown>)[c] ??
                                                                            '—',
                                                                    )}
                                                                </td>
                                                            ))}
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    </>
                                ) : null}

                                <details className="mt-4">
                                    <summary className="cursor-pointer text-sm text-[rgba(245,240,232,0.65)]">
                                        Debug (interpreted JSON spec)
                                    </summary>
                                    <pre className="mt-2 max-h-64 overflow-auto rounded-xl border border-white/10 bg-black/40 p-3 text-xs text-[#f5f0e8]">
                                        {JSON.stringify(result.debug?.spec ?? null, null, 2)}
                                    </pre>
                                </details>
                            </div>
                        )}
                    </div>

                    <div className="library-glass-panel p-6">
                        <div className="font-display text-lg font-semibold text-[#f5f0e8]">
                            Library Insights
                        </div>
                        <div className="mt-4 grid gap-3 md:grid-cols-4">
                            <div className="library-metric-nested p-4">
                                <div className="text-xs font-medium uppercase tracking-wide text-[rgba(255,255,255,0.6)]">
                                    Completed
                                </div>
                                <div className="library-stat-mega mt-1">{insights.completed}</div>
                            </div>
                            <div className="library-metric-nested p-4">
                                <div className="text-xs font-medium uppercase tracking-wide text-[rgba(255,255,255,0.6)]">
                                    Reading
                                </div>
                                <div className="library-stat-mega mt-1">{insights.reading}</div>
                            </div>
                            <div className="library-metric-nested p-4">
                                <div className="text-xs font-medium uppercase tracking-wide text-[rgba(255,255,255,0.6)]">
                                    Plan to read
                                </div>
                                <div className="library-stat-mega mt-1">{insights.planToRead}</div>
                            </div>
                            <div className="library-metric-nested p-4">
                                <div className="text-xs font-medium uppercase tracking-wide text-[rgba(255,255,255,0.6)]">
                                    Completion rate
                                </div>
                                <div className="library-stat-mega mt-1">{insights.completionRate}%</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
