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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
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
    public function create(ProfileEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $profile = new ProfileModel();

        $profile

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

    public function update(ProfileEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        if (null !== $profile = ProfileQuery::create()->findPk($event->getId())) {
            $profile

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

    public function updateResourceAccess(ProfileEvent $event): void
    {
        if (null !== $profile = ProfileQuery::create()->findPk($event->getId())) {
            ProfileResourceQuery::create()->filterByProfileId($event->getId())->delete();
            foreach ($event->getResourceAccess() as $resourceCode => $accesses) {
                $manager = new AccessManager(0);
                $manager->build($accesses);

                $profileResource = new ProfileResource();
                $profileResource->setProfileId($event->getId())
                    ->setResource(ResourceQuery::create()->findOneByCode($resourceCode))
                    ->setAccess($manager->getAccessValue());

                $profileResource->save();
            }

            $event->setProfile($profile);
        }
    }

    public function updateModuleAccess(ProfileEvent $event): void
    {
        if (null !== $profile = ProfileQuery::create()->findPk($event->getId())) {
            ProfileModuleQuery::create()->filterByProfileId($event->getId())->delete();
            foreach ($event->getModuleAccess() as $moduleCode => $accesses) {
                $manager = new AccessManager(0);
                $manager->build($accesses);

                $profileModule = new ProfileModule();
                $profileModule->setProfileId($event->getId())
                    ->setModule(ModuleQuery::create()->findOneByCode($moduleCode))
                    ->setAccess($manager->getAccessValue());

                $profileModule->save();
            }

            $event->setProfile($profile);
        }
    }

    public function delete(ProfileEvent $event): void
    {
        if (null !== $profile = ProfileQuery::create()->findPk($event->getId())) {
            $profile
                ->delete()
            ;

            $event->setProfile($profile);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::PROFILE_CREATE => ['create', 128],
            TheliaEvents::PROFILE_UPDATE => ['update', 128],
            TheliaEvents::PROFILE_DELETE => ['delete', 128],
            TheliaEvents::PROFILE_RESOURCE_ACCESS_UPDATE => ['updateResourceAccess', 128],
            TheliaEvents::PROFILE_MODULE_ACCESS_UPDATE => ['updateModuleAccess', 128],
        ];
    }
}
