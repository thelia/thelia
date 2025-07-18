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

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface as SecurityUserInterface;
use Thelia\Core\Security\User\UserInterface;
use Thelia\Core\Security\User\UserPermissionsTrait;
use Thelia\Model\Base\Admin as BaseAdmin;

/**
 * Skeleton subclass for representing a row from the 'admin' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class Admin extends BaseAdmin implements UserInterface, SecurityUserInterface, PasswordAuthenticatedUserInterface
{
    use UserPermissionsTrait;

    public function preInsert(?ConnectionInterface $con = null): bool
    {
        parent::preInsert($con);

        // Set the serial number (for auto-login)
        $this->setRememberMeSerial(uniqid('', true));

        return true;
    }

    public function setPassword($password)
    {
        if ($this->isNew() && (null === $password || '' === trim($password))) {
            throw new \InvalidArgumentException('customer password is mandatory on creation');
        }

        if (null !== $password && '' !== trim($password)) {
            $this->setAlgo('PASSWORD_BCRYPT');

            return parent::setPassword(password_hash($password, \PASSWORD_BCRYPT));
        }

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password ?? '';
    }

    public function checkPassword($password): bool
    {
        return password_verify((string) $password, $this->getPassword());
    }

    public function getUsername(): string
    {
        return $this->getLogin();
    }

    public function eraseCredentials(): void
    {
        parent::setPassword(null);
        $this->resetModified();
    }

    public function getRoles(): array
    {
        return ['ADMIN', 'ROLE_ADMIN'];
    }

    public function getToken(): string
    {
        return $this->getRememberMeToken();
    }

    public function setToken($token): void
    {
        $this->setRememberMeToken($token)->save();
    }

    public function getSerial(): string
    {
        return $this->getRememberMeSerial();
    }

    public function setSerial($serial): void
    {
        $this->setRememberMeSerial($serial)->save();
    }

    public function getUserIdentifier(): string
    {
        return $this->getUsername();
    }

    public function getId(): int
    {
        return parent::getId();
    }

    public function getLocale(): string
    {
        return parent::getLocale();
    }
}
