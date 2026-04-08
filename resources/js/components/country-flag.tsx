import { createElement, type ReactNode, useMemo } from 'react';
import { countryFlags } from '@/data/country-flags';
import { svgDataUri } from '@/lib/svg-data-uri';
import { cn } from '@/lib/utils';

type CountryFlagIconProps = {
    className?: string;
    countryName: string;
};

const blockedSvgElements = new Set(['foreignobject', 'script']);
const countryNameAliases: Record<string, string> = {
    'democratic republic of the congo': 'Congo - Kinshasa',
    "korea, democratic people's republic of": 'North Korea',
    'korea, republic of': 'South Korea',
    'republic of the congo': 'Congo - Brazzaville',
    'russian federation': 'Russia',
    türkiye: 'Turkey',
    'united kingdom of great britain and northern ireland': 'United Kingdom',
    'united states of america': 'United States',
};

function normalizeCountryName(countryName: string): string {
    return countryName
        .normalize('NFKD')
        .replace(/\p{M}/gu, '')
        .trim()
        .replace(/\s+/g, ' ')
        .toLowerCase();
}

const normalizedCountryFlags = new Map(
    countryFlags.map((country) => [
        normalizeCountryName(country.name),
        country,
    ]),
);

export function findCountryFlag(countryName: string) {
    const normalizedCountryName = normalizeCountryName(countryName);
    const alias = countryNameAliases[normalizedCountryName];

    return (
        normalizedCountryFlags.get(normalizedCountryName) ??
        (alias
            ? normalizedCountryFlags.get(normalizeCountryName(alias))
            : null) ??
        null
    );
}

function fallbackFlagImage(svg: string, className?: string): ReactNode {
    return (
        <img
            aria-hidden="true"
            alt=""
            src={svgDataUri(svg)}
            className={cn('block size-5 rounded-sm', className)}
        />
    );
}

function sanitizeSvgAttribute(
    name: string,
    value: string,
): [string, string] | null {
    const normalizedName = name.toLowerCase();

    if (normalizedName.startsWith('on')) {
        return null;
    }

    if (
        ['href', 'xlink:href'].includes(normalizedName) &&
        value.trim().toLowerCase().startsWith('javascript:')
    ) {
        return null;
    }

    return [name === 'class' ? 'className' : name, value];
}

function toReactNode(
    node: ChildNode,
    key: string,
    rootClassName?: string,
): ReactNode {
    if (node.nodeType === Node.TEXT_NODE) {
        return node.textContent;
    }

    if (node.nodeType !== Node.ELEMENT_NODE) {
        return null;
    }

    const element = node as Element;
    const elementName = element.tagName.toLowerCase();

    if (blockedSvgElements.has(elementName)) {
        return null;
    }

    const props: Record<string, string> & { key: string } = { key };

    for (const attribute of Array.from(element.attributes)) {
        const sanitizedAttribute = sanitizeSvgAttribute(
            attribute.name,
            attribute.value,
        );

        if (!sanitizedAttribute) {
            continue;
        }

        const [name, value] = sanitizedAttribute;
        props[name] = value;
    }

    if (elementName === 'svg') {
        props.className = cn(
            'block size-5 rounded-sm',
            props.className,
            rootClassName,
        );
        props['aria-hidden'] = 'true';
        props.focusable = 'false';
    }

    const children = Array.from(element.childNodes)
        .map((childNode, index) => toReactNode(childNode, `${key}-${index}`))
        .filter((childNode) => childNode !== null);

    return createElement(elementName, props, ...children);
}

function renderInlineFlagSvg(svg: string, className?: string): ReactNode {
    if (typeof DOMParser === 'undefined') {
        return fallbackFlagImage(svg, className);
    }

    try {
        const document = new DOMParser().parseFromString(svg, 'image/svg+xml');
        const root = document.documentElement;

        if (root.tagName.toLowerCase() !== 'svg') {
            return fallbackFlagImage(svg, className);
        }

        return (
            toReactNode(root, 'flag', className) ??
            fallbackFlagImage(svg, className)
        );
    } catch {
        return fallbackFlagImage(svg, className);
    }
}

export function CountryFlagIcon({
    className,
    countryName,
}: CountryFlagIconProps) {
    const country = findCountryFlag(countryName);
    const flagIcon = useMemo(
        () => (country ? renderInlineFlagSvg(country.svg, className) : null),
        [className, country],
    );

    return flagIcon;
}

export function CountryFlagOption({
    className,
    countryName,
}: CountryFlagIconProps) {
    return (
        <span className={cn('flex items-center gap-2', className)}>
            <CountryFlagIcon countryName={countryName} />
            <span className="truncate">{countryName}</span>
        </span>
    );
}
