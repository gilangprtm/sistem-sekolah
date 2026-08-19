import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';

import { CardOverview } from './card-overview';
import { CashFlowOverview } from './cash-flow-overview';
import { IncomeReliability } from './income-reliability';
import { MonthlyCashFlow } from './kpis/monthly-cash-flow';
import { NetWorth } from './kpis/net-worth';
import { PrimaryAccount } from './kpis/primary-account';
import { SavingsRate } from './kpis/savings-rate';
import { SpendingBreakdown } from './spending-breakdown';

export default function Page() {
    return (
        <div>
            <Tabs className="gap-4" defaultValue="overview">
                <TabsList>
                    <TabsTrigger value="overview">Overview</TabsTrigger>
                    <TabsTrigger disabled value="activity">
                        Activity
                    </TabsTrigger>
                    <TabsTrigger disabled value="insights">
                        Insights
                    </TabsTrigger>
                    <TabsTrigger disabled value="utilities">
                        Utilities
                    </TabsTrigger>
                </TabsList>

                <TabsContent value="overview">
                    <div className="flex flex-col gap-4 **:data-[slot=card]:shadow-xs">
                        <div className="grid grid-cols-1 gap-4 *:data-[slot=card]:gap-2 *:data-[slot=card]:bg-linear-to-t *:data-[slot=card]:from-primary/5 *:data-[slot=card]:to-card sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-4">
                            <PrimaryAccount />
                            <NetWorth />
                            <MonthlyCashFlow />
                            <SavingsRate />
                        </div>

                        <div className="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
                            <div className="flex flex-col gap-4">
                                <CashFlowOverview />

                                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                    <SpendingBreakdown />
                                    <IncomeReliability />
                                </div>
                            </div>

                            <CardOverview />
                        </div>
                    </div>
                </TabsContent>
            </Tabs>
        </div>
    );
}
