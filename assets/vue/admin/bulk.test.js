import { describe, expect, it } from 'vitest';
import { bulkMessage, planBulk, runBulk } from './bulk.js';

const items = [{ id: 'a', paid: true }, { id: 'b', paid: false }, { id: 'c', paid: true }, { id: 'd', paid: true }];
const skip = (item) => (item.paid ? null : 'notPaid');

describe('planBulk', () => {
    it('keeps the items the action applies to and counts the others by reason', () => {
        const plan = planBulk(items, skip);
        expect(plan.targets.map((item) => item.id)).toEqual(['a', 'c', 'd']);
        expect(plan.skipped).toEqual({ notPaid: 1 });
    });

    it('applies to everything without a skip rule', () => {
        expect(planBulk(items).targets).toHaveLength(4);
    });
});

describe('runBulk', () => {
    it('calls one item after the other and counts done, skipped and refused', async () => {
        const calls = [];
        let running = 0;
        const perform = async (item) => {
            running += 1;
            expect(running).toBe(1);
            calls.push(item.id);
            await Promise.resolve();
            running -= 1;
            if (item.id !== 'a') throw new Error(`refused ${item.id}`);
        };
        const progress = [];

        const outcome = await runBulk(items, { skip, perform }, (done, total) => progress.push([done, total]));

        expect(calls).toEqual(['a', 'c', 'd']);
        expect(outcome).toEqual({ done: 1, skipped: { notPaid: 1 }, failed: ['c', 'd'], reason: 'refused c' });
        expect(progress).toEqual([[1, 3], [2, 3], [3, 3]]);
    });

    it('reports a clean run', async () => {
        const outcome = await runBulk(items.slice(0, 1), { perform: async () => {} });
        expect(outcome).toEqual({ done: 1, skipped: {}, failed: [], reason: null });
    });
});

describe('bulkMessage', () => {
    const t = (key, named, count) => `${key}:${JSON.stringify(named)}:${count}`;

    it('says how many were done, skipped and why, and refused with the first reason', () => {
        expect(bulkMessage(t, 'done.key', { done: 2, skipped: { notPaid: 1, retired: 2 }, failed: ['c'], reason: 'No.' })).toBe(
            'done.key:{"count":2}:2 admin.bulk.skipped.notPaid:{"count":1}:1 admin.bulk.skipped.retired:{"count":2}:2 admin.bulk.failed:{"count":1,"reason":"No."}:1',
        );
    });

    it('leaves out what did not happen', () => {
        expect(bulkMessage(t, 'done.key', { done: 3, skipped: {}, failed: [], reason: null })).toBe('done.key:{"count":3}:3');
        expect(bulkMessage(t, 'done.key', { done: 0, skipped: {}, failed: ['a'], reason: 'No.' })).toBe('admin.bulk.failed:{"count":1,"reason":"No."}:1');
    });
});
