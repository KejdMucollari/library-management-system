import { ButtonHTMLAttributes } from 'react';

export default function DangerButton({
    className = '',
    disabled,
    children,
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement>) {
    return (
        <button
            {...props}
            className={
                `inline-flex items-center rounded-md border border-transparent bg-[#e06c6c] px-4 py-2 text-xs font-semibold uppercase tracking-widest text-stone-950 transition duration-150 ease-in-out hover:bg-[#f08080] focus:outline-none focus:ring-2 focus:ring-[#e06c6c]/55 focus:ring-offset-0 active:bg-[#c95a5a] ${
                    disabled && 'opacity-25'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
