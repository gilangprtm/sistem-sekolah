import { CalendarPanel } from './calendar-panel';
import { FocusCard } from './focus-card';
import { ProjectsSection } from './projects-section';
import { QuickActions } from './quick-actions';
import { QuoteCard } from './quote-card';
import { RecentNotesCard } from './recent-notes-card';
import { SummaryCards } from './summary-cards';
import { TasksSection } from './tasks-section';
import { WeeklySummaryCard } from './weekly-summary-card';

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
