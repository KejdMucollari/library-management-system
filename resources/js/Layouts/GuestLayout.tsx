import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="library-shell flex min-h-screen flex-col items-center justify-center px-4 py-10 sm:py-12">
            <div className="library-bg-fixed" aria-hidden />
            <div className="library-overlay-fixed" aria-hidden />
            <div className="library-content flex w-full max-w-md flex-col items-center">
                <Link href="/">
                    <ApplicationLogo className="h-16 w-16 fill-current text-[#c9a84c] sm:h-20 sm:w-20" />
                </Link>

                <div className="library-glass-panel mt-8 w-full p-6 sm:p-8">
                    {children}
                </div>
            </div>
        </div>
    );
}
