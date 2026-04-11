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

use Thelia\Core\Event\CustomerTitle\CustomerTitleEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\CustomerTitle;
use Thelia\Model\CustomerTitleQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class CustomerTitleActionTest extends ActionIntegrationTestCase
{
    public function testCreatePersistsCustomerTitleWithI18n(): void
    {
        $event = new CustomerTitleEvent();
        $event
            ->setLocale('en_US')
            ->setLong('Mister')
            ->setShort('Mr')
            ->setDefault(false);

        $this->dispatch($event, TheliaEvents::CUSTOMER_TITLE_CREATE);

        $title = $event->getCustomerTitle();
        self::assertNotNull($title);
        self::assertSame('Mister', $title->setLocale('en_US')->getLong());
        self::assertSame('Mr', $title->setLocale('en_US')->getShort());
    }

    public function testUpdateChangesLongAndShortLabels(): void
    {
        $existing = (new CustomerTitle())
            ->setPosition('99')
            ->setLocale('en_US')
            ->setLong('Old')
            ->setShort('O');
        $existing->save();

        $event = new CustomerTitleEvent();
        $event
            ->setCustomerTitle($existing)
            ->setLocale('en_US')
            ->setLong('Updated')
            ->setShort('U')
            ->setDefault(false);

        $this->dispatch($event, TheliaEvents::CUSTOMER_TITLE_UPDATE);

        $reloaded = CustomerTitleQuery::create()->findPk($existing->getId());
        self::assertSame('Updated', $reloaded->setLocale('en_US')->getLong());
        self::assertSame('U', $reloaded->setLocale('en_US')->getShort());
    }
}
