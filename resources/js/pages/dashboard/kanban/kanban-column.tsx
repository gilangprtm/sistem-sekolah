import {
    SortableContext,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { GripVertical, MoreVertical, Plus } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

import { SortableTaskCard } from './sortable-task-card';
import type { Column, Task } from './types';

interface KanbanColumnProps {
    column: Column;
    tasks: Task[];
}

export function KanbanColumn({ column, tasks }: KanbanColumnProps) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
        isOver,
    } = useSortable({
        id: column.id,
        data: { type: 'column', columnId: column.id },
    });

    return (
        <section
            ref={setNodeRef}
            style={{
                transform: transform
                    ? `translate3d(${transform.x}px, ${transform.y}px, 0)`
                    : undefined,
                transition,
            }}
            className={cn(
                'flex min-h-0 flex-col rounded-t-xl border bg-muted/50 transition-colors',
                isOver && 'bg-muted/70',
                isDragging && 'opacity-60',
            )}
        >
            <div className="flex items-start justify-between gap-3 px-4 pt-4 pb-3">
                <div className="min-w-0 space-y-1">
                    <div className="flex items-center gap-0.5">
                        <Button
                            variant="ghost"
                            size="icon-xs"
                            className="-ml-2 cursor-grab text-foreground/70 active:cursor-grabbing"
                            aria-label={`Drag ${column.title} column`}
                            {...attributes}
                            {...listeners}
                        >
                            <GripVertical />
                        </Button>
                        <h2 className="truncate text-base leading-none font-medium">
                            {column.title}
                        </h2>
                    </div>
                    <p className="text-sm leading-none text-muted-foreground tabular-nums">
                        {tasks.length} {tasks.length === 1 ? 'task' : 'tasks'}
                    </p>
                </div>
                <div className="-mr-2 flex items-center gap-0.5 text-muted-foreground">
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Add task to ${column.title}`}
                    >
                        <Plus />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`${column.title} column actions`}
                    >
                        <MoreVertical />
                    </Button>
                </div>
            </div>

            <SortableContext
                items={tasks.map((task) => task.id)}
                strategy={verticalListSortingStrategy}
            >
                <div className="flex min-h-0 flex-1 scrollbar-thin [scrollbar-color:var(--border)_transparent] flex-col gap-3 overflow-y-auto px-3 pb-3 [&::-webkit-scrollbar]:w-1 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-border [&::-webkit-scrollbar-track]:bg-transparent">
                    {tasks.map((task) => (
                        <SortableTaskCard
                            key={task.id}
                            task={task}
                            columnId={column.id}
                        />
                    ))}
                </div>
            </SortableContext>
        </section>
    );
}
