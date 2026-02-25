const _CONVERSION_UNIT = 1024;

/**
 * Given a value in megabytes converts it back down into bytes.
 */
function mbToBytes(megabytes: number): number {
    return Math.floor(megabytes * _CONVERSION_UNIT * _CONVERSION_UNIT);
}

/**
 * Given an amount of bytes, converts them into a human readable string format
 * using "1024" as the divisor.
 */
function bytesToString(bytes: number, decimals = 2): string {
    const k = _CONVERSION_UNIT;

    if (bytes < 1) return '0 Bytes';

    decimals = Math.floor(Math.max(0, decimals));
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    const value = Number((bytes / Math.pow(k, i)).toFixed(decimals));

    return `${value} ${['Bytes', 'KiB', 'MiB', 'GiB', 'TiB'][i]}`;
}

/**
 * Formats an IPv4 or IPv6 address.
 */
function ip(value: string): string {
    // noinspection RegExpSimplifiable
    return /([a-f0-9:]+:+)+[a-f0-9]+/.test(value) ? `[${value}]` : value;
}

/**
 * Formats an IP alias and port. 
 * If the alias is '@disabled', it returns a placeholder text.
 * If the alias starts with an '@' symbol, it bypasses standard Pterodactyl 
 * formatting, allowing for completely custom strings (like SRV domains or 
 * custom external ports) to be displayed without the default allocation 
 * port being forcefully appended.
 */
function formatIpAlias(alias: string | null, ipAddress: string, port: number): string {
    if (alias === '@disabled') {
        return 'Айпи недоступен';
    }

    if (alias && alias.startsWith('@')) {
        return alias.substring(1);
    }

    const displayAddress = alias || ip(ipAddress);
    return `${displayAddress}:${port}`;
}

export { ip, mbToBytes, bytesToString, formatIpAlias };
