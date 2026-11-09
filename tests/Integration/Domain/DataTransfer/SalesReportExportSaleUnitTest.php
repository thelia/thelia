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

namespace Thelia\Tests\Integration\Domain\DataTransfer;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Thelia\Core\Archiver\ArchiverManager;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Serializer\SerializerManager;
use Thelia\Domain\DataTransfer\Exception\DataTransferNoDataFoundException;
use Thelia\Domain\DataTransfer\Export\Type\SalesByCategoryExport;
use Thelia\Domain\DataTransfer\Export\Type\SalesByCustomerExport;
use Thelia\Domain\DataTransfer\Export\Type\SalesByProductExport;
use Thelia\Domain\DataTransfer\ExportHandler;
use Thelia\Domain\DataTransfer\Job\ExportJobLauncher;
use Thelia\Domain\DataTransfer\Job\JobClaim;
use Thelia\Domain\DataTransfer\Job\JobLifecycle;
use Thelia\Domain\DataTransfer\Job\JobStatus;
use Thelia\Domain\DataTransfer\Job\RunExportJob;
use Thelia\Domain\DataTransfer\Job\RunExportJobHandler;
use Thelia\Domain\SaleUnit\Access\AdminSaleUnitScope;
use Thelia\Messenger\Transport\ConfiguredQueues;
use Thelia\Messenger\Transport\ShopDatabaseConnection;
use Thelia\Model\Admin;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\Export;
use Thelia\Model\ExportQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderStatus;
use Thelia\Model\SaleUnit;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * An administrator attached to some sale units exports the sales of those units only, by
 * every path that runs an export for an administrator: the job a worker runs, the job run at
 * once when there is no queue, and the handler given the units. A national order (placed on
 * no sale unit) is left out for a restricted administrator; one attached to none, and a run
 * with no administrator (the console, a scheduled report), cover the whole shop.
 */
final class SalesReportExportSaleUnitTest extends IntegrationTestCase
{
    private const string IN_THE_PERIOD = '2001-06-15 12:00:00';

    private FixtureFactory $factory;

    private SaleUnit $mine;

    private SaleUnit $theirs;

    /** @var list<string> */
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->mine = $this->factory->saleUnit();
        $this->theirs = $this->factory->saleUnit();

        $customer = $this->factory->customer($this->factory->customerTitle());

        // The orders of the other units sell more: the best seller of the whole shop is not one of ours.
        $this->orderOn($this->mine->getId(), 'MINE', $customer, '10.000000');
        $this->orderOn($this->theirs->getId(), 'THEIRS', $customer, '20.000000');
        $this->orderOn(null, 'NATIONAL', $customer, '20.000000');
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        $this->getService(AdminSaleUnitScope::class)->reset();
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function theSalesExports(): iterable
    {
        yield 'by category' => [SalesByCategoryExport::class];
        yield 'by product' => [SalesByProductExport::class];
        yield 'by customer' => [SalesByCustomerExport::class];
    }

    public function testTheHandlerGivenTheUnitsExportsTheOrdersOfThoseUnitsOnly(): void
    {
        $file = $this->csvOf($this->handlerExport(SalesByProductExport::class, [$this->mine->getId()]));

        self::assertSame(['MINE'], array_column($file, 'Product reference'));
    }

    public function testTheHandlerGivenNoUnitExportsTheWholeShop(): void
    {
        $file = $this->csvOf($this->handlerExport(SalesByProductExport::class, null));

        self::assertEqualsCanonicalizing(['MINE', 'THEIRS', 'NATIONAL'], array_column($file, 'Product reference'));
    }

    public function testTheHandlerGivenAnEmptyListOfUnitsReachesNoOrder(): void
    {
        $this->expectException(DataTransferNoDataFoundException::class);

        $this->handlerExport(SalesByProductExport::class, []);
    }

    #[DataProvider('theSalesExports')]
    public function testARestrictedAdministratorExportsTheirUnitsOnlyWhenTheJobIsRunByAWorker(string $class): void
    {
        $admin = $this->restrictedAdmin([$this->mine]);
        $queue = $this->queue();

        $job = $this->launcherWith($queue)->launch($this->exportOf($class), 'thelia.csv', language: LangQuery::create()->findOneByLocale('en_US'), rangeDate: $this->june(), adminId: $admin->getId());

        self::assertSame(JobStatus::QUEUED, $job->getJobStatus());

        $this->getService(RunExportJobHandler::class)(new RunExportJob($job->getId()));
        $job->reload();
        $this->files[] = (string) $job->getFilePath();

        self::assertSame(JobStatus::DONE, $job->getJobStatus(), (string) $job->getError());
        $this->assertOnlyMine($class, $this->csvOf((string) $job->getFilePath()));
    }

    #[DataProvider('theSalesExports')]
    public function testARestrictedAdministratorExportsTheirUnitsOnlyWhenTheJobRunsAtOnce(string $class): void
    {
        $admin = $this->restrictedAdmin([$this->mine]);

        $job = $this->getService(ExportJobLauncher::class)->launch($this->exportOf($class), 'thelia.csv', language: LangQuery::create()->findOneByLocale('en_US'), rangeDate: $this->june(), adminId: $admin->getId());
        $this->files[] = (string) $job->getFilePath();

        self::assertSame(JobStatus::DONE, $job->getJobStatus(), (string) $job->getError());
        $this->assertOnlyMine($class, $this->csvOf((string) $job->getFilePath()));
    }

    public function testAnAdministratorAttachedToNoUnitExportsTheWholeShop(): void
    {
        $admin = $this->factory->restrictedAdmin([AdminResources::ORDER => [AccessManager::VIEW]]);

        $job = $this->getService(ExportJobLauncher::class)->launch($this->exportOf(SalesByProductExport::class), 'thelia.csv', language: LangQuery::create()->findOneByLocale('en_US'), rangeDate: $this->june(), adminId: $admin->getId());
        $this->files[] = (string) $job->getFilePath();

        self::assertEqualsCanonicalizing(['MINE', 'THEIRS', 'NATIONAL'], array_column($this->csvOf((string) $job->getFilePath()), 'Product reference'));
    }

    public function testAJobNamingNoAdministratorExportsTheWholeShop(): void
    {
        $job = $this->getService(ExportJobLauncher::class)->launch($this->exportOf(SalesByProductExport::class), 'thelia.csv', language: LangQuery::create()->findOneByLocale('en_US'), rangeDate: $this->june());
        $this->files[] = (string) $job->getFilePath();

        self::assertEqualsCanonicalizing(['MINE', 'THEIRS', 'NATIONAL'], array_column($this->csvOf((string) $job->getFilePath()), 'Product reference'));
    }

    public function testTheSummaryOfARestrictedExportCountsItsOrdersOnly(): void
    {
        $export = new SalesByProductExport();
        $export->setLang(LangQuery::create()->findOneByLocale('en_US'));
        $export->setRangeDate($this->june());
        $export->restrictToSaleUnits([$this->mine->getId()]);

        $lines = array_map(static fn ($line): array => [$line->label, $line->value], $export->summarize());

        self::assertStringStartsWith('MINE (', $lines[1][1], 'The best seller of the unit, and nothing of the other orders.');
    }

    /**
     * @param list<array<string, string>> $rows
     */
    private function assertOnlyMine(string $class, array $rows): void
    {
        if (SalesByProductExport::class === $class) {
            self::assertSame(['MINE'], array_column($rows, 'Product reference'));

            return;
        }

        if (SalesByCategoryExport::class === $class) {
            // The uncategorized goods of the order, then the discounts, the shipping and the total.
            self::assertSame('1', end($rows)['Orders'], 'The total counts the order of the unit only.');

            return;
        }

        self::assertCount(1, $rows);
        self::assertSame('1', $rows[0]['Orders']);
    }

    /**
     * @param list<int>|null $saleUnitIds
     */
    private function handlerExport(string $class, ?array $saleUnitIds): string
    {
        $event = $this->getService(ExportHandler::class)->export(
            $this->exportOf($class),
            $this->getService(SerializerManager::class)->get('thelia.csv'),
            null,
            LangQuery::create()->findOneByLocale('en_US'),
            false,
            false,
            $this->june(),
            null,
            $saleUnitIds,
        );
        $this->files[] = $event->getFilePath();

        return $event->getFilePath();
    }

    /**
     * @return list<array<string, string>>
     */
    private function csvOf(string $file): array
    {
        $handle = fopen($file, 'r');
        $headers = fgetcsv($handle, null, ',', '"', '');
        $rows = [];

        while (false !== ($row = fgetcsv($handle, null, ',', '"', ''))) {
            $rows[] = array_combine($headers, $row);
        }

        fclose($handle);

        return $rows;
    }

    private function exportOf(string $class): Export
    {
        $ref = match ($class) {
            SalesByCategoryExport::class => 'thelia.export.sales_by_category',
            SalesByProductExport::class => 'thelia.export.sales_by_product',
            default => 'thelia.export.sales_by_customer',
        };

        return ExportQuery::create()->findOneByRef($ref) ?? self::fail("The export $ref is not in the catalogue.");
    }

    /**
     * @param list<SaleUnit> $units
     */
    private function restrictedAdmin(array $units): Admin
    {
        $admin = $this->factory->restrictedAdmin([AdminResources::ORDER => [AccessManager::VIEW]]);

        foreach ($units as $unit) {
            $this->factory->adminSaleUnit($admin, $unit);
        }

        return $admin;
    }

    private function orderOn(?int $saleUnitId, string $productRef, Customer $customer, string $price): Order
    {
        $order = $this->factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID]);
        $order->setCreatedAt(new \DateTime(self::IN_THE_PERIOD));
        $order->setSaleUnitId($saleUnitId);
        $order->save($this->getPropelConnection());
        $this->factory->orderProduct($order, ['productRef' => $productRef, 'title' => $productRef, 'price' => $price, 'promoPrice' => $price]);

        return $order;
    }

    /**
     * @return array{start: \DateTime, end: \DateTime}
     */
    private function june(): array
    {
        return ['start' => new \DateTime('2001-06-01 00:00:00'), 'end' => new \DateTime('2001-06-30 23:59:59')];
    }

    private function launcherWith(MessageBusInterface $bus): ExportJobLauncher
    {
        return new ExportJobLauncher(
            $this->getService(SerializerManager::class),
            $this->getService(ArchiverManager::class),
            new JobLifecycle(new JobClaim(), $bus, new ConfiguredQueues($this->getService(ShopDatabaseConnection::class), 'doctrine://default', 'doctrine://default?queue_name=heavy', 'doctrine://default?queue_name=failed')),
        );
    }

    private function queue(): MessageBusInterface
    {
        return new class implements MessageBusInterface {
            public function dispatch(object $message, array $stamps = []): Envelope
            {
                return Envelope::wrap($message, $stamps);
            }
        };
    }
}
