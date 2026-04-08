function encodeSvgBase64(svg: string): string {
    if (typeof globalThis.btoa === 'function') {
        const utf8Bytes = encodeURIComponent(svg).replace(
            /%([0-9A-F]{2})/g,
            (_, hex: string) => String.fromCharCode(Number.parseInt(hex, 16)),
        );

        return globalThis.btoa(utf8Bytes);
    }

    return Buffer.from(svg, 'utf-8').toString('base64');
}

export function svgDataUri(svg: string): string {
    return `data:image/svg+xml;base64,${encodeSvgBase64(svg)}`;
}
