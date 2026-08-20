import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    FolderGit2,
    LayoutGrid,
    LayoutDashboard,
    Lock,
    PackageSearch,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

type PageProps = {
    auth?: {
        user?: {
            id: number;
            name: string;
            email: string;
        };
        permissions?: string[];
        roles?: string[];
    };
};

export function AppSidebar() {
    const { auth } = usePage<PageProps>().props;
    const permissions = auth?.permissions ?? [];
    const roles = auth?.roles ?? [];
    const isSuperAdmin = roles.includes('Super Admin');

    const can = (permission: string) => isSuperAdmin || permissions.includes(permission);

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: 'Dashboard Inventaris',
            href: '/inventory/dashboard',
            icon: LayoutDashboard,
        },
        {
            title: 'Inventaris',
            href: '/inventory',
            icon: PackageSearch,
        },
    ];

    // Menu manajemen hanya untuk Super Admin (users.manage / roles.manage)
    const managementNavItems: NavItem[] = [];

    if (can('users.manage')) {
        managementNavItems.push({
            title: 'Users',
            href: '/users',
            icon: Users,
        });
    }

    if (can('roles.manage')) {
        managementNavItems.push({
            title: 'Roles & Permissions',
            href: '/roles',
            icon: Lock,
        });
    }

    const footerNavItems: NavItem[] = [
        {
            title: 'Repository',
            href: 'https://github.com/gilangprtm/sistem-sekolah',
            icon: FolderGit2,
        },
        {
            title: 'Documentation',
            href: 'https://laravel.com/docs/starter-kits#react',
            icon: BookOpen,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
                {managementNavItems.length > 0 && (
                    <NavMain items={managementNavItems} />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
