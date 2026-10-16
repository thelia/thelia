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

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\MailerInterface;
use Thelia\Core\Event\PdfEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Domain\Order\Service\OrderHistoryRecorder;
use Thelia\Domain\SaleUnit\Config\SaleUnitConfigScope;
use Thelia\Domain\SaleUnit\Config\SaleUnitConfigService;
use Thelia\Domain\SaleUnit\SaleUnitActivityChecker;
use Thelia\Domain\SaleUnit\SaleUnitContext;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleConfigQuery;
use Thelia\Model\Order;
use Thelia\Model\SaleUnit;
use Thelia\Module\BaseModule;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\RecordingMailerFactory;
use Thelia\Test\Trait\RecordsSqlQueries;
use Thelia\Tests\Support\Module\SaleUnitConfigProbe;

/**
 * A sale unit sets its own store settings and gives its own values to the settings
 * of a module; everything it does not set falls back on the national value. The unit
 * that counts is the one of the request, or the one of the order for what is about an
 * order, an e-mail or a document, from a command as well as from a request.
 */
final class SaleUnitConfigTest extends IntegrationTestCase
{
    use RecordsSqlQueries;

    private SaleUnitConfigService $saleUnitConfig;

    private SaleUnitContext $saleUnitContext;

    protected function setUp(): void
    {
        parent::setUp();

        $this->saleUnitConfig = $this->getService(SaleUnitConfigService::class);
        $this->saleUnitContext = $this->getService(SaleUnitContext::class);

        // The id of a module code is memoised: the probe module is a new row in each test.
        (new \ReflectionProperty(BaseModule::class, 'moduleIds'))->setValue(null, []);
    }

    protected function tearDown(): void
    {
        $this->getService(SaleUnitContext::class)->reset();
        $this->getService(SaleUnitActivityChecker::class)->reset();
        (new \ReflectionProperty(BaseModule::class, 'moduleIds'))->setValue(null, []);

        parent::tearDown();
    }

    public function testTheCurrentSaleUnitReadsTheStoreSettingsItSetsAndTheNationalOnesOtherwise(): void
    {
        $fixtures = $this->createFixtureFactory();
        $lyon = $fixtures->saleUnit();
        $paris = $fixtures->saleUnit();
        ConfigQuery::write('store_name', 'National shop');
        ConfigQuery::write('store_email', 'shop@example.com');
        $this->saleUnitConfig->setStoreValue($lyon->getId(), 'store_name', 'Lyon shop');

        $this->saleUnitContext->setSaleUnit($lyon);
        self::assertSame('Lyon shop', ConfigQuery::getStoreName());
        self::assertSame('shop@example.com', ConfigQuery::getStoreEmail(), 'a setting the unit does not set is the national one');

        $this->saleUnitContext->setSaleUnit($paris);
        self::assertSame('National shop', ConfigQuery::getStoreName(), 'a unit that sets nothing reads the national value');

        $this->saleUnitContext->setSaleUnit(null);
        self::assertSame('National shop', ConfigQuery::getStoreName());

        self::assertSame('Lyon shop', $this->saleUnitConfig->getStoreValue($lyon->getId(), 'store_name'));
        self::assertSame('National shop', $this->saleUnitConfig->getStoreValue(null, 'store_name'));
    }

    public function testAStoreSettingWrittenDuringTheRequestIsReadAtOnceAndItsRemovalBringsTheNationalValueBack(): void
    {
        $lyon = $this->createFixtureFactory()->saleUnit();
        ConfigQuery::write('store_name', 'National shop');
        $this->saleUnitContext->setSaleUnit($lyon);

        self::assertSame('National shop', ConfigQuery::getStoreName());

        $this->saleUnitConfig->setStoreValue($lyon->getId(), 'store_name', 'Lyon shop');
        self::assertSame('Lyon shop', ConfigQuery::getStoreName());

        $this->saleUnitConfig->removeStoreValue($lyon->getId(), 'store_name');
        self::assertSame('National shop', ConfigQuery::getStoreName());
    }

    public function testTheSettingsTheTaxesDependOnStayNational(): void
    {
        $lyon = $this->createFixtureFactory()->saleUnit();

        $this->expectException(\InvalidArgumentException::class);

        $this->saleUnitConfig->setStoreValue($lyon->getId(), 'store_country', '64');
    }

    public function testAModuleReadsTheValueTheCurrentSaleUnitGivesItAndTheNationalOneOtherwise(): void
    {
        $fixtures = $this->createFixtureFactory();
        $lyon = $fixtures->saleUnit();
        $paris = $fixtures->saleUnit();
        $this->probeModule();

        SaleUnitConfigProbe::setConfigValue('site_id', 'national-site');
        SaleUnitConfigProbe::setConfigValue('mode', 'PRODUCTION');
        SaleUnitConfigProbe::setConfigValueForSaleUnit($lyon->getId(), 'site_id', 'lyon-site', secret: true);

        $this->saleUnitContext->setSaleUnit($lyon);
        self::assertSame('lyon-site', SaleUnitConfigProbe::getConfigValue('site_id'));
        self::assertSame('lyon-site', SaleUnitConfigProbe::getConfigValue('site_id', null, 'fr_FR'), 'a unit value is the same in every locale');
        self::assertSame('PRODUCTION', SaleUnitConfigProbe::getConfigValue('mode'), 'a setting the unit does not set is the national one');

        $this->saleUnitContext->setSaleUnit($paris);
        self::assertSame('national-site', SaleUnitConfigProbe::getConfigValue('site_id'));

        $this->saleUnitContext->setSaleUnit(null);
        self::assertSame('national-site', SaleUnitConfigProbe::getConfigValue('site_id'));
    }

    /**
     * The notification a payment provider sends about an order comes from its server,
     * with no visitor and no sale unit resolved: the module reads its keys for the unit
     * of the order, whatever is in scope.
     */
    public function testAModuleReadsTheValueOfAGivenSaleUnitWhateverIsInScope(): void
    {
        $fixtures = $this->createFixtureFactory();
        $lyon = $fixtures->saleUnit();
        $paris = $fixtures->saleUnit();
        $module = $this->probeModule();

        SaleUnitConfigProbe::setConfigValue('secret_key', 'national-key');
        SaleUnitConfigProbe::setConfigValueForSaleUnit($lyon->getId(), 'secret_key', 'lyon-key', secret: true);

        self::assertSame('lyon-key', SaleUnitConfigProbe::getConfigValueForSaleUnit($lyon->getId(), 'secret_key'));
        self::assertSame('national-key', SaleUnitConfigProbe::getConfigValueForSaleUnit($paris->getId(), 'secret_key'));
        self::assertSame('national-key', SaleUnitConfigProbe::getConfigValueForSaleUnit(null, 'secret_key'));

        $this->saleUnitContext->setSaleUnit($paris);
        self::assertSame('lyon-key', SaleUnitConfigProbe::getConfigValueForSaleUnit($lyon->getId(), 'secret_key'));
        self::assertSame('national-key', SaleUnitConfigProbe::getConfigValueForSaleUnit(null, 'secret_key'), 'null reads the national value, not the one in scope');
        self::assertSame('lyon-key', $this->saleUnitConfig->getModuleValue($lyon->getId(), $module->getId(), 'secret_key'));

        SaleUnitConfigProbe::deleteConfigValueForSaleUnit($lyon->getId(), 'secret_key');
        self::assertSame('national-key', SaleUnitConfigProbe::getConfigValueForSaleUnit($lyon->getId(), 'secret_key'));
    }

    public function testRunForGivesTheScopeBackEvenWhenTheCallbackFails(): void
    {
        $lyon = $this->createFixtureFactory()->saleUnit();
        $this->saleUnitContext->setSaleUnit($lyon);

        try {
            SaleUnitConfigScope::runFor(null, static function (): never {
                throw new \RuntimeException('failed');
            });
        } catch (\RuntimeException) {
        }

        self::assertSame($lyon->getId(), SaleUnitConfigScope::current());
    }

    /**
     * Sent from a command, with no request and no sale unit resolved: the sender, the
     * name and the notification recipients are those of the unit of the order.
     */
    public function testAnOrderEmailLeavesWithTheSettingsOfTheSaleUnitOfTheOrder(): void
    {
        $fixtures = $this->createFixtureFactory();
        $lyon = $fixtures->saleUnit();
        ConfigQuery::write('store_name', 'National shop');
        ConfigQuery::write('store_email', 'shop@example.com');
        ConfigQuery::write('store_notification_emails', 'orders@example.com');
        $this->saleUnitConfig->setStoreValue($lyon->getId(), 'store_name', 'Lyon shop');
        $this->saleUnitConfig->setStoreValue($lyon->getId(), 'store_email', 'lyon@example.com');
        $this->saleUnitConfig->setStoreValue($lyon->getId(), 'store_notification_emails', 'orders-lyon@example.com');

        $lyonOrder = $this->orderOf($lyon);
        $nationalOrder = $fixtures->order();
        $mailer = $this->recordingMailer();

        $mailer->sendEmailToCustomer('order_confirmation', $lyonOrder->getCustomer(), ['order_id' => $lyonOrder->getId()]);
        $mailer->sendEmailToShopManagers('order_notification', ['order_ref' => $lyonOrder->getRef()]);
        $mailer->sendEmailToCustomer('order_confirmation', $nationalOrder->getCustomer(), ['order_id' => $nationalOrder->getId()]);

        self::assertSame(['lyon@example.com' => 'Lyon shop'], $mailer->messages[0]['from']);
        self::assertSame(['lyon@example.com' => 'Lyon shop'], $mailer->messages[1]['from']);
        self::assertSame(['orders-lyon@example.com' => 'Lyon shop'], $mailer->messages[1]['to']);
        self::assertSame(['shop@example.com' => 'National shop'], $mailer->messages[2]['from'], 'a national order leaves with the national settings');
        self::assertNull(SaleUnitConfigScope::current(), 'the scope is given back once the e-mail has left');
    }

    /**
     * Printed from a command: the identity on the invoice is the one of the sale unit
     * the order was placed on.
     */
    public function testTheInvoiceOfAnOrderCarriesTheIdentityOfItsSaleUnit(): void
    {
        $lyon = $this->createFixtureFactory()->saleUnit();
        ConfigQuery::write('store_siret', '11111111100011');
        $this->saleUnitConfig->setStoreValue($lyon->getId(), 'store_siret', '22222222200022');
        $order = $this->orderOf($lyon);

        $html = $this->renderInvoice($order);

        self::assertStringContainsString('22222222200022', $html);
        self::assertStringNotContainsString('11111111100011', $html);
    }

    /**
     * Nothing about a sale unit is read for a single shop: neither the store settings,
     * nor the settings of a module, nor the unit of the order an e-mail is about.
     */
    public function testASingleShopReadsNoSaleUnitTable(): void
    {
        $order = $this->createFixtureFactory()->order();
        $this->probeModule();
        SaleUnitConfigProbe::setConfigValue('site_id', 'national-site');
        $mailer = $this->recordingMailer();
        $activityChecker = $this->getService(SaleUnitActivityChecker::class);
        // The shared entry is written once, as the first request of the shop does.
        $activityChecker->isMultiSaleUnitMode();

        $statements = $this->recordSqlQueries(static function () use ($mailer, $order, $activityChecker): void {
            $activityChecker->reset();
            ConfigQuery::read('store_name', '', true);
            SaleUnitConfigProbe::getConfigValue('site_id');
            $mailer->sendEmailToCustomer('order_confirmation', $order->getCustomer(), ['order_id' => $order->getId()]);
        });

        self::assertNotSame([], $statements, 'The work is recorded: it reads the configuration it serves.');
        self::assertSame([], array_values(array_filter(
            $statements,
            static fn (string $statement): bool => 1 === preg_match('/`sale_unit(_\w+)?`/', $statement),
        )), 'A single shop must not ask the database about sale units.');
    }

    private function probeModule(): Module
    {
        $module = (new Module())
            ->setCode('SaleUnitConfigProbe')
            ->setType(BaseModule::CLASSIC_MODULE_TYPE)
            ->setActivate(BaseModule::IS_ACTIVATED)
            ->setPosition(1)
            ->setFullNamespace(SaleUnitConfigProbe::class);
        $module->save();

        ModuleConfigQuery::resetConfigCache();

        return $module;
    }

    private function orderOf(SaleUnit $saleUnit): Order
    {
        $order = $this->createFixtureFactory()->order();
        $order->setSaleUnitId($saleUnit->getId())->save();

        return $order;
    }

    private function recordingMailer(): RecordingMailerFactory
    {
        return new RecordingMailerFactory(
            $this->getService(TemplateHelperInterface::class),
            $this->getService(ParserResolver::class),
            $this->getService(MailerInterface::class),
            $this->getService(OrderHistoryRecorder::class),
            $this->saleUnitConfig,
        );
    }

    private function renderInvoice(Order $order): string
    {
        $html = null;
        $capture = static function (PdfEvent $event) use (&$html): void {
            $html = (string) $event->getContent();
        };

        $dispatcher = $this->getService(EventDispatcherInterface::class);
        $dispatcher->addListener(TheliaEvents::GENERATE_PDF, $capture, 1024);
        $output = sys_get_temp_dir().'/sale-unit-invoice-'.bin2hex(random_bytes(6)).'.pdf';

        try {
            $tester = new CommandTester((new Application(self::$kernel))->find('pdf:render'));
            $tester->execute(['document' => 'invoice', '--order' => (string) $order->getId(), '--out' => $output]);
            $tester->assertCommandIsSuccessful();
        } finally {
            $dispatcher->removeListener(TheliaEvents::GENERATE_PDF, $capture);

            if (file_exists($output)) {
                unlink($output);
            }
        }

        self::assertIsString($html, 'the invoice was rendered');

        return $html;
    }
}
