import { describe, expect, it } from 'vitest';
import cases from '../../../tests/Fixtures/rich-blocks.json';
import { parseRichBlocks } from './richBlocks.js';

describe('parseRichBlocks', () => {
    it.each(cases.map((example) => [example.name, example]))('%s', (name, example) => {
        expect(parseRichBlocks(example.text)).toEqual(example.blocks);
    });
});
