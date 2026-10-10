import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';

export default function AuthLayout({
    title = '',
    description = '',
    children,
    heroBackground = false,
}: {
    title?: string;
    description?: string;
    children: React.ReactNode;
    heroBackground?: boolean;
}) {
    return (
        <AuthLayoutTemplate
            title={title}
            description={description}
            heroBackground={heroBackground}
        >
            {children}
        </AuthLayoutTemplate>
    );
}
