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

use Thelia\Domain\Shipping\ZipCode\Exception\InvalidZipCodeRuleException;
use Thelia\Log\Tlog;

/**
 * One postal code pattern of a delivery zone, in the syntax merchants already know from
 * other shops: an exact code ("75001"), a pattern where * stands for any characters
 * ("20*", "75*"), or a range of numeric codes ("75001...75020", bounds included). A
 * range matches the codes of the length of its bounds only, so "01000...01999" takes
 * "01500" and neither "1500" nor "001500".
 *
 * Codes are compared in their normal form: upper case, without spaces or hyphens, so
 * "sw1a 1aa" matches "SW1A1AA" and "1000-001" matches "1000001".
 */
final readonly class ZipCodePattern
{
    private const RANGE_SEPARATOR = '...';

    /** The length of the `pattern` column of `area_zip_code_rule`. */
    public const MAX_LENGTH = 50;

    private function __construct(
        private string $pattern,
    ) {
    }

    /**
     * @throws InvalidZipCodeRuleException when the pattern can match nothing
     */
    public static function fromString(string $pattern): self
    {
        $normalized = self::normalizePattern($pattern);

        if ('' === str_replace(['*', self::RANGE_SEPARATOR], '', $normalized)) {
            throw new InvalidZipCodeRuleException('A postal code pattern needs at least one character other than *.');
        }

        if (\strlen($normalized) > self::MAX_LENGTH) {
            throw new InvalidZipCodeRuleException('A postal code pattern is 50 characters long at most.');
        }

        if (str_contains($normalized, self::RANGE_SEPARATOR)) {
            [$from, $to] = explode(self::RANGE_SEPARATOR, $normalized, 2);

            if (!ctype_digit($from) || !ctype_digit($to) || \strlen($from) !== \strlen($to) || strcmp($from, $to) > 0) {
                throw new InvalidZipCodeRuleException('A postal code range goes from a number to a greater or equal number of the same length, as in 75001...75020.');
            }
        }

        return new self($normalized);
    }

    /**
     * Text of a rule as a log line may carry it: control characters escaped (a carriage
     * return becomes "\r"), so that a pattern written by hand in the database cannot forge
     * a line of the log. Tlog writes its context as it comes.
     */
    public static function forLog(string $text): string
    {
        return addcslashes($text, "\0..\37\177");
    }

    /**
     * A pattern that matches no postal code: what an unreadable rule of the database stands
     * for (AreaZipCodeRuleQuery::ruleSetFor()).
     */
    public static function matchingNothing(): self
    {
        return new self('');
    }

    /**
     * Whether a postal code is unknown: none, or only spaces. Every rule accepts it, the
     * address decides later. A code that is not blank but that the normal form empties (a
     * zero-width space alone) is not unknown: it is uncertain, as a code that cannot be read.
     */
    public static function isUnknown(?string $zipCode): bool
    {
        return null === $zipCode || 1 === preg_match('/^\s*$/u', $zipCode);
    }

    /**
     * The normal form of a postal code, or null when it cannot be read (not valid UTF-8):
     * such a code is neither unknown nor any code, the rules cannot tell where it is.
     *
     * Compatibility characters are folded first (NFKC: "２０" is "20"), then every space,
     * hyphen and invisible format character goes (a no-break space, a non-breaking hyphen,
     * a zero-width space), and the letters are put in upper case.
     */
    public static function normalize(string $zipCode): ?string
    {
        $folded = \Normalizer::normalize($zipCode, \Normalizer::FORM_KC);

        if (false === $folded) {
            return null;
        }

        $withoutSeparators = preg_replace('/[\s\-\x{2010}\x{2011}\p{Z}\p{Cf}]+/u', '', $folded);

        return null === $withoutSeparators ? null : strtoupper($withoutSeparators);
    }

    /**
     * @param bool $whenUnsure the answer when the code cannot be checked, because it cannot
     *                         be read (not valid UTF-8, or emptied by its normal form) or
     *                         because the expression of a pattern with * cannot be run
     *                         (PCRE limits): true for an exclusion, which then refuses the
     *                         code rather than letting it through
     */
    public function matches(string $zipCode, bool $whenUnsure = false): bool
    {
        if (self::isUnknown($zipCode)) {
            return false;
        }

        return $this->matchesNormalized(self::normalize($zipCode), $whenUnsure);
    }

    /**
     * matches() for a code already in its normal form (null when it could not be read), as
     * ZipCodeRuleSet passes it once for all its rules.
     *
     * @internal
     */
    public function matchesNormalized(?string $zipCode, bool $whenUnsure = false): bool
    {
        // The pattern of an unreadable rule matches nothing, an uncertain code included.
        if ('' === $this->pattern) {
            return false;
        }

        // A code that cannot be read, or that its normal form empties, is uncertain.
        if (null === $zipCode || '' === $zipCode) {
            return $whenUnsure;
        }

        if (str_contains($this->pattern, self::RANGE_SEPARATOR)) {
            [$from, $to] = explode(self::RANGE_SEPARATOR, $this->pattern, 2);

            // Digits of one length compare as strings: no integer overflow, and the leading
            // zeros count.
            return ctype_digit($zipCode)
                && \strlen($zipCode) === \strlen($from)
                && strcmp($zipCode, $from) >= 0
                && strcmp($zipCode, $to) <= 0;
        }

        if (str_contains($this->pattern, '*')) {
            $expression = '/^'.str_replace('\\*', '.*', preg_quote($this->pattern, '/')).'$/u';
            $matched = preg_match($expression, $zipCode);

            if (false === $matched) {
                // The postal code itself stays out of the log: it is part of an address.
                Tlog::getInstance()->warning(
                    'The postal code pattern "{pattern}" could not be checked ({error}): the code is taken as {answer}.',
                    ['pattern' => self::forLog($this->pattern), 'error' => preg_last_error_msg(), 'answer' => $whenUnsure ? 'matching it' : 'not matching it'],
                );

                return $whenUnsure;
            }

            return 1 === $matched;
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
        // A part that cannot be read leaves nothing, and the pattern is refused as empty.
        $normalized = implode(self::RANGE_SEPARATOR, array_map(static fn (string $part): string => self::normalize($part) ?? '', explode(self::RANGE_SEPARATOR, trim($pattern), 2)));

        // "**" means "*": one star per gap keeps the expression linear.
        return (string) preg_replace('/\*+/', '*', $normalized);
    }
}
