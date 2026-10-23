'use client';

import { router } from '@inertiajs/react';
import { EllipsisVertical, KeyRound, LogOut } from 'lucide-react';
import { useRef, useState } from 'react';
import ChangePasswordModal from '@/components/change-password-modal';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { getInitials } from '@/lib/utils';

export function NavUser({
    user,
}: {
    readonly user: {
        readonly name: string;
        readonly email: string;
        readonly avatar: string;
    };
}) {
    const { isMobile } = useSidebar();
    const [isChangePasswordOpen, setIsChangePasswordOpen] = useState(false);
    const userMenuTrigger = useRef<HTMLButtonElement>(null);

    const handleLogout = () => {
        router.post('/logout');
    };

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            ref={userMenuTrigger}
                            size="lg"
                            className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                        >
                            <Avatar className="h-8 w-8 rounded-lg grayscale">
                                <AvatarImage
                                    src={user.avatar || undefined}
                                    alt={user.name}
                                />
                                <AvatarFallback className="rounded-lg">
                                    {getInitials(user.name)}
                                </AvatarFallback>
                            </Avatar>
                            <div className="grid flex-1 text-left text-sm leading-tight">
                                <span className="truncate font-medium">
                                    {user.name}
                                </span>
                                <span className="truncate text-xs text-muted-foreground">
                                    {user.email}
                                </span>
                            </div>
                            <EllipsisVertical className="ml-auto size-4" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                        side={isMobile ? 'bottom' : 'right'}
                        align="end"
                        sideOffset={4}
                        onCloseAutoFocus={(event) => {
                            if (isChangePasswordOpen) {
                                event.preventDefault();
                            }
                        }}
                    >
                        <DropdownMenuLabel className="p-0 font-normal">
                            <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                <Avatar className="h-8 w-8 rounded-lg">
                                    <AvatarImage
                                        src={user.avatar || undefined}
                                        alt={user.name}
                                    />
                                    <AvatarFallback className="rounded-lg">
                                        {getInitials(user.name)}
                                    </AvatarFallback>
                                </Avatar>
                                <div className="grid flex-1 text-left text-sm leading-tight">
                                    <span className="truncate font-medium">
                                        {user.name}
                                    </span>
                                    <span className="truncate text-xs text-muted-foreground">
                                        {user.email}
                                    </span>
                                </div>
                            </div>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            onSelect={() => setIsChangePasswordOpen(true)}
                        >
                            <KeyRound />
                            Ubah Password
                        </DropdownMenuItem>
                        <DropdownMenuItem onSelect={handleLogout}>
                            <LogOut />
                            Log out
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
                {isChangePasswordOpen && (
                    <ChangePasswordModal
                        onClose={() => setIsChangePasswordOpen(false)}
                        onAfterClose={() => userMenuTrigger.current?.focus()}
                    />
                )}
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
