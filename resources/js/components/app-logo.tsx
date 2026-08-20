import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <div className="flex aspect-square size-9 items-center justify-center overflow-hidden rounded-md">
            <AppLogoIcon className="size-full object-contain" />
        </div>
    );
}
