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

namespace Thelia\Tests\Http\BackOffice;

use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\EventDispatcher\EventDispatcherInterface as SymfonyEventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Admin\TwoFactor\AdminTwoFactorManager;
use Thelia\Domain\Admin\TwoFactor\Totp;
use Thelia\Domain\Admin\TwoFactor\TwoFactorChallenge;
use Thelia\Model\Admin;
use Thelia\Model\AdminLogQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Test\WebIntegrationTestCase;

final class AdminTwoFactorLoginTest extends WebIntegrationTestCase
{
    private const PASSWORD = 'correct horse battery';

    private ?Session $lastSession = null;

    protected function setUp(): void
    {
        parent::setUp();

        $dispatcher = $this->getService(EventDispatcherInterface::class);

        if ($dispatcher instanceof SymfonyEventDispatcherInterface) {
            $dispatcher->addListener(
                KernelEvents::RESPONSE,
                function (ResponseEvent $event): void {
                    $request = $event->getRequest();

                    if ($event->isMainRequest() && $request->hasSession()) {
                        $session = $request->getSession();
                        $this->lastSession = $session instanceof Session ? $session : null;
                    }
                },
                -1024,
            );
        }
    }

    protected function tearDown(): void
    {
        ConfigQuery::write(AdminTwoFactorManager::REQUIRED_CONFIG_KEY, '0');

        parent::tearDown();
    }

    public function testAnAccountWithoutSecondFactorSignsInAsBefore(): void
    {
        $admin = $this->admin();

        $this->submitPassword($admin);

        self::assertResponseRedirects('/admin/configuration');
        self::assertSame($admin->getId(), $this->lastSession?->getAdminUser()?->getId());
    }

    public function testAProtectedAccountIsNotSignedInByItsPasswordAlone(): void
    {
        $admin = $this->admin();
        $this->enableSecondFactor($admin);

        $this->submitPassword($admin);

        self::assertResponseRedirects('/admin/two-factor');
        self::assertNull($this->lastSession?->getAdminUser());
        self::assertNotNull($this->lastSession?->get(TwoFactorChallenge::SESSION_KEY));

        $this->request('GET', '/admin/configuration');

        self::assertNull($this->lastSession?->getAdminUser());
        self::assertFalse($this->client->getResponse()->isSuccessful());
    }

    public function testTheRightCodeFinishesTheSignInAndReachesTheRequestedPage(): void
    {
        $admin = $this->admin();
        $secret = $this->enableSecondFactor($admin);

        $this->submitPassword($admin);
        $this->submitCode($this->nextCode($secret));

        self::assertResponseRedirects('/admin/configuration');
        self::assertSame($admin->getId(), $this->lastSession?->getAdminUser()?->getId());
        self::assertNull($this->lastSession?->get(TwoFactorChallenge::SESSION_KEY));
    }

    public function testABackupCodeFinishesTheSignIn(): void
    {
        $admin = $this->admin();
        $manager = $this->getService(AdminTwoFactorManager::class);
        $secret = $manager->newSecret($admin);
        $backupCodes = $manager->confirmEnrolment($admin, $secret, $this->currentCode($secret)) ?? [];

        $this->submitPassword($admin);
        $this->submitCode($backupCodes[0]);

        self::assertSame($admin->getId(), $this->lastSession?->getAdminUser()?->getId());
    }

    public function testAWrongCodeShowsTheSameMessageAsAWrongPassword(): void
    {
        $admin = $this->admin();
        $secret = $this->enableSecondFactor($admin);

        $loginPage = $this->request('GET', '/admin/login');
        $wrongPasswordPage = $this->request('POST', '/admin/checklogin', [
            'thelia_admin_login' => [
                'username' => $admin->getLogin(),
                'password' => 'wrong password',
                'success_url' => '/admin/configuration',
                '_token' => (string) $loginPage->filter('input[name="thelia_admin_login[_token]"]')->attr('value'),
            ],
        ]);
        $wrongPasswordMessage = trim($wrongPasswordPage->filter('[data-testid="login-error"]')->text());

        $this->submitPassword($admin);
        $wrongCodePage = $this->submitCode($this->wrongCode($secret));

        self::assertResponseIsSuccessful();
        self::assertNotSame('', $wrongPasswordMessage);
        self::assertSame($wrongPasswordMessage, trim($wrongCodePage->filter('[data-testid="two-factor-error"]')->text()));
        self::assertNull($this->lastSession?->getAdminUser());
    }

    public function testFiveWrongCodesSendTheAdministratorBackToThePassword(): void
    {
        $admin = $this->admin();
        $secret = $this->enableSecondFactor($admin);

        $this->submitPassword($admin);

        for ($attempt = 1; $attempt < TwoFactorChallenge::MAX_ATTEMPTS; ++$attempt) {
            $this->submitCode($this->wrongCode($secret));
            self::assertResponseIsSuccessful();
        }

        $this->submitCode($this->wrongCode($secret));

        self::assertResponseRedirects('/admin/login');
        self::assertNull($this->lastSession?->get(TwoFactorChallenge::SESSION_KEY));

        $this->request('POST', '/admin/two-factor/check', ['thelia_admin_two_factor_code' => ['code' => $this->nextCode($secret)]]);

        self::assertResponseRedirects('/admin/login');
        self::assertNull($this->lastSession?->getAdminUser());
    }

    public function testTheCodePageIsOnlyReachableAfterThePassword(): void
    {
        $this->request('GET', '/admin/two-factor');

        self::assertResponseRedirects('/admin/login');
    }

    public function testTheRememberMeCookieOfAProtectedAccountAsksForTheCodeInsteadOfSigningIn(): void
    {
        $admin = $this->admin();
        $secret = $this->enableSecondFactor($admin);
        $admin->setRememberMeToken('remember-token')->setRememberMeSerial('remember-serial')->save($this->getPropelConnection());
        $this->client->getCookieJar()->set(new Cookie('armcn', base64_encode($admin->getLogin()."\0remember-token\0remember-serial")));

        $this->request('GET', '/admin/configuration');

        self::assertNull($this->lastSession?->getAdminUser());
        self::assertNotNull($this->lastSession?->get(TwoFactorChallenge::SESSION_KEY));

        $this->request('GET', '/admin/login');
        self::assertResponseRedirects('/admin/two-factor');

        $this->submitCode($this->nextCode($secret));

        self::assertSame($admin->getId(), $this->lastSession?->getAdminUser()?->getId());
    }

    public function testTheRememberMeCookieOfAProtectedAccountDoesNothingOnTheStorefront(): void
    {
        $admin = $this->admin();
        $this->enableSecondFactor($admin);
        $admin->setRememberMeToken('remember-token')->setRememberMeSerial('remember-serial')->save($this->getPropelConnection());
        $this->client->getCookieJar()->set(new Cookie('armcn', base64_encode($admin->getLogin()."\0remember-token\0remember-serial")));

        $this->request('GET', '/');

        self::assertNull($this->lastSession?->get(TwoFactorChallenge::SESSION_KEY));
        self::assertNull($this->lastSession?->getAdminUser());
    }

    public function testTheRememberMeCookieOfAnUnprotectedAccountStillSignsIn(): void
    {
        $admin = $this->admin();
        $admin->setRememberMeToken('remember-token')->setRememberMeSerial('remember-serial')->save($this->getPropelConnection());
        $this->client->getCookieJar()->set(new Cookie('armcn', base64_encode($admin->getLogin()."\0remember-token\0remember-serial")));

        $this->request('GET', '/admin/configuration');

        self::assertSame($admin->getId(), $this->lastSession?->getAdminUser()?->getId());
    }

    public function testTheSettingSendsAnAdministratorWithoutSecondFactorToTheActivationUntilItIsDone(): void
    {
        ConfigQuery::write(AdminTwoFactorManager::REQUIRED_CONFIG_KEY, '1');
        $admin = $this->admin();

        $this->submitPassword($admin);
        $this->request('GET', '/admin/configuration');

        self::assertResponseRedirects('/admin/two-factor/setup');

        $crawler = $this->request('GET', '/admin/two-factor/setup');
        self::assertResponseIsSuccessful();
        $secret = (string) $crawler->filter('[data-testid="two-factor-secret"]')->attr('value');

        $crawler = $this->request('POST', '/admin/two-factor/setup/confirm', [
            'thelia_admin_two_factor_code' => [
                'code' => $this->currentCode($secret),
                '_token' => (string) $crawler->filter('input[name="thelia_admin_two_factor_code[_token]"]')->attr('value'),
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertCount(AdminTwoFactorManager::BACKUP_CODE_COUNT, $crawler->filter('[data-testid="two-factor-backup-codes"] li'));

        $this->request('GET', '/admin/configuration');
        self::assertResponseIsSuccessful();
    }

    public function testTheSettingAlsoGuardsAModuleAdminRouteOutsideTheAdminPrefix(): void
    {
        ConfigQuery::write(AdminTwoFactorManager::REQUIRED_CONFIG_KEY, '1');
        $admin = $this->admin();

        $this->submitPassword($admin);
        $this->request('POST', '/open_api/block_group');

        self::assertResponseRedirects('/admin/two-factor/setup');
    }

    public function testWithoutTheSettingNoAdministratorIsSentToTheActivation(): void
    {
        $admin = $this->admin();

        $this->submitPassword($admin);
        $this->request('GET', '/admin/configuration');

        self::assertResponseIsSuccessful();
    }

    public function testCancellingTheCodeStepGivesThePasswordFormBack(): void
    {
        $admin = $this->admin();
        $this->enableSecondFactor($admin);

        $this->submitPassword($admin);
        $this->request('POST', '/admin/two-factor/cancel');

        self::assertResponseRedirects('/admin/login');
        self::assertNull($this->lastSession?->get(TwoFactorChallenge::SESSION_KEY));

        $this->request('GET', '/admin/login');
        self::assertResponseIsSuccessful();
    }

    public function testFiveWrongCodesAfterTheRememberMeCookieRetireTheCookie(): void
    {
        $admin = $this->admin();
        $secret = $this->enableSecondFactor($admin);
        $admin->setRememberMeToken('remember-token')->setRememberMeSerial('remember-serial')->save($this->getPropelConnection());
        $this->client->getCookieJar()->set(new Cookie('armcn', base64_encode($admin->getLogin()."\0remember-token\0remember-serial")));

        $this->request('GET', '/admin/configuration');

        for ($attempt = 1; $attempt <= TwoFactorChallenge::MAX_ATTEMPTS; ++$attempt) {
            $this->submitCode($this->wrongCode($secret));
        }

        self::assertResponseRedirects('/admin/login');
        $admin->reload();
        self::assertNull($admin->getRememberMeToken());

        $this->request('GET', '/admin/configuration');

        self::assertNull($this->lastSession?->get(TwoFactorChallenge::SESSION_KEY));
        self::assertNull($this->lastSession?->getAdminUser());
    }

    public function testACodeSubmittedWithoutTheFormTokenDoesNotSpendAnAttempt(): void
    {
        $admin = $this->admin();
        $this->enableSecondFactor($admin);

        $this->submitPassword($admin);

        for ($attempt = 1; $attempt <= TwoFactorChallenge::MAX_ATTEMPTS; ++$attempt) {
            $this->request('POST', '/admin/two-factor/check', ['thelia_admin_two_factor_code' => ['code' => '123456']]);
        }

        self::assertSame(0, $this->lastSession?->get(TwoFactorChallenge::SESSION_KEY)['attempts'] ?? null);
    }

    public function testTheActivationSecretLivesInTheSessionAndNotInTheDatabase(): void
    {
        $admin = $this->admin();

        $this->submitPassword($admin);
        $firstSecret = (string) $this->request('GET', '/admin/two-factor/setup')->filter('[data-testid="two-factor-secret"]')->attr('value');
        $sameSessionSecret = (string) $this->request('GET', '/admin/two-factor/setup')->filter('[data-testid="two-factor-secret"]')->attr('value');

        self::assertNotSame('', $firstSecret);
        self::assertSame($firstSecret, $sameSessionSecret);
        self::assertNull(\Thelia\Model\AdminTwoFactorQuery::create()->findPk($admin->getId()));
        self::assertSame(
            ['admin_id' => $admin->getId(), 'secret' => $firstSecret],
            $this->lastSession?->get(\Thelia\Controller\Admin\SessionController::TWO_FACTOR_PENDING_SECRET_SESSION_KEY),
        );
    }

    public function testTheAdminLogKeepsNoCookie(): void
    {
        $admin = $this->admin();
        $this->enableSecondFactor($admin);
        $crawler = $this->request('GET', '/admin/login');

        $this->client->request('POST', '/admin/checklogin', [
            'thelia_admin_login' => [
                'username' => $admin->getLogin(),
                'password' => self::PASSWORD,
                'success_url' => '/admin/configuration',
                '_token' => (string) $crawler->filter('input[name="thelia_admin_login[_token]"]')->attr('value'),
            ],
        ], [], ['HTTP_COOKIE' => 'armcn=a-remember-me-cookie-value']);

        $entry = AdminLogQuery::create()->filterByAdminLogin($admin->getLogin())->filterByMessage('Password accepted, second factor required')->findOne();

        self::assertNotNull($entry);
        self::assertStringNotContainsStringIgnoringCase('cookie:', (string) $entry->getRequest());
        self::assertStringNotContainsString('a-remember-me-cookie-value', (string) $entry->getRequest());
    }

    public function testAFailedLoginDoesNotJournalThePasswordThatWasTyped(): void
    {
        $admin = $this->admin();
        $crawler = $this->request('GET', '/admin/login');

        $form = [
            'thelia_admin_login' => [
                'username' => $admin->getLogin(),
                'password' => 'almost-the-right-password',
                'success_url' => '/admin/configuration',
                '_token' => (string) $crawler->filter('input[name="thelia_admin_login[_token]"]')->attr('value'),
            ],
        ];

        $this->client->request('POST', '/admin/checklogin', $form, [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], http_build_query($form));

        $entry = AdminLogQuery::create()
            ->filterByMessage(\sprintf("Authentication failure for username '%s'", $admin->getLogin()))
            ->findOne();

        self::assertNotNull($entry);
        self::assertStringNotContainsString('almost-the-right-password', (string) $entry->getRequest());
    }

    private function request(string $method, string $uri, array $parameters = []): \Symfony\Component\DomCrawler\Crawler
    {
        $requestStack = $this->getService(RequestStack::class);

        while (($request = $requestStack->getCurrentRequest()) instanceof Request && !$request->hasSession()) {
            $requestStack->pop();
        }

        return $this->client->request($method, $uri, $parameters);
    }

    private function admin(): Admin
    {
        return $this->createFixtureFactory()->admin(['password' => self::PASSWORD]);
    }

    private function submitPassword(Admin $admin): void
    {
        $crawler = $this->request('GET', '/admin/login');

        $this->request('POST', '/admin/checklogin', [
            'thelia_admin_login' => [
                'username' => $admin->getLogin(),
                'password' => self::PASSWORD,
                'success_url' => '/admin/configuration',
                '_token' => (string) $crawler->filter('input[name="thelia_admin_login[_token]"]')->attr('value'),
            ],
        ]);
    }

    private function submitCode(string $code): \Symfony\Component\DomCrawler\Crawler
    {
        $crawler = $this->request('GET', '/admin/two-factor');
        $token = $crawler->filter('input[name="thelia_admin_two_factor_code[_token]"]');

        self::assertGreaterThan(0, $token->count(), 'The code form must carry a CSRF token');

        return $this->request('POST', '/admin/two-factor/check', [
            'thelia_admin_two_factor_code' => [
                'code' => $code,
                '_token' => (string) $token->attr('value'),
            ],
        ]);
    }

    private function enableSecondFactor(Admin $admin): string
    {
        $manager = $this->getService(AdminTwoFactorManager::class);
        $secret = $manager->newSecret($admin);
        $manager->confirmEnrolment($admin, $secret, $this->currentCode($secret));

        return $secret;
    }

    private function currentCode(string $secret): string
    {
        $totp = new Totp();

        return $totp->codeAt($secret, $totp->stepAt(time()));
    }

    private function wrongCode(string $secret): string
    {
        $totp = new Totp();
        $step = $totp->stepAt(time());
        $validCodes = [$totp->codeAt($secret, $step - 1), $totp->codeAt($secret, $step), $totp->codeAt($secret, $step + 1)];

        foreach (['000000', '111111', '222222', '333333'] as $candidate) {
            if (!\in_array($candidate, $validCodes, true)) {
                return $candidate;
            }
        }

        throw new \LogicException('No wrong code found.');
    }

    private function nextCode(string $secret): string
    {
        $totp = new Totp();

        return $totp->codeAt($secret, $totp->stepAt(time()) + 1);
    }
}
