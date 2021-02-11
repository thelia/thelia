<?php

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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Administrator\AdministratorEvent;
use Thelia\Core\Event\Administrator\AdministratorUpdatePasswordEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\Admin as AdminModel;
use Thelia\Model\AdminQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Tools\TokenProvider;

class Administrator extends BaseAction implements EventSubscriberInterface
{
    /** @var MailerFactory  */
    protected $mailer;

    /** @var  TokenProvider */
    protected $tokenProvider;

    public function __construct(MailerFactory $mailer, TokenProvider $tokenProvider)
    {
        $this->mailer = $mailer;
        $this->tokenProvider = $tokenProvider;
    }

    /**
     * @param $eventName
     */
    public function create(AdministratorEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $administrator = new AdminModel();

        $administrator
            ->setFirstname($event->getFirstname())
            ->setLastname($event->getLastname())
            ->setEmail($event->getEmail())
            ->setLogin($event->getLogin())
            ->setPassword($event->getPassword())
            ->setProfileId($event->getProfile())
            ->setLocale($event->getLocale())
        ;

        $administrator->save();

        $event->setAdministrator($administrator);
    }

    /**
     * @param $eventName
     */
    public function update(AdministratorEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if (null !== $administrator = AdminQuery::create()->findPk($event->getId())) {
            $administrator
                ->setFirstname($event->getFirstname())
                ->setLastname($event->getLastname())
                ->setLogin($event->getLogin())
                ->setEmail($event->getEmail())
                ->setProfileId($event->getProfile())
                ->setLocale($event->getLocale())
            ;

            if ('' !== $event->getPassword()) {
                $administrator->setPassword($event->getPassword());
            }

            $administrator->save();

            $event->setAdministrator($administrator);
        }
    }

    /**
     */
    public function delete(AdministratorEvent $event)
    {
        if (null !== $administrator = AdminQuery::create()->findPk($event->getId())) {
            $administrator
                ->delete()
            ;

            $event->setAdministrator($administrator);
        }
    }

    public function updatePassword(AdministratorUpdatePasswordEvent $event)
    {
        $admin = $event->getAdmin();

        $admin
            ->setPassword($event->getPassword())
            ->setPasswordRenewToken(null)
            ->save();
    }

    public function createPassword(AdministratorEvent $event)
    {
        $admin = $event->getAdministrator();

        $email = $admin->getEmail();

        if (! empty($email)) {
            $renewToken = $this->tokenProvider->getToken();

            $admin
                ->setPasswordRenewToken($renewToken)
                ->save();

            $this->mailer->sendEmailMessage(
                'new_admin_password',
                [ ConfigQuery::getStoreEmail() => ConfigQuery::getStoreName() ],
                [ $email => $admin->getFirstname() . ' ' . $admin->getLastname() ],
                [
                    'token'     => $renewToken,
                    'admin'     => $admin
                ]
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::ADMINISTRATOR_CREATE                        => ['create', 128],
            TheliaEvents::ADMINISTRATOR_UPDATE                        => ['update', 128],
            TheliaEvents::ADMINISTRATOR_DELETE                        => ['delete', 128],
            TheliaEvents::ADMINISTRATOR_UPDATEPASSWORD                => ['updatePassword', 128],
            TheliaEvents::ADMINISTRATOR_CREATEPASSWORD                => ['createPassword', 128]
        ];
    }
}
