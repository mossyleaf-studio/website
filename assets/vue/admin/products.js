const blank = (text) => text.trim() === '';

const plain = (text) => text.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();

export function completeness(product) {
    const gaps = [];
    if (product.photos.length === 0) gaps.push('noPhoto');
    if (blank(product.titleFr) || (!blank(product.description) && blank(product.descriptionFr))) gaps.push('frenchMissing');
    return gaps;
}

export function totalStock(product) {
    return product.variants.reduce((sum, variant) => sum + variant.stock, 0);
}

export function filterProducts(products, { query, category }) {
    const words = plain(query).split(/\s+/).filter(Boolean);
    return products.filter((product) => {
        if (category && product.category.id !== category) return false;
        const haystack = plain([product.reference, product.name, product.displayName, product.title, product.titleFr, ...product.variants.map((variant) => variant.sku)].join(' '));
        return words.every((word) => haystack.includes(word));
    });
}
