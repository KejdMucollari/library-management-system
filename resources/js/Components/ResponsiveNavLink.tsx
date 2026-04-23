import { InertiaLinkProps, Link } from '@inertiajs/react';

export default function ResponsiveNavLink({
    active = false,
    className = '',
    children,
    ...props
}: InertiaLinkProps & { active?: boolean }) {
    return (
        <Link
            {...props}
            className={`flex w-full items-start border-l-4 py-2 pe-4 ps-3 ${
                active
                    ? 'border-[#c9a84c] bg-white/[0.06] text-[#c9a84c] focus:border-[#d4b76a] focus:bg-white/[0.08] focus:text-[#d4b76a]'
                    : 'border-transparent text-[rgba(245,240,232,0.75)] hover:border-white/15 hover:bg-white/[0.04] hover:text-[#f5f0e8] focus:border-white/15 focus:bg-white/[0.04] focus:text-[#f5f0e8]'
            } text-base font-medium transition duration-150 ease-in-out focus:outline-none ${className}`}
        >
            {children}
        </Link>
    );
}
