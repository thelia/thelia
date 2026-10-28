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

namespace Thelia\Core\DependencyInjection\Extension;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * Accepts the `ai:` configuration the Flex recipes of symfony/ai-*-platform write to config/packages, and does nothing with it.
 *
 * Those recipes assume symfony/ai-bundle. Thelia reads the connections of the shop from its database and
 * builds the platforms itself, so it does not ship that bundle; without an extension that owns the `ai` key,
 * the kernel refuses to boot on a fresh install or after a composer update. The bundle of the core registers
 * this extension only when no other one owns the key (see TheliaBundle::build()).
 *
 * @internal
 */
final class AiRecipeConfigurationExtension extends Extension implements ConfigurationInterface
{
    public const string ALIAS = 'ai';

    public function getAlias(): string
    {
        return self::ALIAS;
    }

    /**
     * @param array<array<string, mixed>> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        // Processing the configuration against a tree that drops every key tells the container that the
        // `%env(...)%` values of the recipes are ignored: without it, the cache dump fails on "environment
        // variables never used" (MISTRAL_API_KEY, OPENAI_API_KEY...).
        $this->processConfiguration($this, $configs);
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder(self::ALIAS);
        $tree->getRootNode()->ignoreExtraKeys();

        return $tree;
    }
}
