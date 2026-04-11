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

use Thelia\Core\Event\Tax\TaxRuleEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\TaxRuleQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class TaxRuleActionTest extends ActionIntegrationTestCase
{
    public function testCreatePersistsTaxRuleWithI18n(): void
    {
        $event = new TaxRuleEvent();
        $event
            ->setLocale('en_US')
            ->setTitle('Standard')
            ->setDescription('Standard tax rule');

        $this->dispatch($event, TheliaEvents::TAX_RULE_CREATE);

        $taxRule = $event->getTaxRule();
        self::assertNotNull($taxRule);
        self::assertSame('Standard', $taxRule->setLocale('en_US')->getTitle());
    }

    public function testUpdateChangesI18nFields(): void
    {
        $existing = $this->factory->taxRule();

        $event = new TaxRuleEvent($existing);
        $event
            ->setId($existing->getId())
            ->setLocale('en_US')
            ->setTitle('Reduced')
            ->setDescription('Reduced rate');

        $this->dispatch($event, TheliaEvents::TAX_RULE_UPDATE);

        self::assertSame(
            'Reduced',
            TaxRuleQuery::create()->findPk($existing->getId())->setLocale('en_US')->getTitle(),
        );
    }
}
