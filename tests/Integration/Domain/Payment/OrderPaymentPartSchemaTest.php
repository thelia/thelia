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

use Thelia\Core\Event\Module\ModuleDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Payment\Enum\OrderPaymentPartState;
use Thelia\Model\LangQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPaymentPart;
use Thelia\Model\OrderPaymentPartQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The table that holds the shares of an order's payment, and what becomes of a share
 * when the module it names is deleted.
 */
final class OrderPaymentPartSchemaTest extends ActionIntegrationTestCase
{
    private const MODULE_CODE = 'OrderPaymentPartSchemaModule';

    /**
     * Replaying the script runs DDL, which commits the open transaction even when the table
     * already exists: that test writes nothing and runs outside the transaction, so it cannot
     * commit what an earlier test of the suite left in one.
     */
    protected function setUp(): void
    {
        $this->useTransaction = 'testTheUpdateScriptBlockReplaysWithoutError' !== $this->name();

        parent::setUp();
    }

    public function testDeletingAModuleKeepsThePartAndFreezesTheModuleTitleOnIt(): void
    {
        $con = $this->getPropelConnection();

        $module = (new Module())
            ->setCode(self::MODULE_CODE)
            ->setFullNamespace('Thelia\\Tests\\Missing\\'.self::MODULE_CODE)
            ->setVersion('1.0.0')
            ->setType(BaseModule::PAYMENT_MODULE_TYPE)
            ->setCategory('payment')
            ->setActivate(BaseModule::IS_NOT_ACTIVATED);
        $module->save($con);
        $moduleId = $module->getId();

        $order = $this->factory->order(null, ['paymentModuleCode' => self::MODULE_CODE]);
        $locale = LangQuery::create()->findPk($order->getLangId())->getLocale();
        $module->setLocale($locale)->setTitle('Gift card')->save($con);

        $part = (new OrderPaymentPart())
            ->setOrderId($order->getId())
            ->setPaymentModuleId($moduleId)
            ->setAmount('12.500000')
            ->setIsPrimary(false)
            ->setPosition(0);
        $part->save($con);
        $partId = $part->getId();

        self::assertSame(OrderPaymentPartState::PENDING->value, OrderPaymentPartQuery::create()->findPk($partId)->getState());

        $this->dispatch(new ModuleDeleteEvent($moduleId), TheliaEvents::MODULE_DELETE);

        self::assertNull(ModuleQuery::create()->findPk($moduleId), 'The module is gone.');
        $reloaded = OrderPaymentPartQuery::create()->findPk($partId);
        self::assertNotNull($reloaded, 'The part stays.');
        self::assertNull($reloaded->getPaymentModuleId());
        self::assertSame('Gift card', $reloaded->getPaymentModuleTitle());
        self::assertSame('12.500000', $reloaded->getAmount());
    }

    public function testTheUpdateScriptBlockReplaysWithoutError(): void
    {
        $script = (string) file_get_contents(\dirname(__DIR__, 4).'/setup/update/sql/3.3.0.sql');
        self::assertSame(
            1,
            preg_match('/CREATE TABLE IF NOT EXISTS `order_payment_part`.*?ROW_FORMAT=DYNAMIC;/s', $script, $block),
            'The update script creates the table.',
        );

        $pdo = $this->getPropelConnection()->getWrappedConnection();
        $pdo->exec($block[0]);
        $pdo->exec($block[0]);

        $statement = $pdo->query(
            "SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_payment_part' ORDER BY ORDINAL_POSITION",
        );
        $columns = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            // MariaDB 10 prints the display width of an integer (int(11)), MySQL 8 and MariaDB 11 do not.
            $type = preg_replace('/^(tinyint|smallint|mediumint|int|bigint)\(\d+\)/', '$1', $row['COLUMN_TYPE']);
            $columns[$row['COLUMN_NAME']] = $row['IS_NULLABLE'].' '.$type;
        }

        self::assertSame([
            'id' => 'NO int',
            'order_id' => 'NO int',
            'payment_module_id' => 'YES int',
            'payment_module_title' => 'YES varchar(255)',
            'amount' => 'NO decimal(16,6)',
            'state' => 'NO varchar(16)',
            'is_primary' => 'NO tinyint',
            'position' => 'NO int',
            'created_at' => 'YES datetime',
            'updated_at' => 'YES datetime',
        ], $columns);
    }

    public function testTheStateEnumCoversTheFourValuesOfTheContract(): void
    {
        self::assertSame(
            ['pending', 'settled', 'failed', 'released'],
            array_map(static fn (OrderPaymentPartState $state): string => $state->value, OrderPaymentPartState::cases()),
        );
    }
}
