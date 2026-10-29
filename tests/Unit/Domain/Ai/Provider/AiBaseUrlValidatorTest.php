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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;
use Thelia\Domain\Ai\Provider\AiBaseUrlValidator;
use Thelia\Domain\Webhook\WebhookUrlValidator;

final class AiBaseUrlValidatorTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function acceptedPublicly(): iterable
    {
        yield 'https with a path' => ['https://api.scaleway.ai/v1'];
        yield 'https on a port' => ['https://93.184.216.34:8443'];
    }

    #[DataProvider('acceptedPublicly')]
    public function testAPublicHttpsAddressIsAccepted(string $url): void
    {
        $this->validator()->validate($url, false);

        $this->addToAssertionCount(1);
    }

    /** @return iterable<string, array{string}> */
    public static function refusedPublicly(): iterable
    {
        yield 'plain http' => ['http://93.184.216.34/v1'];
        yield 'loopback' => ['https://127.0.0.1/v1'];
        yield 'cloud metadata' => ['https://169.254.169.254/'];
        yield 'private address' => ['https://10.0.0.5/v1'];
        yield 'credentials' => ['https://user:pass@93.184.216.34/v1'];
        yield 'not an address' => ['not a url'];
        yield 'another scheme' => ['file:///etc/passwd'];
    }

    #[DataProvider('refusedPublicly')]
    public function testAnythingButAPublicHttpsAddressIsRefused(string $url): void
    {
        $this->expectException(InvalidAiArgumentException::class);
        $this->expectExceptionMessage('The base URL is not allowed');

        $this->validator()->validate($url, false);
    }

    /** @return iterable<string, array{string}> */
    public static function acceptedOnTheLan(): iterable
    {
        yield 'plain http on the LAN' => ['http://192.168.1.20:11434'];
        yield 'loopback' => ['http://127.0.0.1:11434'];
        yield 'a host name of the intranet' => ['http://ollama.local:11434'];
        yield 'https' => ['https://10.0.0.5/v1'];
    }

    #[DataProvider('acceptedOnTheLan')]
    public function testThePrivateNetworkAcceptsPlainHttpWhenTheConnectionAllowsIt(string $url): void
    {
        $this->validator()->validate($url, true);

        $this->addToAssertionCount(1);
    }

    /** @return iterable<string, array{string}> */
    public static function refusedOnTheLan(): iterable
    {
        yield 'credentials' => ['http://user:pass@192.168.1.20'];
        yield 'another scheme' => ['ftp://192.168.1.20'];
        yield 'file' => ['file:///etc/passwd'];
        yield 'no scheme' => ['192.168.1.20:11434'];
        yield 'a space' => ['http://192.168.1.20 /x'];
        yield 'a line break' => ["http://192.168.1.20/\r\nHost: evil"];
    }

    #[DataProvider('refusedOnTheLan')]
    public function testEvenOnTheLanTheAddressStaysAnAbsoluteHttpAddressWithoutCredentials(string $url): void
    {
        $this->expectException(InvalidAiArgumentException::class);

        $this->validator()->validate($url, true);
    }

    public function testThePlainHttpFlagOfTheWebhooksChangesNothingForAConnection(): void
    {
        $this->expectException(InvalidAiArgumentException::class);
        $this->expectExceptionMessage('The base URL is not allowed');

        (new AiBaseUrlValidator(new WebhookUrlValidator(allowInsecure: true)))->validate('http://93.184.216.34/v1', false);
    }

    public function testTheFlagOfTheAiLayerAllowsPlainHttpOnAPublicAddressAndNothingElse(): void
    {
        $validator = new AiBaseUrlValidator(new WebhookUrlValidator(), allowInsecure: true);

        $validator->validate('http://93.184.216.34/v1', false);
        $this->addToAssertionCount(1);

        $this->expectException(InvalidAiArgumentException::class);

        $validator->validate('http://10.0.0.5/v1', false);
    }

    private function validator(): AiBaseUrlValidator
    {
        return new AiBaseUrlValidator(new WebhookUrlValidator());
    }
}
