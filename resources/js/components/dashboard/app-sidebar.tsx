import { Link } from '@inertiajs/react';
import {
    CircleHelp,
    ClipboardList,
    Command,
    Database,
    File,
    Search,
    Settings,
} from 'lucide-react';
import { useShallow } from 'zustand/react/shallow';

import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { APP_CONFIG } from '@/config/app-config';
import { rootUser } from '@/data/users';
import { sidebarItems } from '@/navigation/sidebar/sidebar-items';
import { usePreferencesStore } from '@/stores/preferences/preferences-provider';

import { NavFooter } from './nav-footer';
import { NavMain } from './nav-main';
import { NavUser } from './nav-user';

/** Document links available for sidebar (commented in content by default). */
export const sidebarDocumentItems = [
    {
        name: 'Data Library',
        url: '#',
        icon: Database,
    },
    {
        name: 'Reports',
        url: '#',
        icon: ClipboardList,
    },
    {
        name: 'Word Assistant',
        url: '#',
        icon: File,
    },
] as const;

/** Secondary links available for sidebar (commented in content by default). */
export const sidebarSecondaryItems = [
    {
        title: 'Settings',
        url: '#',
        icon: Settings,
    },
    {
        title: 'Get Help',
        url: '#',
        icon: CircleHelp,
    },
    {
        title: 'Search',
        url: '#',
        icon: Search,
    },
];

export function AppSidebar({
    variant = 'sidebar',
    collapsible = 'offcanvas',
    ...props
}: React.ComponentProps<typeof Sidebar> & {
    variant?: 'sidebar' | 'floating' | 'inset';
    collapsible?: 'offcanvas' | 'icon' | 'none';
}) {
    const { sidebarVariant, sidebarCollapsible, isSynced } =
        usePreferencesStore(
            useShallow((s) => ({
                sidebarVariant: s.values.sidebar_variant,
                sidebarCollapsible: s.values.sidebar_collapsible,
                isSynced: s.isSynced,
            })),
        );

    const effectiveVariant = isSynced ? sidebarVariant : variant;
    const effectiveCollapsible = isSynced ? sidebarCollapsible : collapsible;

    return (
        <Sidebar
            {...props}
            variant={effectiveVariant}
            collapsible={effectiveCollapsible}
        >
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton asChild>
                            <Link href="/dashboard/default">
                                <Command />
                                <span className="text-base font-semibold">
                                    {APP_CONFIG.name}
                                </span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>
            <SidebarContent>
                <NavMain items={sidebarItems} />
                {/* Available: NavDocuments + NavSecondary (commented like Next.js reference) */}
                {/* <NavDocuments items={sidebarDocumentItems} /> */}
                {/* <NavSecondary items={sidebarSecondaryItems} className="mt-auto" /> */}
            </SidebarContent>
            <SidebarFooter>
                <NavFooter />
                <NavUser user={rootUser} />
            </SidebarFooter>
        </Sidebar>
    );
}
