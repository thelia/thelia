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
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Thelia\Domain\Webhook\WebhookHttpClientFactory;

final class WebhookHttpClientFactoryTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function privateTargets(): iterable
    {
        yield 'loopback address' => ['http://127.0.0.1:9/hook'];
        yield 'name that resolves to loopback' => ['http://localhost:9/hook'];
        yield 'site local ipv6' => ['http://[fec0::1]:9/hook'];
        yield 'ipv4 translated private' => ['http://[::ffff:0:a00:1]:9/hook'];
        yield 'benchmarking range' => ['http://198.18.0.1:9/hook'];
    }

    #[DataProvider('privateTargets')]
    public function testTheClientRefusesToConnectToAPrivateNetwork(string $url): void
    {
        $client = WebhookHttpClientFactory::create();

        $this->expectException(TransportExceptionInterface::class);
        $this->expectExceptionMessageMatches('/blocked/i');

        $client->request('POST', $url, ['body' => '{}'])->getStatusCode();
    }

    public function testARedirectIsNotFollowed(): void
    {
        $marker = $this->directory().'/second-was-called';
        $port = $this->serve('
            if ("/first" === parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH)) { header("Location: /second", true, 307); return; }
            file_put_contents('.var_export($marker, true).', "yes");
        ');

        $status = WebhookHttpClientFactory::create(self::PUBLIC_ONLY)->request('POST', 'http://127.0.0.1:'.$port.'/first', ['body' => '{}'])->getStatusCode();

        self::assertSame(307, $status);
        self::assertFileDoesNotExist($marker, 'The redirect was followed.');
    }

    public function testAReceiverThatStaysSilentIsGivenUpOn(): void
    {
        $port = $this->serve('sleep(8);');

        $started = microtime(true);

        try {
            WebhookHttpClientFactory::create(self::PUBLIC_ONLY)->request('POST', 'http://127.0.0.1:'.$port.'/slow', ['body' => '{}'])->getStatusCode();
            self::fail('The client waited for a receiver that never answered.');
        } catch (TransportExceptionInterface) {
            self::assertLessThan(7, microtime(true) - $started);
        }
    }

    private const array PUBLIC_ONLY = ['10.0.0.0/8'];

    /** @var list<resource> */
    private array $servers = [];

    private ?string $directory = null;

    protected function tearDown(): void
    {
        foreach ($this->servers as $server) {
            proc_terminate($server);
            proc_close($server);
        }

        if (null !== $this->directory) {
            array_map('unlink', glob($this->directory.'/*') ?: []);
            @rmdir($this->directory);
        }
    }

    private function directory(): string
    {
        if (null === $this->directory) {
            $this->directory = sys_get_temp_dir().'/webhook-client-'.bin2hex(random_bytes(4));
            mkdir($this->directory);
        }

        return $this->directory;
    }

    private function serve(string $routerBody): int
    {
        file_put_contents($this->directory().'/router.php', '<?php '.$routerBody);
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        $port = (int) substr(strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);
        $this->servers[] = proc_open([\PHP_BINARY, '-S', '127.0.0.1:'.$port, $this->directory().'/router.php'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);

        for ($try = 0; $try < 50 && !@fsockopen('127.0.0.1', $port); ++$try) {
            usleep(100_000);
        }

        return $port;
    }
}
