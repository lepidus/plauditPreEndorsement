export const MAX_DISPLAY_LENGTH = 40;

export function truncate(value, max = MAX_DISPLAY_LENGTH) {
  if (!value) return "";
  const str = String(value);
  return str.length > max ? str.substring(0, max) + "…" : str;
}
