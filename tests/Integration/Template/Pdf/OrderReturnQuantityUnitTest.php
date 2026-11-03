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

namespace Thelia\Tests\Integration\Template\Pdf;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\PdfEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Domain\OrderReturn\Service\OrderReturnPdfService;
use Thelia\Model\LangQuery;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderReturn;
use Thelia\Model\OrderReturnLine;
use Thelia\Model\OrderReturnStatus;
use Thelia\Model\OrderReturnStatusQuery;
use Thelia\Model\OrderStatus;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The return document writes the quantity of a line with the decimals and the unit frozen on the
 * order line it returns, in the language of the order: "1,25 kg", "2" for two bottles of 0.75 l,
 * and "2" again for a line placed before quantities had decimals.
 *
 * The document belongs to the PDF template package, released on its own cycle: a package that
 * does not call `format_quantity` yet is reported as skipped rather than failed.
 */
final class OrderReturnQuantityUnitTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $file = $this->getService(TemplateHelperInterface::class)->getActivePdfTemplate()->getAbsolutePath()
            .\DIRECTORY_SEPARATOR.'order_return.html.twig';

        if (!file_exists($file) || !str_contains((string) file_get_contents($file), 'format_quantity')) {
            self::markTestSkipped('The installed PDF template does not format the quantities of the return document yet.');
        }
    }

    public function testAQuantitySoldByMeasureReadsWithItsUnitInTheLanguageOfTheOrder(): void
    {
        self::assertStringContainsString('1,25 kg', $this->render('fr_FR'));
        self::assertStringContainsString('1.25 kg', $this->render('en_US'));
    }

    public function testAQuantitySoldByThePieceReadsAsAWholeNumberWhateverItsUnit(): void
    {
        $html = $this->render('fr_FR');

        self::assertMatchesRegularExpression('#<p[^>]*>\s*2\s*</p>#', $this->rowOf($html, 'Bottle of 0.75 l'));
        self::assertStringNotContainsString('2 l', $html);
    }

    public function testALineOfAnOrderPlacedBeforeDecimalsReadsAsBefore(): void
    {
        $row = $this->rowOf($this->render('fr_FR'), 'Legacy product');

        self::assertMatchesRegularExpression('#<p[^>]*>\s*3\s*</p>#', $row);
        self::assertStringNotContainsString('3,0', $row);
    }

    private function render(string $orderLocale): string
    {
        $factory = new FixtureFactory($this->getPropelConnection());
        $customer = $factory->customer($factory->customerTitle());
        $order = $factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID]);
        $order->setLangId(LangQuery::create()->findOneByLocale($orderLocale)?->getId() ?? throw new \RuntimeException('Language missing: run bin/test-prepare.'))
            ->save($this->getPropelConnection());

        $return = (new OrderReturn())
            ->setOrder($order)
            ->setCustomer($customer)
            ->setOrderReturnStatus(OrderReturnStatusQuery::create()->findOneByCode(OrderReturnStatus::CODE_ACCEPTED));
        $return->save($this->getPropelConnection());

        $this->addLine($factory, $order, $return, 'Weighed product', 1.25, 2, 'kg');
        $this->addLine($factory, $order, $return, 'Bottle of 0.75 l', 2.0, 0, 'l');
        $this->addLine($factory, $order, $return, 'Legacy product', 3.0, 0, null);
        $return->clearOrderReturnLines();

        $html = '';
        $dispatcher = $this->getService(EventDispatcherInterface::class);
        $capture = static function (PdfEvent $event) use (&$html): void {
            $html = (string) $event->getContent();
        };
        $dispatcher->addListener(TheliaEvents::GENERATE_PDF, $capture, 1000);

        try {
            $this->getService(OrderReturnPdfService::class)->render($return);
        } finally {
            $dispatcher->removeListener(TheliaEvents::GENERATE_PDF, $capture);
        }

        self::assertNotSame('', $html, 'The return document was not rendered.');

        return $html;
    }

    private function addLine(FixtureFactory $factory, \Thelia\Model\Order $order, OrderReturn $return, string $title, float $quantity, int $decimals, ?string $unitCode): void
    {
        $orderProduct = $factory->orderProduct($order, ['title' => $title, 'quantity' => $quantity]);
        self::assertInstanceOf(OrderProduct::class, $orderProduct);
        $orderProduct->setQuantityDecimals($decimals)->setUnitCode($unitCode)->save($this->getPropelConnection());

        (new OrderReturnLine())
            ->setOrderReturn($return)
            ->setOrderProduct($orderProduct)
            ->setQuantity($quantity)
            ->save($this->getPropelConnection());
    }

    private function rowOf(string $html, string $title): string
    {
        self::assertSame(1, preg_match('#<tr[^>]*>(?:(?!</tr>).)*'.preg_quote($title, '#').'.*?</tr>#s', $html, $matches), \sprintf('The line "%s" is not in the document.', $title));

        return $matches[0];
    }
}
