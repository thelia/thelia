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

namespace Thelia\Domain\Catalog\Product\Identifier;

use Thelia\Core\Translation\Translator;

/**
 * A combination was given a code that is not a GTIN.
 *
 * Thrown before the row is written, whatever wrote it (back office, API, import, module),
 * and only when the code changes: a code stored before the check existed stays as it is
 * until someone edits it.
 */
class InvalidGtinException extends \InvalidArgumentException
{
    public function __construct(
        public readonly string $submittedCode,
        public readonly GtinViolation $violation,
        public readonly ?string $combinationReference = null,
    ) {
        parent::__construct(self::describe($submittedCode, $violation, $combinationReference));
    }

    public static function describe(string $submittedCode, GtinViolation $violation, ?string $combinationReference = null): string
    {
        $reason = match ($violation) {
            GtinViolation::NotDigits => self::trans('a GTIN is made of digits only'),
            GtinViolation::Length => self::trans(
                'a GTIN has 8, 12, 13 or 14 digits, this one has %count',
                ['%count' => (string) \strlen(Gtin::normalize($submittedCode))],
            ),
            GtinViolation::CheckDigit => self::trans('its check digit is wrong, the code was probably mistyped'),
        };

        if (null === $combinationReference || '' === $combinationReference) {
            return self::trans('The GTIN "%code" is refused: %reason.', ['%code' => $submittedCode, '%reason' => $reason]);
        }

        return self::trans(
            'The GTIN "%code" of the combination %ref is refused: %reason.',
            ['%code' => $submittedCode, '%ref' => $combinationReference, '%reason' => $reason],
        );
    }

    /**
     * @param array<string, string> $parameters
     */
    private static function trans(string $message, array $parameters = []): string
    {
        try {
            return Translator::getInstance()->trans($message, $parameters);
        } catch (\RuntimeException) {
            return strtr($message, $parameters);
        }
    }
}
