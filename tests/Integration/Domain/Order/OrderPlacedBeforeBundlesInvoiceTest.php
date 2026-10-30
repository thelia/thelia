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

namespace Thelia\Tests\Integration\Domain\Order;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Thelia\Core\Event\PdfEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * An order placed before bundles existed holds lines that never heard of line_type: the
 * column came with its default. The lines are written in raw SQL, without the column, as the
 * database held them. The order stays readable and its invoice is generated again, through
 * the real pdf:render path (template, parser, GENERATE_PDF), whose HTML is captured on the way.
 */
final class OrderPlacedBeforeBundlesInvoiceTest extends IntegrationTestCase
{
    private ?string $outputFile = null;

    private ?\Closure $capture = null;

    protected function tearDown(): void
    {
        if (null !== $this->capture) {
            static::getContainer()->get('event_dispatcher')->removeListener(TheliaEvents::GENERATE_PDF, $this->capture);
        }

        if (null !== $this->outputFile && file_exists($this->outputFile)) {
            unlink($this->outputFile);
        }

        parent::tearDown();
    }

    public function testTheInvoiceOfAnOrderWhoseLinesNeverHadALineTypeIsGeneratedAgain(): void
    {
        $factory = $this->createFixtureFactory();
        $order = $factory->order();
        $saleElementsId = (int) $factory->product($factory->category(), $factory->taxRule(), $factory->currency())->getDefaultSaleElements()->getId();
        $references = ['OLD-REF-A', 'OLD-REF-B'];

        // Written the way a line was before bundles: the line_type column is not named.
        $insert = $this->getPropelConnection()->prepare(
            'INSERT INTO order_product (order_id, product_ref, product_sale_elements_ref, product_sale_elements_id, title, quantity, price, promo_price, was_new, was_in_promo, virtual) '
            .'VALUES (:order, :reference, :saleElementsReference, :saleElements, :title, 2, 15, 15, 1, 0, 0)',
        );

        foreach ($references as $reference) {
            $insert->execute([
                'order' => (int) $order->getId(),
                'reference' => $reference,
                'saleElementsReference' => $reference.'-PSE',
                'saleElements' => $saleElementsId,
                'title' => 'Old line '.$reference,
            ]);
        }

        $lines = OrderProductQuery::create()->filterByOrderId($order->getId())->find();
        self::assertCount(2, $lines);

        foreach ($lines as $line) {
            self::assertSame(OrderProduct::LINE_TYPE_PRODUCT, $line->getLineType(), 'The default of the column reads as a plain product.');
            self::assertFalse($line->isBundleLine());
            self::assertFalse($line->isBundleComponentLine());
        }

        $html = null;
        $this->capture = static function (PdfEvent $event) use (&$html): void {
            $html = $event->getContent();
        };
        static::getContainer()->get('event_dispatcher')->addListener(TheliaEvents::GENERATE_PDF, $this->capture, 255);

        $this->outputFile = sys_get_temp_dir().'/invoice_before_bundles_'.uniqid('', true).'.pdf';
        $tester = new CommandTester((new Application(self::$kernel))->find('pdf:render'));
        $tester->execute([
            'document' => 'invoice',
            '--order' => (string) $order->getId(),
            '--locale' => 'fr_FR',
            '--out' => $this->outputFile,
        ]);

        $tester->assertCommandIsSuccessful();
        $pdf = (string) file_get_contents($this->outputFile);
        self::assertStringStartsWith('%PDF', $pdf);
        self::assertGreaterThan(1000, \strlen($pdf), 'A PDF with a body, not an empty shell.');

        self::assertIsString($html, 'The HTML of the invoice went through GENERATE_PDF.');

        foreach ($references as $reference) {
            self::assertStringContainsString($reference, $html, 'Each line of the old order is on the invoice.');
        }
    }
}
