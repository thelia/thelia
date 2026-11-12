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

use Thelia\Core\Event\PdfEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Domain\Order\Service\PickingSlipPdfService;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The `preparation` document of the installed PDF theme, rendered for real through the picking slip service:
 * one page per order, the physical lines only, in the order of their references, and no price anywhere.
 *
 * The document belongs to the PDF theme, released on its own cycle: an installed theme that has no
 * `preparation` template yet is reported as skipped rather than failed.
 */
final class PickingSlipTemplateTest extends IntegrationTestCase
{
    private const string MARKER = 'preparation.product-location';

    private const string PRICE = '7771.55';

    public function testAMixedOrderListsItsPhysicalLinesInReferenceOrderWithoutPrice(): void
    {
        $this->skipUnlessTheThemeHasThePickingSlip();

        $factory = $this->factory();
        $order = $factory->order();
        $factory->orderProduct($order, ['productRef' => 'ZZ-LAST', 'title' => 'Last physical', 'quantity' => 2.5, 'price' => self::PRICE, 'promoPrice' => self::PRICE]);
        $factory->orderProduct($order, ['productRef' => 'AA-FIRST', 'title' => 'First physical', 'price' => self::PRICE, 'promoPrice' => self::PRICE]);
        $factory->orderProduct($order, ['productRef' => 'MM-DIGITAL', 'title' => 'Digital book', 'virtual' => 1, 'price' => self::PRICE, 'promoPrice' => self::PRICE]);
        $factory->orderProduct($order, ['productRef' => 'GIFT-WRAP', 'title' => 'Gift wrapping', 'lineType' => OrderProduct::LINE_TYPE_SERVICE, 'price' => self::PRICE, 'promoPrice' => self::PRICE]);

        [$html, $pdf] = $this->render([$order]);

        self::assertStringContainsString('PICKING SLIP', $html);
        self::assertStringContainsString($order->getRef(), $html);
        self::assertStringContainsString($order->getCustomer()->getLastname(), $html);
        self::assertStringContainsString('data:image/svg+xml;base64,', $html);
        self::assertStringContainsString('First physical', $html);
        self::assertStringContainsString('2.5', $html);
        self::assertLessThan(strpos($html, 'Last physical'), strpos($html, 'First physical'), 'Lines are sorted by reference.');
        self::assertStringNotContainsString('Digital book', $html);
        self::assertStringNotContainsString('Gift wrapping', $html);
        self::assertStringNotContainsString('7771', $html, 'A picking slip carries no price.');
        self::assertStringNotContainsString('Nothing to pick', $html);
        self::assertStringNotContainsString('Location', $html, 'No module wrote a location: no column.');
        self::assertSame(1, $this->pageCount($pdf));
    }

    public function testAnOrderOfVirtualProductsSaysThereIsNothingToPick(): void
    {
        $this->skipUnlessTheThemeHasThePickingSlip();

        $factory = $this->factory();
        $order = $factory->order();
        $factory->orderProduct($order, ['title' => 'Digital book', 'virtual' => 1]);

        [$html, $pdf] = $this->render([$order]);

        self::assertStringContainsString('Nothing to pick: this order only holds virtual products.', $html);
        self::assertStringNotContainsString('Digital book', $html);
        self::assertSame(1, $this->pageCount($pdf));
    }

    public function testABatchOfThreeOrdersGivesOnePagePerOrder(): void
    {
        $this->skipUnlessTheThemeHasThePickingSlip();

        $factory = $this->factory();
        $orders = [];
        foreach (['ONE', 'TWO', 'THREE'] as $name) {
            $order = $factory->order();
            $factory->orderProduct($order, ['title' => 'Widget '.$name, 'price' => self::PRICE, 'promoPrice' => self::PRICE]);
            $orders[] = $order;
        }

        [$html, $pdf] = $this->render($orders);

        self::assertSame(3, $this->pageCount($pdf));
        foreach ($orders as $order) {
            self::assertStringContainsString($order->getRef(), $html);
        }
        self::assertSame(2, substr_count($html, 'page-break-before: always;'));
        self::assertStringNotContainsString('7771', $html);
    }

    public function testTheComponentsOfABundleArePickedUnderItsHeader(): void
    {
        $this->skipUnlessTheThemeHasThePickingSlip();

        $factory = $this->factory();
        $order = $factory->order();
        $bundle = $factory->orderProduct($order, ['productRef' => 'BUNDLE', 'title' => 'Starter pack', 'lineType' => OrderProduct::LINE_TYPE_BUNDLE, 'quantity' => 2.0]);
        $component = $factory->orderProduct($order, ['productRef' => 'PART', 'title' => 'Pack part', 'lineType' => OrderProduct::LINE_TYPE_BUNDLE_COMPONENT, 'quantity' => 4.0]);
        $component->setParent((int) $bundle->getId())->save($this->getPropelConnection());

        [$html] = $this->render([$order]);

        self::assertStringContainsString('Starter pack', $html);
        self::assertLessThan(strpos($html, 'Pack part'), strpos($html, 'Starter pack'));
        self::assertStringNotContainsString('Nothing to pick', $html);
    }

    private function factory(): FixtureFactory
    {
        return new FixtureFactory($this->getPropelConnection());
    }

    /**
     * @param list<Order> $orders
     *
     * @return array{string, string} the HTML handed to the PDF engine, and the PDF
     */
    private function render(array $orders): array
    {
        $html = '';
        $this->getService('event_dispatcher')->addListener(
            TheliaEvents::GENERATE_PDF,
            static function (PdfEvent $event) use (&$html): void {
                $html = (string) $event->getContent();
            },
            1000,
        );

        $slip = $this->getService(PickingSlipPdfService::class)->render(array_map(static fn (Order $order): int => (int) $order->getId(), $orders));

        return [$html, $slip->content];
    }

    private function pageCount(string $pdf): int
    {
        return (int) preg_match_all('#/Type\s*/Page\b(?!s)#', $pdf);
    }

    private function skipUnlessTheThemeHasThePickingSlip(): void
    {
        $themeRoot = $this->getService(TemplateHelperInterface::class)->getActivePdfTemplate()->getAbsolutePath();
        $path = $themeRoot.\DIRECTORY_SEPARATOR.'preparation.html.twig';

        if (!file_exists($path) || !str_contains((string) file_get_contents($path), self::MARKER)) {
            self::markTestSkipped(\sprintf('The installed PDF theme has no "%s" in preparation.html.twig.', self::MARKER));
        }
    }
}
