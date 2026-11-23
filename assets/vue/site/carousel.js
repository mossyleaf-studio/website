export const carouselState = ({ scrollLeft, clientWidth, scrollWidth }) => {
    const hidden = scrollWidth - clientWidth;

    if (hidden < 1) {
        return { needed: false, previous: false, next: false };
    }

    return { needed: true, previous: scrollLeft >= 1, next: scrollLeft <= hidden - 1 };
};
