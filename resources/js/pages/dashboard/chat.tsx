import { Chat } from '../chat/_components/chat';
import { conversations } from '../chat/_components/data';

export default function Page() {
    return <Chat conversations={conversations} />;
}
