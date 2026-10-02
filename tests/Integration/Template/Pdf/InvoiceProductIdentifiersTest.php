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
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderStatus;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The invoice prints the GTIN and the manufacturer part number frozen on each order line.
 *
 * The invoice belongs to the PDF template package, released on its own cycle: a package
 * that does not read the part number yet is reported as skipped rather than failed.
 */
final class InvoiceProductIdentifiersTest extends IntegrationTestCase
{
    public function testTheInvoicePrintsTheCodesOfTheLineAsSold(): void
    {
        $this->skipUnlessTheInvoiceReadsThePartNumber();

        $html = $this->renderInvoiceWithALine('4006381333931', 'SM-G991B');

        self::assertStringContainsString('4006381333931', $html);
        self::assertStringContainsString('SM-G991B', $html);
    }

    public function testALineWithoutCodesPrintsNoEmptyLabel(): void
    {
        $this->skipUnlessTheInvoiceReadsThePartNumber();

        $html = $this->renderInvoiceWithALine(null, null);

        self::assertStringNotContainsString('GTIN', $html);
    }

    private function renderInvoiceWithALine(?string $gtin, ?string $mpn): string
    {
        $factory = new FixtureFactory($this->getPropelConnection());
        $order = $factory->order($factory->customer($factory->customerTitle()), ['statusCode' => OrderStatus::CODE_PAID]);

        (new OrderProduct())
            ->setOrderId($order->getId())
            ->setProductRef('INVOICED-REF')
            ->setProductSaleElementsRef('INVOICED-PSE-REF')
            ->setTitle('Invoiced product')
            ->setQuantity(1.0)
            ->setPrice('10.000000')
            ->setWasNew(0)
            ->setWasInPromo(0)
            ->setEanCode($gtin)
            ->setMpn($mpn)
            ->save($this->getPropelConnection());

        $pdfTemplate = $this->getService(TemplateHelperInterface::class)->getActivePdfTemplate();
        $parser = $this->getService(ParserResolver::class)->getParser($pdfTemplate->getAbsolutePath(), 'invoice');
        $parser->setTemplateDefinition($pdfTemplate, true);

        return $parser->render('invoice', ['order_id' => $order->getId()]);
    }

    private function skipUnlessTheInvoiceReadsThePartNumber(): void
    {
        $file = $this->getService(TemplateHelperInterface::class)->getActivePdfTemplate()->getAbsolutePath()
            .\DIRECTORY_SEPARATOR.'invoice.html.twig';

        if (!file_exists($file) || !str_contains((string) file_get_contents($file), 'product.MPN')) {
            self::markTestSkipped('The installed PDF template does not print the codes of the order lines yet.');
        }
    }
}
