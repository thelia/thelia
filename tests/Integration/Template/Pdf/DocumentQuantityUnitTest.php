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

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Model\LangQuery;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderStatus;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The invoice and the delivery note write the quantity of a line with the decimals and the unit
 * the order froze, in the language of the order: "1,25 kg", "2" for two bottles of 0.75 l, and
 * "2" again for a line placed before quantities had decimals.
 *
 * The documents belong to the PDF template package, released on its own cycle: a package that
 * does not call `format_quantity` yet is reported as skipped rather than failed.
 */
final class DocumentQuantityUnitTest extends IntegrationTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function documents(): iterable
    {
        yield 'invoice' => ['invoice'];
        yield 'delivery note' => ['delivery'];
    }

    #[DataProvider('documents')]
    public function testAQuantitySoldByMeasureReadsWithItsUnitInTheLanguageOfTheOrder(string $document): void
    {
        $this->skipUnlessTheDocumentFormatsQuantities($document);

        $french = $this->render($document, 'fr_FR');
        $english = $this->render($document, 'en_US');

        self::assertStringContainsString('1,25 kg', $french);
        self::assertStringContainsString('1.25 kg', $english);
    }

    #[DataProvider('documents')]
    public function testAQuantitySoldByThePieceReadsAsAWholeNumberWhateverItsUnit(string $document): void
    {
        $this->skipUnlessTheDocumentFormatsQuantities($document);

        $html = $this->render($document, 'fr_FR');

        self::assertMatchesRegularExpression('#<p[^>]*>\s*2\s*</p>#', $this->rowOf($html, 'Bottle of 0.75 l'));
        self::assertStringNotContainsString('2 l', $html);
    }

    #[DataProvider('documents')]
    public function testALineOfAnOrderPlacedBeforeDecimalsReadsAsBefore(string $document): void
    {
        $this->skipUnlessTheDocumentFormatsQuantities($document);

        $row = $this->rowOf($this->render($document, 'fr_FR'), 'Legacy product');

        self::assertMatchesRegularExpression('#<p[^>]*>\s*3\s*</p>#', $row);
        self::assertStringNotContainsString('3,0', $row);
    }

    private function render(string $document, string $orderLocale): string
    {
        $factory = new FixtureFactory($this->getPropelConnection());
        $order = $factory->order($factory->customer($factory->customerTitle()), ['statusCode' => OrderStatus::CODE_PAID]);
        $order->setLangId(LangQuery::create()->findOneByLocale($orderLocale)?->getId() ?? throw new \RuntimeException('Language missing: run bin/test-prepare.'))
            ->save($this->getPropelConnection());

        $this->addLine($order->getId(), 'Weighed product', 1.25, 2, 'kg');
        $this->addLine($order->getId(), 'Bottle of 0.75 l', 2.0, 0, 'l');
        $this->addLine($order->getId(), 'Legacy product', 3.0, 0, null);

        $pdfTemplate = $this->getService(TemplateHelperInterface::class)->getActivePdfTemplate();
        $parser = $this->getService(ParserResolver::class)->getParser($pdfTemplate->getAbsolutePath(), $document);
        $parser->setTemplateDefinition($pdfTemplate, true);

        return $parser->render($document, ['order_id' => $order->getId()]);
    }

    private function addLine(int $orderId, string $title, float $quantity, int $decimals, ?string $unitCode): void
    {
        (new OrderProduct())
            ->setOrderId($orderId)
            ->setProductRef('REF-'.md5($title))
            ->setProductSaleElementsRef('PSE-'.md5($title))
            ->setTitle($title)
            ->setQuantity($quantity)
            ->setQuantityDecimals($decimals)
            ->setUnitCode($unitCode)
            ->setPrice('10.000000')
            ->setWasNew(0)
            ->setWasInPromo(0)
            ->save($this->getPropelConnection());
    }

    private function rowOf(string $html, string $title): string
    {
        self::assertSame(1, preg_match('#<tr[^>]*>(?:(?!</tr>).)*'.preg_quote($title, '#').'.*?</tr>#s', $html, $matches), \sprintf('The line "%s" is not in the document.', $title));

        return $matches[0];
    }

    private function skipUnlessTheDocumentFormatsQuantities(string $document): void
    {
        $file = $this->getService(TemplateHelperInterface::class)->getActivePdfTemplate()->getAbsolutePath()
            .\DIRECTORY_SEPARATOR.$document.'.html.twig';

        if (!file_exists($file) || !str_contains((string) file_get_contents($file), 'format_quantity')) {
            self::markTestSkipped(\sprintf('The installed PDF template does not format the quantities of the %s yet.', $document));
        }
    }
}
