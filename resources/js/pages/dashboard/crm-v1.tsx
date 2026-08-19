import { recentLeadsData } from './crm-v1/crm.config';
import { InsightCards } from './crm-v1/insight-cards';
import { OperationalCards } from './crm-v1/operational-cards';
import { OverviewCards } from './crm-v1/overview-cards';
import { RecentLeadsTable } from './crm-v1/recent-leads-table/table';

export default function Page() {
    return (
        <div className="flex flex-col gap-4 md:gap-6">
            <OverviewCards />
            <InsightCards />
            <OperationalCards />
            <RecentLeadsTable data={recentLeadsData} />
        </div>
    );
}
