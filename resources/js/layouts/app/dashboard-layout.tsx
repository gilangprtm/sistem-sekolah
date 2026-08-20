import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { siGithub } from 'simple-icons';

import { useShallow } from 'zustand/react/shallow';

import { AppSidebar } from '@/components/dashboard/app-sidebar';
import { LayoutControls } from '@/components/dashboard/layout-controls';
import { SearchDialog } from '@/components/dashboard/search-dialog';
import { ThemeSwitcher } from '@/components/dashboard/theme-switcher';
import { SimpleIcon } from '@/components/simple-icon';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import {
    SidebarInset,
    SidebarProvider,
    SidebarTrigger,
} from '@/components/ui/sidebar';
import { getClientCookie } from '@/lib/cookie.client';
import { cn } from '@/lib/utils';
import { usePreferencesStore } from '@/stores/preferences/preferences-provider';

export default function DashboardLayout({
    children,
}: Readonly<{ children: ReactNode }>) {
    const sidebarStateCookie = getClientCookie('sidebar_state');
    const defaultOpen = sidebarStateCookie !== 'false';

    const { variant, collapsible } = usePreferencesStore(
        useShallow((s) => ({
            variant: s.values.sidebar_variant,
            collapsible: s.values.sidebar_collapsible,
        })),
    );

    return (
        <SidebarProvider
            defaultOpen={defaultOpen}
            style={
                {
                    '--sidebar-width': 'calc(var(--spacing) * 68)',
                } as React.CSSProperties
            }
        >
            <AppSidebar variant={variant} collapsible={collapsible} />
            <SidebarInset
                className={cn(
                    '[html[data-content-layout=centered]_&>*]:mx-auto',
                    '[html[data-content-layout=centered]_&>*]:w-full',
                    '[html[data-content-layout=centered]_&>*]:max-w-screen-2xl',
                    'peer-data-[variant=inset]:border',
                    '[--dashboard-header-height:--spacing(12)]',
                    'min-w-0 overflow-x-clip',
                )}
            >
                <header
                    className={cn(
                        'flex h-12 shrink-0 items-center gap-2 border-b transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12',
                        '[html[data-navbar-style=sticky]_&]:sticky [html[data-navbar-style=sticky]_&]:top-0 [html[data-navbar-style=sticky]_&]:z-50 [html[data-navbar-style=sticky]_&]:overflow-hidden [html[data-navbar-style=sticky]_&]:rounded-t-[inherit] [html[data-navbar-style=sticky]_&]:bg-background/50 [html[data-navbar-style=sticky]_&]:backdrop-blur-md',
                    )}
                >
                    <div className="flex w-full items-center justify-between px-4 lg:px-6">
                        <div className="flex items-center gap-1 lg:gap-2">
                            <SidebarTrigger className="-ml-1" />
                            <Separator
                                orientation="vertical"
                                className="mx-2 data-[orientation=vertical]:h-4 data-[orientation=vertical]:self-center"
                            />
                            <SearchDialog />
                        </div>
                        <div className="flex items-center gap-2">
                            <LayoutControls />
                            <ThemeSwitcher />
                            <Button asChild size="icon">
                                <Link
                                    prefetch={false}
                                    href="https://github.com/gilangprtm/sistem-sekolah"
                                    target="_blank"
                                    rel="noreferrer"
                                    aria-label="Open GitHub repository"
                                >
                                    <SimpleIcon
                                        icon={siGithub}
                                        className="fill-primary-foreground"
                                    />
                                </Link>
                            </Button>
                        </div>
                    </div>
                </header>
                <div className="min-h-0 min-w-0 flex-1 overflow-x-hidden p-4 has-data-[content-padding=false]:p-0 md:p-6 md:has-data-[content-padding=false]:p-0">
                    {children}
                </div>
            </SidebarInset>
        </SidebarProvider>
    );
}
