import DOMPurify from 'dompurify';
import { marked } from 'marked';

/**
 * Render assistant markdown to safe HTML for v-html.
 * User messages should stay escaped plain text.
 */
export function assistantMarkdownToHtml(src: string): string {
    const html = marked.parse(src, { breaks: true }) as string;

    return DOMPurify.sanitize(html);
}
