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

namespace Thelia\Tests\Unit\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Model\Config;
use Thelia\Model\ConfigQuery;

/**
 * A configuration variable is overridden by the environment variable of the same name, written
 * in upper case with dots and dashes turned into underscores.
 */
final class ConfigTest extends TestCase
{
    private const ENV_NAME = 'THELIA_TEST_MAIL_SMTP_HOST';

    protected function tearDown(): void
    {
        unset($_ENV[self::ENV_NAME]);
        ConfigQuery::resetCache();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function names(): iterable
    {
        yield 'dashes' => ['active-front-template', 'ACTIVE_FRONT_TEMPLATE'];
        yield 'dots' => ['store.name', 'STORE_NAME'];
        yield 'both' => ['thelia-test.mail.smtp-host', 'THELIA_TEST_MAIL_SMTP_HOST'];
        yield 'underscores' => ['store_email', 'STORE_EMAIL'];
        yield 'already an environment name' => ['STORE_SIRET', 'STORE_SIRET'];
    }

    #[DataProvider('names')]
    public function testTheEnvironmentNameOfAVariable(string $configName, string $expected): void
    {
        self::assertSame($expected, Config::getEnvNameFor($configName));
    }

    public function testAModelIsOverriddenByTheEnvironmentVariableOfItsName(): void
    {
        $config = new Config();
        $config->setName('thelia-test.mail.smtp-host');

        self::assertSame(self::ENV_NAME, $config->getEnvName());
        self::assertFalse($config->isOverriddenInEnv());

        $_ENV[self::ENV_NAME] = 'smtp.example.com';

        self::assertTrue($config->isOverriddenInEnv());
        self::assertSame('smtp.example.com', $config->getValue());
    }

    public function testAnEnvironmentVariableWinsOverTheStoredValueWhenReadingAConfigurationVariable(): void
    {
        ConfigQuery::initCache(['thelia-test.mail.smtp-host' => 'localhost']);

        self::assertSame('localhost', ConfigQuery::read('thelia-test.mail.smtp-host'));

        $_ENV[self::ENV_NAME] = 'smtp.example.com';

        self::assertSame('smtp.example.com', ConfigQuery::read('thelia-test.mail.smtp-host'));
    }

    public function testAnEnvironmentValueIsReadAsAString(): void
    {
        ConfigQuery::initCache(['thelia-test.mail.smtp-host' => 'localhost']);
        $_ENV[self::ENV_NAME] = 587;

        self::assertSame('587', ConfigQuery::read('thelia-test.mail.smtp-host'));
    }
}
