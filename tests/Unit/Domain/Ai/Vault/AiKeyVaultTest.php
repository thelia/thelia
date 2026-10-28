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

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Ai\Exception\AiKeyDecryptionException;
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;
use Thelia\Domain\Ai\Vault\AiKeyCipher;
use Thelia\Domain\Ai\Vault\AiKeyVault;

final class AiKeyVaultTest extends TestCase
{
    private const string VARIABLE = 'THELIA_AI_TEST_API_KEY';

    protected function tearDown(): void
    {
        unset($_SERVER[self::VARIABLE], $_ENV[self::VARIABLE]);
        putenv(self::VARIABLE);
    }

    public function testTheStoredKeyIsDecrypted(): void
    {
        $vault = new AiKeyVault(new AiKeyCipher('secret-a'));

        self::assertSame('sk-stored', $vault->resolveFor('mistral-main', $vault->encrypt('sk-stored'), null));
    }

    public function testTheEnvironmentVariableWinsOverTheStoredKey(): void
    {
        $vault = new AiKeyVault(new AiKeyCipher('secret-a'));
        $_SERVER[self::VARIABLE] = ' sk-from-env ';

        self::assertSame('sk-from-env', $vault->resolveFor('mistral-main', $vault->encrypt('sk-stored'), self::VARIABLE));
    }

    public function testAnUnsetVariableFallsBackToTheStoredKeyAndNoKeyIsNull(): void
    {
        $vault = new AiKeyVault(new AiKeyCipher('secret-a'));

        self::assertSame('sk-stored', $vault->resolveFor('mistral-main', $vault->encrypt('sk-stored'), self::VARIABLE));
        self::assertNull($vault->resolveFor('ollama-lan', null, self::VARIABLE));
        self::assertNull($vault->resolveFor('ollama-lan', '', null));
    }

    public function testAKeyEncryptedWithAnotherSecretIsAnErrorThatSaysWhatToDoAndNeverTheBlob(): void
    {
        $blob = (new AiKeyVault(new AiKeyCipher('old-secret')))->encrypt('sk-stored');
        $vault = new AiKeyVault(new AiKeyCipher('new-secret'));

        try {
            $vault->resolveFor('mistral-main', $blob, null);
            self::fail('The blob must not be handed back as a key.');
        } catch (AiKeyDecryptionException $error) {
            self::assertSame('mistral-main', $error->connection);
            self::assertStringContainsString('thelia:ai:rotate-keys', $error->getMessage());
            self::assertStringContainsString('"mistral-main"', $error->getMessage());
            self::assertStringNotContainsString($blob, $error->getMessage());
            self::assertStringNotContainsString('sk-stored', $error->getMessage());
        }
    }

    public function testAPlainKeyInTheEncryptedColumnIsNotSentAsIs(): void
    {
        $this->expectException(AiKeyDecryptionException::class);

        (new AiKeyVault(new AiKeyCipher('secret-a')))->resolveFor('mistral-main', 'sk-plain-key-saved-by-mistake', null);
    }

    public function testTheNameOfTheVariableIsChecked(): void
    {
        $this->expectException(InvalidAiArgumentException::class);

        (new AiKeyVault(new AiKeyCipher('secret-a')))->resolveFor('mistral-main', null, 'NOT A NAME; rm');
    }
}
