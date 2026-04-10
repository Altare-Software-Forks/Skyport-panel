import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';

export function ThemeInjector() {
    const themeCSS = (usePage().props as { themeCSS?: string | null }).themeCSS;

    useEffect(() => {
        const id = 'skyport-theme';
        let style = document.getElementById(id) as HTMLStyleElement | null;

        if (!themeCSS) {
            style?.remove();
            return;
        }

        if (!style) {
            style = document.createElement('style');
            style.id = id;
            document.head.appendChild(style);
        }

        style.textContent = themeCSS;
    }, [themeCSS]);

    return null;
}
