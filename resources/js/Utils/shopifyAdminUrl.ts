export function isSafeShopifyAdminUrl(value: unknown): value is string {
    if (typeof value !== 'string') {
        return false;
    }

    try {
        const url = new URL(value);

        return (
            url.protocol === 'https:' &&
            /^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.myshopify\.com$/.test(url.hostname) &&
            /^\/admin\/orders\/[1-9]\d*$/.test(url.pathname) &&
            url.search === '' &&
            url.hash === ''
        );
    } catch {
        return false;
    }
}
