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

namespace Thelia\Tests\Support\Flexy;

use Thelia\Core\Content\Slot\ContentSlotService;

/**
 * Whether the installed front theme reads its links from the content slots.
 *
 * The core is tested with whichever theme it is given, and a theme published before the
 * slots reads content ids and settings instead: what these tests pin about the slots is
 * reported as skipped there rather than failed. A component reads the slots when it is
 * built with the slot service; the templates moved to the slots in the same release as
 * the header, so the header component stands for the theme as a whole.
 */
final class ThemeContentSlots
{
    private const HEADER_COMPONENT = 'FlexyBundle\\Components\\Layouts\\Header\\Base';

    public static function areReadByTheTheme(): bool
    {
        return self::areReadBy(self::HEADER_COMPONENT);
    }

    public static function areReadBy(string $componentClass): bool
    {
        if (!class_exists($componentClass)) {
            return false;
        }

        $constructor = (new \ReflectionClass($componentClass))->getConstructor();

        foreach ($constructor?->getParameters() ?? [] as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof \ReflectionNamedType && ContentSlotService::class === $type->getName()) {
                return true;
            }
        }

        return false;
    }
}
