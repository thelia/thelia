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
 * Codes are compared in their normal form (normalize()): compatibility characters folded
 * (Unicode NFKC, so a full-width "２０" is "20"), without spaces, dashes or invisible format
 * characters, with the digits of every script written in ASCII and the letters in ASCII
 * upper case. So "sw1a 1aa" matches "SW1A1AA" and "1000-001" matches "1000001". A code
 * that keeps anything else (a letter outside A to Z, a variation selector, a control
 * character) has no normal form: it is uncertain. A pattern holds the same characters, with
 * * and the range separator ...
 *
 * A postal code is in one of three states before the rules read it: unknown (none at all,
 * isUnknown()), a placeholder (typed, but only spaces and dashes, as a buyer types "-" for
 * no code, isPlaceholder()), or a code, certain when it has a normal form and uncertain
 * otherwise.
 */
final readonly class ZipCodePattern
{
    private const RANGE_SEPARATOR = '...';

    /** The length of the `pattern` column of `area_zip_code_rule`, in characters. */
    public const MAX_LENGTH = 50;

    /**
     * Spaces, every dash (\p{Pd}: a hyphen, an en dash, a wave dash...), the minus sign, a
     * math symbol to Unicode, and invisible format characters (a soft hyphen, a zero-width
     * space, a word joiner...).
     */
    private const SEPARATORS = '/[\s\p{Z}\p{Pd}\x{2212}\p{Cf}]+/u';

    /**
     * A typed code of only spaces, dashes and minus signs, as a buyer types "-" for no code.
     */
    private const PLACEHOLDER = '/^[\s\p{Pd}\x{2212}]*$/u';

    /** The invisible format characters nameable() takes out of the code it names. */
    private const FORMAT_CHARACTERS = '/\p{Cf}+/u';

    /**
     * The characters forLog() writes as \u{...}: the C1 controls, line and paragraph
     * separators, and the controls that change the direction of the text around them.
     */
    private const LOG_UNSAFE_CHARACTERS = '/[\x{0080}-\x{009F}\x{061C}\x{200E}\x{200F}\x{2028}\x{2029}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u';

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

        if (null === $normalized) {
            throw new InvalidZipCodeRuleException('A postal code pattern holds digits, letters from A to Z, * and ... only.');
        }

        if ('' === str_replace(['*', self::RANGE_SEPARATOR], '', $normalized)) {
            throw new InvalidZipCodeRuleException('A postal code pattern needs at least one character other than *.');
        }

        if (mb_strlen($normalized) > self::MAX_LENGTH) {
            throw new InvalidZipCodeRuleException(\sprintf('A postal code pattern is %d characters long at most.', self::MAX_LENGTH));
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
     * Text of a rule as a log line may carry it, so that a pattern written by hand in the
     * database cannot forge a line of the log, nor turn the text after it around: the C0
     * controls, DEL and the backslash escaped (a carriage return becomes "\r"), the C1
     * controls, line and paragraph separators and the direction controls (bidi embeddings,
     * isolates and marks) written as "\u{2028}". Tlog writes its context as it comes. A text
     * that is not valid UTF-8 has every byte above ASCII escaped.
     */
    public static function forLog(string $text): string
    {
        $escaped = addcslashes($text, "\0..\37\177\\");
        $readable = preg_replace_callback(
            self::LOG_UNSAFE_CHARACTERS,
            static fn (array $match): string => \sprintf('\\u{%04X}', mb_ord($match[0])),
            $escaped,
        );

        return $readable ?? addcslashes($escaped, "\200..\377");
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
     * Whether there is no postal code at all: no address yet, a cart estimated without one.
     * Every zone takes it, and no rule is read: the address decides later.
     *
     * @phpstan-assert-if-false string $zipCode
     */
    public static function isUnknown(?string $zipCode): bool
    {
        return null === $zipCode;
    }

    /**
     * Whether a typed postal code is a placeholder: empty, or only spaces, dashes and minus
     * signs, as a buyer types "-" for no code. A zone with an inclusion does not take it, a
     * zone with exclusions only or without a rule does (ZipCodeRuleSet).
     */
    public static function isPlaceholder(?string $zipCode): bool
    {
        return null !== $zipCode && 1 === preg_match(self::PLACEHOLDER, $zipCode);
    }

    /**
     * The postal code as a buyer can be told it is why no carrier is offered: a code that has
     * a normal form, as it was typed, its spaces and dashes kept, without the invisible format
     * characters (direction marks and overrides, zero-width spaces) that would turn the text
     * of the message around. Null for an unknown code, a placeholder and an uncertain code,
     * which give the buyer nothing to read.
     */
    public static function nameable(?string $zipCode): ?string
    {
        if (null === $zipCode || self::isPlaceholder($zipCode) || null === self::normalize($zipCode)) {
            return null;
        }

        return preg_replace(self::FORMAT_CHARACTERS, '', $zipCode);
    }

    /**
     * The normal form of a postal code, or null when it has none: not valid UTF-8, emptied,
     * keeping a character outside A to Z and 0 to 9, or an expression PCRE gives up on. Such
     * a code is uncertain: the rules cannot tell where it is.
     *
     * Compatibility characters are folded (NFKC: "２０" is "20"), every space, dash and
     * invisible format character goes (a no-break space, a non-breaking hyphen, a soft
     * hyphen, a zero-width space), the digits of other scripts become ASCII digits, the text
     * is folded again so that what was split by a removed character composes as it would
     * have, and the ASCII letters are put in upper case. What is left must be A to Z and 0
     * to 9 only. Applied twice, it gives the same code.
     */
    public static function normalize(string $zipCode): ?string
    {
        $folded = self::fold($zipCode);

        return null !== $folded && 1 === preg_match('/^[A-Z0-9]+$/', $folded) ? $folded : null;
    }

    /**
     * A placeholder matches no pattern.
     *
     * @param bool $whenUnsure the answer when the code is uncertain, because it has no normal
     *                         form (see normalize()), or when the expression of a pattern
     *                         with * cannot be run (PCRE limits): true for an exclusion,
     *                         which then refuses the code rather than letting it through
     */
    public function matches(string $zipCode, bool $whenUnsure = false): bool
    {
        if (self::isPlaceholder($zipCode)) {
            return false;
        }

        return $this->matchesNormalized(self::normalize($zipCode), $whenUnsure);
    }

    /**
     * matches() for a code already in its normal form, as normalize() gives it and as
     * ZipCodeRuleSet passes it once for all its rules. A code without a normal form (null)
     * or empty is uncertain: the answer is $whenUnsure.
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

    /**
     * The folded form of a postal code or of a part of a pattern, before the check of its
     * characters: NFKC, without separators, with ASCII digits, NFKC again, ASCII letters in
     * upper case. Null when it cannot be computed.
     */
    private static function fold(string $text): ?string
    {
        $folded = \Normalizer::normalize($text, \Normalizer::FORM_KC);

        if (false === $folded) {
            return null;
        }

        $withoutSeparators = preg_replace(self::SEPARATORS, '', $folded);

        if (null === $withoutSeparators) {
            return null;
        }

        // A character that is a digit to Unicode but has no value to ICU stays as it is, and
        // the check of the characters then leaves the code without a normal form.
        $asciiDigits = preg_replace_callback(
            '/[^\x{0030}-\x{0039}\P{Nd}]/u',
            static function (array $digit): string {
                $value = \IntlChar::charDigitValue((int) mb_ord($digit[0]));

                return $value >= 0 && $value <= 9 ? (string) $value : $digit[0];
            },
            $withoutSeparators,
        );
        $refolded = null === $asciiDigits ? false : \Normalizer::normalize($asciiDigits, \Normalizer::FORM_KC);

        return false === $refolded ? null : strtoupper($refolded);
    }

    /**
     * The normal form of a pattern: each side of the range separator folded as a code is,
     * with * kept, or null when a side keeps another character.
     */
    private static function normalizePattern(string $pattern): ?string
    {
        $parts = [];

        foreach (explode(self::RANGE_SEPARATOR, trim($pattern), 2) as $part) {
            $folded = self::fold($part);

            if (null === $folded || 1 !== preg_match('/^[A-Z0-9*]*$/', $folded)) {
                return null;
            }

            $parts[] = $folded;
        }

        // "**" means "*": one star per gap keeps the expression linear.
        return (string) preg_replace('/\*+/', '*', implode(self::RANGE_SEPARATOR, $parts));
    }
}
