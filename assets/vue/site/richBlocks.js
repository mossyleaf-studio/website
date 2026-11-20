import { parseRichText } from './richText.js';

const BLANKS = /^[ \t]+|[ \t]+$/g;

export function parseRichBlocks(text) {
    const blocks = [];
    let paragraph = [];
    let items = [];

    const close = () => {
        if (paragraph.length) {
            blocks.push({ type: 'paragraph', segments: parseRichText(paragraph.join('\n')) });
        }
        if (items.length) {
            blocks.push({ type: 'list', items });
        }
        paragraph = [];
        items = [];
    };

    for (const raw of text.replace(/\r\n?/g, '\n').split('\n')) {
        const line = raw.replace(/^[ \t]+/, '');
        const marker = line.startsWith('## ') ? 'heading' : line.startsWith('- ') ? 'item' : null;
        const content = (marker === null ? line : line.slice(marker === 'heading' ? 3 : 2)).replace(BLANKS, '');
        if (marker !== null && content === '') {
            continue;
        }
        if (content === '' || marker === 'heading' || (marker === 'item' ? paragraph.length > 0 : items.length > 0)) {
            close();
        }
        if (marker === 'heading') {
            blocks.push({ type: 'heading', segments: parseRichText(content) });
        } else if (marker === 'item') {
            items.push(parseRichText(content));
        } else if (content !== '') {
            paragraph.push(content);
        }
    }
    close();

    return blocks;
}
