import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    CreditCard,
    CalendarDays,
    ClipboardList,
    DoorOpen,
    Files,
    GraduationCap,
    LayoutDashboard,
    LayoutGrid,
    Lock,
    NotebookPen,
    NotebookTabs,
    PackageSearch,
    School,
    UserRound,
    Users,
    WalletCards,
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

import { NavMain } from './nav-main';
import { NavUser } from './nav-user';

type PageProps = {
    documentNavigation?: {
        id: string;
        key: string;
        group: string;
        url: string;
    }[];
    auth?: {
        user?: {
            name: string;
            email: string;
        };
        permissions?: string[];
        roles?: string[];
    };
};

export function AppSidebar({
    variant = 'sidebar',
    collapsible = 'offcanvas',
    ...props
}: React.ComponentProps<typeof Sidebar> & {
    variant?: 'sidebar' | 'floating' | 'inset';
    collapsible?: 'offcanvas' | 'icon' | 'none';
}) {
    const { auth, documentNavigation = [] } = usePage<PageProps>().props;
    const permissions = auth?.permissions ?? [];
    const roles = auth?.roles ?? [];
    const isSuperAdmin = roles.includes('Super Admin');

    const can = (permission: string) =>
        isSuperAdmin || permissions.includes(permission);

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
    const teacherItems: NavMainItem[] = [];
    const curriculumItems: NavMainItem[] = [];
    const examItems: NavMainItem[] = [];
    const studentAffairsItems: NavMainItem[] = [];
    const canteenItems: NavMainItem[] = [];

    if (can('teacher.agenda.manage')) {
        teacherItems.push({
            id: 'teacher-agenda',
            title: 'Agenda Guru',
            url: '/guru/agenda',
            icon: NotebookPen,
        });
    }

    if (can('teacher.homeroom.view')) {
        teacherItems.push({
            id: 'teacher-homeroom',
            title: 'Wali Kelas',
            url: '/guru/wali-kelas',
            icon: NotebookTabs,
        });
    }

    if (can('student.card.view')) {
        studentAffairsItems.push({
            id: 'student-cards',
            title: 'Kartu Pelajar',
            url: '/students/cards',
            icon: CreditCard,
        });
    }

    if (can('inventory.dashboard.view')) {
        inventoryItems.push({
            id: 'inventory-dashboard',
            title: 'Dashboard Inventaris',
            url: '/inventaris/inventory/dashboard',
            icon: LayoutDashboard,
        });
    }

    if (can('inventory.view')) {
        inventoryItems.push({
            id: 'inventory',
            title: 'Inventaris',
            url: '/inventaris/inventory',
            icon: PackageSearch,
        });
    }

    if (can('inventory.room.view')) {
        inventoryItems.push({
            id: 'inventory-rooms',
            title: 'Inventaris Ruangan',
            url: '/inventaris/inventory-rooms',
            icon: DoorOpen,
        });
    }

    if (can('kantin.dashboard.view')) {
        canteenItems.push({
            id: 'canteen-dashboard',
            title: 'Dashboard Kantin',
            url: '/kantin/dashboard',
            icon: LayoutDashboard,
        });
    }

    if (can('kantin.barang.view')) {
        canteenItems.push({
            id: 'canteen-items',
            title: 'Master Barang',
            url: '/kantin/barang',
            icon: PackageSearch,
        });
    }

    if (can('kantin.saldo.view') && can('kantin.saldo.history.view')) {
        canteenItems.push({
            id: 'canteen-balance',
            title: 'Saldo Kantin',
            url: '/kantin/saldo',
            icon: WalletCards,
        });
    }

    if (can('student.view')) {
        adminItems.push({
            id: 'students',
            title: 'Siswa',
            url: '/students',
            icon: UserRound,
        });
    }

    if (can('teacher.view')) {
        adminItems.push({
            id: 'teachers',
            title: 'Guru & Staff',
            url: '/teachers',
            icon: GraduationCap,
        });
    }

    if (can('curriculum.view')) {
        curriculumItems.push({
            id: 'academic-years',
            title: 'Tahun Ajaran & Semester',
            url: '/kurikulum/academic-years',
            icon: LayoutDashboard,
        });
    }

    if (can('subject.view')) {
        curriculumItems.push({
            id: 'subjects',
            title: 'Mata Pelajaran',
            url: '/kurikulum/subjects',
            icon: BookOpen,
        });
    }

    if (can('curriculum.rombel.view')) {
        curriculumItems.push({
            id: 'rombels',
            title: 'Rombel',
            url: '/kurikulum/rombels',
            icon: School,
        });
    }

    if (can('curriculum.view')) {
        curriculumItems.push({
            id: 'schedules',
            title: 'Jadwal Pelajaran',
            url: '/kurikulum/schedule',
            icon: CalendarDays,
        });
        curriculumItems.push({
            id: 'management-class',
            title: 'Manajemen Kelas',
            url: '/kurikulum/management-class',
            icon: Users,
        });
    }

    if (can('curriculum.teacher_subject.view')) {
        curriculumItems.push({
            id: 'teacher-subjects',
            title: 'Guru Mata Pelajaran',
            url: '/kurikulum/teacher-subjects',
            icon: GraduationCap,
        });
    }

    if (can('curriculum.uas.view')) {
        curriculumItems.push({
            id: 'exams',
            title: 'Ujian',
            url: '/kurikulum/ujian',
            icon: ClipboardList,
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

    const documentGroups = {
        inventory: inventoryItems,
        curriculum: curriculumItems,
        teacher: teacherItems,
        student_affairs: studentAffairsItems,
        canteen: canteenItems,
        administration: adminItems,
    };

    for (const item of documentNavigation) {
        documentGroups[item.key as keyof typeof documentGroups]?.push({
            id: item.id,
            title: 'Dokumen',
            url: item.url,
            icon: Files,
        });
    }

    const navGroups = [
        { id: 1, label: 'Umum', items: dashboardItems },
        ...(inventoryItems.length > 0
            ? [{ id: 2, label: 'Inventaris', items: inventoryItems }]
            : []),
        ...(curriculumItems.length > 0
            ? [{ id: 3, label: 'Kurikulum', items: curriculumItems }]
            : []),
        ...(teacherItems.length > 0
            ? [{ id: 4, label: 'Guru', items: teacherItems }]
            : []),
        ...(studentAffairsItems.length > 0
            ? [{ id: 5, label: 'Kesiswaan', items: studentAffairsItems }]
            : []),
        ...(canteenItems.length > 0
            ? [{ id: 6, label: 'Kantin', items: canteenItems }]
            : []),
        ...(examItems.length > 0
            ? [{ id: 7, label: 'Ujian', items: examItems }]
            : []),
        ...(adminItems.length > 0
            ? [{ id: 8, label: 'Administrasi', items: adminItems }]
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
                                    Portal SMPN 17 Denpasar
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
