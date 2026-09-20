/**
 * A small client-only basket. No prices, requests, checkout, or Eloquent sync.
 * Storage adapters implement synchronous getItem(key) and setItem(key, value).
 */
export function createBrowserBasket({
    storageKey,
    allowedIds,
    maxItems = 100,
    maxQuantity = 99,
    storage = () => globalThis.localStorage,
    migrate,
} = {}) {
    if (typeof storageKey !== 'string' || storageKey.trim() === '') {
        throw new TypeError('storageKey must be a nonempty string.');
    }
    if (!(Array.isArray(allowedIds) || allowedIds instanceof Set)
        || [...allowedIds].some((id) => typeof id !== 'string' || id === '')) {
        throw new TypeError('allowedIds must be an array or Set of nonempty strings.');
    }
    for (const [name, value] of Object.entries({ maxItems, maxQuantity })) {
        if (!Number.isSafeInteger(value) || value < 1) {
            throw new TypeError(`${name} must be a positive safe integer.`);
        }
    }
    if (typeof storage !== 'function' || (migrate !== undefined && typeof migrate !== 'function')) {
        throw new TypeError('storage and migrate must be functions.');
    }

    const allowed = new Set(allowedIds);
    const listeners = new Set();
    let lines = [];
    let persistent = false;
    let pendingWrite = false;
    let migrationAttempted = false;

    const items = () => lines.map(({ id, quantity }) => ({ id, quantity }));
    const snapshot = () => JSON.stringify({ persistent, items: lines });
    const notify = (before) => {
        if (before === snapshot()) return;
        for (const listener of [...listeners]) {
            listener(items(), { persistent });
        }
    };
    const adapter = () => {
        const value = storage();
        if (!value || typeof value.getItem !== 'function' || typeof value.setItem !== 'function') {
            throw new TypeError('Storage adapters must implement getItem and setItem.');
        }
        return value;
    };
    const persist = (target) => {
        try {
            (target ?? adapter()).setItem(storageKey, JSON.stringify({ version: 1, items: lines }));
            persistent = true;
            pendingWrite = false;
        } catch {
            persistent = false;
            pendingWrite = true;
        }
    };
    const normalize = (value) => {
        if (!Array.isArray(value)) return [];
        const result = new Map();
        for (const line of value) {
            if (!line || typeof line !== 'object' || !allowed.has(line.id)
                || !Number.isSafeInteger(line.quantity) || line.quantity <= 0) continue;
            if (!result.has(line.id) && result.size >= maxItems) continue;
            const quantity = Math.min(maxQuantity, line.quantity);
            result.set(line.id, Math.min(maxQuantity, (result.get(line.id) ?? 0) + quantity));
        }
        return [...result].map(([id, quantity]) => ({ id, quantity }));
    };
    const quantity = (value) => typeof value === 'number' && Number.isFinite(value)
        ? Math.max(0, Math.min(maxQuantity, Math.floor(value)))
        : null;
    const commit = (next) => {
        const before = snapshot();
        lines = next;
        persist();
        notify(before);
        return items();
    };

    function load() {
        const before = snapshot();
        if (pendingWrite) {
            persist();
            notify(before);
            return items();
        }
        try {
            const target = adapter();
            const raw = target.getItem(storageKey);
            if (raw === null || raw === undefined) {
                let legacy = [];
                if (!migrationAttempted && migrate) {
                    migrationAttempted = true;
                    try {
                        legacy = migrate(target);
                    } catch {
                        legacy = [];
                    }
                }
                lines = normalize(legacy);
            } else {
                const value = JSON.parse(raw);
                if (!value || value.version !== 1 || !Array.isArray(value.items)) {
                    throw new TypeError('Unsupported browser basket data.');
                }
                migrationAttempted = true;
                lines = normalize(value.items);
            }
            // Retain an empty envelope too: clear must never revive legacy data.
            persist(target);
        } catch {
            persistent = false;
        }
        notify(before);
        return items();
    }

    const basket = {
        items,
        load,
        add(id, amount = 1) {
            const addition = quantity(amount);
            if (!allowed.has(id) || addition === null || addition === 0) return items();
            const next = items();
            const existing = next.find((line) => line.id === id);
            if (existing) {
                existing.quantity = Math.min(maxQuantity, existing.quantity + addition);
            } else if (next.length < maxItems) {
                next.push({ id, quantity: addition });
            } else {
                return items();
            }
            return commit(next);
        },
        set(id, value) {
            const amount = quantity(value);
            if (!allowed.has(id) || amount === null) return items();
            if (amount === 0) return basket.remove(id);
            const next = items();
            const existing = next.find((line) => line.id === id);
            if (existing) {
                existing.quantity = amount;
            } else if (next.length < maxItems) {
                next.push({ id, quantity: amount });
            } else {
                return items();
            }
            return commit(next);
        },
        remove(id) {
            if (!allowed.has(id)) return items();
            return commit(lines.filter((line) => line.id !== id));
        },
        clear() {
            migrationAttempted = true;
            return commit([]);
        },
        subscribe(listener) {
            if (typeof listener !== 'function') throw new TypeError('Listener must be a function.');
            listeners.add(listener);
            return () => listeners.delete(listener);
        },
        get persistent() {
            return persistent;
        },
    };

    load();

    return basket;
}
