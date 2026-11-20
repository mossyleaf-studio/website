import { EMAIL, characters } from './contactForm.js';

export const CHECKOUT_LIMITS = { email: 254, name: 120, phone: 32, line1: 120, line2: 120, postalCode: 16, city: 80, region: 80 };
export const CHECKOUT_FIELDS = ['email', 'name', 'phone', 'line1', 'line2', 'postalCode', 'city', 'country', 'region', 'terms'];

const REQUIRED = ['email', 'name', 'line1', 'postalCode', 'city'];
const TEXTS = Object.keys(CHECKOUT_LIMITS);

export const needsRegion = (zone) => zone === 'world';

export const emptyDelivery = () => ({ email: '', name: '', phone: '', line1: '', line2: '', postalCode: '', city: '', region: '', terms: false });

const optional = (text) => text.trim() || null;

export const orderPayload = (form, { lines, country, expectedTotal, zone }) => ({
    lines: lines.map(({ sku, quantity }) => ({ sku, quantity })),
    country,
    expectedTotal,
    email: form.email.trim(),
    name: form.name.trim(),
    phone: optional(form.phone),
    line1: form.line1.trim(),
    line2: optional(form.line2),
    postalCode: form.postalCode.trim(),
    city: form.city.trim(),
    region: needsRegion(zone) ? optional(form.region) : null,
    terms: form.terms,
});

function textError(field, value, required) {
    if (value === '') return required ? { key: 'form.required' } : null;
    if (/[\u0000-\u001f\u007f\u2028\u2029]/u.test(value)) return { key: 'form.lineBreak' };
    if (characters(value) > CHECKOUT_LIMITS[field]) return { key: 'form.tooLong', max: CHECKOUT_LIMITS[field] };
    if (field === 'email' && !EMAIL.test(value)) return { key: 'form.email' };
    return null;
}

export function checkoutErrors(form, { country, zone }) {
    const region = needsRegion(zone);
    const errors = TEXTS.filter((field) => field !== 'region' || region).map((field) => [field, textError(field, form[field].trim(), REQUIRED.includes(field) || field === 'region')]);
    errors.push(['country', country ? null : { key: 'checkout.errors.country' }], ['terms', form.terms ? null : { key: 'checkout.errors.terms' }]);

    return Object.fromEntries(errors.filter(([, error]) => error !== null));
}

export const serverErrors = (violations) =>
    violations.reduce((errors, { propertyPath, title }) => (CHECKOUT_FIELDS.includes(propertyPath) && !(propertyPath in errors) ? { ...errors, [propertyPath]: { text: title } } : errors), {});

export const firstInvalid = (errors) => CHECKOUT_FIELDS.find((field) => field in errors) ?? null;
