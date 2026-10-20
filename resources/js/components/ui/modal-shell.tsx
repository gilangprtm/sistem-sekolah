import type { ComponentProps } from 'react';
import { DialogContent } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

type ModalShellProps = ComponentProps<typeof DialogContent> & {
    size?: 'small' | 'medium' | 'large' | 'extra-large';
};

const sizeClasses: Record<NonNullable<ModalShellProps['size']>, string> = {
    small: 'lg:max-w-sm',
    medium: 'lg:max-w-[35vw]',
    large: 'lg:max-w-[60vw]',
    'extra-large': 'lg:max-w-[85vw]',
};

export default function ModalShell({
    size = 'small',
    className,
    ...props
}: ModalShellProps) {
    return (
        <DialogContent
            className={cn(
                'max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-[calc(100%-2rem)]',
                sizeClasses[size],
                className,
            )}
            {...props}
        />
    );
}
