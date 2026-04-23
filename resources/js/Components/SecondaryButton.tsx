import { ButtonHTMLAttributes } from 'react';

export default function SecondaryButton({
    type = 'button',
    className = '',
    disabled,
    children,
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement>) {
    return (
        <button
            {...props}
            type={type}
            className={
                `inline-flex items-center rounded-md border border-white/15 bg-black/25 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-[#f5f0e8] shadow-sm backdrop-blur-md transition duration-150 ease-in-out hover:border-white/25 hover:bg-black/35 focus:outline-none focus:ring-2 focus:ring-[#c9a84c]/35 focus:ring-offset-0 disabled:opacity-25 ${
                    disabled && 'opacity-25'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
