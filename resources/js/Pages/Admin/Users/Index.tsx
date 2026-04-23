import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

type UserRow = {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
};

type Paginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
};

export default function AdminUsersIndex({
    users,
    filters,
}: {
    users: Paginated<UserRow>;
    filters: { q: string };
}) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <h2 className="font-display text-2xl font-semibold leading-tight tracking-tight text-[#f5f0e8]">
                        Users
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
            <Head title="Admin users" />

            <div className="py-10 sm:py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <div className="library-glass-panel p-6">
                        <div className="flex gap-3">
                            <TextInput
                                className="w-full"
                                value={filters.q}
                                onChange={(e) =>
                                    router.get(
                                        route('admin.users.index'),
                                        { ...filters, q: e.target.value },
                                        { preserveState: true, replace: true },
                                    )
                                }
                                placeholder="Search name or email…"
                            />
                        </div>
                    </div>

                    <div className="library-glass-panel overflow-hidden p-0">
                        <div className="library-table-wrap overflow-x-auto">
                            <table className="min-w-full">
                                <thead className="bg-black/20">
                                    <tr>
                                        <th className="px-6 py-3 text-left">Name</th>
                                        <th className="px-6 py-3 text-left">Email</th>
                                        <th className="px-6 py-3 text-left">Role</th>
                                        <th className="px-6 py-3" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {users.data.map((u) => (
                                        <tr key={u.id}>
                                            <td className="px-6 py-4 text-sm font-medium text-[#f5f0e8]">
                                                {u.name}
                                            </td>
                                            <td className="px-6 py-4 text-sm text-[rgba(245,240,232,0.85)]">
                                                {u.email}
                                            </td>
                                            <td className="px-6 py-4 text-sm text-[rgba(245,240,232,0.85)]">
                                                {u.is_admin ? 'Admin' : 'User'}
                                            </td>
                                            <td className="px-6 py-4 text-right text-sm">
                                                <Link
                                                    className="font-medium text-[#c9a84c] hover:text-[#d4b76a]"
                                                    href={route('admin.users.edit', u.id)}
                                                >
                                                    Manage
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                    {users.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={4}
                                                className="px-6 py-10 text-center text-sm text-[rgba(245,240,232,0.55)]"
                                            >
                                                No users found.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <div className="flex flex-wrap gap-2 border-t border-white/10 p-4">
                            {users.links.map((l, idx) => (
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
