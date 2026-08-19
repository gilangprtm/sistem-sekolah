import { ChartAreaInteractive } from './default-v1/chart-area-interactive';
import data from './default-v1/data.json';
import { ProposalSectionsTable } from './default-v1/proposal-sections-table/table';
import { SectionCards } from './default-v1/section-cards';

export default function Page() {
    return (
        <div className="@container/main flex flex-col gap-4 md:gap-6">
            <SectionCards />
            <ChartAreaInteractive />
            <ProposalSectionsTable data={data} />
        </div>
    );
}
