import { ButtonHTMLAttributes } from 'react';

export default function PrimaryButton({
    className = '',
    disabled,
    children,
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement>) {
    return (
        <button
            {...props}
            className={
                `inline-flex items-center rounded-md border border-[rgba(201,168,76,0.45)] bg-gradient-to-r from-[#8f6f2a] via-[#c9a84c] to-[#d4b76a] px-4 py-2 text-xs font-semibold uppercase tracking-widest text-stone-900 transition duration-150 ease-in-out hover:from-[#7a5f24] hover:via-[#b89640] hover:to-[#c9a55c] focus:outline-none focus:ring-2 focus:ring-[#c9a84c]/55 focus:ring-offset-0 focus:ring-offset-transparent active:from-[#6b5220] active:via-[#a68438] active:to-[#b8923f] ${
                    disabled && 'opacity-25'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
