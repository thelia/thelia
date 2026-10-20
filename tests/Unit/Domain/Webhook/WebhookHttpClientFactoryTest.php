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
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Thelia\Domain\Webhook\WebhookHttpClientFactory;

final class WebhookHttpClientFactoryTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function privateTargets(): iterable
    {
        yield 'loopback address' => ['http://127.0.0.1:9/hook'];
        yield 'name that resolves to loopback' => ['http://localhost:9/hook'];
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
        $directory = sys_get_temp_dir().'/webhook-redirect-'.bin2hex(random_bytes(4));
        mkdir($directory);
        $marker = $directory.'/second-was-called';
        file_put_contents($directory.'/router.php', '<?php
            if ("/first" === parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH)) { header("Location: /second", true, 302); return; }
            file_put_contents('.var_export($marker, true).', "yes");
        ');
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        $port = (int) substr(strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);
        $server = proc_open([\PHP_BINARY, '-S', '127.0.0.1:'.$port, $directory.'/router.php'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);

        try {
            for ($try = 0; $try < 50 && !@fsockopen('127.0.0.1', $port); ++$try) {
                usleep(100_000);
            }

            $status = HttpClient::create(WebhookHttpClientFactory::OPTIONS)->request('POST', 'http://127.0.0.1:'.$port.'/first', ['body' => '{}'])->getStatusCode();

            self::assertSame(302, $status);
            self::assertFileDoesNotExist($marker, 'The redirect was followed.');
        } finally {
            proc_terminate($server);
            proc_close($server);
            @unlink($marker);
            @unlink($directory.'/router.php');
            @rmdir($directory);
        }
    }
}
