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

namespace Thelia\Tests\Integration\Action;

use Thelia\Core\Event\Administrator\AdministratorEvent;
use Thelia\Core\Event\Administrator\AdministratorUpdatePasswordEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\AdminQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class AdministratorActionTest extends ActionIntegrationTestCase
{
    public function testCreatePersistsAdminWithHashedPassword(): void
    {
        $profile = $this->factory->profile(['code' => 'test-admin-profile-1']);

        $event = new AdministratorEvent();
        $event
            ->setFirstname('Admin')
            ->setLastname('Test')
            ->setLogin('admin-action-test')
            ->setPassword('s3cret!pass')
            ->setEmail('admin-action-test@example.com')
            ->setProfile($profile->getId())
            ->setLocale('en_US');

        $this->dispatch($event, TheliaEvents::ADMINISTRATOR_CREATE);

        $admin = $event->getAdministrator();
        self::assertNotNull($admin);
        self::assertSame('admin-action-test', $admin->getLogin());
        self::assertSame('PASSWORD_BCRYPT', $admin->getAlgo());
        self::assertTrue(password_verify('s3cret!pass', $admin->getPassword()));
    }

    public function testUpdateChangesProfileAndName(): void
    {
        $original = $this->factory->profile(['code' => 'test-admin-profile-2a']);
        $updated = $this->factory->profile(['code' => 'test-admin-profile-2b']);
        $admin = $this->factory->admin(['login' => 'admin-update-test']);

        $event = new AdministratorEvent($admin);
        $event
            ->setId($admin->getId())
            ->setFirstname('Updated')
            ->setLastname('Name')
            ->setLogin('admin-update-test')
            ->setEmail($admin->getEmail())
            ->setProfile($updated->getId())
            ->setLocale('en_US')
            ->setPassword('');

        $this->dispatch($event, TheliaEvents::ADMINISTRATOR_UPDATE);

        $reloaded = AdminQuery::create()->findPk($admin->getId());
        self::assertSame('Updated', $reloaded->getFirstname());
        self::assertSame($updated->getId(), $reloaded->getProfileId());
    }

    public function testUpdatePasswordReplacesHashAndClearsRenewToken(): void
    {
        $admin = $this->factory->admin(['login' => 'admin-pass-test']);
        $admin->setPasswordRenewToken('pending-token')->save();
        $previousHash = $admin->getPassword();

        $event = new AdministratorUpdatePasswordEvent($admin);
        $event->setPassword('newPassword!');
        $this->dispatch($event, TheliaEvents::ADMINISTRATOR_UPDATEPASSWORD);

        $reloaded = AdminQuery::create()->findPk($admin->getId());
        self::assertNotSame($previousHash, $reloaded->getPassword());
        self::assertTrue(password_verify('newPassword!', $reloaded->getPassword()));
        self::assertNull($reloaded->getPasswordRenewToken());
    }

    public function testDeleteRemovesAdmin(): void
    {
        $admin = $this->factory->admin(['login' => 'admin-delete-test']);
        $adminId = $admin->getId();

        $event = new AdministratorEvent($admin);
        $event->setId($adminId);

        $this->dispatch($event, TheliaEvents::ADMINISTRATOR_DELETE);

        self::assertNull(AdminQuery::create()->findPk($adminId));
    }
}
