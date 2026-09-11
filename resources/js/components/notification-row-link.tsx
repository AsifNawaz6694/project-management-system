import { Link } from '@inertiajs/react';
import { type ReactNode } from 'react';

interface Props {
    /** Where the notification points, when it points anywhere. */
    href?: string | null;
    onClick: () => void;
    className?: string;
    children: ReactNode;
}

/**
 * A notification row: a link when it has a destination, a button when it does
 * not.
 *
 * Previously this was a dynamic `const Wrap = item.link ? Link : 'div'`, which
 * cannot be typed soundly — TypeScript resolves the union to Link's props and
 * demands an href that the div branch never has. A clickable div was also not
 * reachable by keyboard; a button is.
 */
export function NotificationRowLink({ href, onClick, className, children }: Props) {
    if (href) {
        return (
            <Link href={href} onClick={onClick} className={className}>
                {children}
            </Link>
        );
    }

    return (
        <button type="button" onClick={onClick} className={className}>
            {children}
        </button>
    );
}
