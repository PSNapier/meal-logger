import type { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}

/** Local-timezone ISO date (YYYY-MM-DD). Avoids UTC date shift from toISOString(). */
export function localIsoDate(d = new Date()): string {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(
        d.getDate(),
    ).padStart(2, '0')}`;
}

/** Returns true for well-formed, calendar-valid YYYY-MM-DD strings. */
export function isValidIsoDate(s: string): boolean {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(s)) {
        return false;
    }

    const d = new Date(`${s}T00:00:00`);

    return !isNaN(d.getTime()) && d.toISOString().startsWith(s);
}
