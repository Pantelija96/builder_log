// Models expose dd.MM.yyyy. dates; never pass that format to Date.parse.
// Date-only ISO values are accepted too. Time-of-day is ignored, like Android.
export function dateSortTimestamp(value, invalid = -Infinity, lenient = false) {
  const display = /^(\d{1,2})\.(\d{1,2})\.(\d{4})\./.exec(value);
  const iso = display ? null : /^(\d{4})-(\d{2})-(\d{2})/.exec(value);
  if (!display && !iso) return invalid;
  const year = Number(display ? display[3] : iso[1]);
  const month = Number(display ? display[2] : iso[2]);
  const day = Number(display ? display[1] : iso[3]);
  const date = new Date(0);
  date.setUTCFullYear(year, month - 1, day);
  if (!lenient && (date.getUTCFullYear() !== year || date.getUTCMonth() !== month - 1 || date.getUTCDate() !== day)) return invalid;
  return date.getTime();
}
