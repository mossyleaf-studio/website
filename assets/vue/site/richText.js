const LINK = /\[([^\]\n]+)\]\((https:\/\/[^\s)]+)\)/g;

export function parseRichText(text) {
    const segments = [];
    let cursor = 0;

    for (const match of text.matchAll(LINK)) {
        if (match.index > cursor) {
            segments.push({ text: text.slice(cursor, match.index) });
        }
        segments.push({ text: match[1], href: match[2] });
        cursor = match.index + match[0].length;
    }

    if (cursor < text.length) {
        segments.push({ text: text.slice(cursor) });
    }

    return segments;
}
