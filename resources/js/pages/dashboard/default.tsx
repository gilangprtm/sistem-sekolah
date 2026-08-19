import { MetricCards } from './default/metric-cards';
import { PerformanceOverview } from './default/performance-overview';
import { SubscriberOverview } from './default/subscriber-overview';

export default function Page() {
    return (
        <div className="@container/main flex flex-col gap-4 md:gap-6">
            <MetricCards />
            <PerformanceOverview />
            <SubscriberOverview />
        </div>
    );
}
