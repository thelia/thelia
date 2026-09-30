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

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Domain\Admin\TwoFactor\AdminTwoFactorManager;
use Thelia\Domain\Admin\TwoFactor\Totp;
use Thelia\Domain\Admin\TwoFactor\TwoFactorVerification;
use Thelia\Model\Admin;
use Thelia\Model\AdminLogQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Test\WebIntegrationTestCase;

final class AdminTwoFactorScreensTest extends WebIntegrationTestCase
{
    private const PASSWORD = 'correct horse battery';

    protected function setUp(): void
    {
        parent::setUp();

        $adminTemplate = $this->getService(TemplateHelperInterface::class)->getActiveAdminTemplate();

        if (!file_exists($adminTemplate->getAbsolutePath().\DIRECTORY_SEPARATOR.'configuration/account/two-factor.html.twig')) {
            self::markTestSkipped('The installed back-office theme has no account security page.');
        }
    }

    protected function tearDown(): void
    {
        ConfigQuery::write(AdminTwoFactorManager::REQUIRED_CONFIG_KEY, '0');

        parent::tearDown();
    }

    public function testTheCodePageOffersABackupCodeAndIsNeverCached(): void
    {
        $admin = $this->admin();
        $this->enableSecondFactor($admin);
        $this->signInWithPassword($admin);

        $page = $this->request('GET', '/admin/two-factor');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertSame('one-time-code', $page->filter('[data-testid="two-factor-code"]')->attr('autocomplete'));
        self::assertSame('', (string) $page->filter('[data-testid="two-factor-code"]')->attr('value'));
        self::assertCount(1, $page->filter('[data-testid="two-factor-toggle"]'));
        self::assertCount(1, $page->filter('form[action="/admin/two-factor/cancel"]'));
    }

    public function testTheActivationShowsAQrCodeThenBackupCodesThatAReloadDoesNotShowAgain(): void
    {
        $admin = $this->admin();
        $this->signInWithPassword($admin);

        $setup = $this->request('GET', '/admin/account/two-factor');
        self::assertSame('/admin/two-factor/setup', $setup->filter('[data-testid="account-two-factor-enable"]')->attr('href'));

        $setup = $this->request('GET', '/admin/two-factor/setup');
        $secret = (string) $setup->filter('[data-testid="two-factor-secret"]')->attr('value');

        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertStringStartsWith('otpauth://totp/', (string) $setup->filter('[data-testid="two-factor-qr"]')->attr('data-bo-two-factor-qr-uri-value'));
        self::assertStringContainsString('secret='.$secret, (string) $setup->filter('[data-testid="two-factor-qr"]')->attr('data-bo-two-factor-qr-uri-value'));

        $confirmation = [
            'thelia_admin_two_factor_code' => [
                'code' => $this->currentCode($secret),
                '_token' => (string) $setup->filter('input[name="thelia_admin_two_factor_code[_token]"]')->attr('value'),
            ],
        ];
        $codes = $this->request('POST', '/admin/two-factor/setup/confirm', $confirmation);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertCount(AdminTwoFactorManager::BACKUP_CODE_COUNT, $codes->filter('[data-testid="two-factor-backup-codes"] li'));
        self::assertCount(1, $codes->filter('[data-testid="two-factor-backup-codes-warning"]'));
        self::assertCount(1, $codes->filter('[data-testid="two-factor-backup-codes-copy"]'));

        $this->request('POST', '/admin/two-factor/setup/confirm', $confirmation);
        self::assertResponseRedirects('/admin');

        $this->request('GET', '/admin/two-factor/setup');
        self::assertResponseRedirects('/admin');

        $status = $this->request('GET', '/admin/account/two-factor');
        self::assertSame('Turned on', trim($status->filter('[data-testid="account-two-factor-status"]')->text()));
        self::assertSame(1, AdminLogQuery::create()->filterByAdminLogin($admin->getLogin())->filterByMessage('Second factor enabled')->count());
    }

    public function testRegeneratingTheBackupCodesShowsNewOnesAndRetiresTheOldOnes(): void
    {
        $admin = $this->admin();
        $previousCodes = $this->enableSecondFactor($admin);
        $this->signInFully($admin);

        $account = $this->request('GET', '/admin/account/two-factor');
        $codes = $this->request('POST', '/admin/account/two-factor/backup-codes', [
            '_token' => $this->tokenOf($account, 'account-two-factor-regenerate-form'),
        ]);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        $newCodes = $codes->filter('[data-testid="two-factor-backup-codes"] li')->each(static fn (Crawler $item): string => trim($item->text()));
        self::assertCount(AdminTwoFactorManager::BACKUP_CODE_COUNT, $newCodes);

        $manager = $this->getService(AdminTwoFactorManager::class);
        self::assertSame(TwoFactorVerification::Refused, $manager->verify($admin, $previousCodes[0]));
        self::assertSame(TwoFactorVerification::BackupCode, $manager->verify($admin, $newCodes[0]));
    }

    public function testRegeneratingWithoutTheTokenChangesNothing(): void
    {
        $admin = $this->admin();
        $previousCodes = $this->enableSecondFactor($admin);
        $this->signInFully($admin);

        $this->request('POST', '/admin/account/two-factor/backup-codes', ['_token' => 'forged']);

        self::assertResponseRedirects('/admin/account/two-factor');
        self::assertSame(TwoFactorVerification::BackupCode, $this->getService(AdminTwoFactorManager::class)->verify($admin, $previousCodes[0]));
    }

    public function testDisablingNeedsThePasswordOfTheAccount(): void
    {
        $admin = $this->admin();
        $this->enableSecondFactor($admin);
        $this->signInFully($admin);
        $manager = $this->getService(AdminTwoFactorManager::class);

        $account = $this->request('GET', '/admin/account/two-factor');
        $this->request('POST', '/admin/account/two-factor/disable', [
            '_token' => $this->tokenOf($account, 'account-two-factor-disable-form'),
            'password' => 'not the password',
        ]);

        self::assertResponseRedirects('/admin/account/two-factor');
        self::assertTrue($manager->isEnabledFor($admin));

        $account = $this->request('GET', '/admin/account/two-factor');
        $this->request('POST', '/admin/account/two-factor/disable', [
            '_token' => $this->tokenOf($account, 'account-two-factor-disable-form'),
            'password' => self::PASSWORD,
        ]);

        self::assertResponseRedirects('/admin/account/two-factor');
        self::assertFalse($manager->isEnabledFor($admin));
        self::assertSame(1, AdminLogQuery::create()->filterByAdminLogin($admin->getLogin())->filterByMessage('Second factor disabled')->count());
    }

    public function testAnAdministratorAllowedToUpdateAdministratorsResetsTheSecondFactorOfAnother(): void
    {
        $locked = $this->createFixtureFactory()->restrictedAdmin(
            [AdminResources::PRODUCT => [AccessManager::VIEW]],
            ['password' => self::PASSWORD],
        );
        $this->enableSecondFactor($locked);
        $helper = $this->createFixtureFactory()->restrictedAdmin(
            [AdminResources::ADMINISTRATOR => [AccessManager::VIEW, AccessManager::UPDATE]],
            ['password' => self::PASSWORD],
        );
        $this->signInWithPassword($helper);

        $list = $this->request('GET', '/admin/configuration/administrators');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $list->filter('[data-administrator-id="'.$locked->getId().'"][data-bs-target="#administrator-two-factor-reset-modal"]'));
        self::assertCount(0, $list->filter('[data-administrator-id="'.$helper->getId().'"][data-bs-target="#administrator-two-factor-reset-modal"]'));

        $this->request('POST', (string) $list->filter('[data-testid="administrator-two-factor-reset-form"]')->attr('action'), [
            'administrator_id' => $locked->getId(),
            '_token' => $this->tokenOf($list, 'administrator-two-factor-reset-form'),
        ]);

        self::assertResponseRedirects('/admin/configuration/administrators');
        self::assertFalse($this->getService(AdminTwoFactorManager::class)->isEnabledFor($locked));
        self::assertSame(1, AdminLogQuery::create()
            ->filterByAdminLogin($helper->getLogin())
            ->filterByResourceId($locked->getId())
            ->filterByMessage(\sprintf("Second factor of administrator '%s' reset by administrator '%s'", $locked->getLogin(), $helper->getLogin()))
            ->count());

        $afterReset = $this->request('GET', '/admin/configuration/administrators');
        self::assertStringContainsString($locked->getLogin(), $afterReset->filter('.alert-success')->text(''));
    }

    public function testARestrictedAdministratorCannotResetTheSecondFactorOfASuperadministrator(): void
    {
        $superadministrator = $this->admin();
        self::assertNull($superadministrator->getProfileId());
        $this->enableSecondFactor($superadministrator);
        $restricted = $this->createFixtureFactory()->restrictedAdmin(
            [AdminResources::ADMINISTRATOR => [AccessManager::VIEW, AccessManager::UPDATE]],
            ['password' => self::PASSWORD],
        );
        $this->signInWithPassword($restricted);

        $list = $this->request('GET', '/admin/configuration/administrators');
        self::assertCount(0, $list->filter('[data-administrator-id="'.$superadministrator->getId().'"][data-bs-target="#administrator-two-factor-reset-modal"]'));

        $this->request('POST', (string) $list->filter('[data-testid="administrator-two-factor-reset-form"]')->attr('action'), [
            'administrator_id' => $superadministrator->getId(),
            '_token' => $this->tokenOf($list, 'administrator-two-factor-reset-form'),
        ]);

        self::assertResponseRedirects('/admin/configuration/administrators');
        self::assertTrue($this->getService(AdminTwoFactorManager::class)->isEnabledFor($superadministrator));

        $afterRefusal = $this->request('GET', '/admin/configuration/administrators');
        self::assertStringContainsString('Only a superadministrator can edit a superadministrator account.', $afterRefusal->filter('.alert-danger')->text(''));
    }

    public function testDisablingTheSecondFactorOnAShopThatRequiresItAsksToEnableItAgainRightAway(): void
    {
        $admin = $this->admin();
        $this->enableSecondFactor($admin);
        ConfigQuery::write(AdminTwoFactorManager::REQUIRED_CONFIG_KEY, '1');
        $this->signInFully($admin);

        $account = $this->request('GET', '/admin/account/two-factor');
        self::assertResponseIsSuccessful();
        $this->request('POST', '/admin/account/two-factor/disable', [
            '_token' => $this->tokenOf($account, 'account-two-factor-disable-form'),
            'password' => self::PASSWORD,
        ]);
        self::assertFalse($this->getService(AdminTwoFactorManager::class)->isEnabledFor($admin));

        $this->request('GET', '/admin/account/two-factor');
        self::assertResponseRedirects('/admin/two-factor/setup');
    }

    public function testAnAdministratorWhoMayNotViewAdministratorsStillReachesTheirAccountSecurity(): void
    {
        $admin = $this->createFixtureFactory()->restrictedAdmin(
            [AdminResources::PRODUCT => [AccessManager::VIEW]],
            ['password' => self::PASSWORD],
        );
        $this->signInWithPassword($admin);

        $this->request('GET', '/admin/configuration/administrators');
        self::assertResponseStatusCodeSame(403);

        $account = $this->request('GET', '/admin/account/two-factor');
        self::assertResponseIsSuccessful();
        self::assertSame('/admin/two-factor/setup', $account->filter('[data-testid="account-two-factor-enable"]')->attr('href'));
    }

    public function testAnAdministratorWhoseSecondFactorAPeerResetsOnAShopThatRequiresItIsSentToTheActivation(): void
    {
        $admin = $this->admin();
        $this->enableSecondFactor($admin);
        ConfigQuery::write(AdminTwoFactorManager::REQUIRED_CONFIG_KEY, '1');
        $this->signInFully($admin);

        $this->request('GET', '/admin/account/two-factor');
        self::assertResponseIsSuccessful();

        $this->getService(AdminTwoFactorManager::class)->resetOnBehalfOf($admin, $this->admin());

        $this->request('GET', '/admin/account/two-factor');
        self::assertResponseRedirects('/admin/two-factor/setup');
    }

    public function testATokenInTheUrlDoesNotDisableTheSecondFactor(): void
    {
        $admin = $this->admin();
        $this->enableSecondFactor($admin);
        $this->signInFully($admin);

        $account = $this->request('GET', '/admin/account/two-factor');
        $this->request('POST', '/admin/account/two-factor/disable?_token='.$this->tokenOf($account, 'account-two-factor-disable-form'), [
            'password' => self::PASSWORD,
        ]);

        self::assertTrue($this->getService(AdminTwoFactorManager::class)->isEnabledFor($admin));
    }

    public function testATokenInTheUrlDoesNotResetTheSecondFactorOfAnother(): void
    {
        $locked = $this->createFixtureFactory()->restrictedAdmin(
            [AdminResources::PRODUCT => [AccessManager::VIEW]],
            ['password' => self::PASSWORD],
        );
        $this->enableSecondFactor($locked);
        $this->signInWithPassword($this->admin());

        $list = $this->request('GET', '/admin/configuration/administrators');
        $this->request('POST', '/admin/configuration/administrators/two-factor-reset?_token='.$this->tokenOf($list, 'administrator-two-factor-reset-form'), [
            'administrator_id' => $locked->getId(),
        ]);

        self::assertTrue($this->getService(AdminTwoFactorManager::class)->isEnabledFor($locked));
    }

    public function testTheOwnRowOfTheListLeadsToTheAccountSecurityPage(): void
    {
        $admin = $this->admin();
        $other = $this->admin();
        $this->signInWithPassword($admin);

        $list = $this->request('GET', '/admin/configuration/administrators');

        self::assertCount(1, $list->filter('a[href="/admin/account/two-factor"]')->reduce(
            static fn (Crawler $link): bool => str_contains($link->ancestors()->filter('tr')->first()->text(''), $admin->getLogin())
                && !str_contains($link->ancestors()->filter('tr')->first()->text(''), $other->getLogin()),
        ));
    }

    public function testResettingASecondFactorWithoutTheTokenChangesNothing(): void
    {
        $locked = $this->admin();
        $this->enableSecondFactor($locked);
        $this->signInWithPassword($this->admin());

        $this->request('POST', '/admin/configuration/administrators/two-factor-reset', [
            'administrator_id' => $locked->getId(),
            '_token' => 'forged',
        ]);

        self::assertResponseRedirects('/admin/configuration/administrators');
        self::assertTrue($this->getService(AdminTwoFactorManager::class)->isEnabledFor($locked));
    }

    public function testAnAdministratorWhoMayOnlyViewAdministratorsCannotResetASecondFactor(): void
    {
        $locked = $this->admin();
        $this->enableSecondFactor($locked);
        $viewer = $this->createFixtureFactory()->restrictedAdmin(
            [AdminResources::ADMINISTRATOR => [AccessManager::VIEW]],
            ['password' => self::PASSWORD],
        );
        $this->signInWithPassword($viewer);

        $list = $this->request('GET', '/admin/configuration/administrators');
        self::assertCount(0, $list->filter('[data-bs-target="#administrator-two-factor-reset-modal"]'));

        $this->request('POST', '/admin/configuration/administrators/two-factor-reset', ['administrator_id' => $locked->getId()]);

        self::assertResponseStatusCodeSame(403);
        self::assertTrue($this->getService(AdminTwoFactorManager::class)->isEnabledFor($locked));
    }

    public function testTheStoreSettingTurnsTheObligationOn(): void
    {
        $this->signInWithPassword($this->admin());

        $page = $this->request('GET', '/admin/configuration/store');
        $form = $page->filter('[data-testid="config-store-admin-two-factor-required"]')->closest('form')?->form();
        self::assertNotNull($form);
        $form['thelia_configuration_store[admin_two_factor_required]']->tick();
        $form->setValues([
            'thelia_configuration_store[store_name]' => 'Test shop',
            'thelia_configuration_store[store_email]' => 'shop@example.com',
            'thelia_configuration_store[store_notification_emails]' => 'shop@example.com',
            'thelia_configuration_store[store_address1]' => '1 Main Street',
            'thelia_configuration_store[store_zipcode]' => '75001',
            'thelia_configuration_store[store_city]' => 'Paris',
        ]);

        $this->client->submit($form);

        self::assertSame('1', ConfigQuery::read(AdminTwoFactorManager::REQUIRED_CONFIG_KEY, '0', true));
    }

    private function request(string $method, string $uri, array $parameters = []): Crawler
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

    private function signInWithPassword(Admin $admin): void
    {
        $login = $this->request('GET', '/admin/login');

        $this->request('POST', '/admin/checklogin', [
            'thelia_admin_login' => [
                'username' => $admin->getLogin(),
                'password' => self::PASSWORD,
                'success_url' => '/admin',
                '_token' => (string) $login->filter('input[name="thelia_admin_login[_token]"]')->attr('value'),
            ],
        ]);
    }

    private function signInFully(Admin $admin): void
    {
        $this->signInWithPassword($admin);

        $codePage = $this->request('GET', '/admin/two-factor');
        $secret = (string) \Thelia\Model\AdminTwoFactorQuery::create()->findPk($admin->getId())?->getSecret();
        $totp = new Totp();

        $this->request('POST', '/admin/two-factor/check', [
            'thelia_admin_two_factor_code' => [
                'code' => $totp->codeAt($secret, $totp->stepAt(time()) + 1),
                '_token' => (string) $codePage->filter('input[name="thelia_admin_two_factor_code[_token]"]')->attr('value'),
            ],
        ]);

        self::assertResponseRedirects('/admin');
    }

    /**
     * @return list<string>
     */
    private function enableSecondFactor(Admin $admin): array
    {
        $manager = $this->getService(AdminTwoFactorManager::class);
        $secret = $manager->newSecret($admin);

        return $manager->confirmEnrolment($admin, $secret, $this->currentCode($secret)) ?? [];
    }

    private function tokenOf(Crawler $page, string $formTestId): string
    {
        return (string) $page->filter('[data-testid="'.$formTestId.'"] input[name="_token"]')->attr('value');
    }

    private function currentCode(string $secret): string
    {
        $totp = new Totp();

        return $totp->codeAt($secret, $totp->stepAt(time()));
    }
}
