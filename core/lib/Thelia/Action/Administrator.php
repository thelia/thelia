<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

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
     * @param AdministratorEvent $event
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     */
    public function create(AdministratorEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $administrator = new AdminModel();

        $administrator
            ->setDispatcher($dispatcher)
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
     * @param AdministratorEvent $event
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     */
    public function update(AdministratorEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if (null !== $administrator = AdminQuery::create()->findPk($event->getId())) {
            $administrator
                ->setDispatcher($dispatcher)
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
     * @param AdministratorEvent $event
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
        return array(
            TheliaEvents::ADMINISTRATOR_CREATE                        => array('create', 128),
            TheliaEvents::ADMINISTRATOR_UPDATE                        => array('update', 128),
            TheliaEvents::ADMINISTRATOR_DELETE                        => array('delete', 128),
            TheliaEvents::ADMINISTRATOR_UPDATEPASSWORD                => array('updatePassword', 128),
            TheliaEvents::ADMINISTRATOR_CREATEPASSWORD                => array('createPassword', 128)
        );
    }
}
