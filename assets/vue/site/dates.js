const TAGS = { en: 'en-GB', fr: 'fr-FR' };

const partsOf = (date) => {
    const [year, month, day] = date.split('-').map(Number);

    return { year, month, day };
};

const monthName = (month, locale, width = 'long') => new Intl.DateTimeFormat(TAGS[locale] ?? TAGS.en, { month: width, timeZone: 'UTC' }).format(Date.UTC(2000, month - 1, 1));

export function formatRange(startsOn, endsOn, locale) {
    const start = partsOf(startsOn);
    const end = partsOf(endsOn);
    const endText = `${end.day} ${monthName(end.month, locale)} ${end.year}`;

    if (start.year !== end.year) {
        return `${start.day} ${monthName(start.month, locale)} ${start.year} – ${endText}`;
    }
    if (start.month !== end.month) {
        return `${start.day} ${monthName(start.month, locale)} – ${endText}`;
    }
    if (start.day !== end.day) {
        return `${start.day}–${endText}`;
    }

    return endText;
}

export function stubOf(startsOn, endsOn, locale, currentYear) {
    const start = partsOf(startsOn);
    const end = partsOf(endsOn);
    const sameMonth = start.year === end.year && start.month === end.month;

    return {
        days: start.day === end.day && sameMonth ? `${end.day}` : `${start.day}–${end.day}`,
        month: sameMonth ? monthName(end.month, locale) : `${monthName(start.month, locale, 'short')}–${monthName(end.month, locale, 'short')}`,
        year: start.year !== end.year ? `${start.year}–${end.year}` : end.year === currentYear ? null : `${end.year}`,
    };
}
