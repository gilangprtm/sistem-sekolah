import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    FolderGit2,
    LayoutDashboard,
    LayoutGrid,
    Lock,
    PackageSearch,
    Users,
} from 'lucide-react';
import { useShallow } from 'zustand/react/shallow';

import AppLogo from '@/components/app-logo';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import type { NavMainItem } from '@/navigation/sidebar/sidebar-items';
import { dashboard } from '@/routes';
import { usePreferencesStore } from '@/stores/preferences/preferences-provider';

import { NavFooter  } from './nav-footer';
import type {NavFooterItem} from './nav-footer';
import { NavMain } from './nav-main';
import { NavUser } from './nav-user';

type PageProps = {
    auth?: {
        user?: {
            name: string;
            email: string;
        };
        permissions?: string[];
        roles?: string[];
    };
};

const footerNavItems: NavFooterItem[] = [
    {
        title: 'Repository',
        url: 'https://github.com/gilangprtm/sistem-sekolah',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        url: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
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
    const { auth } = usePage<PageProps>().props;
    const permissions = auth?.permissions ?? [];
    const roles = auth?.roles ?? [];
    const isSuperAdmin = roles.includes('Super Admin');

    const can = (permission: string) => isSuperAdmin || permissions.includes(permission);

    const { sidebarVariant, sidebarCollapsible, isSynced } = usePreferencesStore(
        useShallow((s) => ({
            sidebarVariant: s.values.sidebar_variant,
            sidebarCollapsible: s.values.sidebar_collapsible,
            isSynced: s.isSynced,
        })),
    );

    const effectiveVariant = isSynced ? sidebarVariant : variant;
    const effectiveCollapsible = isSynced ? sidebarCollapsible : collapsible;

    const dashboardItems: NavMainItem[] = [
        {
            id: 'dashboard',
            title: 'Dashboard',
            url: dashboard().url,
            icon: LayoutGrid,
        },
    ];
    const inventoryItems: NavMainItem[] = [];
    const adminItems: NavMainItem[] = [];

    if (can('inventory.dashboard.view')) {
        inventoryItems.push({
            id: 'inventory-dashboard',
            title: 'Dashboard Inventaris',
            url: '/inventory/dashboard',
            icon: LayoutDashboard,
        });
    }

    if (can('inventory.view')) {
        inventoryItems.push({
            id: 'inventory',
            title: 'Inventaris',
            url: '/inventory',
            icon: PackageSearch,
        });
    }

    if (can('users.manage')) {
        adminItems.push({
            id: 'users',
            title: 'Users',
            url: '/users',
            icon: Users,
        });
    }

    if (can('roles.manage')) {
        adminItems.push({
            id: 'roles',
            title: 'Roles & Permissions',
            url: '/roles',
            icon: Lock,
        });
    }

    const navGroups = [
        { id: 1, label: 'Umum', items: dashboardItems },
        ...(inventoryItems.length > 0
            ? [{ id: 2, label: 'Inventaris', items: inventoryItems }]
            : []),
        ...(adminItems.length > 0
            ? [{ id: 3, label: 'Administrasi', items: adminItems }]
            : []),
    ];

    return (
        <Sidebar
            {...props}
            variant={effectiveVariant}
            collapsible={effectiveCollapsible}
        >
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                                <span className="truncate text-sm font-semibold tracking-tight group-data-[collapsible=icon]:hidden">
                                    SMPN 17 DENPASAR
                                </span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>
            <SidebarContent>
                <NavMain items={navGroups} />
            </SidebarContent>
            <SidebarFooter>
                <NavFooter items={footerNavItems} showSupportCard={false} />
                <NavUser
                    user={{
                        name: auth?.user?.name ?? 'User',
                        email: auth?.user?.email ?? '',
                        avatar: '',
                    }}
                />
            </SidebarFooter>
        </Sidebar>
    );
}
