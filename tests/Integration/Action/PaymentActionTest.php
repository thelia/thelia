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

use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\ActionIntegrationTestCase;

final class PaymentActionTest extends ActionIntegrationTestCase
{
    public function testIsValidDispatchesForPaymentModule(): void
    {
        $module = ModuleQuery::create()
            ->filterByType(BaseModule::PAYMENT_MODULE_TYPE)
            ->filterByActivate(BaseModule::IS_ACTIVATED)
            ->findOne();

        if (null === $module) {
            self::markTestSkipped('No active payment module in test DB');
        }

        $moduleInstance = $module->getPaymentModuleInstance(self::getContainer());

        $event = new IsValidPaymentEvent(
            $moduleInstance,
            $this->factory->cart(),
        );

        $this->dispatch($event, TheliaEvents::MODULE_PAYMENT_IS_VALID);

        self::assertIsBool($event->isValidModule());
    }
}
