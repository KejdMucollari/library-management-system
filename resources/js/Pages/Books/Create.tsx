import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import BookForm from '@/Pages/Books/Partials/BookForm';
import { Head, Link, useForm } from '@inertiajs/react';

type BookFormData = {
    title: string;
    author: string;
    genre_id: string;
    status: string;
    pages: string;
    price: string;
};

export default function BooksCreate({
    statusOptions,
    genres,
}: {
    statusOptions: string[];
    genres: { id: number; name: string }[];
}) {
    const form = useForm<BookFormData>({
        title: '',
        author: '',
        genre_id: '',
        status: statusOptions[0] ?? 'plan_to_read',
        pages: '',
        price: '',
    });

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <h2 className="font-display text-2xl font-semibold leading-tight tracking-tight text-[#f5f0e8]">
                        Add book
                    </h2>
                    <Link
                        className="text-sm font-medium text-[#c9a84c] hover:text-[#d4b76a]"
                        href={route('books.index')}
                    >
                        Back
                    </Link>
                </div>
            }
        >
            <Head title="Add book" />

            <div className="py-10 sm:py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="library-glass-panel p-6 sm:p-8">
                        <BookForm
                            form={form}
                            statusOptions={statusOptions}
                            genres={genres}
                            submitLabel="Create"
                            onSubmit={() => form.post(route('books.store'))}
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

