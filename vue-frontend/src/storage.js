export function readText(key) {
  try {
    return globalThis.localStorage?.getItem(key) ?? null;
  } catch {
    return null;
  }
}

export function readJson(key, fallback, validate = () => true) {
  try {
    const raw = readText(key);
    if (raw === null) return fallback;
    const value = JSON.parse(raw);
    return validate(value) ? value : fallback;
  } catch {
    return fallback;
  }
}

export function writeText(key, value) {
  try {
    globalThis.localStorage?.setItem(key, value);
    return true;
  } catch {
    return false;
  }
}

export function writeJson(key, value) {
  return writeText(key, JSON.stringify(value));
}

export function removeStored(key) {
  try {
    globalThis.localStorage?.removeItem(key);
  } catch {
    // The current tab still clears its in-memory session when storage is blocked.
  }
}
