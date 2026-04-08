import { countryFlags } from '@/data/country-flags';
import { svgDataUri } from '@/lib/svg-data-uri';
import { cn } from '@/lib/utils';

type CountryFlagIconProps = {
    className?: string;
    countryName: string;
};

export function findCountryFlag(countryName: string) {
    return countryFlags.find((country) => country.name === countryName) ?? null;
}

export function CountryFlagIcon({
    className,
    countryName,
}: CountryFlagIconProps) {
    const country = findCountryFlag(countryName);

    if (!country) {
        return null;
    }

    return (
        <img
            aria-hidden="true"
            alt=""
            src={svgDataUri(country.svg)}
            className={cn('block size-5 rounded-sm', className)}
        />
    );
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
