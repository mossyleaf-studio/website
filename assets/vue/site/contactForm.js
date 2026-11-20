export const CONTACT_LIMITS = { name: 100, email: 254, message: 4000 };

const FIELDS = Object.keys(CONTACT_LIMITS);
const SINGLE_LINE = ['name', 'email'];
const EMAIL = /^(?=[^@]{1,64}@)(?!\.)(?!.*\.\.)[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+(?<!\.)@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+$/;

export const characters = (text) => [...text].length;

export const contactPayload = ({ name, email, message, website }) => ({ name: name.trim(), email: email.trim(), message: message.trim(), website });

function fieldError(field, value) {
    if (value === '') return { key: 'required' };
    if (SINGLE_LINE.includes(field) && /[\r\n]/.test(value)) return { key: 'lineBreak' };
    if (characters(value) > CONTACT_LIMITS[field]) return { key: 'tooLong', max: CONTACT_LIMITS[field] };
    if (field === 'email' && !EMAIL.test(value)) return { key: 'email' };
    return null;
}

export function contactErrors(form) {
    const payload = contactPayload(form);
    return Object.fromEntries(FIELDS.map((field) => [field, fieldError(field, payload[field])]).filter(([, error]) => error !== null));
}

export const serverErrors = (violations) =>
    violations.reduce((errors, { propertyPath, title }) => (FIELDS.includes(propertyPath) && !(propertyPath in errors) ? { ...errors, [propertyPath]: { text: title } } : errors), {});
