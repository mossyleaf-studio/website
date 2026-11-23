import { readdirSync, readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { expect, test } from 'vitest';

const files = (directory) =>
    readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
        const path = new URL(`${entry.name}${entry.isDirectory() ? '/' : ''}`, directory);

        return entry.isDirectory() ? files(path) : [path];
    });

test('the public site paints without blend modes, which Firefox scrolls slowly on old machines', () => {
    const offenders = [new URL('./', import.meta.url), new URL('../../styles/site/', import.meta.url)]
        .flatMap(files)
        .filter((file) => /\.(css|vue)$/.test(file.pathname))
        .filter((file) => /(background|mix)-blend-mode/.test(readFileSync(file, 'utf8')))
        .map((file) => fileURLToPath(file).replace(/^.*\/assets\//, 'assets/'));

    expect(offenders).toEqual([]);
});

test('the public site styles do without :has(), which Firefox before 121 does not know', () => {
    const offenders = [new URL('./', import.meta.url), new URL('../../styles/site/', import.meta.url)]
        .flatMap(files)
        .filter((file) => /\.(css|vue)$/.test(file.pathname))
        .filter((file) => readFileSync(file, 'utf8').includes(':has('))
        .map((file) => fileURLToPath(file).replace(/^.*\/assets\//, 'assets/'));

    expect(offenders).toEqual([]);
});
