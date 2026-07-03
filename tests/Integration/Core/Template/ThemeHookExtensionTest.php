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
 * The TwigEngine theme_hook() function is registered on the engine and, when no module
 * answers a hook point, renders an empty string instead of raising an error, so a theme
 * can declare extension points that stay inert until a module opts in.
 */
final class ThemeHookExtensionTest extends IntegrationTestCase
{
    public function testUnansweredThemeHookRendersEmptyString(): void
    {
        $rendered = $this->render("[{{ theme_hook('nonexistent.hook') }}]");

        self::assertSame('[]', $rendered);
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
