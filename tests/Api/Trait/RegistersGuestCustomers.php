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

namespace Thelia\Tests\Api\Trait;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\ConfigQuery;

/**
 * Opening guest accounts over the API, from a test.
 *
 * Two things need care. The shop refuses the whole flow until the setting is turned
 * on, so every test that expects it to work has to say so; and the registration is
 * rate limited on the client address, which is the same address for the whole suite,
 * so the counters are cleared between tests or the last tests of a file would be
 * measuring the first ones.
 */
trait RegistersGuestCustomers
{
    private ?string $previousGuestCheckoutMode = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousGuestCheckoutMode = ConfigQuery::getGuestCheckoutMode();
        $this->clearRateLimits();
    }

    protected function tearDown(): void
    {
        ConfigQuery::write('guest_checkout_mode', (string) $this->previousGuestCheckoutMode);

        parent::tearDown();
    }

    protected function enableGuestCheckout(string $mode = 'enabled'): void
    {
        ConfigQuery::write('guest_checkout_mode', $mode);
    }

    protected function clearRateLimits(): void
    {
        $pool = static::getContainer()->get('cache.rate_limiter');

        if ($pool instanceof CacheItemPoolInterface) {
            $pool->clear();
        }
    }

    /**
     * Registers a guest and returns the response together with its decoded body.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array{0: Response, 1: array<string, mixed>}
     */
    protected function registerGuest(array $overrides = []): array
    {
        $payload = array_merge([
            'email' => 'guest-api-'.bin2hex(random_bytes(6)).'@test.com',
            'firstname' => 'Guest',
            'lastname' => 'Visitor',
        ], $overrides);

        $response = $this->jsonRequest('POST', '/api/front/guest-customers', $payload);
        $body = json_decode((string) $response->getContent(), true);

        return [$response, \is_array($body) ? $body : []];
    }

    /**
     * Registers a guest as a visitor who shares nothing with the previous one.
     *
     * The cart and the customer of a visit live in the session, and a test process
     * holds one session service for every request it makes — so without this, two
     * "different" guests would be handed the same cart and the ownership tests would
     * pass for the wrong reason. Dropping the cookies is not enough: the object behind
     * the session is shared inside the kernel, and it keeps the cart of the last visit,
     * including the copy Session holds statically for a cart never written yet.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array{0: Response, 1: array<string, mixed>}
     */
    protected function registerGuestInAFreshSession(array $overrides = []): array
    {
        $this->client->getCookieJar()->clear();

        $session = static::getContainer()->get(Session::class);
        $session->setSessionCart(null);
        $session->clear();

        return $this->registerGuest($overrides);
    }

    /**
     * The claims of a JWT, read without checking the signature: a test asserts on what
     * the shop put in the token, and the server is the one that verifies it.
     *
     * @return array<string, mixed>
     */
    protected static function jwtPayload(string $token): array
    {
        $parts = explode('.', $token);

        self::assertCount(3, $parts, 'A JWT is made of three parts.');

        $claims = json_decode(
            (string) base64_decode(strtr($parts[1], '-_', '+/'), true),
            true,
            flags: \JSON_THROW_ON_ERROR,
        );

        self::assertIsArray($claims);

        return $claims;
    }
}
