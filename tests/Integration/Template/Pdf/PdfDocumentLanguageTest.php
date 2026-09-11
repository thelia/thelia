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
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\OrderStatus;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * An invoice and a delivery note are pieces the customer keeps: they come out in the
 * language the order was placed in, not in the language of whoever prints them.
 *
 * The Twig engine replaces the `locale` a caller passes with the language of the current
 * request (TwigParser::render), so a document that trusts the variable called `locale`
 * prints the language of the person at the screen — a French back office hands a French
 * invoice to a German customer.
 *
 * These documents belong to the PDF template package, which ships on its own release
 * cycle: a package older than the fix is reported as skipped rather than failed.
 */
final class PdfDocumentLanguageTest extends IntegrationTestCase
{
    /**
     * @return iterable<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function documentsAndLanguages(): iterable
    {
        // Both directions for both documents, so a hard-coded language passes none of them.
        foreach (['invoice', 'delivery'] as $document) {
            yield $document.': a German order printed from a French session' => [
                $document, 'de_DE', 'fr_FR', 'Rechnungsdatum',
            ];
            yield $document.': a French order printed from a German session' => [
                $document, 'fr_FR', 'de_DE', 'Date de facturation',
            ];
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('documentsAndLanguages')]
    public function testTheDocumentIsPrintedInTheLanguageOfTheOrder(
        string $document,
        string $orderLocale,
        string $visitorLocale,
        string $expectedLabel,
    ): void {
        $this->skipUnlessTheTemplateShips($document);

        $this->getService(LangService::class)->setLang($this->lang($visitorLocale));

        // The scenario only means something while the visitor really is in that language:
        // the engine reads it from the session to build its own `locale`.
        self::assertSame(
            $visitorLocale,
            $this->getService(LangService::class)->getLang()?->getLocale(),
            'The session language drives what the Twig engine calls `locale`.',
        );

        $html = $this->render($document, $orderLocale);

        self::assertStringContainsString(
            $expectedLabel,
            $html,
            \sprintf('The %s of an order in %s must be printed in %s, whoever is looking.', $document, $orderLocale, $orderLocale),
        );
    }

    private function render(string $document, string $orderLocale): string
    {
        $factory = new FixtureFactory($this->getPropelConnection());

        $customer = $factory->customer($factory->customerTitle());
        $order = $factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID]);
        $order->setLangId($this->lang($orderLocale)->getId())->save($this->getPropelConnection());

        $pdfTemplate = $this->getService(TemplateHelperInterface::class)->getActivePdfTemplate();
        $parser = $this->getService(ParserResolver::class)->getParser($pdfTemplate->getAbsolutePath(), $document);
        $parser->setTemplateDefinition($pdfTemplate, true);

        return $parser->render($document, ['order_id' => $order->getId()]);
    }

    private function skipUnlessTheTemplateShips(string $document): void
    {
        $templatePath = $this->getService(TemplateHelperInterface::class)
            ->getActivePdfTemplate()
            ->getAbsolutePath();

        if (!file_exists($templatePath.\DIRECTORY_SEPARATOR.$document.'.html.twig')) {
            self::markTestSkipped(\sprintf('The active PDF template ships no %s document.', $document));
        }
    }

    /**
     * A language of the shop, taken as it is installed — a language the merchant has
     * deactivated is still the language an old order was placed in.
     */
    private function lang(string $locale): Lang
    {
        return LangQuery::create()->findOneByLocale($locale)
            ?? throw new \RuntimeException(\sprintf('Language %s is missing — run bin/test-prepare.', $locale));
    }
}
