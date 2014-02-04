<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Action;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Profile\ProfileEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Profile as ProfileModel;
use Thelia\Model\ProfileModule;
use Thelia\Model\ProfileModuleQuery;
use Thelia\Model\ProfileQuery;
use Thelia\Model\ProfileResource;
use Thelia\Model\ProfileResourceQuery;
use Thelia\Model\ResourceQuery;

class Profile extends BaseAction implements EventSubscriberInterface
{
    /**
     * @param ProfileEvent $event
     */
    public function create(ProfileEvent $event)
    {
        $profile = new ProfileModel();

        $profile
            ->setDispatcher($event->getDispatcher())
            ->setCode($event->getCode())
            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())
            ->setChapo($event->getChapo())
            ->setDescription($event->getDescription())
            ->setPostscriptum($event->getPostscriptum())
        ;

        $profile->save();

        $event->setProfile($profile);
    }

    /**
     * @param ProfileEvent $event
     */
    public function update(ProfileEvent $event)
    {
        if (null !== $profile = ProfileQuery::create()->findPk($event->getId())) {

            $profile
                ->setDispatcher($event->getDispatcher())
                ->setLocale($event->getLocale())
                ->setTitle($event->getTitle())
                ->setChapo($event->getChapo())
                ->setDescription($event->getDescription())
                ->setPostscriptum($event->getPostscriptum())
            ;

            $profile->save();

            $event->setProfile($profile);
        }
    }

    /**
     * @param ProfileEvent $event
     */
    public function updateResourceAccess(ProfileEvent $event)
    {
        if (null !== $profile = ProfileQuery::create()->findPk($event->getId())) {
            ProfileResourceQuery::create()->filterByProfileId($event->getId())->delete();
            foreach ($event->getResourceAccess() as $resourceCode => $accesses) {
                $manager = new AccessManager(0);
                $manager->build($accesses);

                $profileResource = new ProfileResource();
                $profileResource->setProfileId($event->getId())
                    ->setResource(ResourceQuery::create()->findOneByCode($resourceCode))
                    ->setAccess( $manager->getAccessValue() );

                $profileResource->save();

            }

            $event->setProfile($profile);
        }
    }

    /**
     * @param ProfileEvent $event
     */
    public function updateModuleAccess(ProfileEvent $event)
    {
        if (null !== $profile = ProfileQuery::create()->findPk($event->getId())) {
            ProfileModuleQuery::create()->filterByProfileId($event->getId())->delete();
            foreach ($event->getModuleAccess() as $moduleCode => $accesses) {
                $manager = new AccessManager(0);
                $manager->build($accesses);

                $profileModule = new ProfileModule();
                $profileModule->setProfileId($event->getId())
                    ->setModule(ModuleQuery::create()->findOneByCode($moduleCode))
                    ->setAccess( $manager->getAccessValue() );

                $profileModule->save();

            }

            $event->setProfile($profile);
        }
    }

    /**
     * @param ProfileEvent $event
     */
    public function delete(ProfileEvent $event)
    {
        if (null !== $profile = ProfileQuery::create()->findPk($event->getId())) {

            $profile
                ->delete()
            ;

            $event->setProfile($profile);
        }
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::PROFILE_CREATE                        => array("create", 128),
            TheliaEvents::PROFILE_UPDATE                        => array("update", 128),
            TheliaEvents::PROFILE_DELETE                        => array("delete", 128),
            TheliaEvents::PROFILE_RESOURCE_ACCESS_UPDATE        => array("updateResourceAccess", 128),
            TheliaEvents::PROFILE_MODULE_ACCESS_UPDATE          => array("updateModuleAccess", 128),
        );
    }
}
