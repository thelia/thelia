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
 * A manufacturer part number longer than the column that keeps it.
 *
 * Refused rather than cut: a part number cut short points at another part.
 */
class InvalidMpnException extends \InvalidArgumentException
{
    public const int MAXIMUM_LENGTH = 255;

    public function __construct(
        public readonly string $submittedMpn,
        public readonly ?string $combinationReference = null,
    ) {
        $parameters = ['%max' => (string) self::MAXIMUM_LENGTH, '%ref' => (string) $combinationReference];
        $message = null === $combinationReference || '' === $combinationReference
            ? 'The manufacturer part number must be %max characters at most.'
            : 'The manufacturer part number of the combination %ref must be %max characters at most.';

        try {
            $message = Translator::getInstance()->trans($message, $parameters);
        } catch (\RuntimeException) {
            $message = strtr($message, $parameters);
        }

        parent::__construct($message);
    }
}
