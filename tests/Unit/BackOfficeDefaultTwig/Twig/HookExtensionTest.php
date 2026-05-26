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

namespace Thelia\Tests\Unit\BackOfficeDefaultTwig\Twig;

use BackOfficeDefaultTwigBundle\Service\Hook\LegacyHookAliases;
use BackOfficeDefaultTwigBundle\Twig\HookExtension;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Template\TemplateDefinition;

final class HookExtensionTest extends TestCase
{
    public function testSafeHookReplaysLegacyHookNameOnTheSameEvent(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            $this->backOfficeHook('attribute.update-form'),
            static fn (HookRenderEvent $event) => $event->add('current-output'),
        );
        // A module shipped only with the legacy Smarty hook name keeps contributing.
        $dispatcher->addListener(
            $this->backOfficeHook('attribute-edit-form.bottom'),
            static fn (HookRenderEvent $event) => $event->add('legacy-output'),
        );

        $output = $this->extension($dispatcher)->safeHook('attribute.update-form');

        self::assertStringContainsString('current-output', $output);
        self::assertStringContainsString('legacy-output', $output);
    }

    public function testSafeHookDoesNotReplayForAHookWithoutLegacyAlias(): void
    {
        $dispatcher = new EventDispatcher();
        $legacyCalled = false;
        $dispatcher->addListener(
            $this->backOfficeHook('attribute-edit-form.bottom'),
            static function () use (&$legacyCalled): void { $legacyCalled = true; },
        );

        $this->extension($dispatcher)->safeHook('feature.update-form');

        self::assertFalse($legacyCalled, 'only the requested hook and its own aliases must be dispatched');
    }

    private function extension(EventDispatcher $dispatcher): HookExtension
    {
        return new HookExtension($dispatcher, new NullLogger(), new LegacyHookAliases());
    }

    private function backOfficeHook(string $name): string
    {
        return \sprintf('hook.%s.%s', TemplateDefinition::BACK_OFFICE, $name);
    }
}
