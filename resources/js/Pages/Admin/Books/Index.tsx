import LibraryGlassSelect from '@/Components/LibraryGlassSelect';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

type BookRow = {
    id: number;
    title: string;
    author: string | null;
    genre: string | null;
    status: string;
    user: { id: number; name: string; email: string };
};

type Paginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
};

export default function AdminBooksIndex({
    books,
    filters,
    statusOptions,
}: {
    books: Paginated<BookRow>;
    filters: { q: string; genre: string; status: string; user: string };
    statusOptions: string[];
}) {
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
                        Admin — Books
                    </h2>
                    <Link
                        href={route('admin.dashboard')}
                        className="text-sm font-medium text-[#c9a84c] hover:text-[#d4b76a]"
                    >
                        Back
                    </Link>
                </div>
            }
        >
            <Head title="Admin books" />

            <div className="py-10 sm:py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <div className="library-glass-panel relative z-30 isolate p-6">
                        <div className="grid gap-4 md:grid-cols-4">
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
                                    placeholder="Title or author…"
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium text-[rgba(255,255,255,0.65)]">
                                    Owner
                                </label>
                                <TextInput
                                    className="mt-1 block w-full"
                                    value={filters.user}
                                    onChange={(e) =>
                                        router.get(
                                            route('admin.books.index'),
                                            { ...filters, user: e.target.value },
                                            {
                                                preserveState: true,
                                                preserveScroll: true,
                                                replace: true,
                                            },
                                        )
                                    }
                                    placeholder="Name or email…"
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium text-[rgba(255,255,255,0.65)]">
                                    Genre
                                </label>
                                <TextInput
                                    className="mt-1 block w-full"
                                    value={filters.genre}
                                    onChange={(e) =>
                                        router.get(
                                            route('admin.books.index'),
                                            { ...filters, genre: e.target.value },
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
                        </div>
                    </div>

                    <div className="library-glass-panel relative z-10 overflow-hidden p-0">
                        <div className="library-table-wrap overflow-x-auto">
                            <table className="min-w-full">
                                <thead className="bg-black/20">
                                    <tr>
                                        <th className="px-6 py-3 text-left">Title</th>
                                        <th className="px-6 py-3 text-left">Author</th>
                                        <th className="px-6 py-3 text-left">Owner</th>
                                        <th className="px-6 py-3 text-left">Status</th>
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
                                                {b.user?.name ?? '—'}
                                            </td>
                                            <td className="px-6 py-4 text-sm text-[rgba(245,240,232,0.85)]">
                                                {b.status.replaceAll('_', ' ')}
                                            </td>
                                        </tr>
                                    ))}
                                    {books.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={4}
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
