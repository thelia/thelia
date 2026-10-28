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

namespace Thelia\Tests\Integration\Domain\Ai\Tool;

use Thelia\Domain\Ai\Tool\Mcp\McpAllowedHosts;
use Thelia\Model\ConfigQuery;
use Thelia\Model\LangQuery;
use Thelia\Test\IntegrationTestCase;

final class McpAllowedHostsTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testTheHostsOfTheShopAndTheLoopbackNamesAreAllowedByDefault(): void
    {
        ConfigQuery::write('url_site', 'https://Shop.Example.test:8443/base');

        self::assertSame(['localhost', '127.0.0.1', '[::1]', 'shop.example.test'], (new McpAllowedHosts(''))->hosts());
    }

    public function testAShopUrlWithoutSchemeIsRead(): void
    {
        ConfigQuery::write('url_site', 'shop.example.test');

        self::assertContains('shop.example.test', (new McpAllowedHosts(''))->hosts());
    }

    public function testTheDomainOfEachLanguageIsAllowedWhenTheShopHasOnePerLanguage(): void
    {
        ConfigQuery::write('url_site', 'https://shop.example.test');
        $lang = LangQuery::create()->findOneByByDefault(1);
        $lang->setUrl('https://fr.example.test')->save();

        ConfigQuery::write('one_domain_foreach_lang', '0');
        self::assertNotContains('fr.example.test', (new McpAllowedHosts(''))->hosts());

        ConfigQuery::write('one_domain_foreach_lang', '1');
        self::assertContains('fr.example.test', (new McpAllowedHosts(''))->hosts());
    }

    public function testAnEmptyShopUrlLeavesTheLoopbackNames(): void
    {
        ConfigQuery::write('url_site', '');

        self::assertSame(['localhost', '127.0.0.1', '[::1]'], (new McpAllowedHosts(''))->hosts());
    }

    public function testTheEnvironmentVariableWinsAndTheDatabaseIsNotRead(): void
    {
        ConfigQuery::write('url_site', 'https://shop.example.test');

        self::assertSame(['mcp.example.test', 'other.example.test'], (new McpAllowedHosts(' mcp.example.test , ,other.example.test'))->hosts());
    }
}
