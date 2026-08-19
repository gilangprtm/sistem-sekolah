import { infrastructureGroups } from './infrastructure-data';
import { InfrastructureHeader } from './infrastructure-header';
import { ProjectEnvironments } from './project-environments';

// Import this stylesheet in any page or component that renders country flag classes.
import '../../../../css/flags-icons/flags.css';

export default function Page() {
    return (
        <div className="flex flex-col gap-4">
            <InfrastructureHeader />

            <div className="flex flex-col gap-4">
                {infrastructureGroups.map((group) => (
                    <ProjectEnvironments key={group.name} group={group} />
                ))}
            </div>
        </div>
    );
}
