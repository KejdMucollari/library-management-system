import { InputHTMLAttributes } from 'react';

export default function Checkbox({
    className = '',
    ...props
}: InputHTMLAttributes<HTMLInputElement>) {
    return (
        <input
            {...props}
            type="checkbox"
            className={
                'rounded border-white/25 bg-black/20 text-[#c9a84c] shadow-sm focus:ring-2 focus:ring-[#c9a84c]/40 focus:ring-offset-0 ' +
                className
            }
        />
    );
}
