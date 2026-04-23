import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import BookForm from '@/Pages/Books/Partials/BookForm';
import { Head, Link, useForm } from '@inertiajs/react';

type Book = {
    id: number;
    title: string;
    author: string | null;
    genre_id: number | null;
    status: string;
    pages: number | null;
    price: string | null;
};

type BookFormData = {
    title: string;
    author: string;
    genre_id: string;
    status: string;
    pages: string;
    price: string;
};

export default function BooksEdit({
    book,
    statusOptions,
    genres,
}: {
    book: Book;
    statusOptions: string[];
    genres: { id: number; name: string }[];
}) {
    const form = useForm<BookFormData>({
        title: book.title ?? '',
        author: book.author ?? '',
        genre_id: book.genre_id?.toString() ?? '',
        status: book.status ?? (statusOptions[0] ?? 'plan_to_read'),
        pages: book.pages?.toString() ?? '',
        price: book.price ?? '',
    });

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <h2 className="font-display text-2xl font-semibold leading-tight tracking-tight text-[#f5f0e8]">
                        Edit book
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
            <Head title="Edit book" />

            <div className="py-10 sm:py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="library-glass-panel p-6 sm:p-8">
                        <BookForm
                            form={form}
                            statusOptions={statusOptions}
                            genres={genres}
                            submitLabel="Save"
                            onSubmit={() => form.put(route('books.update', book.id))}
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

