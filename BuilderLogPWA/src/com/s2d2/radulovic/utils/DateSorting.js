export function dateSortTimestamp(value) {
    if (!value) {
        return 0;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return 0;
    }

    return date.getTime();
}