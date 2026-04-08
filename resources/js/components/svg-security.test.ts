import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';

const twoFactorModalPath = resolve(
    import.meta.dirname,
    'two-factor-setup-modal.tsx',
);
const countryFlagPath = resolve(import.meta.dirname, 'country-flag.tsx');
const nodesPagePath = resolve(import.meta.dirname, '../pages/admin/nodes.tsx');
const locationsPagePath = resolve(
    import.meta.dirname,
    '../pages/admin/locations.tsx',
);
const svgDataUriPath = resolve(import.meta.dirname, '../lib/svg-data-uri.ts');

test('svg-rendering components avoid inline SVG injection sinks', () => {
    const twoFactorModalContents = readFileSync(twoFactorModalPath, 'utf8');
    const countryFlagContents = readFileSync(countryFlagPath, 'utf8');
    const nodesPageContents = readFileSync(nodesPagePath, 'utf8');
    const locationsPageContents = readFileSync(locationsPagePath, 'utf8');
    const svgDataUriContents = readFileSync(svgDataUriPath, 'utf8');

    assert.doesNotMatch(twoFactorModalContents, /dangerouslySetInnerHTML/);
    assert.match(
        twoFactorModalContents,
        /<img[\s\S]*src=\{svgDataUri\(qrCodeSvg\)\}/,
    );

    assert.doesNotMatch(countryFlagContents, /dangerouslySetInnerHTML/);
    assert.match(countryFlagContents, /DOMParser/);
    assert.match(countryFlagContents, /svgDataUri\(svg\)/);

    assert.match(svgDataUriContents, /data:image\/svg\+xml;base64,/);

    assert.match(
        nodesPageContents,
        /export default function Nodes\(\{ nodes, locations = \[\], filters \}: Props\)/,
    );
    assert.match(nodesPageContents, /filters\?\.search \?\? ''/);
    assert.match(locationsPageContents, /filters\?\.search \?\? ''/);
});
