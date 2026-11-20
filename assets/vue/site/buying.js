export const preselectedVariant = (variants) => variants.find((variant) => variant.available > 0) ?? null;

export const boundedQuantity = (value, available) => Math.min(Math.max(1, available), Math.max(1, Math.trunc(value) || 1));
