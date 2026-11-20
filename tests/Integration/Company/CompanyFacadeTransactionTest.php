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

namespace Thelia\Tests\Integration\Company;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Domain\Company\CompanyFacade;
use Thelia\Model\Company;
use Thelia\Model\CompanyQuery;

/**
 * An operation of the facade is one transaction: what it wrote before it failed is not kept. The
 * test opens none of its own, since a transaction inside another one cannot be rolled back alone.
 */
final class CompanyFacadeTransactionTest extends CompanyDomainTestCase
{
    protected bool $useTransaction = false;

    public function testWhatAFailingOperationWroteIsRolledBack(): void
    {
        $name = 'Rolled back '.bin2hex(random_bytes(4));

        try {
            $this->inTransaction(static function (ConnectionInterface $connection) use ($name): void {
                $company = new Company();
                $company->setName($name);
                $company->save($connection);

                throw new \RuntimeException('Failed after writing.');
            });
            self::fail('The failure was swallowed.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Failed after writing.', $exception->getMessage());
        }

        self::assertSame(0, CompanyQuery::create()->filterByName($name)->count());
    }

    public function testWhatASuccessfulOperationWroteIsCommitted(): void
    {
        $name = 'Committed '.bin2hex(random_bytes(4));

        try {
            $this->inTransaction(static function (ConnectionInterface $connection) use ($name): void {
                $company = new Company();
                $company->setName($name);
                $company->save($connection);
            });

            self::assertFalse($this->getPropelConnection()->inTransaction(), 'The transaction is closed.');
            self::assertSame(1, CompanyQuery::create()->filterByName($name)->count());
        } finally {
            CompanyQuery::create()->filterByName($name)->delete();
        }
    }

    private function inTransaction(callable $work): void
    {
        (new \ReflectionMethod(CompanyFacade::class, 'inTransaction'))->invoke($this->facade, $work);
    }
}
