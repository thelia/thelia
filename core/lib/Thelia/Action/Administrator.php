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

namespace Thelia\Action;

use Propel\Runtime\Propel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Administrator\AdministratorEvent;
use Thelia\Core\Event\Administrator\AdministratorSaleUnitsEvent;
use Thelia\Core\Event\Administrator\AdministratorUpdatePasswordEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\SaleUnit\Access\AdminSaleUnitScope;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\Admin as AdminModel;
use Thelia\Model\AdminQuery;
use Thelia\Model\AdminSaleUnit;
use Thelia\Model\AdminSaleUnitQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\AdminSaleUnitTableMap;
use Thelia\Model\SaleUnitQuery;
use Thelia\Tools\TokenProvider;

class Administrator extends BaseAction implements EventSubscriberInterface
{
    public function __construct(
        protected MailerFactory $mailer,
        protected TokenProvider $tokenProvider,
        protected ?AdminSaleUnitScope $adminSaleUnitScope = null,
    ) {
    }

    public function create(AdministratorEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $administrator = new AdminModel();

        $administrator
            ->setFirstname($event->getFirstname())
            ->setLastname($event->getLastname())
            ->setEmail($event->getEmail())
            ->setLogin($event->getLogin())
            ->setPassword($event->getPassword())
            ->setProfileId(null !== $event->getProfile() ? (int) $event->getProfile() : null)
            ->setLocale($event->getLocale());

        $administrator->save();

        $event->setAdministrator($administrator);
    }

    public function update(AdministratorEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        if (null === $administrator = AdminQuery::create()->findPk($event->getId())) {
            return;
        }
        $administrator
            ->setFirstname($event->getFirstname())
            ->setLastname($event->getLastname())
            ->setLogin($event->getLogin())
            ->setEmail($event->getEmail())
            ->setProfileId(null !== $event->getProfile() ? (int) $event->getProfile() : null)
            ->setLocale($event->getLocale());

        if ('' !== $event->getPassword()) {
            $administrator->setPassword($event->getPassword());
        }

        $administrator->save();

        $event->setAdministrator($administrator);
    }

    public function delete(AdministratorEvent $event): void
    {
        if (null === $administrator = AdminQuery::create()->findPk($event->getId())) {
            return;
        }
        $administrator
            ->delete();

        $event->setAdministrator($administrator);
    }

    /**
     * Replaces the sale units the administrator is restricted to; an empty list lifts
     * the restriction. An id naming no sale unit is ignored, and a list naming none
     * that exists is refused.
     */
    public function updateSaleUnits(AdministratorSaleUnitsEvent $event): void
    {
        if (null === AdminQuery::create()->findPk($event->getAdminId())) {
            return;
        }

        $saleUnitIds = [] === $event->getSaleUnitIds()
            ? []
            : array_map('intval', SaleUnitQuery::create()
                ->filterById($event->getSaleUnitIds())
                ->select(['Id'])
                ->find()
                ->getData());

        // Only an empty list lifts the restriction: a list naming no unit that
        // exists is a mistake, and taking it as empty would open every unit.
        if ([] === $saleUnitIds && [] !== $event->getSaleUnitIds()) {
            throw new \InvalidArgumentException('None of the sale units the administrator was to be restricted to exists: the restriction is left as it was.');
        }

        $con = Propel::getWriteConnection(AdminSaleUnitTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            AdminSaleUnitQuery::create()
                ->filterByAdminId($event->getAdminId())
                ->delete($con);

            foreach ($saleUnitIds as $saleUnitId) {
                (new AdminSaleUnit())
                    ->setAdminId($event->getAdminId())
                    ->setSaleUnitId($saleUnitId)
                    ->save($con);
            }

            $con->commit();
        } catch (\Throwable $exception) {
            $con->rollBack();

            throw $exception;
        }

        sort($saleUnitIds);
        $event->setSaleUnitIds($saleUnitIds);
        $this->adminSaleUnitScope?->forget($event->getAdminId());
    }

    public function updatePassword(AdministratorUpdatePasswordEvent $event): void
    {
        $admin = $event->getAdmin();

        $admin
            ->setPassword($event->getPassword())
            ->setPasswordRenewToken(null)
            ->save();
    }

    public function createPassword(AdministratorEvent $event): void
    {
        $admin = $event->getAdministrator();
        if (null === $admin) {
            return;
        }

        $email = $admin->getEmail();
        if (empty($email)) {
            return;
        }

        $renewToken = $this->tokenProvider->getToken();
        $admin
            ->setPasswordRenewToken($renewToken)
            ->save();

        $this->mailer->sendEmailMessage(
            'new_admin_password',
            [ConfigQuery::getStoreEmail() => ConfigQuery::getStoreName()],
            [$email => $admin->getFirstname().' '.$admin->getLastname()],
            [
                'token' => $renewToken,
                'admin' => $admin,
            ],
        );
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::ADMINISTRATOR_CREATE => ['create', 128],
            TheliaEvents::ADMINISTRATOR_UPDATE => ['update', 128],
            TheliaEvents::ADMINISTRATOR_DELETE => ['delete', 128],
            TheliaEvents::ADMINISTRATOR_UPDATEPASSWORD => ['updatePassword', 128],
            TheliaEvents::ADMINISTRATOR_CREATEPASSWORD => ['createPassword', 128],
            TheliaEvents::ADMINISTRATOR_UPDATE_SALE_UNITS => ['updateSaleUnits', 128],
        ];
    }
}
