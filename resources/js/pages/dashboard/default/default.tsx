import { MetricCards } from './metric-cards';
import { PerformanceOverview } from './performance-overview';
import { SubscriberOverview } from './subscriber-overview';

export default function Page() {
    return (
        <div className="@container/main flex flex-col gap-4 md:gap-6">
            <MetricCards />
            <PerformanceOverview />
            <SubscriberOverview />
        </div>
    );
}
