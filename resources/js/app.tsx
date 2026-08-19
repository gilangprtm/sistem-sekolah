import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import DashboardLayout from '@/layouts/app/dashboard-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import type { PreferenceValueMap } from '@/lib/preferences/preferences-config';
import { PREFERENCE_DEFAULTS } from '@/lib/preferences/preferences-config';
import { ThemeBootScript } from '@/scripts/theme-boot';
import { PreferencesStoreProvider } from '@/stores/preferences/preferences-provider';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

declare module '@inertiajs/react' {
    interface SharedData {
        preferences: PreferenceValueMap;
        sidebarOpen: boolean;
    }
}

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;
            case name.startsWith('auth/v1/') || name.startsWith('auth/v2/'):
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [DashboardLayout, SettingsLayout];
            case name.startsWith('dashboard/'):
                return DashboardLayout;
            default:
                return DashboardLayout;
        }
    },
    strictMode: true,
    withApp(app, { page }) {
        const initialValues = (page.props.preferences ??
            PREFERENCE_DEFAULTS) as PreferenceValueMap;

        return (
            <>
                <ThemeBootScript />
                <PreferencesStoreProvider initialValues={initialValues}>
                    <TooltipProvider delayDuration={0}>
                        {app}
                        <Toaster />
                    </TooltipProvider>
                </PreferencesStoreProvider>
            </>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
