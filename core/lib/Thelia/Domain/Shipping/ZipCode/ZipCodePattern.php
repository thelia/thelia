<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Domain\Shipping\ZipCode;

/**
 * One postal code pattern of a delivery zone, in the syntax merchants already know from
 * other shops: an exact code ("75001"), a pattern where * stands for any characters
 * ("20*", "75*"), or a range of numeric codes ("75001...75020", bounds included).
 *
 * Codes are compared in their normal form: upper case, without spaces or hyphens, so
 * "sw1a 1aa" matches "SW1A1AA" and "1000-001" matches "1000001".
 */
final readonly class ZipCodePattern
{
    private const RANGE_SEPARATOR = '...';

    private function __construct(
        private string $pattern,
    ) {
    }

    /**
     * @throws \InvalidArgumentException when the pattern can match nothing
     */
    public static function fromString(string $pattern): self
    {
        $normalized = self::normalizePattern($pattern);

        if ('' === str_replace(['*', self::RANGE_SEPARATOR], '', $normalized)) {
            throw new \InvalidArgumentException('A postal code pattern needs at least one character other than *.');
        }

        if (str_contains($normalized, self::RANGE_SEPARATOR)) {
            [$from, $to] = explode(self::RANGE_SEPARATOR, $normalized, 2);

            if (!ctype_digit($from) || !ctype_digit($to) || (int) $from > (int) $to) {
                throw new \InvalidArgumentException('A postal code range goes from a number to a greater or equal number, as in 75001...75020.');
            }
        }

        return new self($normalized);
    }

    public static function normalize(string $zipCode): string
    {
        return strtoupper((string) preg_replace('/[\s\-]+/u', '', $zipCode));
    }

    public function matches(string $zipCode): bool
    {
        $zipCode = self::normalize($zipCode);

        if ('' === $zipCode) {
            return false;
        }

        if (str_contains($this->pattern, self::RANGE_SEPARATOR)) {
            [$from, $to] = explode(self::RANGE_SEPARATOR, $this->pattern, 2);

            return ctype_digit($zipCode) && (int) $zipCode >= (int) $from && (int) $zipCode <= (int) $to;
        }

        if (str_contains($this->pattern, '*')) {
            $expression = '/^'.str_replace('\\*', '.*', preg_quote($this->pattern, '/')).'$/';

            return 1 === preg_match($expression, $zipCode);
        }

        return $this->pattern === $zipCode;
    }

    public function __toString(): string
    {
        return $this->pattern;
    }

    private static function normalizePattern(string $pattern): string
    {
        // The range separator survives the normal form, the hyphens and spaces do not.
        return implode(self::RANGE_SEPARATOR, array_map(self::normalize(...), explode(self::RANGE_SEPARATOR, trim($pattern), 2)));
    }
}
