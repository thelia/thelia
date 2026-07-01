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

use Thelia\Model\Config;
use Thelia\Test\IntegrationTestCase;
use Twig\Environment;

/**
 * The TwigEngine config() function reads a store configuration value, the Twig counterpart
 * of the Smarty {config key="..."} plugin, with an optional fallback when the key is unset.
 */
final class ConfigExtensionTest extends IntegrationTestCase
{
    public function testConfigReturnsStoredValue(): void
    {
        $config = new Config();
        $config->setName('twig_config_probe');
        $config->setValue('Acme Store');
        $config->setHidden(0);
        $config->setSecured(0);
        $config->save($this->getPropelConnection());

        self::assertSame('Acme Store', $this->render("{{ config('twig_config_probe') }}"));
    }

    public function testConfigReturnsFallbackWhenKeyIsMissing(): void
    {
        self::assertSame('fallback', $this->render("{{ config('twig_config_absent_key', 'fallback') }}"));
    }

    private function render(string $template): string
    {
        /** @var Environment $twig */
        $twig = static::getContainer()->get('twig');

        return $twig->createTemplate($template)->render();
    }
}
