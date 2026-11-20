export function splitConventions(conventions) {
    return {
        upcoming: conventions.filter((convention) => convention.upcoming),
        past: conventions.filter((convention) => !convention.upcoming),
    };
}

export function conventionForm(convention = null) {
    return {
        name: convention?.name ?? '',
        place: convention?.place ?? '',
        startsOn: convention?.startsOn ?? '',
        endsOn: convention?.endsOn ?? '',
        url: convention?.url ?? '',
        note: { en: convention?.note ?? '', fr: convention?.noteFr ?? '' },
    };
}

export function conventionPayload(form) {
    return { name: form.name, place: form.place, startsOn: form.startsOn, endsOn: form.endsOn, url: form.url, note: form.note.en, noteFr: form.note.fr };
}
