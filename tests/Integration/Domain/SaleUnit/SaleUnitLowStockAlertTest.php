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

namespace Thelia\Tests\Integration\Domain\SaleUnit;

use Propel\Runtime\Propel;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\NullTransport;
use Thelia\Core\Event\ProductSaleElement\LowStockReachedEvent;
use Thelia\Core\Event\ProductSaleElement\SaleUnitStockChangedEvent;
use Thelia\Core\Event\ProductSaleElement\StockLevelChange;
use Thelia\Core\Event\ProductSaleElement\StockLevelChangedEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Domain\Catalog\Stock\LowStockAlertMailer;
use Thelia\Domain\Catalog\Stock\LowStockThreshold;
use Thelia\Domain\Catalog\Stock\StockLevelAnnouncer;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\SaleUnit;
use Thelia\Model\SaleUnitProductSaleElements;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Test\RecordingMailerFactory;
use Thelia\Tests\Support\Stock\TestCallerTransaction;

/**
 * The own stock of a sale unit runs low on its own threshold: the unit's when it has one, the
 * sale element's otherwise, the shop's last. Its changes are told on their own event, never on
 * the event of the national counter, and its alert names the sale unit.
 */
final class SaleUnitLowStockAlertTest extends ActionIntegrationTestCase
{
    /** @var list<LowStockReachedEvent> */
    private array $signals = [];

    /** @var list<StockLevelChange> */
    private array $toldNationally = [];

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    private ProductSaleElements $productSaleElements;

    private SaleUnit $saleUnit;

    protected function setUp(): void
    {
        parent::setUp();

        ConfigQuery::write('low_stock_threshold', '20');
        ConfigQuery::write('store_notification_emails', 'shop@example.com');
        $this->getService(TestCallerTransaction::class)->ignoreTheLevelsOpenOn(Propel::getConnection(ProductSaleElementsTableMap::DATABASE_NAME));

        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
        $this->productSaleElements = $this->factory->productSaleElement($product, ['quantity' => 100.0]);
        $this->saleUnit = $this->factory->saleUnit();

        $this->signals = [];
        $this->toldNationally = [];
        // Records the signal and stops it before the default e-mail listener.
        $this->listen(TheliaEvents::PRODUCT_SALE_ELEMENTS_LOW_STOCK_REACHED, function (LowStockReachedEvent $event): void {
            $this->signals[] = $event;
            $event->stopPropagation();
        }, 1000);
        $this->listen(TheliaEvents::PRODUCT_SALE_ELEMENTS_STOCK_CHANGED, function (StockLevelChangedEvent $event): void {
            $this->toldNationally = [...$this->toldNationally, ...$event->getChanges()];
        });
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];
        $this->getService(TestCallerTransaction::class)->reset();
        $this->getService(StockLevelAnnouncer::class)->reset();
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testWithoutAThresholdOfItsOwnTheUnitTakesTheOneOfItsSaleElementThenTheShopOne(): void
    {
        $this->unitStock(30.0);
        $threshold = $this->getService(LowStockThreshold::class);

        self::assertSame(20.0, $threshold->forSaleUnit($this->saleUnit->getId(), $this->productSaleElements->getId()));

        $this->productSaleElements->setLowStockThreshold(4.0)->save();
        self::assertSame(4.0, $threshold->forSaleUnit($this->saleUnit->getId(), $this->productSaleElements->getId()));
    }

    public function testTheThresholdOfTheUnitOverridesTheOthersAndZeroIsAValue(): void
    {
        $this->productSaleElements->setLowStockThreshold(4.0)->save();
        $stock = $this->unitStock(30.0);
        $threshold = $this->getService(LowStockThreshold::class);

        $stock->setLowStockThreshold(7.0)->save();
        self::assertSame(7.0, $threshold->forSaleUnit($this->saleUnit->getId(), $this->productSaleElements->getId()));

        $stock->setLowStockThreshold(0.0)->save();
        self::assertSame(0.0, $threshold->forSaleUnit($this->saleUnit->getId(), $this->productSaleElements->getId()));
    }

    public function testAUnitStockGoingUnderItsThresholdSignalsWithTheUnitThenStaysQuiet(): void
    {
        $this->unitStock(10.0)->setLowStockThreshold(5.0)->save();
        $announcer = $this->getService(StockLevelAnnouncer::class);

        $announcer->announce($this->productSaleElements->getId(), 10.0, 6.0, $this->saleUnit->getId());
        self::assertSame([], $this->signals, 'Above its own threshold, under the shop one: no signal.');

        $announcer->announce($this->productSaleElements->getId(), 6.0, 5.0, $this->saleUnit->getId());
        self::assertCount(1, $this->signals);
        self::assertSame($this->productSaleElements->getId(), $this->signals[0]->getProductSaleElementsId());
        self::assertSame($this->saleUnit->getId(), $this->signals[0]->getSaleUnitId());
        self::assertSame(5.0, $this->signals[0]->getQuantity());
        self::assertSame(5.0, $this->signals[0]->getThreshold());

        $announcer->announce($this->productSaleElements->getId(), 5.0, 2.0, $this->saleUnit->getId());
        self::assertCount(1, $this->signals);

        self::assertSame([], $this->toldNationally, 'The national counter did not move: nothing is told on its event.');
    }

    public function testTheNationalSignalCarriesNoSaleUnit(): void
    {
        $this->getService(StockLevelAnnouncer::class)->announce($this->productSaleElements->getId(), 22.0, 19.0);

        self::assertCount(1, $this->signals);
        self::assertNull($this->signals[0]->getSaleUnitId());
    }

    public function testAUnitChangeKeptForTheTransactionOfACallerIsToldOnceOverFromTheStockReadBack(): void
    {
        $stock = $this->unitStock(10.0);
        $told = [];
        $this->listen(TheliaEvents::SALE_UNIT_STOCK_CHANGED, static function (SaleUnitStockChangedEvent $event) use (&$told): void {
            $told = [...$told, ...$event->getChanges()];
        });
        $announcer = $this->getService(StockLevelAnnouncer::class);
        $connection = Propel::getConnection(ProductSaleElementsTableMap::DATABASE_NAME);

        $connection->beginTransaction();
        $stock->setQuantity('4.000000')->save($connection);
        $announcer->announce($this->productSaleElements->getId(), 10.0, 4.0, $this->saleUnit->getId());
        self::assertSame([], $told, 'Nothing is told while the caller holds its transaction open.');
        $connection->commit();

        $announcer->flush();

        self::assertEquals([new StockLevelChange($this->productSaleElements->getId(), 10.0, 4.0, $this->saleUnit->getId())], $told);
        self::assertSame([], $this->toldNationally);
    }

    public function testTheAlertOfAUnitNamesTheUnit(): void
    {
        $mailer = new RecordingMailerFactory(
            $this->getService(TemplateHelperInterface::class),
            $this->getService(ParserResolver::class),
            $this->getService(MailerInterface::class),
        );

        (new LowStockAlertMailer($mailer))->onLowStockReached(new LowStockReachedEvent($this->productSaleElements->getId(), 4.0, 5.0, $this->saleUnit->getId()));

        $sent = $mailer->parametersOfMessagesSent('low_stock_alert');
        self::assertCount(1, $sent);
        self::assertSame($this->saleUnit->getId(), $sent[0]['sale_unit_id'] ?? null);
        self::assertSame($this->saleUnit->getCode(), $sent[0]['sale_unit_code'] ?? null);
        self::assertSame((string) $this->saleUnit->getTitle(), $sent[0]['sale_unit_title'] ?? null);
        self::assertSame(4.0, $sent[0]['quantity']);
    }

    /**
     * The alert of a unit as the shop manager reads it: the unit is named in the HTML and in the
     * text. The CI of the core installs the mail template of its main branch, which may not name
     * the unit yet: the test is skipped until the installed template carries it.
     */
    public function testTheRenderedAlertOfAUnitNamesTheUnit(): void
    {
        $directory = $this->getService(TemplateHelperInterface::class)->getActiveMailTemplate()->getAbsolutePath();

        foreach (['low_stock_alert.html.twig', 'low_stock_alert.txt.twig'] as $file) {
            if (!str_contains((string) @file_get_contents($directory.DS.$file), 'sale_unit_title')) {
                self::markTestSkipped(\sprintf('The installed mail template (%s) does not name the sale unit yet.', $directory));
            }
        }

        $this->saleUnit->setTitle('North warehouse')->save();
        $mailer = new RecordingMailerFactory(
            $this->getService(TemplateHelperInterface::class),
            $this->getService(ParserResolver::class),
            $this->getService(MailerInterface::class),
        );
        (new LowStockAlertMailer($mailer))->onLowStockReached(new LowStockReachedEvent($this->productSaleElements->getId(), 4.0, 5.0, $this->saleUnit->getId()));
        $parameters = $mailer->parametersOfMessagesSent('low_stock_alert')[0] ?? null;
        self::assertIsArray($parameters);

        $email = (new MailerFactory(
            $this->getService(TemplateHelperInterface::class),
            $this->getService(ParserResolver::class),
            $this->getService(MailerInterface::class),
            new NullTransport(),
        ))->createEmailMessage('low_stock_alert', ['sender@example.com' => 'Sender'], ['recipient@example.com' => 'Recipient'], $parameters, 'en_US');

        self::assertMatchesRegularExpression('/^Sale unit : North warehouse$/m', (string) $email->getTextBody());
        self::assertMatchesRegularExpression('#Sale unit</strong></td>\s*<td[^>]*>North warehouse</td>#', (string) $email->getHtmlBody());
    }

    private function unitStock(float $quantity): SaleUnitProductSaleElements
    {
        return $this->factory->saleUnitProductSaleElements($this->saleUnit, $this->productSaleElements, $quantity);
    }

    private function listen(string $eventName, callable $listener, int $priority = 0): void
    {
        $this->dispatcher->addListener($eventName, $listener, $priority);
        $this->registeredListeners[] = [$eventName, $listener];
    }
}
