import { BookOpenCheck, Megaphone, Plus } from 'lucide-react';

import { Button } from '@/components/ui/button';

import { AssignmentStatus } from './academy/assignment-status';
import { ClassSchedule } from './academy/class-schedule';
import { KpiCards } from './academy/kpi-cards';
import { PerformanceHighlights } from './academy/performance-highlights';
import { UpcomingEvents } from './academy/upcoming-events';

export default function Page() {
    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div className="space-y-1">
                    <h1 className="text-3xl tracking-tight">
                        Academy Dashboard
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Good morning, Teacher. Here's a quick overview of
                        today's activity.
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2 lg:w-fit">
                    <Button size="sm">
                        <Megaphone />
                        New Announcement
                    </Button>
                    <Button size="sm" variant="outline">
                        <BookOpenCheck />
                        Gradebook
                    </Button>
                    <Button size="sm" variant="outline">
                        <Plus />
                        Add Assignment
                    </Button>
                </div>
            </div>

            <KpiCards />

            <div className="grid grid-cols-1 gap-4 xl:grid-cols-12">
                <div className="xl:col-span-5">
                    <ClassSchedule />
                </div>
                <div className="xl:col-span-7">
                    <AssignmentStatus />
                </div>
            </div>

            <div className="grid grid-cols-1 gap-4 xl:grid-cols-12">
                <div className="xl:col-span-8">
                    <PerformanceHighlights />
                </div>
                <div className="xl:col-span-4">
                    <UpcomingEvents />
                </div>
            </div>
        </div>
    );
}
