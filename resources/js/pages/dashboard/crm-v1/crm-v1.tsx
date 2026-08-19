import { recentLeadsData } from './crm.config';
import { InsightCards } from './insight-cards';
import { OperationalCards } from './operational-cards';
import { OverviewCards } from './overview-cards';
import { RecentLeadsTable } from './recent-leads-table/table';

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
