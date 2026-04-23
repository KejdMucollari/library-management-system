import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link } from '@inertiajs/react';

export default function BooksLanding({
    canLogin,
    canRegister,
}: {
    canLogin: boolean;
    canRegister: boolean;
}) {
    return (
        <GuestLayout>
            <Head title="Books" />

            <div className="space-y-4">
                <h1 className="font-display text-2xl font-semibold tracking-tight text-[#f5f0e8]">
                    Personal Library
                </h1>
                <p className="text-sm text-[rgba(245,240,232,0.7)]">
                    Track your books, genres, and reading status. Sign in to
                    manage your library.
                </p>

                <div className="flex flex-wrap gap-3">
                    {canLogin && (
                        <Link
                            href={route('login')}
                            className="inline-flex items-center rounded-md border border-[rgba(201,168,76,0.45)] bg-gradient-to-r from-[#8f6f2a] via-[#c9a84c] to-[#d4b76a] px-4 py-2 text-xs font-semibold uppercase tracking-widest text-stone-900 shadow-sm transition hover:from-[#7a5f24] hover:via-[#b89640] hover:to-[#c9a55c]"
                        >
                            Login
                        </Link>
                    )}
                    {canRegister && (
                        <Link
                            href={route('register')}
                            className="inline-flex items-center rounded-md border border-white/15 bg-black/25 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-[#f5f0e8] shadow-sm backdrop-blur-md transition hover:border-white/25 hover:bg-black/35"
                        >
                            Register
                        </Link>
                    )}
                </div>
            </div>
        </GuestLayout>
    );
}

