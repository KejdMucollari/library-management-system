import { ImgHTMLAttributes } from 'react';

export default function ApplicationLogo({
    src = '/images/library_app_logo.svg',
    alt = 'Library',
    className,
    ...props
}: ImgHTMLAttributes<HTMLImageElement>) {
    return (
        <img
            src={src}
            alt={alt}
            className={['object-contain', className].filter(Boolean).join(' ')}
            {...props}
        />
    );
}
