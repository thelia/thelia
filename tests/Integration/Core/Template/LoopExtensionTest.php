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

namespace Thelia\Tests\Integration\Core\Template;

use Thelia\Test\IntegrationTestCase;
use Twig\Environment;

/**
 * The TwigEngine loop() function must run a Thelia loop and expose each row as an
 * associative array (uppercase output names), so a template reads {{ row.KEY }} exactly
 * like the Smarty {loop} plugin, with {% else %} standing in for {ifloop}/{elseloop}.
 * It is registered by the engine (not the front theme) so it works for emails and PDFs too.
 */
final class LoopExtensionTest extends IntegrationTestCase
{
    public function testLoopExposesRowsAsAssociativeArrays(): void
    {
        // Reads the uppercase output name off the row as an array key, like Smarty's {loop}.
        $rendered = $this->render("{% for c in loop('cur', 'currency') %}[{{ c.ISOCODE }}]{% endfor %}");

        self::assertStringContainsString('[EUR]', $rendered);
    }

    public function testEmptyLoopFallsBackToElseBranch(): void
    {
        $rendered = $this->render("{% for p in loop('op', 'order_product', {order: 0}) %}row{% else %}empty{% endfor %}");

        self::assertSame('empty', $rendered);
    }

    public function testLoopCountMatchesRenderedRows(): void
    {
        $count = (int) $this->render("{{ loopCount('currency') }}");
        $rows = $this->render("{% for c in loop('cnt', 'currency') %}x{% endfor %}");

        self::assertSame(\strlen($rows), $count);
        self::assertGreaterThanOrEqual(1, $count);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(string $template, array $context = []): string
    {
        /** @var Environment $twig */
        $twig = static::getContainer()->get('twig');

        return $twig->createTemplate($template)->render($context);
    }
}
