function csrfToken(): string {
    const meta = document.querySelector<HTMLMetaElement>(
        'meta[name="csrf-token"]',
    );
    if (meta?.content) {
        return meta.content;
    }

    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
    if (!m?.[1]) {
        return '';
    }

    return decodeURIComponent(m[1]).replace(/^"|"$/g, '');
}

/**
 * POST multipart form data and parse JSON (for settings import preview / store).
 */
export async function postFormDataJson(
    url: string,
    formData: FormData,
): Promise<unknown> {
    const token = csrfToken();
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(token ? { 'X-XSRF-TOKEN': token } : {}),
        },
        body: formData,
    });

    const data: unknown = await response.json().catch(() => ({}));

    if (!response.ok) {
        const d = data as Record<string, unknown>;
        if (typeof d.message === 'string') {
            throw new Error(d.message);
        }
        const errors = d.errors as Record<string, string[]> | undefined;
        if (errors) {
            const first = Object.values(errors).flat()[0];
            if (first) {
                throw new Error(first);
            }
        }
        throw new Error(`Request failed (${response.status})`);
    }

    return data;
}
