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

use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Model\Order;
use Thelia\Model\OrderShipment;
use Thelia\Model\OrderShipmentItem;
use Thelia\Model\OrderStatus;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The delivery note of one shipment lists the parcel, not the order: only the lines
 * the shipment carries, with the quantity it carries, under the shipment number.
 * Without a shipment, the note is the one of the whole order, as it always was.
 *
 * The note belongs to the PDF template package, released on its own cycle: a package
 * that does not read the shipment yet is reported as skipped rather than failed.
 */
final class DeliveryShipmentTest extends IntegrationTestCase
{
    public function testTheNoteOfAShipmentListsOnlyWhatTheParcelCarries(): void
    {
        $this->skipUnlessTheDeliveryNoteReadsTheShipment();

        [$order, $shipment] = $this->orderWithAPartialShipment();

        $html = $this->renderDeliveryNote($order, $shipment);

        self::assertSame('2', $this->quantityPrintedFor('Shipped widget', $html));
        self::assertStringNotContainsString('Waiting gadget', $html);
        self::assertStringContainsString($shipment->getRef(), $html);
        self::assertStringContainsString('TRACK-0042', $html);
    }

    public function testTheNoteOfTheOrderIsUnchangedWithoutAShipment(): void
    {
        $this->skipUnlessTheDeliveryNoteReadsTheShipment();

        [$order, $shipment] = $this->orderWithAPartialShipment();

        $html = $this->renderDeliveryNote($order, null);

        self::assertSame('3', $this->quantityPrintedFor('Shipped widget', $html));
        self::assertSame('5', $this->quantityPrintedFor('Waiting gadget', $html));
        self::assertStringNotContainsString($shipment->getRef(), $html);
        self::assertStringNotContainsString('TRACK-0042', $html);
    }

    /**
     * @return array{Order, OrderShipment}
     */
    private function orderWithAPartialShipment(): array
    {
        $connection = $this->getPropelConnection();
        $factory = new FixtureFactory($connection);
        $order = $factory->order(null, ['statusCode' => OrderStatus::CODE_PAID]);
        $shipped = $factory->orderProduct($order, ['quantity' => 3.0, 'title' => 'Shipped widget', 'productRef' => 'SHIPPED-WIDGET']);
        $factory->orderProduct($order, ['quantity' => 5.0, 'title' => 'Waiting gadget', 'productRef' => 'WAITING-GADGET']);

        $shipment = (new OrderShipment())
            ->setOrderId((int) $order->getId())
            ->setRef('SHIPMENT-'.$order->getId().'-1')
            ->setStatus(OrderShipment::STATUS_SHIPPED)
            ->setDeliveryRef('TRACK-0042');
        $shipment->save($connection);

        (new OrderShipmentItem())
            ->setOrderShipmentId((int) $shipment->getId())
            ->setOrderProductId((int) $shipped->getId())
            ->setQuantity(2.0)
            ->save($connection);

        return [$order, $shipment];
    }

    private function renderDeliveryNote(Order $order, ?OrderShipment $shipment): string
    {
        $pdfTemplate = $this->getService(TemplateHelperInterface::class)->getActivePdfTemplate();
        $parser = $this->getService(ParserResolver::class)->getParser($pdfTemplate->getAbsolutePath(), 'delivery');
        $parser->setTemplateDefinition($pdfTemplate, true);

        $parameters = ['order_id' => $order->getId()];
        if (null !== $shipment) {
            $parameters['shipment_id'] = $shipment->getId();
        }

        return $parser->render('delivery', $parameters);
    }

    private function quantityPrintedFor(string $title, string $html): ?string
    {
        $pattern = '#<p class="product-title">'.preg_quote($title, '#').'</p>.*?<td style="text-align:center;">\s*<p>([^<]*)</p>#s';

        return 1 === preg_match($pattern, $html, $matches) ? trim($matches[1]) : null;
    }

    private function skipUnlessTheDeliveryNoteReadsTheShipment(): void
    {
        $file = $this->getService(TemplateHelperInterface::class)->getActivePdfTemplate()->getAbsolutePath()
            .\DIRECTORY_SEPARATOR.'delivery.html.twig';

        if (!file_exists($file) || !str_contains((string) file_get_contents($file), 'shipment_id')) {
            self::markTestSkipped('The installed PDF template does not print the delivery note of a shipment yet.');
        }
    }
}
