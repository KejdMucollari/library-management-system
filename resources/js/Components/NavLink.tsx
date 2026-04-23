import { InertiaLinkProps, Link } from '@inertiajs/react';

export default function NavLink({
    active = false,
    className = '',
    children,
    ...props
}: InertiaLinkProps & { active: boolean }) {
    return (
        <Link
            {...props}
            className={
                'inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none ' +
                (active
                    ? 'border-[#c9a84c] text-[#c9a84c] focus:border-[#d4b76a]'
                    : 'border-transparent text-[rgba(245,240,232,0.65)] hover:border-white/20 hover:text-[#f5f0e8] focus:border-white/20 focus:text-[#f5f0e8]') +
                className
            }
        >
            {children}
        </Link>
    );
}
