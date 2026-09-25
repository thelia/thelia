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

namespace Thelia\Module;

/**
 * Reads the parts of a module.xml descriptor that decide how the shop treats the module,
 * for the install steps that run with and without the kernel.
 */
final readonly class ModuleDescriptor
{
    public const string ENABLED_BY_DEFAULT = 'enabled-by-default';

    /**
     * Whether the module is active right after the shop is installed. A descriptor that
     * says nothing keeps the historical behaviour: active. A value other than 0 or 1 is
     * refused rather than guessed, so a typo never ships a module in the wrong state.
     *
     * @param string $descriptorPath where the descriptor comes from, for the error message
     */
    public static function enabledByDefault(\SimpleXMLElement $descriptor, string $descriptorPath): bool
    {
        $element = $descriptor->{self::ENABLED_BY_DEFAULT};

        if (0 === \count($element)) {
            return true;
        }

        return match (trim((string) $element)) {
            '1' => true,
            '0' => false,
            default => throw new \InvalidArgumentException(\sprintf('<%s> in %s must be 0 or 1, "%s" given.', self::ENABLED_BY_DEFAULT, $descriptorPath, trim((string) $element))),
        };
    }
}
