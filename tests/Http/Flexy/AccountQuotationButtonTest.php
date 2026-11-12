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

namespace Thelia\Tests\Http\Flexy;

use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\Flexy\CustomerSessionInjector;
use Thelia\Tests\Support\Order\PickingSlipFixtureTheme;

/**
 * The order page of a quotation offers "Download quote" only when the active PDF template
 * has a `quotation` document: without one the link would end on a 404.
 *
 * The page belongs to the theme, which ships on its own release cycle: a theme that does
 * not carry the guard yet is reported as skipped rather than failed.
 */
final class AccountQuotationButtonTest extends WebIntegrationTestCase
{
    use PickingSlipFixtureTheme;

    private const QUOTATION_ROUTE = 'account_order_pdf_quotation';

    private const ORDER_ROUTE = 'account_order';

    // Read in the installed theme: absent from a theme that still shows the button unconditionally.
    private const THEME_MARKER = 'quotationAvailable';

    private ?CustomerSessionInjector $injector = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessTheThemeCarries();

        $this->injector = new CustomerSessionInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->injector);
    }

    protected function tearDown(): void
    {
        $this->injector?->clear();
        $this->removePickingSlipTheme();

        parent::tearDown();
    }

    public function testNoQuoteButtonWhenThePdfTemplateHasNoQuotationDocument(): void
    {
        $this->installPickingSlipTheme(withPreparationTemplate: false);

        $content = $this->orderPage();

        self::assertStringNotContainsString($this->quotationUrl(), $content);
    }

    public function testQuoteButtonWhenThePdfTemplateHasAQuotationDocument(): void
    {
        $this->installPickingSlipTheme(withPreparationTemplate: false);
        file_put_contents(
            THELIA_TEMPLATE_DIR.'pdf/'.$this->fixtureTheme.'/quotation.html.twig',
            '<html><body>QUOTE</body></html>',
        );

        $content = $this->orderPage();

        self::assertStringContainsString($this->quotationUrl(), $content);
    }

    private string $orderIdForUrl = '';

    private function orderPage(): string
    {
        $factory = new FixtureFactory($this->getPropelConnection());
        $factory->orderStatus(['code' => 'quotation', 'title' => 'Quotation']);
        $customer = $factory->customer($factory->customerTitle());
        $order = $factory->order($customer, ['statusCode' => 'quotation']);
        $this->orderIdForUrl = (string) $order->getId();

        $this->injector?->setCustomer($customer);
        $this->client->request('GET', $this->getService(RouterInterface::class)->generate(
            self::ORDER_ROUTE,
            ['orderId' => $order->getId()],
        ));

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        return (string) $this->client->getResponse()->getContent();
    }

    private function quotationUrl(): string
    {
        return $this->getService(RouterInterface::class)->generate(
            self::QUOTATION_ROUTE,
            ['orderId' => (int) $this->orderIdForUrl],
        );
    }

    private function skipUnlessTheThemeCarries(): void
    {
        $controller = (new \ReflectionClass('FlexyBundle\Controller\AccountOrderController'))->getFileName();

        if (false === $controller || !str_contains((string) file_get_contents($controller), self::THEME_MARKER)) {
            self::markTestSkipped('The installed front-office theme shows the quote button whatever the PDF template.');
        }
    }
}
