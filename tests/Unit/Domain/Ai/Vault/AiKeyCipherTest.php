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
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;
use Thelia\Domain\Ai\Vault\AiKeyCipher;

final class AiKeyCipherTest extends TestCase
{
    public function testAKeyComesBackAsItWasSaved(): void
    {
        $cipher = new AiKeyCipher('a-long-application-secret');

        $blob = $cipher->encrypt('sk-live-1234567890');

        self::assertSame('sk-live-1234567890', $cipher->decrypt($blob));
    }

    public function testTheBlobHoldsNeitherTheKeyNorTheSameBytesTwice(): void
    {
        $cipher = new AiKeyCipher('a-long-application-secret');

        $first = $cipher->encrypt('sk-live-1234567890');

        self::assertStringNotContainsString('sk-live', $first);
        self::assertStringNotContainsString('sk-live', (string) base64_decode(substr($first, 3), true));
        self::assertNotSame($first, $cipher->encrypt('sk-live-1234567890'));
    }

    public function testAnotherSecretCannotOpenTheBlob(): void
    {
        $blob = (new AiKeyCipher('the-old-secret'))->encrypt('sk-live-1234567890');

        self::assertNull((new AiKeyCipher('the-new-secret'))->decrypt($blob));
    }

    public function testWhatIsNotABlobIsNeitherOpenedNorTakenForAKey(): void
    {
        $cipher = new AiKeyCipher('a-long-application-secret');

        self::assertNull($cipher->decrypt('sk-live-1234567890'), 'a plain key');
        self::assertNull($cipher->decrypt('v1:'), 'empty');
        self::assertNull($cipher->decrypt('v1:not base64 !!'), 'bad base64');
        self::assertNull($cipher->decrypt('v1:'.base64_encode('too short')), 'too short');

        $blob = $cipher->encrypt('sk-live-1234567890');
        $tampered = 'v1:'.base64_encode(substr_replace((string) base64_decode(substr($blob, 3), true), 'X', 30, 1));

        self::assertNull($cipher->decrypt($tampered), 'altered');
    }

    public function testTheKeyDerivedForTheAiLayerIsNotTheSecretNorAnotherContext(): void
    {
        $secret = 'a-long-application-secret';
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $otherContext = hash_hkdf('sha256', $secret, \SODIUM_CRYPTO_SECRETBOX_KEYBYTES, 'another.feature');
        $blob = 'v1:'.base64_encode($nonce.sodium_crypto_secretbox('sk-live-1234567890', $nonce, $otherContext));

        self::assertNull((new AiKeyCipher($secret))->decrypt($blob));
    }

    public function testAnEmptySecretIsRefusedAndTheTraceDoesNotShowIt(): void
    {
        $previous = ini_set('zend.exception_ignore_args', '0');

        try {
            new AiKeyCipher('   ');
            self::fail('A blank secret must be refused.');
        } catch (InvalidAiArgumentException $refused) {
            self::assertStringContainsString('Object(SensitiveParameterValue)', $refused->getTraceAsString());
        } finally {
            ini_set('zend.exception_ignore_args', (string) $previous);
        }
    }
}
