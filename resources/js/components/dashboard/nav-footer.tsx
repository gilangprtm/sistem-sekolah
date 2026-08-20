import type { LucideIcon } from 'lucide-react';
import { BookOpen, FolderGit2 } from 'lucide-react';
import type { ComponentPropsWithoutRef } from 'react';

import { SidebarSupportCard } from '@/components/dashboard/sidebar-support-card';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';

export type NavFooterItem = {
    title: string;
    url: string;
    icon?: LucideIcon | null;
};

export const defaultNavFooterItems: NavFooterItem[] = [
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

export function NavFooter({
    items = defaultNavFooterItems,
    className,
    showSupportCard = true,
    ...props
}: ComponentPropsWithoutRef<typeof SidebarGroup> & {
    items?: NavFooterItem[];
    showSupportCard?: boolean;
}) {
    return (
        <div className="flex flex-col gap-2">
            {showSupportCard ? <SidebarSupportCard /> : null}
            <SidebarGroup
                {...props}
                className={cn(
                    'group-data-[collapsible=icon]:p-0',
                    className,
                )}
            >
                <SidebarGroupContent>
                    <SidebarMenu>
                        {items.map((item) => (
                            <SidebarMenuItem key={item.title}>
                                <SidebarMenuButton
                                    asChild
                                    className="text-neutral-600 hover:text-neutral-800 dark:text-neutral-300 dark:hover:text-neutral-100"
                                >
                                    <a
                                        href={item.url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        {item.icon ? (
                                            <item.icon className="h-5 w-5" />
                                        ) : null}
                                        <span>{item.title}</span>
                                    </a>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        ))}
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>
        </div>
    );
}
