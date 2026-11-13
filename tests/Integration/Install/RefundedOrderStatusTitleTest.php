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

namespace Thelia\Tests\Integration\Install;

use Propel\Runtime\Propel;
use Thelia\Model\Map\OrderStatusI18nTableMap;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusI18nQuery;
use Thelia\Model\OrderStatusQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * The French title of the refunded status agrees with "commande", on a fresh install and on
 * an updated shop, without overwriting a title the merchant chose.
 */
final class RefundedOrderStatusTitleTest extends IntegrationTestCase
{
    public function testAFreshInstallSeedsTheFeminineTitle(): void
    {
        self::assertSame('Remboursée', $this->frenchTitle());
    }

    public function testTheUpdateFeminisesTheTitleTheInstallWroteAndCanBeReplayed(): void
    {
        $this->setFrenchTitle('Remboursé');

        $this->runStatementsOf3400();
        $this->runStatementsOf3400();

        self::assertSame('Remboursée', $this->frenchTitle());
    }

    public function testTheUpdateKeepsATitleTheMerchantChose(): void
    {
        $this->setFrenchTitle('Avoir émis');

        $this->runStatementsOf3400();

        self::assertSame('Avoir émis', $this->frenchTitle());
    }

    private function statusId(): int
    {
        $status = OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_REFUNDED);
        self::assertNotNull($status);

        return (int) $status->getId();
    }

    private function frenchTitle(): ?string
    {
        OrderStatusI18nTableMap::clearInstancePool();

        return OrderStatusI18nQuery::create()->filterById($this->statusId())->filterByLocale('fr_FR')->findOne()?->getTitle();
    }

    private function setFrenchTitle(string $title): void
    {
        Propel::getWriteConnection(OrderStatusI18nTableMap::DATABASE_NAME)
            ->prepare('UPDATE order_status_i18n SET title = :title WHERE id = :id AND locale = :locale')
            ->execute([':title' => $title, ':id' => $this->statusId(), ':locale' => 'fr_FR']);
    }

    private function runStatementsOf3400(): void
    {
        $sql = (string) file_get_contents(THELIA_SETUP_DIRECTORY.'update'.\DIRECTORY_SEPARATOR.'sql'.\DIRECTORY_SEPARATOR.'3.4.0.sql');
        $start = strpos($sql, '-- French title of the refunded order status');
        self::assertNotFalse($start, '3.4.0.sql no longer holds the refunded status block.');

        // The block is the last of the script, up to the line that turns the key checks back on.
        $end = strpos($sql, 'SET FOREIGN_KEY_CHECKS = 1;', $start);
        self::assertNotFalse($end);

        $connection = Propel::getWriteConnection(OrderStatusI18nTableMap::DATABASE_NAME);
        $run = 0;

        foreach (explode(';', substr($sql, $start, $end - $start)) as $chunk) {
            $statement = trim(preg_replace('/^\s*--.*$/m', '', $chunk) ?? '');

            if ('' !== $statement) {
                $connection->exec($statement);
                ++$run;
            }
        }

        self::assertSame(1, $run);
    }
}
