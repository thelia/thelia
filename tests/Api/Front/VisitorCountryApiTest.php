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

namespace Thelia\Tests\Api\Front;

use Symfony\Component\HttpFoundation\Response;
use Thelia\Model\ConfigQuery;
use Thelia\Test\ApiTestCase;

final class VisitorCountryApiTest extends ApiTestCase
{
    protected function tearDown(): void
    {
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testItIsPublicAndAnswersTheModeAndTheDetectedCountry(): void
    {
        $this->configure('suggest');

        $response = $this->get(['CF-IPCountry' => 'be']);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['mode' => 'suggest', 'detected' => 'BE'], json_decode($response->getContent(), true));
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
    }

    public function testNothingDetectedIsNull(): void
    {
        $this->configure('apply');

        $data = json_decode($this->get(['CF-IPCountry' => 'XX'])->getContent(), true);

        self::assertSame('apply', $data['mode']);
        self::assertArrayHasKey('detected', $data);
        self::assertNull($data['detected']);
    }

    public function testOffModeDetectsNothingWhateverTheHeaderSays(): void
    {
        $this->configure('off');

        self::assertSame(['mode' => 'off', 'detected' => null], json_decode($this->get(['CF-IPCountry' => 'BE'])->getContent(), true));
    }

    public function testTheApiStartsNoSession(): void
    {
        $this->configure('apply');

        $response = $this->get(['CF-IPCountry' => 'BE']);

        self::assertSame([], $response->headers->getCookies());
        self::assertFalse($response->headers->has('Set-Cookie'));
    }

    private function configure(string $mode): void
    {
        ConfigQuery::write('visitor_country_mode', $mode);
        ConfigQuery::resetCache();
    }

    /**
     * @param array<string, string> $headers
     */
    private function get(array $headers): Response
    {
        $server = ['HTTP_ACCEPT' => 'application/json'];
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        $this->client->request('GET', '/api/front/visitor_country', server: $server);

        return $this->client->getResponse();
    }
}
