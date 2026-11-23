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

export function collectionLabel(collection, language) {
    const [own, other] = language === 'fr' ? [collection.labelFr, collection.label] : [collection.label, collection.labelFr];
    return own || other || collection.name;
}

export function filterProducts(products, { query, category, collection = '' }) {
    const words = plain(query).split(/\s+/).filter(Boolean);
    return products.filter((product) => {
        if (category && product.category.id !== category) return false;
        if (collection === 'none' && product.collection) return false;
        if (collection && collection !== 'none' && product.collection?.id !== collection) return false;
        const haystack = plain([product.reference, product.name, product.displayName, product.title, product.titleFr, ...product.variants.map((variant) => variant.sku)].join(' '));
        return words.every((word) => haystack.includes(word));
    });
}

export function pickSkip(product) {
    if (product.retired) return 'retired';
    return product.picked ? 'alreadyPicked' : null;
}

export function pickState(product) {
    if (!product.picked) return 'off';
    return product.visible ? 'on' : 'hidden';
}

export function unpickSkip(product) {
    return product.picked ? null : 'notPicked';
}
