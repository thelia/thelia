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

namespace Thelia\Domain\Catalog\Tax;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Tax\TaxEvent;
use Thelia\Core\Event\Tax\TaxRuleEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Catalog\Tax\DTO\TaxCreateDTO;
use Thelia\Domain\Catalog\Tax\DTO\TaxRuleCreateDTO;
use Thelia\Domain\Catalog\Tax\DTO\TaxRuleUpdateDTO;
use Thelia\Domain\Catalog\Tax\DTO\TaxUpdateDTO;
use Thelia\Domain\Catalog\Tax\Exception\TaxNotFoundException;
use Thelia\Domain\Catalog\Tax\Exception\TaxRuleNotFoundException;
use Thelia\Model\Tax;
use Thelia\Model\TaxQuery;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRuleQuery;

final readonly class TaxFacade
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function createTax(TaxCreateDTO $dto): Tax
    {
        $event = new TaxEvent();
        $event
            ->setTitle($dto->title)
            ->setLocale($dto->locale)
            ->setType($dto->type)
            ->setDescription($dto->description)
            ->setRequirements($dto->requirements);

        $this->dispatcher->dispatch($event, TheliaEvents::TAX_CREATE);

        return $event->getTax();
    }

    public function updateTax(int $taxId, TaxUpdateDTO $dto): Tax
    {
        $tax = $this->getTaxById($taxId);

        if (null === $tax) {
            throw TaxNotFoundException::withId($taxId);
        }

        $event = new TaxEvent($tax);
        $event
            ->setId($taxId)
            ->setTitle($dto->title)
            ->setLocale($dto->locale)
            ->setType($dto->type)
            ->setDescription($dto->description)
            ->setRequirements($dto->requirements);

        $this->dispatcher->dispatch($event, TheliaEvents::TAX_UPDATE);

        return $event->getTax();
    }

    public function deleteTax(int $taxId): void
    {
        $tax = $this->getTaxById($taxId);

        if (null === $tax) {
            throw TaxNotFoundException::withId($taxId);
        }

        $event = new TaxEvent($tax);
        $event->setId($taxId);

        $this->dispatcher->dispatch($event, TheliaEvents::TAX_DELETE);
    }

    public function getTaxById(int $taxId): ?Tax
    {
        return TaxQuery::create()->findPk($taxId);
    }

    public function getAllTaxes(): array
    {
        return TaxQuery::create()
            ->orderByCreatedAt()
            ->find()
            ->getData();
    }

    public function createTaxRule(TaxRuleCreateDTO $dto): TaxRule
    {
        $event = new TaxRuleEvent();
        $event
            ->setTitle($dto->title)
            ->setLocale($dto->locale)
            ->setDescription($dto->description);

        $this->dispatcher->dispatch($event, TheliaEvents::TAX_RULE_CREATE);

        return $event->getTaxRule();
    }

    public function updateTaxRule(int $taxRuleId, TaxRuleUpdateDTO $dto): TaxRule
    {
        $taxRule = $this->getTaxRuleById($taxRuleId);

        if (null === $taxRule) {
            throw TaxRuleNotFoundException::withId($taxRuleId);
        }

        $event = new TaxRuleEvent($taxRule);
        $event
            ->setId($taxRuleId)
            ->setTitle($dto->title)
            ->setLocale($dto->locale)
            ->setDescription($dto->description)
            ->setCountryList($dto->countryList)
            ->setCountryDeletedList($dto->countryDeletedList)
            ->setTaxList($dto->taxList);

        $this->dispatcher->dispatch($event, TheliaEvents::TAX_RULE_UPDATE);

        return $event->getTaxRule();
    }

    public function deleteTaxRule(int $taxRuleId): void
    {
        $taxRule = $this->getTaxRuleById($taxRuleId);

        if (null === $taxRule) {
            throw TaxRuleNotFoundException::withId($taxRuleId);
        }

        $event = new TaxRuleEvent($taxRule);
        $event->setId($taxRuleId);

        $this->dispatcher->dispatch($event, TheliaEvents::TAX_RULE_DELETE);
    }

    public function setDefaultTaxRule(int $taxRuleId): TaxRule
    {
        $taxRule = $this->getTaxRuleById($taxRuleId);

        if (null === $taxRule) {
            throw TaxRuleNotFoundException::withId($taxRuleId);
        }

        $event = new TaxRuleEvent($taxRule);
        $event->setId($taxRuleId);

        $this->dispatcher->dispatch($event, TheliaEvents::TAX_RULE_SET_DEFAULT);

        return $event->getTaxRule();
    }

    public function getTaxRuleById(int $taxRuleId): ?TaxRule
    {
        return TaxRuleQuery::create()->findPk($taxRuleId);
    }

    public function getDefaultTaxRule(): ?TaxRule
    {
        return TaxRuleQuery::create()->findOneByIsDefault(true);
    }

    public function getAllTaxRules(): array
    {
        return TaxRuleQuery::create()
            ->orderByCreatedAt()
            ->find()
            ->getData();
    }
}
