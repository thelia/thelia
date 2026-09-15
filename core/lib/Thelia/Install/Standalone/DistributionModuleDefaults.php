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

namespace Thelia\Install\Standalone;

/**
 * What the distribution says about the modules it ships: which ones wait for the
 * merchant to activate them instead of being active from the first boot.
 *
 * Read from the `extra.thelia.modules-disabled-by-default` list of the project's
 * composer.json, the file the distribution hands to every new shop, so the list of
 * shipped modules and their default state live in the same place.
 *
 * The list has no hold over a module a theme requires: template:set activates every
 * dependency of the selected theme once the modules are registered.
 */
final readonly class DistributionModuleDefaults
{
    public const string COMPOSER_EXTRA_KEY = 'modules-disabled-by-default';

    /** @param string[] $disabledByDefault module codes */
    public function __construct(private array $disabledByDefault = [])
    {
    }

    public static function fromComposerJson(string $composerJsonPath): self
    {
        if (!is_file($composerJsonPath)) {
            return new self();
        }

        $content = file_get_contents($composerJsonPath);
        if (false === $content) {
            return new self();
        }

        $composer = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        $disabled = $composer['extra']['thelia'][self::COMPOSER_EXTRA_KEY] ?? [];

        if (!\is_array($disabled) || !array_is_list($disabled)) {
            throw new \InvalidArgumentException(\sprintf('extra.thelia.%s in %s must be a list of module codes.', self::COMPOSER_EXTRA_KEY, $composerJsonPath));
        }

        foreach ($disabled as $code) {
            if (!\is_string($code) || '' === $code) {
                throw new \InvalidArgumentException(\sprintf('extra.thelia.%s in %s must only contain module codes.', self::COMPOSER_EXTRA_KEY, $composerJsonPath));
            }
        }

        return new self($disabled);
    }

    public function isDisabledByDefault(string $moduleCode): bool
    {
        return \in_array($moduleCode, $this->disabledByDefault, true);
    }
}
