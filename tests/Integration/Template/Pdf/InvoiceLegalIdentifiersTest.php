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
use Thelia\Model\ConfigQuery;
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\OrderStatus;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The legal identifiers of the shop are printed by the invoice in the page footer, on one line, and
 * in the imprint, one per line, in a fixed order, escaped, with the reverse charge of a buyer who
 * owes the VAT between the identifiers of the shop and its legal mentions.
 */
final class InvoiceLegalIdentifiersTest extends IntegrationTestCase
{
    private const KEYS = ['store_siret', 'store_ape_code', 'store_vat_intracom', 'store_eori', 'store_vat_exempt', 'store_registration_exempt', 'store_legal_mentions', 'store_business_id'];

    public function testEveryIdentifierIsPrintedInOrderInTheFooterAndTheImprint(): void
    {
        [$footer, $imprint] = $this->render(
            ['store_siret' => '123 456', 'store_ape_code' => '4791B', 'store_vat_intracom' => 'FR12345', 'store_eori' => 'FR999', 'store_vat_exempt' => '1', 'store_registration_exempt' => '1', 'store_legal_mentions' => 'Capital & <b>1000</b> EUR'],
            true,
        );

        self::assertStringContainsString(
            '- SIRET : 123 456 - Code APE : 4791B - TVA : FR12345 - EORI : FR999 - TVA non applicable, article 293 B du CGI - TVA du client : DE777 - Autoliquidation : TVA due par le preneur - Dispensé d&#039;immatriculation au registre du commerce et des sociétés (RCS) et au répertoire des métiers (RM) - Capital &amp; &lt;b&gt;1000&lt;/b&gt; EUR',
            $footer,
        );
        self::assertStringContainsString(
            '<p>SIRET : 123 456</p><p>Code APE : 4791B</p><p>TVA : FR12345</p><p>EORI : FR999</p><p>TVA non applicable, article 293 B du CGI</p><p>TVA du client : DE777</p><p>Autoliquidation : TVA due par le preneur</p><p>Dispensé d&#039;immatriculation au registre du commerce et des sociétés (RCS) et au répertoire des métiers (RM)</p><p>Capital &amp; &lt;b&gt;1000&lt;/b&gt; EUR</p>',
            $imprint,
        );
    }

    public function testAShopThatNeverFilledTheKeysKeepsItsLegacyIdentifier(): void
    {
        [$footer, $imprint] = $this->render(['store_business_id' => 'LEGACY 42'], false);

        self::assertStringContainsString('- LEGACY 42', $footer);
        self::assertStringContainsString('<p>LEGACY 42</p>', $imprint);
    }

    public function testAShopWithNoIdentifierPrintsNoSeparator(): void
    {
        [$footer, $imprint] = $this->render([], false);

        self::assertStringNotContainsString(' - ', $footer);
        self::assertStringNotContainsString('<p>', preg_replace('#<p class="store-imprint-name">.*?</p>#s', '', $imprint) ?? '');
    }

    /**
     * @param array<string, string> $values
     *
     * @return array{string, string} the footer and the imprint, on one line
     */
    private function render(array $values, bool $reverseCharge): array
    {
        foreach (self::KEYS as $key) {
            ConfigQuery::write($key, $values[$key] ?? '');
        }

        $factory = new FixtureFactory($this->getPropelConnection());
        $order = $factory->order($factory->customer($factory->customerTitle()), ['statusCode' => OrderStatus::CODE_PAID]);

        if ($reverseCharge) {
            OrderAddressQuery::create()->findPk($order->getInvoiceOrderAddressId())?->setVatExempted(1)->setVatNumber('DE777')->save($this->getPropelConnection());
        }

        $pdfTemplate = $this->getService(TemplateHelperInterface::class)->getActivePdfTemplate();
        $parser = $this->getService(ParserResolver::class)->getParser($pdfTemplate->getAbsolutePath(), 'invoice');
        $parser->setTemplateDefinition($pdfTemplate, true);
        $html = $parser->render('invoice', ['order_id' => $order->getId()]);

        preg_match('#<div class="pdf-footer">.*?</div>#s', $html, $footer);
        preg_match('#<td class="store-imprint align-right">.*?</td>#s', $html, $imprint);

        self::assertNotEmpty($footer, 'The footer is not in the invoice.');
        self::assertNotEmpty($imprint, 'The imprint is not in the invoice.');

        return [(string) preg_replace('/\s+/', ' ', $footer[0]), (string) preg_replace('/\s+/', ' ', $imprint[0])];
    }
}
