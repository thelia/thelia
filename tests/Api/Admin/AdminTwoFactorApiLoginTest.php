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

namespace Thelia\Tests\Api\Admin;

use Symfony\Component\HttpFoundation\Response;
use Thelia\Domain\Admin\TwoFactor\AdminTwoFactorManager;
use Thelia\Domain\Admin\TwoFactor\Totp;
use Thelia\Model\Admin;
use Thelia\Model\ConfigQuery;
use Thelia\Test\ApiTestCase;

final class AdminTwoFactorApiLoginTest extends ApiTestCase
{
    private const PASSWORD = 'correct horse battery';

    protected function tearDown(): void
    {
        ConfigQuery::write(AdminTwoFactorManager::REQUIRED_CONFIG_KEY, '0');

        parent::tearDown();
    }

    public function testAnAccountWithoutSecondFactorGetsItsTokenAsBefore(): void
    {
        $response = $this->logIn($this->admin(), self::PASSWORD);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertArrayHasKey('token', $this->decode($response));
    }

    public function testAProtectedAccountGetsNoTokenWithoutACode(): void
    {
        $admin = $this->admin();
        $this->enableSecondFactor($admin);

        $response = $this->logIn($admin, self::PASSWORD);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertArrayNotHasKey('token', $this->decode($response));
    }

    public function testAProtectedAccountGetsItsTokenWithTheRightCode(): void
    {
        $admin = $this->admin();
        $secret = $this->enableSecondFactor($admin);

        $response = $this->logIn($admin, self::PASSWORD, $this->nextCode($secret));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertArrayHasKey('token', $this->decode($response));
    }

    public function testAProtectedAccountGetsNoTokenWithAWrongCode(): void
    {
        $admin = $this->admin();
        $secret = $this->enableSecondFactor($admin);
        $wrongCode = '000000' === $this->nextCode($secret) ? '111111' : '000000';

        $response = $this->logIn($admin, self::PASSWORD, $wrongCode);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testACodeCannotBeReplayedForASecondToken(): void
    {
        $admin = $this->admin();
        $secret = $this->enableSecondFactor($admin);
        $code = $this->nextCode($secret);

        self::assertSame(Response::HTTP_OK, $this->logIn($admin, self::PASSWORD, $code)->getStatusCode());
        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->logIn($admin, self::PASSWORD, $code)->getStatusCode());
    }

    public function testAWrongPasswordAnswersTheSameWhetherTheAccountIsProtectedOrNot(): void
    {
        $protected = $this->admin();
        $secret = $this->enableSecondFactor($protected);

        $protectedAnswer = $this->logIn($protected, 'wrong password', $this->nextCode($secret));
        $unprotectedAnswer = $this->logIn($this->admin(), 'wrong password');
        $protectedWithoutCode = $this->logIn($protected, self::PASSWORD);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $protectedAnswer->getStatusCode());
        self::assertSame($this->decode($unprotectedAnswer), $this->decode($protectedAnswer));
        self::assertSame($this->decode($unprotectedAnswer), $this->decode($protectedWithoutCode));
    }

    public function testTheSettingRefusesATokenToAnAccountThatHasNoSecondFactorYet(): void
    {
        ConfigQuery::write(AdminTwoFactorManager::REQUIRED_CONFIG_KEY, '1');

        $response = $this->logIn($this->admin(), self::PASSWORD);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testARefreshTokenFromBeforeTheSecondFactorStopsWorkingOnceItIsEnabled(): void
    {
        $admin = $this->admin();
        $refreshToken = (string) $this->decode($this->logIn($admin, self::PASSWORD))['refresh_token'];

        $this->enableSecondFactor($admin);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->refresh($refreshToken)->getStatusCode());
    }

    public function testARefreshTokenObtainedWithTheCodeKeepsRefreshing(): void
    {
        $admin = $this->admin();
        $secret = $this->enableSecondFactor($admin);
        $refreshToken = (string) $this->decode($this->logIn($admin, self::PASSWORD, $this->nextCode($secret)))['refresh_token'];

        $refreshed = $this->refresh($refreshToken);

        self::assertSame(Response::HTTP_OK, $refreshed->getStatusCode());
        self::assertSame(Response::HTTP_OK, $this->refresh((string) $this->decode($refreshed)['refresh_token'])->getStatusCode());
    }

    public function testARefreshTokenStopsWorkingWhenTheSecondFactorIsReset(): void
    {
        $admin = $this->admin();
        $secret = $this->enableSecondFactor($admin);
        $refreshToken = (string) $this->decode($this->logIn($admin, self::PASSWORD, $this->nextCode($secret)))['refresh_token'];

        $this->getService(AdminTwoFactorManager::class)->resetOnBehalfOf($admin, $this->admin());

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->refresh($refreshToken)->getStatusCode());
    }

    public function testTheSettingRefusesRefreshesAndCallsToAnAccountWithoutSecondFactor(): void
    {
        $admin = $this->admin();
        $login = $this->decode($this->logIn($admin, self::PASSWORD));

        ConfigQuery::write(AdminTwoFactorManager::REQUIRED_CONFIG_KEY, '1');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->refresh((string) $login['refresh_token'])->getStatusCode());

        $this->jsonRequest('GET', '/api/admin/customers', token: (string) $login['token']);
        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    private function refresh(string $refreshToken): Response
    {
        $this->client->request(
            'POST',
            '/api/admin/token/refresh',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: json_encode(['refresh_token' => $refreshToken], \JSON_THROW_ON_ERROR),
        );

        return $this->client->getResponse();
    }

    private function admin(): Admin
    {
        return $this->createFixtureFactory()->admin(['password' => self::PASSWORD]);
    }

    private function logIn(Admin $admin, string $password, ?string $code = null): Response
    {
        $payload = ['username' => $admin->getLogin(), 'password' => $password];

        if (null !== $code) {
            $payload['code'] = $code;
        }

        $this->client->request(
            'POST',
            '/api/admin/login',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        return $this->client->getResponse();
    }

    private function enableSecondFactor(Admin $admin): string
    {
        $manager = $this->getService(AdminTwoFactorManager::class);
        $totp = new Totp();
        $secret = $manager->newSecret($admin);
        $manager->confirmEnrolment($admin, $secret, $totp->codeAt($secret, $totp->stepAt(time())));

        return $secret;
    }

    private function nextCode(string $secret): string
    {
        $totp = new Totp();

        return $totp->codeAt($secret, $totp->stepAt(time()) + 1);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        $decoded = json_decode((string) $response->getContent(), true);

        return \is_array($decoded) ? $decoded : [];
    }
}
