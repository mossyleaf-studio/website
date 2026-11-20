export const countingZeroAsOne = (choice, choicesLength) => {
    if (choicesLength === 3) return Math.min(choice, 2);
    return choice <= 1 ? 0 : 1;
};
