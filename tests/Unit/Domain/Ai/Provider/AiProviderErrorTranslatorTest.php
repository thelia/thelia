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

namespace Thelia\Tests\Unit\Domain\Ai\Provider;

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Thelia\Domain\Ai\AiProvider;
use Thelia\Domain\Ai\Provider\AiProviderErrorTranslator;
use Thelia\Tests\Support\Ai\AiProviderTestCase;

final class AiProviderErrorTranslatorTest extends AiProviderTestCase
{
    private const string KEY_IN_CLEAR = 'sk-live-ZZZZ0123456789';

    public function testTheKeyNeverSurvivesInTheMessage(): void
    {
        $translated = (new AiProviderErrorTranslator())->translate(new RuntimeException('401: invalid key '.self::KEY_IN_CLEAR.' for this account'), $this->target(AiProvider::Mistral, null, self::KEY_IN_CLEAR));

        self::assertNotNull($translated);
        self::assertStringNotContainsString(self::KEY_IN_CLEAR, $translated->getMessage());
        self::assertStringContainsString('[hidden]', $translated->getMessage());
    }

    public function testTheChainOfTheErrorIsDroppedWhenItHoldsTheKey(): void
    {
        $error = new RuntimeException('Failed', 0, new TransportException('Authorization: Bearer '.self::KEY_IN_CLEAR));

        $translated = (new AiProviderErrorTranslator())->translate($error, $this->target(AiProvider::Mistral, null, self::KEY_IN_CLEAR));

        self::assertNotNull($translated);
        self::assertNull($translated->getPrevious());
    }

    public function testTheChainIsKeptWhenItIsClean(): void
    {
        $error = new RuntimeException('Failed');

        self::assertSame($error, (new AiProviderErrorTranslator())->translate($error, $this->target(AiProvider::Mistral, null))?->getPrevious());
    }

    public function testABearerTokenIsHiddenEvenWhenItIsNotTheKeyOfTheConnection(): void
    {
        self::assertSame('Sent Bearer [hidden] to the host', AiProviderErrorTranslator::redact('Sent Bearer abcdef0123456789 to the host', null));
    }

    public function testALongMessageIsCut(): void
    {
        self::assertLessThanOrEqual(500, mb_strlen(AiProviderErrorTranslator::redact(str_repeat('x', 5000), null)));
    }

    public function testATransportFailureIsRetriedButARefusedAddressIsNot(): void
    {
        $translator = new AiProviderErrorTranslator();
        $target = $this->target(AiProvider::Mistral, null);

        self::assertTrue($translator->translate(new TransportException('Connection timed out'), $target)?->retryable);
        self::assertFalse($translator->translate(new TransportException('IP 127.0.0.1 is blocked for "http://127.0.0.1/".'), $target)?->retryable);
        self::assertFalse($translator->translate(new RuntimeException('Unsupported finish reason'), $target)?->retryable);
    }

    public function testABugOfTheCodeIsNotDisguisedAsAProviderFailure(): void
    {
        self::assertNull((new AiProviderErrorTranslator())->translate(new \LogicException('a bug'), $this->target(AiProvider::Mistral, null)));
        self::assertNull((new AiProviderErrorTranslator())->translate(new \TypeError('a bug'), $this->target(AiProvider::Mistral, null)));
    }
}
