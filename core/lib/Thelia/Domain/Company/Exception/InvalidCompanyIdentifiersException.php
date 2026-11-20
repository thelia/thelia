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

namespace Thelia\Domain\Company\Exception;

use Thelia\Domain\Legal\CompanyIdentifierViolation;

/**
 * The SIRET or the VAT number stated for a company is one the rules of the shop refuse, the same
 * rules an address is judged by. The message is the first violation in its untranslated form;
 * a front that speaks the language of its reader translates each of {@see self::violations()}.
 */
final class InvalidCompanyIdentifiersException extends CompanyException
{
    /**
     * @param non-empty-list<CompanyIdentifierViolation> $violations
     */
    public function __construct(private readonly array $violations)
    {
        parent::__construct(strtr($violations[0]->message, $violations[0]->parameters));
    }

    /**
     * @return non-empty-list<CompanyIdentifierViolation>
     */
    public function violations(): array
    {
        return $this->violations;
    }
}
