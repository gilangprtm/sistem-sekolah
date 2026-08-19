import { mails } from '../mail/_components/data';
import { MailComponent } from '../mail/_components/mail';
import { DEFAULT_MAIL_LAYOUT } from '../mail/_components/mail-layout-config';

export default function Page() {
    return (
        <MailComponent mails={mails} defaultLayout={[...DEFAULT_MAIL_LAYOUT]} />
    );
}
