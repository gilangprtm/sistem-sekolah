import { Head } from '@inertiajs/react';

import { Roles } from './roles/roles';
import { roles } from './roles/roles-table/data';

export default function Page() {
    return (
        <>
            <Head title="Roles" />
            <Roles roles={roles} />
        </>
    );
}
