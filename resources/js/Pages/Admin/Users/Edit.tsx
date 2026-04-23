import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import LibraryGlassSelect from '@/Components/LibraryGlassSelect';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

type User = {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
};

export default function AdminUsersEdit({ user }: { user: User }) {
    const form = useForm<{ is_admin: boolean }>({
        is_admin: user.is_admin,
    });

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <h2 className="font-display text-2xl font-semibold leading-tight tracking-tight text-[#f5f0e8]">
                        Manage user
                    </h2>
                    <Link
                        href={route('admin.users.index')}
                        className="text-sm font-medium text-[#c9a84c] hover:text-[#d4b76a]"
                    >
                        Back
                    </Link>
                </div>
            }
        >
            <Head title="Manage user" />

            <div className="py-10 sm:py-12">
                <div className="mx-auto max-w-3xl space-y-6 sm:px-6 lg:px-8">
                    <div className="library-glass-panel p-6">
                        <div className="space-y-1">
                            <div className="text-lg font-medium text-[#f5f0e8]">{user.name}</div>
                            <div className="text-sm text-[rgba(245,240,232,0.65)]">{user.email}</div>
                        </div>

                        <form
                            className="mt-6 space-y-4"
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.put(route('admin.users.update', user.id));
                            }}
                        >
                            <div>
                                <InputLabel htmlFor="is_admin" value="Role" />
                                <LibraryGlassSelect
                                    id="is_admin"
                                    className="mt-1"
                                    value={form.data.is_admin ? '1' : '0'}
                                    onChange={(v) => form.setData('is_admin', v === '1')}
                                    options={[
                                        { value: '0', label: 'User' },
                                        { value: '1', label: 'Admin' },
                                    ]}
                                />
                                <InputError className="mt-2" message={form.errors.is_admin} />
                            </div>

                            <div className="flex items-center gap-4">
                                <PrimaryButton disabled={form.processing}>Save</PrimaryButton>
                            </div>
                        </form>
                    </div>

                    <div className="library-glass-panel p-6">
                        <div className="text-sm text-[rgba(245,240,232,0.75)]">
                            Deleting a user will also delete their books.
                        </div>
                        <div className="mt-4">
                            <DangerButton
                                disabled={form.processing}
                                onClick={() => {
                                    if (confirm('Delete this user? This cannot be undone.')) {
                                        form.delete(route('admin.users.destroy', user.id));
                                    }
                                }}
                            >
                                Delete user
                            </DangerButton>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
