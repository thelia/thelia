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

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Administrator\AdministratorEvent;
use Thelia\Core\Event\Administrator\AdministratorUpdatePasswordEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Admin as AdminModel;
use Thelia\Model\AdminQuery;
use Thelia\Model\AdminProfile;
use Thelia\Model\AdminProfileQuery;

class Administrator extends BaseAction implements EventSubscriberInterface
{
    /**
     * @param AdministratorEvent $event
     */
    public function create(AdministratorEvent $event)
    {
        $administrator = new AdminModel();

        $administrator
            ->setDispatcher($event->getDispatcher())
            ->setFirstname($event->getFirstname())
            ->setLastname($event->getLastname())
            ->setLogin($event->getLogin())
            ->setPassword($event->getPassword())
            ->setLocale($event->getLocale())
        ;

        $administrator->save();

        /*
         * Update profiles list of admin
         */
        $profilesList = $event->getProfilesList();
        foreach($profilesList as $aProfile){
            $myAdminProfile = new AdminProfile();
            $myAdminProfile->setAdminId($administrator->getId())
                ->setProfileId($aProfile)
                ->save();
        }

        $event->setAdministrator($administrator);
    }

    /**
     * @param AdministratorEvent $event
     */
    public function update(AdministratorEvent $event)
    {
        if (null !== $administrator = AdminQuery::create()->findPk($event->getId())) {
            $administrator
                ->setDispatcher($event->getDispatcher())
                ->setFirstname($event->getFirstname())
                ->setLastname($event->getLastname())
                ->setLogin($event->getLogin())
                ->setLocale($event->getLocale())
            ;

            if ('' !== $event->getPassword()) {
                $administrator->setPassword($event->getPassword());
            }

            /*
             * Update profiles list of admin
             */
            $profilesListe = $event->getProfilesList();
            AdminProfileQuery::create()->filterByAdminId($event->getId())->delete();
            if(!empty($profilesListe) && !in_array('0',$profilesListe)){
                foreach($profilesListe as $aProfile){
                    $myAdminProfile = new AdminProfile();
                    $myAdminProfile->setAdminId($event->getId())
                        ->setProfileId($aProfile)
                        ->save();
                }
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
        $admin->setPassword($event->getPassword())
            ->save();
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::ADMINISTRATOR_CREATE                        => array('create', 128),
            TheliaEvents::ADMINISTRATOR_UPDATE                        => array('update', 128),
            TheliaEvents::ADMINISTRATOR_DELETE                        => array('delete', 128),
            TheliaEvents::ADMINISTRATOR_UPDATEPASSWORD              => array('updatePassword', 128)
        );
    }
}
