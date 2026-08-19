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

namespace Thelia\Form;

use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Legal\CompanyIdentifierRules;
use Thelia\Domain\Legal\CompanyIdentifierViolation;
use Thelia\Model\CountryQuery;

/**
 * Reports on an address form what CompanyIdentifierRules has to say about its identifiers.
 *
 * Both fields are required as soon as a company name is typed, and ignored otherwise: the
 * obligation depends on a sibling field, which no NotBlank can express - hence Callback
 * constraints, the way AddressCountryValidationTrait handles the zip code and the state.
 */
trait AddressLegalIdentifiersValidationTrait
{
    public function verifySiret($value, ExecutionContextInterface $context): void
    {
        foreach ($this->legalIdentifierViolations($context) as $violation) {
            if ($violation->isAboutSiret()) {
                $context->addViolation(
                    Translator::getInstance()->trans($violation->message, $violation->parameters),
                );
            }
        }
    }

    public function verifyVatNumber($value, ExecutionContextInterface $context): void
    {
        foreach ($this->legalIdentifierViolations($context) as $violation) {
            if (!$violation->isAboutSiret()) {
                $context->addViolation(
                    Translator::getInstance()->trans($violation->message, $violation->parameters),
                );
            }
        }
    }

    /**
     * @return list<CompanyIdentifierViolation>
     */
    private function legalIdentifierViolations(ExecutionContextInterface $context): array
    {
        $data = $context->getRoot()->getData();

        return CompanyIdentifierRules::violationsFor(
            self::legalIdentifierString($data, 'company'),
            self::legalIdentifierString($data, 'siret'),
            self::legalIdentifierString($data, 'vat_number'),
            self::legalIdentifiersCountryCode($data),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function legalIdentifierString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return \is_string($value) ? $value : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function legalIdentifiersCountryCode(array $data): ?string
    {
        $countryId = $data['country'] ?? null;

        if (null === $countryId || '' === $countryId) {
            return null;
        }

        $country = CountryQuery::create()->findPk($countryId);

        return null === $country ? null : $country->getIsoalpha2();
    }
}
