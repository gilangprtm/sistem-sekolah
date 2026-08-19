import { KpiCards } from './crm/kpi-cards';
import { OpportunitiesSection } from './crm/opportunities-section';
import { PipelineActivity } from './crm/pipeline-activity';
import { TaskReminders } from './crm/task-reminders';

export default function Page() {
    return (
        <div className="flex flex-col gap-4 md:gap-6">
            <KpiCards />
            <PipelineActivity />
            <TaskReminders />
            <OpportunitiesSection />
        </div>
    );
}
