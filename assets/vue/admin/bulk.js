export function planBulk(items, skip = () => null) {
    const targets = [];
    const skipped = {};
    for (const item of items) {
        const reason = skip(item);
        if (reason) skipped[reason] = (skipped[reason] ?? 0) + 1;
        else targets.push(item);
    }
    return { targets, skipped };
}

export async function runBulk(items, { skip, perform }, progress = () => {}) {
    const { targets, skipped } = planBulk(items, skip);
    const failed = [];
    let done = 0;
    let reason = null;
    for (const item of targets) {
        try {
            await perform(item);
            done += 1;
        } catch (error) {
            failed.push(item.id);
            reason ??= error.message;
        }
        progress(done + failed.length, targets.length);
    }
    return { done, skipped, failed, reason };
}

export function bulkMessage(t, doneKey, { done, skipped, failed, reason }) {
    return [
        done > 0 && t(doneKey, { count: done }, done),
        ...Object.entries(skipped).map(([why, count]) => t(`admin.bulk.skipped.${why}`, { count }, count)),
        failed.length > 0 && t('admin.bulk.failed', { count: failed.length, reason }, failed.length),
    ].filter(Boolean).join(' ');
}
