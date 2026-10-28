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
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Thelia\Domain\Ai\Provider\AiHttpClientFactory;

final class AiHttpClientFactoryTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function nonPublicAddresses(): iterable
    {
        yield 'loopback' => ['http://127.0.0.1/v1'];
        yield 'loopback in IPv6' => ['http://[::1]/v1'];
        yield 'cloud metadata' => ['http://169.254.169.254/latest/meta-data'];
        yield 'private class A' => ['http://10.0.0.5/v1'];
        yield 'private class B' => ['http://172.16.0.9/v1'];
        yield 'private class C' => ['http://192.168.1.20/v1'];
        yield 'unspecified' => ['http://0.0.0.0/v1'];
    }

    #[DataProvider('nonPublicAddresses')]
    public function testAPrivateAddressIsRefusedBeforeAnyRequestLeaves(string $url): void
    {
        $reached = 0;
        $client = $this->factory($reached)->create(false);

        try {
            $client->request('POST', $url);
            self::fail('The request should have been refused.');
        } catch (TransportExceptionInterface $refusal) {
            self::assertStringContainsString('blocked', $refusal->getMessage());
        }

        self::assertSame(0, $reached);
    }

    public function testAPublicAddressGoes(): void
    {
        $reached = 0;

        $this->factory($reached)->create(false)->request('POST', 'https://93.184.216.34/v1')->getStatusCode();

        self::assertSame(1, $reached);
    }

    public function testTheLanIsReachableWhenTheConnectionAllowsIt(): void
    {
        $reached = 0;
        $client = $this->factory($reached)->create(true);

        foreach (['http://127.0.0.1:11434/api/chat', 'http://192.168.1.20:11434/api/chat', 'http://10.0.0.5/v1'] as $url) {
            $client->request('POST', $url)->getStatusCode();
        }

        self::assertSame(3, $reached);
    }

    /** @return iterable<string, array{string}> */
    public static function neverReachable(): iterable
    {
        yield 'cloud metadata' => ['http://169.254.169.254/latest/meta-data'];
        yield 'link-local in IPv6' => ['http://[fe80::1]/v1'];
    }

    #[DataProvider('neverReachable')]
    public function testSomeAddressesStayRefusedEvenWhenThePrivateNetworkIsAllowed(string $url): void
    {
        $reached = 0;

        $this->expectException(TransportExceptionInterface::class);

        try {
            $this->factory($reached)->create(true)->request('POST', $url);
        } finally {
            self::assertSame(0, $reached);
        }
    }

    public function testTheDelaysAreTheOnesOfTheParametersAndRedirectsAreOff(): void
    {
        $seen = [];
        $transport = new MockHttpClient(static function (string $method, string $url, array $options) use (&$seen): MockResponse {
            $seen = $options;

            return new MockResponse('{}');
        });

        (new AiHttpClientFactory(60, 300))->withTransport($transport)->create(false)->request('POST', 'https://93.184.216.34/v1')->getStatusCode();

        self::assertSame(60.0, (float) $seen['timeout']);
        self::assertSame(300.0, (float) $seen['max_duration']);
        self::assertSame(0, $seen['max_redirects']);
    }

    public function testARedirectIsNotFollowed(): void
    {
        $calls = [];
        $transport = new MockHttpClient(static function (string $method, string $url) use (&$calls): MockResponse {
            $calls[] = $url;

            return new MockResponse('', ['http_code' => 302, 'redirect_url' => 'https://93.184.216.35/elsewhere']);
        });

        $response = (new AiHttpClientFactory(60, 300))->withTransport($transport)->create(false)->request('POST', 'https://93.184.216.34/v1');

        self::assertSame(302, $response->getStatusCode());
        self::assertSame(['https://93.184.216.34/v1'], $calls, 'the key would go to the next address');
    }

    public function testTheDelaysOfTheContainerAreTheLongOnes(): void
    {
        $container = new ContainerBuilder();
        (new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 5).'/core/lib/Thelia/Config/Resources/parameters')))->load('ai.php');

        self::assertSame(60, $container->getParameter('thelia.ai.http_timeout'));
        self::assertSame(300, $container->getParameter('thelia.ai.http_max_duration'));
    }

    private function factory(int &$reached): AiHttpClientFactory
    {
        $transport = new MockHttpClient(static function () use (&$reached): MockResponse {
            ++$reached;

            return new MockResponse('{}');
        });

        return (new AiHttpClientFactory(60, 300))->withTransport($transport);
    }
}
