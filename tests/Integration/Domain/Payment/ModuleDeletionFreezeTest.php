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

namespace Thelia\Tests\Integration\Domain\Payment;

use Psr\Log\AbstractLogger;
use Thelia\Core\Event\Module\ModuleDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Lang;
use Thelia\Model\Map\OrderPaymentPartTableMap;
use Thelia\Model\Module;
use Thelia\Model\OrderPaymentPart;
use Thelia\Model\OrderPaymentPartQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The title a deleted module carries is written on its payment parts, in the language of
 * their order, by a number of statements that does not grow with the number of parts.
 */
final class ModuleDeletionFreezeTest extends ActionIntegrationTestCase
{
    public function testTheTitleIsFrozenInTheLanguageOfEachPartsOrderInAConstantNumberOfQueries(): void
    {
        $con = $this->getPropelConnection();
        $english = $this->factory->lang();
        $other = $this->factory->lang(['title' => 'Other', 'code' => 'zq', 'locale' => 'zq_ZQ']);

        $few = $this->moduleWithParts('OrderPaymentFreezeFew', 2, [$english, $other]);
        $many = $this->moduleWithParts('OrderPaymentFreezeMany', 8, [$english, $other]);

        $statements = [];
        $logger = new class($statements) extends AbstractLogger {
            /** @param list<string> $statements */
            public function __construct(public array &$statements)
            {
            }

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->statements[] = (string) $message;
            }
        };

        $con->useDebug(true);
        $con->setLogger($logger);

        $this->dispatch(new ModuleDeleteEvent($few['module']->getId()), TheliaEvents::MODULE_DELETE);
        $queriesForFew = $this->countOnPartsAndTheirOrders($statements);
        $statements = [];

        $this->dispatch(new ModuleDeleteEvent($many['module']->getId()), TheliaEvents::MODULE_DELETE);
        $queriesForMany = $this->countOnPartsAndTheirOrders($statements);
        $con->useDebug(false);

        self::assertGreaterThan(0, $queriesForFew);
        self::assertSame($queriesForFew, $queriesForMany, 'The number of statements on the parts does not depend on their number.');

        OrderPaymentPartTableMap::clearInstancePool();
        foreach ([$few, $many] as $group) {
            foreach ($group['expectedTitles'] as $partId => $title) {
                $part = OrderPaymentPartQuery::create()->findPk($partId);
                self::assertNull($part->getPaymentModuleId());
                self::assertSame($title, $part->getPaymentModuleTitle());
            }
        }
    }

    /**
     * @param list<string> $statements
     */
    private function countOnPartsAndTheirOrders(array $statements): int
    {
        return \count(array_filter(
            $statements,
            static fn (string $sql): bool => str_contains($sql, 'order_payment_part') || str_contains($sql, 'FROM `order`'),
        ));
    }

    /**
     * @param list<Lang> $langs the languages the orders alternate between
     *
     * @return array{module: Module, expectedTitles: array<int, string>}
     */
    private function moduleWithParts(string $code, int $partCount, array $langs): array
    {
        $con = $this->getPropelConnection();
        $module = (new Module())
            ->setCode($code)
            ->setFullNamespace('Thelia\\Tests\\Missing\\'.$code)
            ->setVersion('1.0.0')
            ->setType(BaseModule::PAYMENT_MODULE_TYPE)
            ->setCategory('payment')
            ->setActivate(BaseModule::IS_NOT_ACTIVATED);
        $module->save($con);

        foreach ($langs as $lang) {
            $module->setLocale($lang->getLocale())->setTitle($code.' '.$lang->getLocale())->save($con);
        }

        $expected = [];
        for ($i = 0; $i < $partCount; ++$i) {
            $lang = $langs[$i % \count($langs)];
            $order = $this->factory->order(null, ['paymentModuleCode' => $code]);
            $order->setLangId($lang->getId())->save($con);

            $part = (new OrderPaymentPart())
                ->setOrderId($order->getId())
                ->setPaymentModuleId($module->getId())
                ->setAmount('1.000000')
                ->setIsPrimary(false)
                ->setPosition(0);
            $part->save($con);
            $expected[$part->getId()] = $code.' '.$lang->getLocale();
        }

        return ['module' => $module, 'expectedTitles' => $expected];
    }
}
