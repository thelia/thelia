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

namespace Thelia\Tests\Integration\Domain\Admin\TwoFactor;

use Symfony\Component\Console\Tester\CommandTester;
use Thelia\Command\AdminTwoFactorResetCommand;
use Thelia\Domain\Admin\TwoFactor\AdminTwoFactorManager;
use Thelia\Domain\Admin\TwoFactor\Totp;
use Thelia\Domain\Admin\TwoFactor\TwoFactorVerification;
use Thelia\Model\Admin;
use Thelia\Model\AdminLogQuery;
use Thelia\Model\AdminTwoFactorBackupCodeQuery;
use Thelia\Model\AdminTwoFactorQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Test\IntegrationTestCase;

final class AdminTwoFactorManagerTest extends IntegrationTestCase
{
    public function testAnAccountIsNotProtectedUntilAFirstCodeProvesTheSecret(): void
    {
        $admin = $this->createFixtureFactory()->admin();
        $manager = $this->manager();

        $secret = $manager->newSecret($admin);

        self::assertFalse($manager->isEnabledFor($admin));
        self::assertNull(AdminTwoFactorQuery::create()->findPk($admin->getId()));
        self::assertNull($manager->confirmEnrolment($admin, $secret, $this->wrongCode($secret)));
        self::assertFalse($manager->isEnabledFor($admin));

        $backupCodes = $manager->confirmEnrolment($admin, $secret, $this->currentCode($secret));

        self::assertTrue($manager->isEnabledFor($admin));
        self::assertCount(AdminTwoFactorManager::BACKUP_CODE_COUNT, $backupCodes);
        self::assertCount(AdminTwoFactorManager::BACKUP_CODE_COUNT, array_unique($backupCodes));
        foreach ($backupCodes as $backupCode) {
            self::assertSame(AdminTwoFactorManager::BACKUP_CODE_LENGTH, \strlen($backupCode));
        }
    }

    public function testTheCodeThatEnabledTheAccountCannotBeReplayedToSignIn(): void
    {
        $admin = $this->createFixtureFactory()->admin();
        $manager = $this->manager();
        $secret = $manager->newSecret($admin);
        $code = $this->currentCode($secret);
        $manager->confirmEnrolment($admin, $secret, $code);

        self::assertSame(TwoFactorVerification::Refused, $manager->verify($admin, $code));
    }

    public function testATotpCodeSignsInOnceAndIsThenRefused(): void
    {
        $admin = $this->createFixtureFactory()->admin();
        $manager = $this->manager();
        $secret = $this->enable($admin);

        $nextCode = (new Totp())->codeAt($secret, (new Totp())->stepAt(time()) + 1);

        self::assertSame(TwoFactorVerification::Totp, $manager->verify($admin, $nextCode));
        self::assertSame(TwoFactorVerification::Refused, $manager->verify($admin, $nextCode));
    }

    public function testABackupCodeWorksOnceOnly(): void
    {
        $admin = $this->createFixtureFactory()->admin();
        $backupCodes = $this->enableAndReturnBackupCodes($admin);
        $manager = $this->manager();

        self::assertSame(TwoFactorVerification::BackupCode, $manager->verify($admin, strtoupper($backupCodes[3])));
        self::assertSame(TwoFactorVerification::Refused, $manager->verify($admin, $backupCodes[3]));
        self::assertSame(AdminTwoFactorManager::BACKUP_CODE_COUNT - 1, $manager->remainingBackupCodeCount($admin));
    }

    public function testRegeneratingTheBackupCodesInvalidatesThePreviousOnes(): void
    {
        $admin = $this->createFixtureFactory()->admin();
        $previousCodes = $this->enableAndReturnBackupCodes($admin);
        $manager = $this->manager();

        $newCodes = $manager->regenerateBackupCodes($admin);

        self::assertSame(TwoFactorVerification::Refused, $manager->verify($admin, $previousCodes[0]));
        self::assertSame(TwoFactorVerification::BackupCode, $manager->verify($admin, $newCodes[0]));
    }

    public function testBackupCodesAreStoredAsPasswordHashesOnly(): void
    {
        $admin = $this->createFixtureFactory()->admin();
        $backupCodes = $this->enableAndReturnBackupCodes($admin);

        $storedHashes = AdminTwoFactorBackupCodeQuery::create()->filterByAdminId($admin->getId())->find()->getColumnValues('codeHash');

        self::assertCount(AdminTwoFactorManager::BACKUP_CODE_COUNT, $storedHashes);
        foreach ($storedHashes as $storedHash) {
            self::assertNotContains($storedHash, $backupCodes);
            self::assertSame('2y', password_get_info($storedHash)['algo']);
        }
    }

    public function testAWrongCodeIsRefusedAndJournalledWithoutTheCode(): void
    {
        $admin = $this->createFixtureFactory()->admin();
        $secret = $this->enable($admin);
        $wrongCode = $this->wrongCode($secret);

        self::assertSame(TwoFactorVerification::Refused, $this->manager()->verify($admin, $wrongCode));

        $entries = AdminLogQuery::create()->filterByAdminLogin($admin->getLogin())->find();
        self::assertContains('Second factor verification failed', $entries->getColumnValues('message'));
        foreach ($entries as $entry) {
            self::assertStringNotContainsString($wrongCode, $entry->getMessage().$entry->getRequest());
        }
    }

    public function testTenFailuresLockTheAccountEvenAcrossFreshAttempts(): void
    {
        $admin = $this->createFixtureFactory()->admin();
        $secret = $this->enable($admin);
        $manager = $this->manager();

        for ($failure = 1; $failure <= 10; ++$failure) {
            self::assertSame(TwoFactorVerification::Refused, $manager->verify($admin, $this->wrongCode($secret)));
        }

        self::assertSame(TwoFactorVerification::Refused, $manager->verify($admin, $this->nextCode($secret)));
        self::assertSame(1, AdminLogQuery::create()->filterByAdminLogin($admin->getLogin())->filterByMessage('Second factor verification refused: too many failures on this account')->count());
    }

    public function testTheEnrolmentMarkChangesWhenTheSecondFactorIsReplaced(): void
    {
        $admin = $this->createFixtureFactory()->admin();
        $manager = $this->manager();

        self::assertNull($manager->enrolmentMarkOf($admin));

        $this->enable($admin);
        $firstMark = $manager->enrolmentMarkOf($admin);
        self::assertNotNull($firstMark);

        $manager->disable($admin);
        self::assertNull($manager->enrolmentMarkOf($admin));
    }

    public function testTheCommandLineResetRemovesTheSecondFactorAndIsJournalled(): void
    {
        $admin = $this->createFixtureFactory()->admin();
        $this->enable($admin);

        $tester = new CommandTester($this->getService(AdminTwoFactorResetCommand::class));
        $tester->execute(['login' => $admin->getLogin()]);

        $tester->assertCommandIsSuccessful();
        self::assertFalse($this->manager()->isEnabledFor($admin));
        self::assertSame(1, AdminLogQuery::create()->filterByAdminLogin($admin->getLogin())->filterByMessage('Second factor reset from the command line')->count());
    }

    public function testDisablingRemovesTheSecretAndTheBackupCodes(): void
    {
        $admin = $this->createFixtureFactory()->admin();
        $this->enable($admin);
        $manager = $this->manager();

        $manager->disable($admin);

        self::assertFalse($manager->isEnabledFor($admin));
        self::assertNull(AdminTwoFactorQuery::create()->findPk($admin->getId()));
        self::assertSame(0, AdminTwoFactorBackupCodeQuery::create()->filterByAdminId($admin->getId())->count());
    }

    public function testAPeerResetIsJournalledWithBothAccountsAndCannotTargetOneself(): void
    {
        $factory = $this->createFixtureFactory();
        $locked = $factory->admin();
        $helper = $factory->admin();
        $this->enable($locked);
        $manager = $this->manager();

        $manager->resetOnBehalfOf($locked, $helper);

        self::assertFalse($manager->isEnabledFor($locked));
        $message = \sprintf("Second factor of administrator '%s' reset by administrator '%s'", $locked->getLogin(), $helper->getLogin());
        self::assertSame(1, AdminLogQuery::create()->filterByAdminLogin($helper->getLogin())->filterByMessage($message)->count());

        $this->expectException(\LogicException::class);
        $manager->resetOnBehalfOf($helper, $helper);
    }

    public function testDeletingTheAccountTakesItsSecondFactorAlong(): void
    {
        $admin = $this->createFixtureFactory()->admin();
        $this->enable($admin);
        $adminId = $admin->getId();

        $admin->delete($this->getPropelConnection());

        self::assertNull(AdminTwoFactorQuery::create()->findPk($adminId));
        self::assertSame(0, AdminTwoFactorBackupCodeQuery::create()->filterByAdminId($adminId)->count());
    }

    public function testEnablingTheSecondFactorRetiresTheRememberMeToken(): void
    {
        $admin = $this->createFixtureFactory()->admin();
        $admin->setRememberMeToken('a-token')->save($this->getPropelConnection());

        $this->enable($admin);

        $admin->reload();

        self::assertNull($admin->getRememberMeToken());
    }

    public function testTheSettingMakesEnrolmentMandatoryOnlyWhenTurnedOn(): void
    {
        $admin = $this->createFixtureFactory()->admin();
        $manager = $this->manager();

        ConfigQuery::write(AdminTwoFactorManager::REQUIRED_CONFIG_KEY, '0');
        self::assertFalse($manager->mustEnrol($admin));

        ConfigQuery::write(AdminTwoFactorManager::REQUIRED_CONFIG_KEY, '1');
        try {
            self::assertTrue($manager->mustEnrol($admin));
            $this->enable($admin);
            self::assertFalse($manager->mustEnrol($admin));
        } finally {
            ConfigQuery::write(AdminTwoFactorManager::REQUIRED_CONFIG_KEY, '0');
        }
    }

    private function manager(): AdminTwoFactorManager
    {
        return $this->getService(AdminTwoFactorManager::class);
    }

    private function currentCode(string $secret): string
    {
        $totp = new Totp();

        return $totp->codeAt($secret, $totp->stepAt(time()));
    }

    private function enable(Admin $admin): string
    {
        $secret = $this->manager()->newSecret($admin);
        $this->manager()->confirmEnrolment($admin, $secret, $this->currentCode($secret));

        return $secret;
    }

    private function nextCode(string $secret): string
    {
        $totp = new Totp();

        return $totp->codeAt($secret, $totp->stepAt(time()) + 1);
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

    /**
     * @return list<string>
     */
    private function enableAndReturnBackupCodes(Admin $admin): array
    {
        $secret = $this->manager()->newSecret($admin);

        return $this->manager()->confirmEnrolment($admin, $secret, $this->currentCode($secret)) ?? [];
    }
}
