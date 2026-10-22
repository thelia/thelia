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

namespace Thelia\Tests\Unit\Domain\Webhook;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Webhook\Exception\InvalidWebhookUrlException;
use Thelia\Domain\Webhook\WebhookUrlValidator;

final class WebhookUrlValidatorTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function refusedUrls(): iterable
    {
        yield 'loopback name' => ['https://localhost/hook'];
        yield 'loopback subdomain' => ['https://shop.localhost/hook'];
        yield 'loopback ip' => ['https://127.0.0.1/hook'];
        yield 'loopback short form' => ['https://127.1/hook'];
        yield 'loopback decimal form' => ['https://2130706433/hook'];
        yield 'loopback hex form' => ['https://0x7f000001/hook'];
        yield 'private 10' => ['https://10.0.0.5/hook'];
        yield 'private 172' => ['https://172.16.4.1/hook'];
        yield 'private 192' => ['https://192.168.1.1/hook'];
        yield 'link local metadata' => ['https://169.254.169.254/latest/meta-data'];
        yield 'shared address space' => ['https://100.64.0.1/hook'];
        yield 'alibaba metadata' => ['https://100.100.100.200/latest/meta-data'];
        yield 'ietf protocol assignments' => ['https://192.0.0.1/hook'];
        yield 'multicast' => ['https://224.0.0.1/hook'];
        yield 'documentation range' => ['https://203.0.113.9/hook'];
        yield 'benchmarking' => ['https://198.18.0.1/hook'];
        yield 'unspecified' => ['https://0.0.0.0/hook'];
        yield 'ipv6 loopback' => ['https://[::1]/hook'];
        yield 'ipv6 unique local' => ['https://[fd00::1]/hook'];
        yield 'ipv6 link local' => ['https://[fe80::1]/hook'];
        yield 'ipv4 mapped loopback' => ['https://[::ffff:127.0.0.1]/hook'];
        yield 'ipv4 mapped private' => ['https://[::ffff:10.0.0.1]/hook'];
        yield 'nat64 loopback' => ['https://[64:ff9b::7f00:1]/hook'];
        yield '6to4 loopback' => ['https://[2002:7f00:1::1]/hook'];
        yield 'ipv6 site local' => ['https://[fec0::1]/hook'];
        yield 'ipv6 discard only' => ['https://[100::1]/hook'];
        yield 'ipv4 translated private' => ['https://[::ffff:0:a00:1]/hook'];
        yield 'internal suffix' => ['https://db.internal/hook'];
        yield 'local suffix' => ['https://printer.local/hook'];
        yield 'credentials in the url' => ['https://user:pass@example.com/hook'];
        yield 'plain http' => ['http://example.com/hook'];
        yield 'other scheme' => ['ftp://example.com/hook'];
        yield 'no scheme' => ['example.com/hook'];
        yield 'no host' => ['https:///hook'];
        yield 'empty' => [''];
        yield 'port zero' => ['https://example.com:0/hook'];
        yield 'control character' => ["https://example.com/ho\nok"];
        yield 'a sentence' => ['not a url'];
        yield 'a space in the path' => ['https://example.com/ho ok'];
    }

    public function testTheMessageNamesWhatIsWrongWithTheAddress(): void
    {
        $validator = new WebhookUrlValidator();

        foreach (['' => 'The address is empty.', 'not a url' => 'The address contains a space or a control character.', 'example.com/hook' => 'The address must be an absolute URL.'] as $url => $message) {
            try {
                $validator->validate((string) $url);
                self::fail('"'.$url.'" was accepted.');
            } catch (InvalidWebhookUrlException $exception) {
                self::assertSame($message, $exception->getMessage(), '"'.$url.'"');
            }
        }
    }

    #[DataProvider('refusedUrls')]
    public function testItRefusesAnAddressThatCouldReachTheShopNetwork(string $url): void
    {
        $this->expectException(InvalidWebhookUrlException::class);

        (new WebhookUrlValidator())->validate($url);
    }

    /** @return iterable<string, array{string}> */
    public static function acceptedUrls(): iterable
    {
        yield 'plain host' => ['https://example.com/hook'];
        yield 'port and query' => ['https://hooks.example.org:8443/in?source=shop'];
        yield 'public ipv4' => ['https://93.184.216.34/hook'];
        yield 'public ipv6' => ['https://[2606:2800:220:1:248:1893:25c8:1946]/hook'];
    }

    #[DataProvider('acceptedUrls')]
    public function testItAcceptsAPublicHttpsAddress(string $url): void
    {
        (new WebhookUrlValidator())->validate($url);

        $this->addToAssertionCount(1);
    }

    public function testPlainHttpIsAcceptedOnlyWhenTheShopAllowsIt(): void
    {
        (new WebhookUrlValidator(allowInsecure: true))->validate('http://example.com/hook');

        $this->addToAssertionCount(1);
    }

    public function testAnAllowedInsecureShopStillRefusesPrivateAddresses(): void
    {
        $this->expectException(InvalidWebhookUrlException::class);

        (new WebhookUrlValidator(allowInsecure: true))->validate('http://10.0.0.5/hook');
    }
}
