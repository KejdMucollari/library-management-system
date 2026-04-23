import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import LibraryGlassSelect from '@/Components/LibraryGlassSelect';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { InertiaFormProps } from '@inertiajs/react';

type BookFormData = {
    title: string;
    author: string;
    genre_id: string;
    status: string;
    pages: string;
    price: string;
};

export default function BookForm({
    form,
    statusOptions,
    genres,
    submitLabel,
    onSubmit,
}: {
    form: InertiaFormProps<BookFormData>;
    statusOptions: string[];
    genres: { id: number; name: string }[];
    submitLabel: string;
    onSubmit: () => void;
}) {
    const genreOptions = [
        { value: '', label: 'None' },
        ...genres.map((g) => ({ value: String(g.id), label: g.name })),
    ];

    const statusSelectOptions = statusOptions.map((s) => ({
        value: s,
        label: s.replaceAll('_', ' '),
    }));

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                onSubmit();
            }}
            className="space-y-6"
        >
            <div>
                <InputLabel htmlFor="title" value="Title" />
                <TextInput
                    id="title"
                    className="mt-1 block w-full"
                    value={form.data.title}
                    onChange={(e) => form.setData('title', e.target.value)}
                    required
                />
                <InputError className="mt-2" message={form.errors.title} />
            </div>

            <div>
                <InputLabel htmlFor="author" value="Author" />
                <TextInput
                    id="author"
                    className="mt-1 block w-full"
                    value={form.data.author}
                    onChange={(e) => form.setData('author', e.target.value)}
                />
                <InputError className="mt-2" message={form.errors.author} />
            </div>

            <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                <div>
                    <InputLabel htmlFor="genre_id" value="Genre" />
                    <LibraryGlassSelect
                        id="genre_id"
                        className="mt-1"
                        value={form.data.genre_id}
                        onChange={(genre_id) => form.setData('genre_id', genre_id)}
                        options={genreOptions}
                    />
                    <InputError className="mt-2" message={form.errors.genre_id} />
                </div>

                <div>
                    <InputLabel htmlFor="status" value="Status" />
                    <LibraryGlassSelect
                        id="status"
                        className="mt-1"
                        value={form.data.status}
                        onChange={(status) => form.setData('status', status)}
                        options={statusSelectOptions}
                    />
                    <InputError className="mt-2" message={form.errors.status} />
                </div>

                <div>
                    <InputLabel htmlFor="pages" value="Pages" />
                    <TextInput
                        id="pages"
                        type="number"
                        className="mt-1 block w-full"
                        value={form.data.pages}
                        onChange={(e) => form.setData('pages', e.target.value)}
                        min={1}
                    />
                    <InputError className="mt-2" message={form.errors.pages} />
                </div>
            </div>

            <div>
                <InputLabel htmlFor="price" value="Price" />
                <TextInput
                    id="price"
                    type="number"
                    className="mt-1 block w-full"
                    value={form.data.price}
                    onChange={(e) => form.setData('price', e.target.value)}
                    min={0}
                    step="0.01"
                />
                <InputError className="mt-2" message={form.errors.price} />
            </div>

            <div className="flex items-center gap-4">
                <PrimaryButton disabled={form.processing}>{submitLabel}</PrimaryButton>
            </div>
        </form>
    );
}

