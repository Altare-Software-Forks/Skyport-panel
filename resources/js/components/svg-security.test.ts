import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';

const twoFactorModalPath = resolve(
    import.meta.dirname,
    'two-factor-setup-modal.tsx',
);
const countryFlagPath = resolve(import.meta.dirname, 'country-flag.tsx');

test('svg-rendering components avoid inline SVG injection sinks', () => {
    const twoFactorModalContents = readFileSync(twoFactorModalPath, 'utf8');
    const countryFlagContents = readFileSync(countryFlagPath, 'utf8');

    assert.doesNotMatch(twoFactorModalContents, /dangerouslySetInnerHTML/);
    assert.match(
        twoFactorModalContents,
        /<img[\s\S]*src=\{svgDataUri\(qrCodeSvg\)\}/,
    );

    assert.doesNotMatch(countryFlagContents, /dangerouslySetInnerHTML/);
    assert.match(
        countryFlagContents,
        /<img[\s\S]*src=\{svgDataUri\(country\.svg\)\}/,
    );
});
