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

namespace Thelia\Core\Hook\Theme;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Answers a theme hook point declared with the theme_hook() Twig function.
 *
 * A theme declares its extension points by calling theme_hook('page.zone.position')
 * in its templates. A module implements this interface to inject content there:
 * supports() filters the hook points the module answers to, render() returns the
 * HTML fragment, built from the parameters passed by the template.
 *
 * Implementations are collected through the "thelia.theme_hook" tag (autoconfigured).
 * Use the tag priority to control rendering order between modules.
 */
#[AutoconfigureTag('thelia.theme_hook')]
interface ThemeHookInterface
{
    public function supports(string $hookName): bool;

    /**
     * @param array<string, mixed> $parameters the parameters passed by the template at the hook point
     */
    public function render(string $hookName, array $parameters): string;
}
