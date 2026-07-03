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

namespace Thelia\Tests\Unit\Core\Template;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Thelia\Core\Hook\Theme\ThemeHookInterface;
use TwigEngine\Service\ThemeHookRenderer;

/**
 * ThemeHookRenderer concatenates the output of every "thelia.theme_hook" handler that
 * supports the hook, in the collected order, skips non-supporting handlers, and isolates
 * a failing handler (logged in production, rethrown in debug) so one module never breaks
 * a whole page.
 */
final class ThemeHookRendererTest extends TestCase
{
    public function testConcatenatesSupportingHandlersInOrder(): void
    {
        $renderer = new ThemeHookRenderer(false, new NullLogger(), [
            $this->handler('page.top', 'A'),
            $this->handler('page.top', 'B'),
        ]);

        self::assertSame('AB', $renderer->render('page.top'));
    }

    public function testSkipsNonSupportingHandler(): void
    {
        $renderer = new ThemeHookRenderer(false, new NullLogger(), [
            $this->handler('page.top', 'A'),
            $this->handler('other.hook', 'B'),
        ]);

        self::assertSame('A', $renderer->render('page.top'));
    }

    public function testHookWithoutHandlerRendersEmptyString(): void
    {
        $renderer = new ThemeHookRenderer(false, new NullLogger(), []);

        self::assertSame('', $renderer->render('page.top'));
    }

    public function testHandlerReceivesParameters(): void
    {
        $renderer = new ThemeHookRenderer(false, new NullLogger(), [
            new class implements ThemeHookInterface {
                public function supports(string $hookName): bool
                {
                    return true;
                }

                public function render(string $hookName, array $parameters): string
                {
                    return (string) ($parameters['label'] ?? '');
                }
            },
        ]);

        self::assertSame('hello', $renderer->render('page.top', ['label' => 'hello']));
    }

    public function testFailingHandlerIsLoggedAndOthersStillRenderInProduction(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $renderer = new ThemeHookRenderer(false, $logger, [
            $this->failingHandler('page.top'),
            $this->handler('page.top', 'B'),
        ]);

        self::assertSame('B', $renderer->render('page.top'));
    }

    public function testFailingHandlerRethrowsInDebug(): void
    {
        $renderer = new ThemeHookRenderer(true, new NullLogger(), [
            $this->failingHandler('page.top'),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $renderer->render('page.top');
    }

    private function handler(string $supportedHook, string $output): ThemeHookInterface
    {
        return new class($supportedHook, $output) implements ThemeHookInterface {
            public function __construct(
                private readonly string $supportedHook,
                private readonly string $output,
            ) {
            }

            public function supports(string $hookName): bool
            {
                return $hookName === $this->supportedHook;
            }

            public function render(string $hookName, array $parameters): string
            {
                return $this->output;
            }
        };
    }

    private function failingHandler(string $supportedHook): ThemeHookInterface
    {
        return new class($supportedHook) implements ThemeHookInterface {
            public function __construct(
                private readonly string $supportedHook,
            ) {
            }

            public function supports(string $hookName): bool
            {
                return $hookName === $this->supportedHook;
            }

            public function render(string $hookName, array $parameters): string
            {
                throw new \RuntimeException('boom');
            }
        };
    }
}
