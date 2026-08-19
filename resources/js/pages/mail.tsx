import { getClientCookie } from '@/lib/cookie.client';

import { mails } from './mail/_components/data';
import { MailComponent } from './mail/_components/mail';
import {
    DEFAULT_MAIL_LAYOUT,
    MAIL_LAYOUT_COOKIE,
} from './mail/_components/mail-layout-config';

export default function Page() {
    const layoutCookie = getClientCookie(MAIL_LAYOUT_COOKIE);

    const layout = layoutCookie
        ? JSON.parse(layoutCookie)
        : DEFAULT_MAIL_LAYOUT;

    return <MailComponent mails={mails} defaultLayout={layout} />;
}
