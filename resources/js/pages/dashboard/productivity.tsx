import { CalendarPanel } from './productivity/calendar-panel';
import { FocusCard } from './productivity/focus-card';
import { ProjectsSection } from './productivity/projects-section';
import { QuickActions } from './productivity/quick-actions';
import { QuoteCard } from './productivity/quote-card';
import { RecentNotesCard } from './productivity/recent-notes-card';
import { SummaryCards } from './productivity/summary-cards';
import { TasksSection } from './productivity/tasks-section';
import { WeeklySummaryCard } from './productivity/weekly-summary-card';

export default function Page() {
    return (
        <div className="grid gap-6 lg:grid-cols-12">
            <section className="lg:col-span-9">
                <div className="flex flex-col gap-6">
                    <div className="flex flex-col gap-2">
                        <h1 className="text-3xl leading-none tracking-tight text-foreground">
                            Good morning, Arham.
                        </h1>
                        <p className="text-lg leading-none text-muted-foreground">
                            Let&apos;s make today productive and meaningful.
                        </p>
                    </div>
                    <SummaryCards />
                    <TasksSection />
                    <ProjectsSection />
                    <QuickActions />
                    <QuoteCard />
                </div>
            </section>

            <section className="flex flex-col gap-6 lg:col-span-3">
                <CalendarPanel />
                <FocusCard />
                <RecentNotesCard />
                <WeeklySummaryCard />
            </section>
        </div>
    );
}
