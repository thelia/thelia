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

namespace Thelia\Domain\Admin\TwoFactor;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Model\Admin;
use Thelia\Model\AdminLog;
use Thelia\Model\AdminTwoFactor;
use Thelia\Model\AdminTwoFactorBackupCode;
use Thelia\Model\AdminTwoFactorBackupCodeQuery;
use Thelia\Model\AdminTwoFactorQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\AdminTwoFactorBackupCodeTableMap;
use Thelia\Model\Map\AdminTwoFactorTableMap;

final readonly class AdminTwoFactorManager
{
    public const REQUIRED_CONFIG_KEY = 'admin_two_factor_required';
    public const BACKUP_CODE_COUNT = 10;
    public const BACKUP_CODE_LENGTH = 10;
    private const BACKUP_CODE_ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';
    private const LOG_RESOURCE = 'admin';
    private const LOG_ACTION = 'TWO_FACTOR';

    public function __construct(
        private Totp $totp,
        private RequestStack $requestStack,
        #[Autowire(service: 'limiter.admin_two_factor_failures_per_account')]
        private RateLimiterFactoryInterface $failureLimiter,
    ) {
    }

    public function isRequired(): bool
    {
        return '1' === (string) ConfigQuery::read(self::REQUIRED_CONFIG_KEY, '0');
    }

    public function isEnabledFor(Admin $admin): bool
    {
        return $this->enabledTwoFactorOf($admin) instanceof AdminTwoFactor;
    }

    public function mustEnrol(Admin $admin): bool
    {
        return $this->isRequired() && !$this->isEnabledFor($admin);
    }

    public function enrolmentMarkOf(Admin $admin): ?string
    {
        return $this->enabledTwoFactorOf($admin)?->getEnabledAt()?->format('U');
    }

    public function newSecret(Admin $admin): string
    {
        if ($this->isEnabledFor($admin)) {
            throw new \LogicException('The second factor of this administrator is already enabled.');
        }

        return $this->totp->generateSecret();
    }

    public function provisioningUriFor(Admin $admin, #[\SensitiveParameter] string $secret): string
    {
        $storeName = trim((string) ConfigQuery::getStoreName());

        return $this->totp->provisioningUri($secret, (string) $admin->getLogin(), '' !== $storeName ? $storeName : 'Thelia');
    }

    /**
     * @return list<string>|null the backup codes, shown once, or null when the code does not prove the secret
     */
    public function confirmEnrolment(Admin $admin, #[\SensitiveParameter] string $secret, #[\SensitiveParameter] string $code): ?array
    {
        if ($this->isEnabledFor($admin)) {
            return null;
        }

        $step = $this->totp->matchingStep($secret, $code, time(), null);

        if (null === $step) {
            $this->log($admin, 'Second factor activation refused: the code does not match the secret');

            return null;
        }

        $connection = Propel::getWriteConnection(AdminTwoFactorTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            AdminTwoFactorQuery::create()->filterByAdminId($admin->getId())->delete($connection);
            (new AdminTwoFactor())
                ->setAdminId($admin->getId())
                ->setSecret($secret)
                ->setEnabledAt(new \DateTime())
                ->setLastUsedStep($step)
                ->save($connection);
            $backupCodes = $this->replaceBackupCodes($admin, $connection);
            $admin->setRememberMeToken(null)->save($connection);
            $connection->commit();
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }

        $this->failureLimiterOf($admin)->reset();
        $this->log($admin, 'Second factor enabled');

        return $backupCodes;
    }

    public function verify(Admin $admin, #[\SensitiveParameter] string $code): TwoFactorVerification
    {
        $twoFactor = $this->enabledTwoFactorOf($admin);

        if (!$twoFactor instanceof AdminTwoFactor) {
            return TwoFactorVerification::Refused;
        }

        $failureLimiter = $this->failureLimiterOf($admin);

        if (0 === $failureLimiter->consume(0)->getRemainingTokens()) {
            $this->log($admin, 'Second factor verification refused: too many failures on this account');

            return TwoFactorVerification::Refused;
        }

        $code = strtolower(str_replace([' ', '-'], '', $code));

        $verification = \strlen($code) === self::BACKUP_CODE_LENGTH
            ? ($this->consumeBackupCode($admin, $code) ? TwoFactorVerification::BackupCode : TwoFactorVerification::Refused)
            : ($this->consumeTotpStep($admin, $twoFactor, $code) ? TwoFactorVerification::Totp : TwoFactorVerification::Refused);

        if (!$verification->isAccepted()) {
            $failureLimiter->consume();
            $this->log($admin, 'Second factor verification failed');
        }

        return $verification;
    }

    /**
     * @return list<string>
     */
    public function regenerateBackupCodes(Admin $admin): array
    {
        if (!$this->isEnabledFor($admin)) {
            throw new \LogicException('The second factor of this administrator is not enabled.');
        }

        $backupCodes = $this->replaceBackupCodes($admin);
        $this->log($admin, 'Second factor backup codes regenerated, the previous ones no longer work');

        return $backupCodes;
    }

    public function disable(Admin $admin): void
    {
        $this->remove($admin);
        $this->log($admin, 'Second factor disabled');
    }

    public function resetOnBehalfOf(Admin $admin, Admin $resetBy): void
    {
        if ($admin->getId() === $resetBy->getId()) {
            throw new \LogicException('An administrator cannot reset their own second factor.');
        }

        $this->remove($admin);
        $this->log(
            $resetBy,
            \sprintf("Second factor of administrator '%s' reset by administrator '%s'", $admin->getLogin(), $resetBy->getLogin()),
            (int) $admin->getId(),
        );
    }

    public function resetFromCommandLine(Admin $admin): void
    {
        $this->remove($admin);
        $this->log($admin, 'Second factor reset from the command line');
    }

    public function remainingBackupCodeCount(Admin $admin): int
    {
        return AdminTwoFactorBackupCodeQuery::create()
            ->filterByAdminId($admin->getId())
            ->filterByUsedAt(null, Criteria::ISNULL)
            ->count();
    }

    private function enabledTwoFactorOf(Admin $admin): ?AdminTwoFactor
    {
        if (null === $admin->getId()) {
            return null;
        }

        AdminTwoFactorTableMap::clearInstancePool();

        return AdminTwoFactorQuery::create()
            ->filterByAdminId($admin->getId())
            ->filterByEnabledAt(null, Criteria::ISNOTNULL)
            ->findOne();
    }

    private function consumeTotpStep(Admin $admin, AdminTwoFactor $twoFactor, #[\SensitiveParameter] string $code): bool
    {
        $step = $this->totp->matchingStep($twoFactor->getSecret(), $code, time(), $twoFactor->getLastUsedStep());

        if (null === $step) {
            return false;
        }

        $claimed = AdminTwoFactorQuery::create()
            ->filterByAdminId($admin->getId())
            ->condition('never_used', AdminTwoFactorTableMap::COL_LAST_USED_STEP.' IS NULL')
            ->condition('used_earlier', AdminTwoFactorTableMap::COL_LAST_USED_STEP.' < ?', $step, \PDO::PARAM_INT)
            ->where(['never_used', 'used_earlier'], Criteria::LOGICAL_OR)
            ->update(['LastUsedStep' => $step]);

        AdminTwoFactorTableMap::clearInstancePool();

        return 1 === $claimed;
    }

    private function consumeBackupCode(Admin $admin, #[\SensitiveParameter] string $code): bool
    {
        $matchingBackupCode = null;

        $unusedBackupCodes = AdminTwoFactorBackupCodeQuery::create()
            ->filterByAdminId($admin->getId())
            ->filterByUsedAt(null, Criteria::ISNULL)
            ->find();

        foreach ($unusedBackupCodes as $backupCode) {
            if (password_verify($code, $backupCode->getCodeHash()) && !$matchingBackupCode instanceof AdminTwoFactorBackupCode) {
                $matchingBackupCode = $backupCode;
            }
        }

        if (!$matchingBackupCode instanceof AdminTwoFactorBackupCode) {
            return false;
        }

        $claimed = AdminTwoFactorBackupCodeQuery::create()
            ->filterById($matchingBackupCode->getId())
            ->filterByUsedAt(null, Criteria::ISNULL)
            ->update(['UsedAt' => (new \DateTime())->format('Y-m-d H:i:s')]);

        AdminTwoFactorBackupCodeTableMap::clearInstancePool();

        if (1 !== $claimed) {
            return false;
        }

        $this->log($admin, \sprintf('Second factor backup code used, %d left', $this->remainingBackupCodeCount($admin)));

        return true;
    }

    /**
     * @return list<string>
     */
    private function replaceBackupCodes(Admin $admin, ?ConnectionInterface $connection = null): array
    {
        AdminTwoFactorBackupCodeQuery::create()->filterByAdminId($admin->getId())->delete($connection);

        $backupCodes = [];

        for ($index = 0; $index < self::BACKUP_CODE_COUNT; ++$index) {
            $backupCode = $this->randomBackupCode();
            $backupCodes[] = $backupCode;

            (new AdminTwoFactorBackupCode())
                ->setAdminId($admin->getId())
                ->setCodeHash(password_hash($backupCode, \PASSWORD_BCRYPT))
                ->save($connection);
        }

        return $backupCodes;
    }

    private function randomBackupCode(): string
    {
        $alphabetLength = \strlen(self::BACKUP_CODE_ALPHABET);
        $backupCode = '';

        for ($index = 0; $index < self::BACKUP_CODE_LENGTH; ++$index) {
            $backupCode .= self::BACKUP_CODE_ALPHABET[random_int(0, $alphabetLength - 1)];
        }

        return $backupCode;
    }

    private function remove(Admin $admin): void
    {
        AdminTwoFactorBackupCodeQuery::create()->filterByAdminId($admin->getId())->delete();
        AdminTwoFactorQuery::create()->filterByAdminId($admin->getId())->delete();
        AdminTwoFactorTableMap::clearInstancePool();
    }

    private function failureLimiterOf(Admin $admin): LimiterInterface
    {
        return $this->failureLimiter->create('admin-'.$admin->getId());
    }

    private function log(Admin $admin, string $message, ?int $resourceId = null): void
    {
        $request = $this->requestStack->getCurrentRequest();

        AdminLog::append(
            self::LOG_RESOURCE,
            self::LOG_ACTION,
            $message,
            $request instanceof Request ? $request : new Request(),
            $admin,
            false,
            $resourceId ?? (int) $admin->getId(),
        );
    }
}
