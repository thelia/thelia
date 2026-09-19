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

namespace Thelia\Domain\Pricing\Rule;

use Thelia\Core\Event\CatalogPriceRule\CatalogPriceRuleCreateEvent;
use Thelia\Domain\Pricing\Rule\Engine\EffectType;
use Thelia\Domain\Pricing\Rule\Exception\InvalidCatalogPriceRuleException;
use Thelia\Domain\Pricing\Rule\Scope\ScopeMaterializer;
use Thelia\Model\CatalogPriceRule;

/**
 * What a rule definition has to hold before it is stored, whoever posts it: the back
 * office, the API, the flash sale conversion.
 *
 * Refused early, with one sentence the merchant can act on, rather than stored and
 * silently skipped at pricing time - a rule that says nothing is a rule the merchant
 * believes to be running.
 */
class CatalogPriceRuleDefinitionValidator
{
    public function __construct(private readonly ScopeMaterializer $scopeMaterializer)
    {
    }

    /**
     * @throws InvalidCatalogPriceRuleException
     */
    public function validate(CatalogPriceRuleCreateEvent $definition): void
    {
        if ('' === trim($definition->getTitle())) {
            throw new InvalidCatalogPriceRuleException('A catalog price rule needs a title.');
        }

        $startDate = $definition->getStartDate();
        $endDate = $definition->getEndDate();

        if (null !== $startDate && null !== $endDate && $endDate <= $startDate) {
            throw new InvalidCatalogPriceRuleException('The end date of a catalog price rule must come after its start date.');
        }

        $this->validateEffect($definition);
        $this->validateAudience($definition);
        $this->validateCriteria($definition);
    }

    private function validateEffect(CatalogPriceRuleCreateEvent $definition): void
    {
        $type = EffectType::tryFrom($definition->getEffectType());

        if (null === $type) {
            throw new InvalidCatalogPriceRuleException('The effect of a catalog price rule must be a percentage, an amount or a fixed price.');
        }

        if (EffectType::Percentage === $type) {
            $percentage = $definition->getPercentageValue();

            if (null === $percentage || $percentage <= 0.0 || $percentage > 100.0) {
                throw new InvalidCatalogPriceRuleException('A percentage rule needs a percentage between 0 and 100.');
            }

            return;
        }

        $values = $definition->getEffectValuesByCurrency();

        if ([] === $values) {
            throw new InvalidCatalogPriceRuleException(match ($type) {
                EffectType::Amount => 'An amount rule needs an amount in at least one currency.', EffectType::FixedPrice => 'A fixed price rule needs a price in at least one currency.',
            });
        }

        foreach ($values as $value) {
            if ($value < 0.0) {
                throw new InvalidCatalogPriceRuleException('The amounts and prices of a catalog price rule cannot be negative.');
            }
        }
    }

    private function validateAudience(CatalogPriceRuleCreateEvent $definition): void
    {
        switch ($definition->getAudienceMode()) {
            case CatalogPriceRule::AUDIENCE_MODE_PUBLIC:
                return;
            case CatalogPriceRule::AUDIENCE_MODE_CUSTOMERS:
                if ([] === $definition->getCustomerIds()) {
                    throw new InvalidCatalogPriceRuleException('A rule reserved for named customers needs at least one customer.');
                }

                return;
            case CatalogPriceRule::AUDIENCE_MODE_CUSTOMER_GROUPS:
                throw new InvalidCatalogPriceRuleException('Customer groups are not available yet: reserve the rule for named customers or open it to everyone.');
            default:
                throw new InvalidCatalogPriceRuleException('The audience of a catalog price rule must be everyone or named customers.');
        }
    }

    private function validateCriteria(CatalogPriceRuleCreateEvent $definition): void
    {
        $unknown = array_diff(array_keys($definition->getCriteria()), $this->scopeMaterializer->knownCriterionTypes());

        if ([] !== $unknown) {
            throw new InvalidCatalogPriceRuleException(\sprintf('No installed module resolves the criterion type "%s".', implode('", "', $unknown)));
        }
    }
}
