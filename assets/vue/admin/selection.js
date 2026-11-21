export const tick = (ids, id, checked) => {
    if (!checked) return ids.filter((candidate) => candidate !== id);
    return ids.includes(id) ? ids : [...ids, id];
};

export function headState(ids, visible) {
    const ticked = visible.filter((id) => ids.includes(id)).length;
    if (ticked === 0) return false;
    return ticked === visible.length ? true : 'indeterminate';
}

export const tickAll = (ids, visible, checked) => (checked ? [...new Set([...ids, ...visible])] : ids.filter((id) => !visible.includes(id)));

export const keepVisible = (ids, visible) => ids.filter((id) => visible.includes(id));
