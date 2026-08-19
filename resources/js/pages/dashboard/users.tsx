import { Head } from '@inertiajs/react';

import { users } from './users/data';
import { Users } from './users/users';

export default function Page() {
    return (
        <>
            <Head title="Users" />
            <Users users={users} />
        </>
    );
}
