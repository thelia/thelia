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

namespace Thelia\Tests\Unit\Domain\Ai\Vault;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Ai\Vault\AiKeyCipher;
use Thelia\Domain\Ai\Vault\AiKeyVault;
use Thelia\Model\AiConnection;

/**
 * "Has a key" and "the key the vault reads" must be the same answer: the screens and the API tell the merchant a
 * key is there, so it cannot be one the vault would refuse to read.
 */
final class AiConnectionHasKeyTest extends TestCase
{
    private const string VARIABLE = 'THELIA_AI_TEST_API_KEY';

    protected function tearDown(): void
    {
        unset($_SERVER[self::VARIABLE], $_ENV[self::VARIABLE], $_SERVER['APP_SECRET_COPY'], $_SERVER['DATABASE_API_KEY']);
        putenv(self::VARIABLE);
    }

    public function testAVariableNamedLikeAKeyThatHoldsAValueIsAKey(): void
    {
        $_SERVER[self::VARIABLE] = 'sk-from-env';

        self::assertTrue((new AiConnection())->setApiKeyEnv(self::VARIABLE)->getHasKey());
    }

    public function testAnUnsetOrBlankVariableIsNoKey(): void
    {
        self::assertFalse((new AiConnection())->setApiKeyEnv(self::VARIABLE)->getHasKey());

        $_SERVER[self::VARIABLE] = '   ';

        self::assertFalse((new AiConnection())->setApiKeyEnv(self::VARIABLE)->getHasKey());
    }

    /** @return iterable<string, array{string}> */
    public static function namesTheVaultRefuses(): iterable
    {
        yield 'not named like a key' => ['APP_SECRET_COPY'];
        yield 'a variable of the application' => ['DATABASE_API_KEY'];
        yield 'not a name' => ['NOT A NAME; rm'];
    }

    #[DataProvider('namesTheVaultRefuses')]
    public function testAVariableTheVaultRefusesIsNeverAKeyEvenWhenItHoldsAValue(string $name): void
    {
        $_SERVER[$name] = 'a-value-that-is-there';

        try {
            self::assertFalse((new AiConnection())->setApiKeyEnv($name)->getHasKey());
        } finally {
            unset($_SERVER[$name]);
        }
    }

    public function testAStoredKeyIsAKeyWhateverTheVariableName(): void
    {
        $connection = (new AiConnection())->setApiKeyEncrypted((new AiKeyVault(new AiKeyCipher('secret-a')))->encrypt('sk-stored'))->setApiKeyEnv('APP_SECRET_COPY');

        self::assertTrue($connection->getHasKey());
    }
}
