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

namespace Thelia\Tests\Unit\Domain\Ai;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Ai\Compliance\AiComplianceStatus;
use Thelia\Domain\Ai\Guardrail\AiVerdictReasons;
use Thelia\Domain\Ai\Service\AiFailureExplainer;
use Thelia\Domain\Ai\Service\AiUsageRegistry;
use Thelia\Domain\Ai\Twig\AiTransparencyExtension;

/**
 * Every text of the transparency layer (notice, compliance messages, mark label, reasons of the verdicts) is in
 * the core catalog of the shipped languages, and the French one keeps the placeholders of the source.
 */
final class AiTransparencyCatalogTest extends TestCase
{
    #[DataProvider('texts')]
    public function testTheTextIsInTheCoreCatalog(string $source): void
    {
        $english = require THELIA_LIB.'Config/I18n/en_US.php';
        $french = require THELIA_LIB.'Config/I18n/fr_FR.php';

        self::assertArrayHasKey($source, $english);
        self::assertArrayHasKey($source, $french);
        self::assertNotSame('', trim((string) $french[$source]));
        self::assertNotSame($source, $french[$source], 'the French text is translated');

        preg_match_all('/%[a-z]+%/', $source, $placeholders);
        foreach ($placeholders[0] as $placeholder) {
            self::assertStringContainsString($placeholder, (string) $french[$source]);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function texts(): iterable
    {
        foreach (array_unique([AiUsageRegistry::TRANSPARENCY_NOTICE, ...AiComplianceStatus::sources(), ...AiVerdictReasons::sources(), ...AiFailureExplainer::sources(), ...AiTransparencyExtension::sources()]) as $source) {
            yield $source => [$source];
        }
    }

    public function testNoCompliantTextCitesAnArticleWithoutExplainingIt(): void
    {
        foreach (AiComplianceStatus::sources() as $source) {
            if (str_contains($source, 'article 50')) {
                self::assertStringContainsString('EU AI Act', $source);
                self::assertGreaterThan(60, \strlen($source), 'the article comes with its explanation');
            }
        }
    }
}
