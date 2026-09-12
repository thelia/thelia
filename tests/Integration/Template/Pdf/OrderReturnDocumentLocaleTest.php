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
 * The return document is read by the customer who sends the parcel back, not by whoever
 * happens to print it: it comes out in the language the order was placed in.
 *
 * That is not free. The Twig engine overwrites the `locale` the caller passes with the
 * language of the current request (TwigParser::render), so a template that trusts the
 * variable called `locale` prints the language of the person looking at the screen — a
 * merchant printing in French hands a French slip to a German customer.
 *
 * The document belongs to the PDF template package, which ships on its own release cycle:
 * a package older than the fix is reported as skipped rather than failed.
 */
final class OrderReturnDocumentLocaleTest extends IntegrationTestCase
{
    private const DOCUMENT = 'order_return';

    protected function setUp(): void
    {
        parent::setUp();

        $templatePath = $this->getService(TemplateHelperInterface::class)
            ->getActivePdfTemplate()
            ->getAbsolutePath();

        $file = $templatePath.\DIRECTORY_SEPARATOR.self::DOCUMENT.'.html.twig';

        if (!file_exists($file)) {
            self::markTestSkipped('The active PDF template ships no return document.');
        }

        // The document is a file of a package on its own release cycle: a shop running a
        // version older than the fix must not turn a core build red.
        if (!str_contains((string) file_get_contents($file), 'document_locale')) {
            self::markTestSkipped('The installed PDF template predates the language fix of the return document.');
        }
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string}>
     */
    public static function ordersAndVisitors(): iterable
    {
        // A German order printed by a French visitor, and the other way round, so a
        // hard-coded language cannot pass either case.
        yield 'a German order printed from a French session' => ['de_DE', 'fr_FR', 'Menge'];
        yield 'a French order printed from a German session' => ['fr_FR', 'de_DE', 'Quantité'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('ordersAndVisitors')]
    public function testTheDocumentIsPrintedInTheLanguageOfTheOrder(
        string $orderLocale,
        string $visitorLocale,
        string $expectedLabel,
    ): void {
        $this->getService(LangService::class)->setLang($this->lang($visitorLocale));

        // The scenario only means something while the visitor really is in that language:
        // the engine reads it from the session to build its own `locale`.
        self::assertSame(
            $visitorLocale,
            $this->getService(LangService::class)->getLang()?->getLocale(),
            'The session language drives what the Twig engine calls `locale`.',
        );

        $html = $this->renderReturnDocument($orderLocale);

        self::assertStringContainsString(
            $expectedLabel,
            $html,
            \sprintf('An order in %s must print its labels in %s, whoever is looking.', $orderLocale, $orderLocale),
        );
    }

    /**
     * Renders the document with the very context the core hands it, for an order carried
     * in the given language.
     */
    private function renderReturnDocument(string $orderLocale): string
    {
        $factory = new FixtureFactory($this->getPropelConnection());

        $customer = $factory->customer($factory->customerTitle());
        $order = $factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID]);
        $order->setLangId($this->lang($orderLocale)->getId())->save($this->getPropelConnection());

        $pdfTemplate = $this->getService(TemplateHelperInterface::class)->getActivePdfTemplate();
        $parser = $this->getService(ParserResolver::class)->getParser($pdfTemplate->getAbsolutePath(), self::DOCUMENT);
        $parser->setTemplateDefinition($pdfTemplate, true);

        return $parser->render(self::DOCUMENT, [
            'locale' => $orderLocale,
            'return_ref' => 'RET000000000001',
            'order_ref' => (string) $order->getRef(),
            'status' => 'Accepted',
            'created_at' => new \DateTime(),
            'customer_name' => 'Anna Schmidt',
            'reason' => 'Damaged on arrival',
            'refund' => '10.00 €',
            'lines' => [[
                'title' => 'A chair to send back',
                'ref' => 'RETURN-DOC-REF',
                'quantity' => 1.0,
                'refund' => '10.00 €',
            ]],
            'store_name' => 'The shop',
        ]);
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
