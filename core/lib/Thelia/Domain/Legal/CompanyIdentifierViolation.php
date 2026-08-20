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

namespace Thelia\Domain\Legal;

/**
 * One thing wrong with the legal identifiers of an address, named by the field it belongs to.
 *
 * The message is the untranslated source string: a form binds it to a field of its own naming,
 * an API resource to a property path, and each translates it in its own locale.
 */
final readonly class CompanyIdentifierViolation
{
    public const FIELD_SIRET = 'siret';
    public const FIELD_VAT_NUMBER = 'vatNumber';

    /**
     * @param array<string, string> $parameters
     */
    public function __construct(
        public string $field,
        public string $message,
        public array $parameters = [],
    ) {
    }

    public function isAboutSiret(): bool
    {
        return self::FIELD_SIRET === $this->field;
    }
}
