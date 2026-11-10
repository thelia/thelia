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

namespace Thelia\Tests\Http\BackOffice;

use BackOfficeDefaultTwigBundle\Controller\Configuration\AccountingController;
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Domain\Accounting\AccountingChart;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\ExportJobQuery;
use Thelia\Model\ExportQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductTax;
use Thelia\Model\OrderStatus;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;

/**
 * The chart of accounts as the merchant sets it, and the sales journal exported from the
 * export screen, its report shown once the file is downloaded.
 */
final class AccountingExportsBackOfficeTest extends WebIntegrationTestCase
{
    private const PAGE = '/admin/configuration/accounting';

    private AdminSessionInjector $injector;

    protected function setUp(): void
    {
        // A skip rather than a failure: the core ships with whichever back-office theme it
        // is given, and one that predates the accounting exports has no such screen.
        if (!class_exists(AccountingController::class)) {
            self::markTestSkipped('The installed back-office theme predates the accounting exports.');
        }

        parent::setUp();

        $this->injector = new AdminSessionInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->injector);
        $admin = $this->fixtures()->admin();
        $admin->eraseCredentials();
        $this->injector->setAdmin($admin);
    }

    protected function tearDown(): void
    {
        if (isset($this->injector)) {
            $this->injector->clear();
        }

        parent::tearDown();
        ConfigQuery::resetCache();
    }

    public function testTheChartOfAccountsIsSavedFromItsPage(): void
    {
        $crawler = $this->client->request('GET', self::PAGE);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $form = $crawler->filter('[data-testid="accounting-save"]')->form();
        $values = $form->getPhpValues();
        $values['customer_account'] = '411000';
        $values['shipping_account'] = '708500';
        $values['rates'][0] = ['rate' => '20', 'product' => '706200', 'tax' => '445720'];
        $values['rates'][1] = ['rate' => '5,5', 'product' => '706055', 'tax' => '445705'];

        $this->client->request('POST', $form->getUri(), $values);

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertSame('411000', ConfigQuery::read(AccountingChart::CUSTOMER_ACCOUNT_KEY));
        self::assertSame('20:706200:445720,5.5:706055:445705', ConfigQuery::read(AccountingChart::RATE_ACCOUNTS_KEY));

        $crawler = $this->client->request('GET', self::PAGE);
        self::assertSame('5.5', $crawler->filter('[data-testid="accounting-rate-1"]')->attr('value'));
    }

    public function testAChartThatCannotBeReadIsRefusedAndNothingIsWritten(): void
    {
        ConfigQuery::write(AccountingChart::RATE_ACCOUNTS_KEY, '20:706200:445720');
        $crawler = $this->client->request('GET', self::PAGE);
        $form = $crawler->filter('[data-testid="accounting-save"]')->form();
        $values = $form->getPhpValues();
        $values['rates'][0] = ['rate' => '20', 'product' => '706200', 'tax' => ''];

        $crawler = $this->client->request('POST', $form->getUri(), $values);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('20.00%', $crawler->filter('[data-testid="accounting-error"]')->text(''));
        self::assertSame('20:706200:445720', ConfigQuery::read(AccountingChart::RATE_ACCOUNTS_KEY));
    }

    public function testTheJournalIsDownloadedAndItsReportShownOnItsJob(): void
    {
        ConfigQuery::write(AccountingChart::CUSTOMER_ACCOUNT_KEY, '411000');
        ConfigQuery::write(AccountingChart::SHIPPING_ACCOUNT_KEY, '708500');
        ConfigQuery::write(AccountingChart::RATE_ACCOUNTS_KEY, '20:706200:445720');
        $this->invoicedOrder();
        $exportId = (int) ExportQuery::create()->findOneByRef('thelia.export.sales_journal')->getId();

        $crawler = $this->client->request('GET', '/admin/export/'.$exportId);
        $token = (string) $crawler->filter('input[name="_token"]')->attr('value');
        $this->client->request('POST', '/admin/export/'.$exportId, [
            '_token' => $token,
            'language' => (string) Lang::getDefaultLanguage()->getId(),
            'serializer' => 'thelia.fec',
            'range_date_start' => ['year' => '2026', 'month' => '2'],
            'range_date_end' => ['year' => '2026', 'month' => '2'],
        ]);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertStringStartsWith('text/plain', (string) $this->client->getResponse()->headers->get('Content-Type'));

        $job = ExportJobQuery::create()->filterByExportId($exportId)->orderById(Criteria::DESC)->findOne() ?? self::fail('The export left no job.');
        $this->client->request('GET', '/admin/export/job/'.$job->getId());
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertMatchesRegularExpression('/[1-9]\d* pi(e|è)ces?/', $this->client->getCrawler()->filter('[data-testid="export-job-report"]')->text(''));
    }

    public function testTheFecFormatIsOfferedToTheSalesJournalOnly(): void
    {
        $formats = function (string $ref): array {
            $exportId = (int) ExportQuery::create()->findOneByRef($ref)->getId();
            $crawler = $this->client->request('GET', '/admin/export/'.$exportId);
            self::assertSame(200, $this->client->getResponse()->getStatusCode());

            return $crawler->filter('#bo-export-serializer option')->each(static fn ($option): string => (string) $option->attr('value'));
        };

        self::assertContains('thelia.fec', $formats('thelia.export.sales_journal'));
        self::assertNotContains('thelia.fec', $formats('thelia.export.tax_summary'));
        self::assertNotContains('thelia.fec', $formats('thelia.export.orders'));
        self::assertContains('thelia.csv', $formats('thelia.export.orders'));
    }

    public function testWithoutAChartTheExportSaysWhatToSet(): void
    {
        ConfigQuery::write(AccountingChart::CUSTOMER_ACCOUNT_KEY, '');
        ConfigQuery::write(AccountingChart::SHIPPING_ACCOUNT_KEY, '');
        ConfigQuery::write(AccountingChart::RATE_ACCOUNTS_KEY, '');
        $exportId = (int) ExportQuery::create()->findOneByRef('thelia.export.sales_journal')->getId();

        $crawler = $this->client->request('GET', '/admin/export/'.$exportId);
        $token = (string) $crawler->filter('input[name="_token"]')->attr('value');
        $this->client->request('POST', '/admin/export/'.$exportId, [
            '_token' => $token,
            'language' => (string) LangQuery::create()->findOneByLocale('en_US')->getId(),
            'serializer' => 'thelia.fec',
        ]);

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        $crawler = $this->client->followRedirect();

        $flash = $crawler->filter('[data-testid="bo-flash-error"]')->text('');
        self::assertStringContainsString('the customer account', $flash);
        self::assertStringContainsString('the shipping account', $flash);
        self::assertStringContainsString('the accounts of the tax rates', $flash);
        self::assertStringNotContainsString('server error', $flash);
    }

    private function invoicedOrder(): void
    {
        $fixtures = $this->fixtures();
        $order = $fixtures->order(null, ['postage' => 0, 'statusCode' => OrderStatus::CODE_PAID]);
        $order
            ->setCurrencyId((int) CurrencyQuery::create()->findOneByByDefault(1)->getId())
            ->setInvoiceRef('BO172-'.$order->getId())
            ->setInvoiceDate(new \DateTime('2026-02-10 10:00:00'))
            ->save($this->getPropelConnection());
        $line = (new OrderProduct())
            ->setOrderId($order->getId())
            ->setProductRef('REF')
            ->setProductSaleElementsRef('PSE')
            ->setProductSaleElementsId(0)
            ->setTitle('Line')
            ->setQuantity(1)
            ->setPrice('100')
            ->setPromoPrice('100')
            ->setWasNew(0)
            ->setWasInPromo(0);
        $line->save($this->getPropelConnection());
        (new OrderProductTax())->setOrderProductId($line->getId())->setTitle('VAT')->setAmount('20')->setPromoAmount('20')->save($this->getPropelConnection());
    }

    private function fixtures(): FixtureFactory
    {
        return new FixtureFactory($this->getPropelConnection());
    }
}
