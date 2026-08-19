'use client';

import { siGithub } from 'simple-icons';

import { AccountSwitcher } from '@/components/dashboard/account-switcher';
import { LayoutControls } from '@/components/dashboard/layout-controls';
import { SearchDialog } from '@/components/dashboard/search-dialog';
import { ThemeSwitcher } from '@/components/dashboard/theme-switcher';
import { SimpleIcon } from '@/components/simple-icon';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { users } from '@/data/users';
import { cn } from '@/lib/utils';

export function AppSidebarHeader() {
    return (
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
                        <a
                            href="https://github.com/arhamkhnz/next-shadcn-admin-dashboard"
                            target="_blank"
                            rel="noreferrer"
                            aria-label="Open GitHub repository"
                        >
                            <SimpleIcon
                                icon={siGithub}
                                className="fill-primary-foreground"
                            />
                        </a>
                    </Button>
                    <AccountSwitcher users={users} />
                </div>
            </div>
        </header>
    );
}
