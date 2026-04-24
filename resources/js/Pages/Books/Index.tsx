import LibraryGlassSelect from '@/Components/LibraryGlassSelect';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type BookRow = {
    id: number;
    title: string;
    author: string | null;
    genre: { id: number; name: string } | null;
    status: string;
    pages: number | null;
    price: string | null;
    user?: { id: number; name: string; email: string };
};

type Paginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    meta?: unknown;
};

type AiResult = {
    columns: string[];
    rows: Record<string, unknown>[];
    summary: string;
    debug?: { spec?: unknown };
};

type Recommendation = {
    title: string;
    author: string;
    genre: string;
    reason: string;
};

type RecommendationHistoryRow = {
    title: string;
    author: string;
    genre: string;
    created_at: string;
};

const pillActive =
    'border border-[rgba(201,168,76,0.45)] bg-gradient-to-r from-[#8f6f2a] via-[#c9a84c] to-[#d4b76a] text-stone-900 shadow-sm';
const pillIdle =
    'border border-white/10 bg-black/20 text-[rgba(245,240,232,0.88)] hover:border-white/20 hover:bg-black/30';

export default function BooksIndex({
    books,
    filters,
    statusOptions,
    genres,
    stats,
    genreBreakdown,
    insights,
    isAdmin,
    ai,
    recommendations,
}: {
    books: Paginated<BookRow>;
    filters: { q: string; genre_id: string | number; status: string };
    statusOptions: string[];
    genres: { id: number; name: string }[];
    stats?: {
        totalBooks: number;
        reading: number;
        completed: number;
        favouriteGenre: string;
    };
    genreBreakdown?: { id: number; name: string; count: number; percent: number }[];
    insights?: { title: string; body: string }[];
    isAdmin: boolean;
    ai: { result: AiResult | null };
    recommendations: { recommendations: Recommendation[]; message: string | null } | null;
}) {
    const submitFilters = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            isAdmin ? route('admin.books.index') : route('books.index'),
            { ...filters },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const statusPills: { label: string; value: string }[] = [
        { label: 'All', value: '' },
        { label: 'Reading', value: 'reading' },
        { label: 'Completed', value: 'completed' },
        { label: 'Paused', value: 'paused' },
        { label: 'Plan to read', value: 'plan_to_read' },
    ];

    const genreFilterOptions = [
        { value: '', label: 'All' },
        ...genres.map((g) => ({ value: String(g.id), label: g.name })),
    ];

    if (!isAdmin) {
        const form = useForm<{ question: string }>({ question: '' });
        const result = ai.result;
        const hasAiResult = result != null;
        const isEmptyAiResult =
            hasAiResult &&
            result.columns.length === 0 &&
            result.rows.length === 0 &&
            result.summary.trim().length > 0;
        const hasAiTableRows = hasAiResult && result.rows.length > 0;
        const [recLoading, setRecLoading] = useState(false);
        const recs = recommendations?.recommendations ?? [];
        const recMessage = recommendations?.message ?? null;
        const [historyOpen, setHistoryOpen] = useState(false);
        const [historyLoading, setHistoryLoading] = useState(false);
        const [history, setHistory] = useState<RecommendationHistoryRow[] | null>(null);
        const [historyError, setHistoryError] = useState<string | null>(null);

        return (
            <AuthenticatedLayout
                header={
                    <div className="flex items-center justify-between gap-4">
                        <h2 className="font-display text-2xl font-semibold leading-tight tracking-tight text-[#f5f0e8]">
                            My Library
                        </h2>
                        <Link href={route('books.create')}>
                            <PrimaryButton className="normal-case tracking-normal shadow-[inset_0_1px_0_rgba(255,255,255,0.15),0_0_12px_rgba(201,168,76,0.3)]">
                                Add book
                            </PrimaryButton>
                        </Link>
                    </div>
                }
            >
                <Head title="My Library" />

                <div className="py-10 sm:py-12">
                    <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                        <div className="grid gap-6 lg:grid-cols-12">
                            <div className="space-y-6 lg:col-span-8">
                                <div className="library-glass-panel relative z-30 isolate p-6">
                                    <form
                                        onSubmit={submitFilters}
                                        className="grid gap-4 md:grid-cols-2"
                                    >
                                        <div>
                                            <label className="text-sm font-medium text-[rgba(255,255,255,0.65)]">
                                                Search
                                            </label>
                                            <TextInput
                                                className="mt-1 block w-full"
                                                value={filters.q}
                                                onChange={(e) =>
                                                    router.get(
                                                        route('books.index'),
                                                        { ...filters, q: e.target.value },
                                                        {
                                                            preserveState: true,
                                                            preserveScroll: true,
                                                            replace: true,
                                                        },
                                                    )
                                                }
                                            />
                                        </div>

                                        <div>
                                            <label className="text-sm font-medium text-[rgba(255,255,255,0.65)]">
                                                Genre
                                            </label>
                                            <LibraryGlassSelect
                                                className="mt-1"
                                                value={String(filters.genre_id ?? '')}
                                                onChange={(genre_id) =>
                                                    router.get(
                                                        route('books.index'),
                                                        { ...filters, genre_id },
                                                        {
                                                            preserveState: true,
                                                            preserveScroll: true,
                                                            replace: true,
                                                        },
                                                    )
                                                }
                                                options={genreFilterOptions}
                                            />
                                        </div>
                                    </form>

                                    <div className="mt-4 flex flex-wrap gap-2">
                                        {statusPills.map((p) => {
                                            const active = (filters.status ?? '') === p.value;
                                            return (
                                                <button
                                                    key={p.label}
                                                    type="button"
                                                    className={
                                                        'rounded-full px-3 py-1.5 text-sm transition ' +
                                                        (active ? pillActive : pillIdle)
                                                    }
                                                    onClick={() =>
                                                        router.get(
                                                            route('books.index'),
                                                            { ...filters, status: p.value },
                                                            {
                                                                preserveState: true,
                                                                preserveScroll: true,
                                                                replace: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    {p.label}
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>

                                <div className="library-glass-panel relative z-10 overflow-hidden p-0">
                                    <div className="library-table-wrap overflow-x-auto">
                                        <table className="min-w-full">
                                            <thead className="bg-black/20">
                                                <tr>
                                                    <th className="px-6 py-3 text-left">Title</th>
                                                    <th className="px-6 py-3 text-left">Author</th>
                                                    <th className="px-6 py-3 text-left">Genre</th>
                                                    <th className="px-6 py-3 text-left">Status</th>
                                                    <th className="px-6 py-3" />
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {books.data.map((b) => (
                                                    <tr key={b.id}>
                                                        <td className="px-6 py-4 text-sm font-medium text-[#f5f0e8]">
                                                            {b.title}
                                                        </td>
                                                        <td className="px-6 py-4 text-sm text-[rgba(245,240,232,0.85)]">
                                                            {b.author ?? '—'}
                                                        </td>
                                                        <td className="px-6 py-4 text-sm text-[rgba(245,240,232,0.85)]">
                                                            {b.genre?.name ?? '—'}
                                                        </td>
                                                        <td className="px-6 py-4 text-sm text-[rgba(245,240,232,0.85)]">
                                                            {b.status.replaceAll('_', ' ')}
                                                        </td>
                                                        <td className="px-6 py-4 text-right text-sm">
                                                            <div className="flex justify-end gap-3">
                                                                <Link
                                                                    className="font-medium text-[#c9a84c] hover:text-[#d4b76a]"
                                                                    href={route('books.edit', b.id)}
                                                                >
                                                                    Edit
                                                                </Link>
                                                                <button
                                                                    type="button"
                                                                    className="font-medium text-[#e06c6c] hover:text-[#f08080]"
                                                                    onClick={() => {
                                                                        if (
                                                                            confirm(
                                                                                'Delete this book? This cannot be undone.',
                                                                            )
                                                                        ) {
                                                                            router.delete(
                                                                                route(
                                                                                    'books.destroy',
                                                                                    b.id,
                                                                                ),
                                                                            );
                                                                        }
                                                                    }}
                                                                >
                                                                    Delete
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ))}
                                                {books.data.length === 0 && (
                                                    <tr>
                                                        <td
                                                            colSpan={5}
                                                            className="px-6 py-10 text-center text-sm text-[rgba(245,240,232,0.55)]"
                                                        >
                                                            No books found.
                                                        </td>
                                                    </tr>
                                                )}
                                            </tbody>
                                        </table>
                                    </div>

                                    <div className="flex flex-wrap gap-2 border-t border-white/10 p-4">
                                        {books.links.map((l, idx) => (
                                            <Link
                                                key={idx}
                                                href={l.url ?? ''}
                                                preserveScroll
                                                className={
                                                    'rounded-lg border px-3 py-1 text-sm transition ' +
                                                    (l.active
                                                        ? 'border-[#c9a84c] bg-[#c9a84c]/15 text-[#c9a84c]'
                                                        : 'border-white/15 bg-black/20 text-[rgba(245,240,232,0.8)] hover:border-white/25') +
                                                    (l.url ? '' : ' pointer-events-none opacity-50')
                                                }
                                                dangerouslySetInnerHTML={{ __html: l.label }}
                                            />
                                        ))}
                                    </div>
                                </div>

                                <div className="library-glass-panel p-6">
                                    <div className="font-display text-lg font-semibold text-[#f5f0e8]">
                                        Ask about my library
                                    </div>
                                    <div className="mt-1 text-sm text-[rgba(245,240,232,0.65)]">
                                        Ask natural language questions about your own books.
                                    </div>

                                    <form
                                        className="mt-4 space-y-3"
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            form.post(route('ai.query'), {
                                                preserveScroll: true,
                                                preserveState: true,
                                                replace: true,
                                            });
                                        }}
                                    >
                                        <TextInput
                                            className="block w-full"
                                            value={form.data.question}
                                            onChange={(e) =>
                                                form.setData('question', e.target.value)
                                            }
                                            placeholder="e.g. Show my completed books, Which genre do I read most?"
                                        />
                                        <InputError
                                            className="mt-2"
                                            message={form.errors.question}
                                        />
                                        <PrimaryButton disabled={form.processing}>
                                            Run query
                                        </PrimaryButton>
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
                                                                                    (r as Record<
                                                                                        string,
                                                                                        unknown
                                                                                    >)[c] ?? '—',
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
                                                    {JSON.stringify(
                                                        result.debug?.spec ?? null,
                                                        null,
                                                        2,
                                                    )}
                                                </pre>
                                            </details>
                                        </div>
                                    )}
                                </div>

                                <div className="library-glass-panel p-6">
                                    <div className="flex items-start justify-between gap-4">
                                        <div>
                                            <div className="font-display text-lg font-semibold text-[#f5f0e8]">
                                                Recommended for you
                                            </div>
                                            <div className="mt-1 text-sm text-[rgba(245,240,232,0.65)]">
                                                Based on your reading history and genres
                                            </div>
                                        </div>
                                        <PrimaryButton
                                            disabled={recLoading}
                                            className="normal-case tracking-normal shadow-[inset_0_1px_0_rgba(255,255,255,0.15),0_0_12px_rgba(201,168,76,0.3)]"
                                            onClick={() => {
                                                router.get(
                                                    route('recommendations'),
                                                    {},
                                                    {
                                                        preserveScroll: true,
                                                        // We want fresh flashed recommendations to show immediately.
                                                        preserveState: false,
                                                        replace: true,
                                                        onStart: () => setRecLoading(true),
                                                        onSuccess: () => {
                                                            // Force history to refetch next time since DB changed.
                                                            setHistory(null);
                                                            setHistoryError(null);
                                                        },
                                                        onFinish: () => setRecLoading(false),
                                                    },
                                                );
                                            }}
                                        >
                                            Get recommendations
                                        </PrimaryButton>
                                    </div>

                                    {recLoading && (
                                        <div className="mt-4 grid gap-3 md:grid-cols-3">
                                            {[0, 1, 2].map((i) => (
                                                <div
                                                    key={i}
                                                    className="animate-pulse rounded-xl border border-[rgba(201,168,76,0.2)] bg-[rgba(255,255,255,0.05)] p-4"
                                                >
                                                    <div className="h-4 w-3/4 rounded bg-white/10" />
                                                    <div className="mt-3 h-3 w-1/2 rounded bg-white/10" />
                                                    <div className="mt-3 h-6 w-24 rounded-full bg-white/10" />
                                                    <div className="mt-4 space-y-2">
                                                        <div className="h-3 w-full rounded bg-white/10" />
                                                        <div className="h-3 w-5/6 rounded bg-white/10" />
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}

                                    {!recLoading && recMessage && (
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
                                            {recMessage}
                                        </div>
                                    )}

                                    {!recLoading && recs.length > 0 && (
                                        <div className="mt-4 grid gap-3 md:grid-cols-3">
                                            {recs.slice(0, 3).map((r) => (
                                                <div
                                                    key={`${r.title}-${r.author}`}
                                                    className="rounded-xl border border-[rgba(201,168,76,0.2)] bg-[rgba(255,255,255,0.05)] p-4"
                                                >
                                                    <div className="font-display text-[15px] font-semibold text-[#f5f0e8]">
                                                        {r.title}
                                                    </div>
                                                    <div className="mt-1 text-[13px] text-[rgba(245,240,232,0.65)]">
                                                        {r.author}
                                                    </div>
                                                    <div className="mt-3">
                                                        <span className={pillActive + ' inline-block text-xs'}>
                                                            {r.genre}
                                                        </span>
                                                    </div>
                                                    <div className="mt-3 text-[13px] italic text-[rgba(245,240,232,0.7)]">
                                                        {r.reason}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}

                                    <div className="mt-4 flex items-center justify-between gap-4">
                                        <button
                                            type="button"
                                            className="text-[13px] text-[rgba(255,255,255,0.4)] hover:text-[rgba(201,168,76,0.8)]"
                                            onClick={() => {
                                                if (
                                                    confirm(
                                                        'Reset recommendation history? You may see previously suggested books again.',
                                                    )
                                                ) {
                                                    router.delete(route('recommendations.reset'), {
                                                        preserveScroll: true,
                                                        // We want the UI to reflect cleared history immediately.
                                                        preserveState: true,
                                                        replace: true,
                                                        onSuccess: () => {
                                                            setHistory(null);
                                                            setHistoryError(null);
                                                            setHistoryOpen(false);
                                                        },
                                                    });
                                                }
                                            }}
                                        >
                                            Reset recommendation history
                                        </button>
                                        <button
                                            type="button"
                                            className="text-[13px] text-[rgba(255,255,255,0.4)] hover:text-[rgba(201,168,76,0.8)]"
                                            onClick={async () => {
                                                setHistoryOpen(true);
                                                if (history !== null || historyLoading) return;
                                                setHistoryLoading(true);
                                                setHistoryError(null);
                                                try {
                                                    const resp = await fetch(
                                                        route('recommendations.history'),
                                                        {
                                                            headers: { Accept: 'application/json' },
                                                            credentials: 'same-origin',
                                                        },
                                                    );
                                                    if (!resp.ok) {
                                                        throw new Error(
                                                            `Failed to load history (${resp.status})`,
                                                        );
                                                    }
                                                    const data =
                                                        (await resp.json()) as RecommendationHistoryRow[];
                                                    setHistory(Array.isArray(data) ? data : []);
                                                } catch (e) {
                                                    setHistoryError(
                                                        e instanceof Error
                                                            ? e.message
                                                            : 'Failed to load history.',
                                                    );
                                                    setHistory([]);
                                                } finally {
                                                    setHistoryLoading(false);
                                                }
                                            }}
                                        >
                                            View recommendation history →
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {historyOpen && (
                                <div
                                    className="fixed inset-0 z-[300] flex items-center justify-center px-4 py-8"
                                    onMouseDown={(e) => {
                                        if (e.target === e.currentTarget) {
                                            setHistoryOpen(false);
                                        }
                                    }}
                                >
                                    <div className="absolute inset-0 bg-black/60" />
                                    <div
                                        className="relative z-10 flex w-full max-w-3xl flex-col overflow-hidden max-h-[80vh] min-h-0"
                                        style={{
                                            backdropFilter: 'blur(20px)',
                                            background: 'rgba(30, 20, 10, 0.85)',
                                            border: '1px solid rgba(201,168,76,0.2)',
                                            borderRadius: '16px',
                                        }}
                                    >
                                        <div className="flex items-start justify-between gap-4 border-b border-[rgba(201,168,76,0.15)] px-6 py-4">
                                            <div className="font-display text-xl font-semibold text-[#f5f0e8]">
                                                Recommendation history
                                            </div>
                                            <button
                                                type="button"
                                                className="rounded-lg px-2 py-1 text-[rgba(255,255,255,0.6)] hover:text-[rgba(201,168,76,0.9)]"
                                                onClick={() => setHistoryOpen(false)}
                                                aria-label="Close"
                                            >
                                                ×
                                            </button>
                                        </div>

                                        <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-6 py-4">
                                            {historyLoading && (
                                                <div className="space-y-3">
                                                    {[0, 1, 2, 3].map((i) => (
                                                        <div
                                                            key={i}
                                                            className="h-4 w-full animate-pulse rounded bg-white/10"
                                                        />
                                                    ))}
                                                </div>
                                            )}

                                            {!historyLoading && historyError && (
                                                <div
                                                    className="border border-white/10 backdrop-blur-xl"
                                                    style={{
                                                        borderLeft: '3px solid #c9a84c',
                                                        background: 'rgba(201, 168, 76, 0.08)',
                                                        padding: '12px 16px',
                                                        borderRadius: '8px',
                                                        color: 'rgba(255,255,255,0.8)',
                                                        fontSize: '14px',
                                                    }}
                                                >
                                                    {historyError}
                                                </div>
                                            )}

                                            {!historyLoading &&
                                                !historyError &&
                                                (history ?? []).length === 0 && (
                                                    <div
                                                        className="mx-auto max-w-xl border border-white/10 text-center backdrop-blur-xl"
                                                        style={{
                                                            borderLeft: '3px solid #c9a84c',
                                                            background: 'rgba(201, 168, 76, 0.08)',
                                                            padding: '12px 16px',
                                                            borderRadius: '8px',
                                                            color: 'rgba(255,255,255,0.8)',
                                                            fontSize: '14px',
                                                        }}
                                                    >
                                                        No recommendations yet — click 'Get Recommendations' to get
                                                        started.
                                                    </div>
                                                )}

                                            {!historyLoading &&
                                                !historyError &&
                                                (history ?? []).length > 0 && (() => {
                                                    const groups = new Map<string, RecommendationHistoryRow[]>();
                                                    for (const row of history ?? []) {
                                                        const d = new Date(row.created_at);
                                                        const key = d.toLocaleDateString(undefined, {
                                                            year: 'numeric',
                                                            month: 'long',
                                                            day: 'numeric',
                                                        });
                                                        const arr = groups.get(key) ?? [];
                                                        arr.push(row);
                                                        groups.set(key, arr);
                                                    }

                                                    return Array.from(groups.entries()).map(([date, rows]) => (
                                                        <div key={date}>
                                                            <div
                                                                className="text-[11px] uppercase tracking-[0.08em]"
                                                                style={{
                                                                    color: 'rgba(201, 168, 76, 0.6)',
                                                                    borderBottom:
                                                                        '1px solid rgba(201, 168, 76, 0.15)',
                                                                    paddingBottom: '6px',
                                                                    margin: '16px 0 10px',
                                                                }}
                                                            >
                                                                {date}
                                                            </div>
                                                            <div className="space-y-2">
                                                                {rows.map((r) => (
                                                                    <div
                                                                        key={`${r.created_at}-${r.title}-${r.author}`}
                                                                        className="flex flex-wrap items-center justify-between gap-3"
                                                                    >
                                                                        <div className="min-w-0 flex-1">
                                                                            <div className="truncate font-medium text-[#f5f0e8]">
                                                                                {r.title}
                                                                            </div>
                                                                            <div className="text-[13px] text-[rgba(245,240,232,0.65)]">
                                                                                {r.author}
                                                                            </div>
                                                                        </div>
                                                                        <div>
                                                                            <span className="inline-block rounded-full border border-[rgba(201,168,76,0.45)] bg-[#c9a84c]/15 px-2.5 py-1 text-xs font-medium text-[#c9a84c]">
                                                                                {r.genre}
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                ))}
                                                            </div>
                                                        </div>
                                                    ));
                                                })()}
                                        </div>
                                    </div>
                                </div>
                            )}

                            <div className="space-y-6 lg:col-span-4">
                                <div className="library-glass-panel p-6">
                                    <div className="font-display text-lg font-semibold text-[#f5f0e8]">
                                        Genre breakdown
                                    </div>
                                    <div className="mt-4 space-y-3">
                                        {(genreBreakdown ?? []).length === 0 && (
                                            <div className="text-sm text-[rgba(245,240,232,0.55)]">
                                                No genre data yet.
                                            </div>
                                        )}
                                        {(genreBreakdown ?? []).map((g) => (
                                            <div key={g.id} className="space-y-1">
                                                <div className="flex items-center justify-between text-sm">
                                                    <div className="text-[rgba(245,240,232,0.85)]">
                                                        {g.name}
                                                    </div>
                                                    <div className="text-[rgba(255,255,255,0.5)]">
                                                        {g.count} ({g.percent}%)
                                                    </div>
                                                </div>
                                                <div className="library-genre-bar-track">
                                                    <div
                                                        className="library-genre-bar-fill"
                                                        style={{ width: `${g.percent}%` }}
                                                    />
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>

                                <div className="library-glass-panel p-6">
                                    <div className="font-display text-lg font-semibold text-[#f5f0e8]">
                                        AI insights
                                    </div>
                                    <div className="mt-4 space-y-3">
                                        <div className="grid gap-3 sm:grid-cols-2">
                                            <div className="library-metric-nested p-4">
                                                <div className="text-[11px] font-medium uppercase tracking-wider text-[rgba(255,255,255,0.5)]">
                                                    Total books
                                                </div>
                                                <div className="library-stat-mega mt-1">
                                                    {stats?.totalBooks ?? 0}
                                                </div>
                                            </div>
                                            <div className="library-metric-nested p-4">
                                                <div className="text-[11px] font-medium uppercase tracking-wider text-[rgba(255,255,255,0.5)]">
                                                    Currently reading
                                                </div>
                                                <div className="library-stat-mega mt-1">
                                                    {stats?.reading ?? 0}
                                                </div>
                                            </div>
                                            <div className="library-metric-nested p-4">
                                                <div className="text-[11px] font-medium uppercase tracking-wider text-[rgba(255,255,255,0.5)]">
                                                    Completed
                                                </div>
                                                <div className="library-stat-mega mt-1">
                                                    {stats?.completed ?? 0}
                                                </div>
                                            </div>
                                            <div className="library-metric-nested p-4">
                                                <div className="text-[11px] font-medium uppercase tracking-wider text-[rgba(255,255,255,0.5)]">
                                                    Favourite genre
                                                </div>
                                                <div className="font-display mt-2 text-2xl font-semibold leading-snug text-[#c9a84c]">
                                                    {stats?.favouriteGenre ?? '—'}
                                                </div>
                                            </div>
                                        </div>

                                        {(insights ?? []).slice(0, 3).map((i) => (
                                            <div
                                                key={i.title}
                                                className="rounded-xl border border-white/10 bg-black/20 p-4"
                                            >
                                                <div className="font-display text-lg font-semibold text-[#f5f0e8]">
                                                    {i.title}
                                                </div>
                                                <div className="mt-1 text-sm text-[rgba(245,240,232,0.7)]">
                                                    {i.body}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </AuthenticatedLayout>
        );
    }

    const statusFilterOptions = [
        { value: '', label: 'All' },
        ...statusOptions.map((s) => ({
            value: s,
            label: s.replaceAll('_', ' '),
        })),
    ];

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <h2 className="font-display text-2xl font-semibold leading-tight tracking-tight text-[#f5f0e8]">
                        All Books
                    </h2>
                </div>
            }
        >
            <Head title="Books" />

            <div className="py-10 sm:py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <div className="library-glass-panel relative z-30 isolate p-6">
                        <form onSubmit={submitFilters} className="grid gap-4 md:grid-cols-3">
                            <div>
                                <label className="text-sm font-medium text-[rgba(255,255,255,0.65)]">
                                    Search
                                </label>
                                <TextInput
                                    className="mt-1 block w-full"
                                    value={filters.q}
                                    onChange={(e) =>
                                        router.get(
                                            route('admin.books.index'),
                                            { ...filters, q: e.target.value },
                                            {
                                                preserveState: true,
                                                preserveScroll: true,
                                                replace: true,
                                            },
                                        )
                                    }
                                />
                            </div>

                            <div>
                                <label className="text-sm font-medium text-[rgba(255,255,255,0.65)]">
                                    Genre
                                </label>
                                <LibraryGlassSelect
                                    className="mt-1"
                                    value={String(filters.genre_id ?? '')}
                                    onChange={(genre_id) =>
                                        router.get(
                                            route('admin.books.index'),
                                            { ...filters, genre_id },
                                            {
                                                preserveState: true,
                                                preserveScroll: true,
                                                replace: true,
                                            },
                                        )
                                    }
                                    options={genreFilterOptions}
                                />
                            </div>

                            <div>
                                <label className="text-sm font-medium text-[rgba(255,255,255,0.65)]">
                                    Status
                                </label>
                                <LibraryGlassSelect
                                    className="mt-1"
                                    value={filters.status ?? ''}
                                    onChange={(status) =>
                                        router.get(
                                            route('admin.books.index'),
                                            { ...filters, status },
                                            {
                                                preserveState: true,
                                                preserveScroll: true,
                                                replace: true,
                                            },
                                        )
                                    }
                                    options={statusFilterOptions}
                                />
                            </div>
                        </form>
                    </div>

                    <div className="library-glass-panel relative z-10 overflow-hidden p-0">
                        <div className="library-table-wrap overflow-x-auto">
                            <table className="min-w-full">
                                <thead className="bg-black/20">
                                    <tr>
                                        <th className="px-6 py-3 text-left">Title</th>
                                        <th className="px-6 py-3 text-left">Author</th>
                                        <th className="px-6 py-3 text-left">Genre</th>
                                        <th className="px-6 py-3 text-left">Status</th>
                                        <th className="px-6 py-3 text-left">Owner</th>
                                        <th className="px-6 py-3" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {books.data.map((b) => (
                                        <tr key={b.id}>
                                            <td className="px-6 py-4 text-sm font-medium text-[#f5f0e8]">
                                                {b.title}
                                            </td>
                                            <td className="px-6 py-4 text-sm text-[rgba(245,240,232,0.85)]">
                                                {b.author ?? '—'}
                                            </td>
                                            <td className="px-6 py-4 text-sm text-[rgba(245,240,232,0.85)]">
                                                {b.genre?.name ?? '—'}
                                            </td>
                                            <td className="px-6 py-4 text-sm text-[rgba(245,240,232,0.85)]">
                                                {b.status.replaceAll('_', ' ')}
                                            </td>
                                            <td className="px-6 py-4 text-sm text-[rgba(245,240,232,0.85)]">
                                                {b.user?.name ?? '—'}
                                            </td>
                                            <td className="px-6 py-4 text-right text-sm">
                                                <div className="flex justify-end gap-3">
                                                    <Link
                                                        className="font-medium text-[#c9a84c] hover:text-[#d4b76a]"
                                                        href={route('books.edit', b.id)}
                                                    >
                                                        Edit
                                                    </Link>
                                                    <button
                                                        type="button"
                                                        className="font-medium text-[#e06c6c] hover:text-[#f08080]"
                                                        onClick={() => {
                                                            if (
                                                                confirm(
                                                                    'Delete this book? This cannot be undone.',
                                                                )
                                                            ) {
                                                                router.delete(
                                                                    route('books.destroy', b.id),
                                                                );
                                                            }
                                                        }}
                                                    >
                                                        Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {books.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="px-6 py-10 text-center text-sm text-[rgba(245,240,232,0.55)]"
                                            >
                                                No books found.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <div className="flex flex-wrap gap-2 border-t border-white/10 p-4">
                            {books.links.map((l, idx) => (
                                <Link
                                    key={idx}
                                    href={l.url ?? ''}
                                    preserveScroll
                                    className={
                                        'rounded-lg border px-3 py-1 text-sm transition ' +
                                        (l.active
                                            ? 'border-[#c9a84c] bg-[#c9a84c]/15 text-[#c9a84c]'
                                            : 'border-white/15 bg-black/20 text-[rgba(245,240,232,0.8)] hover:border-white/25') +
                                        (l.url ? '' : ' pointer-events-none opacity-50')
                                    }
                                    dangerouslySetInnerHTML={{ __html: l.label }}
                                />
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
