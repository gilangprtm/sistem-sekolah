import type { HTMLAttributes } from 'react';

/**
 * Logo sekolah. Menggantikan logo bawaan template (Laravel).
 * Dipakai di sidebar, header, dan halaman auth.
 */
export default function AppLogoIcon({ className, ...props }: HTMLAttributes<HTMLImageElement>) {
    return (
        <img
            {...props}
            src="/images/logo-sekolah.png"
            alt="Logo Sekolah"
            className={className}
        />
    );
}
