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

namespace Thelia\Tests\Integration\Action;

use Thelia\Core\Event\Tax\TaxEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Taxation\TaxEngine\TaxType\PricePercentTaxType;
use Thelia\Model\TaxQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class TaxActionTest extends ActionIntegrationTestCase
{
    public function testCreatePersistsTaxWithSerializedRequirements(): void
    {
        $event = new TaxEvent();
        $event
            ->setType(PricePercentTaxType::class)
            ->setRequirements(['percent' => '20'])
            ->setLocale('en_US')
            ->setTitle('VAT 20%')
            ->setDescription('Standard VAT');

        $this->dispatch($event, TheliaEvents::TAX_CREATE);

        $tax = $event->getTax();
        self::assertNotNull($tax);
        self::assertSame(PricePercentTaxType::class, $tax->getType());
        self::assertSame(['percent' => '20'], $tax->getRequirements());
        self::assertSame('VAT 20%', $tax->setLocale('en_US')->getTitle());
    }

    public function testUpdateChangesRequirementsAndTitle(): void
    {
        $tax = $this->factory->tax(['title' => 'Old', 'requirements' => ['percent' => '10']]);

        $event = new TaxEvent($tax);
        $event
            ->setId($tax->getId())
            ->setType(PricePercentTaxType::class)
            ->setRequirements(['percent' => '15'])
            ->setLocale('en_US')
            ->setTitle('Updated')
            ->setDescription('');

        $this->dispatch($event, TheliaEvents::TAX_UPDATE);

        $reloaded = TaxQuery::create()->findPk($tax->getId());
        self::assertSame(['percent' => '15'], $reloaded->getRequirements());
        self::assertSame('Updated', $reloaded->setLocale('en_US')->getTitle());
    }

    public function testDeleteRemovesTax(): void
    {
        $tax = $this->factory->tax();
        $taxId = $tax->getId();

        $event = new TaxEvent($tax);
        $event->setId($taxId);

        $this->dispatch($event, TheliaEvents::TAX_DELETE);

        self::assertNull(TaxQuery::create()->findPk($taxId));
    }
}
