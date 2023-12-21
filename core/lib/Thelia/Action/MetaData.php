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

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\MetaData\MetaDataCreateOrUpdateEvent;
use Thelia\Core\Event\MetaData\MetaDataDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\MetaData as MetaDataModel;
use Thelia\Model\MetaDataQuery;

/**
 * Class MetaData.
 *
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class MetaData extends BaseAction implements EventSubscriberInterface
{
    public function createOrUpdate(MetaDataCreateOrUpdateEvent $event): void
    {
        $metaData = MetaDataQuery::create()
            ->filterByMetaKey($event->getMetaKey())
            ->filterByElementKey($event->getElementKey())
            ->filterByElementId($event->getElementId())
            ->findOne();

        if (null === $metaData) {
            $metaData = new MetaDataModel();
            $metaData
                ->setMetaKey($event->getMetaKey())
                ->setElementKey($event->getElementkey())
                ->setElementId($event->getElementId());
        }
        $metaData->
            setValue($event->getValue());
        $metaData->save();

        $event->setMetaData($metaData);
    }

    public function delete(MetaDataDeleteEvent $event): void
    {
        $metaData = MetaDataQuery::create()
            ->filterByMetaKey($event->getMetaKey())
            ->filterByElementKey($event->getElementKey())
            ->filterByElementId($event->getElementId())
            ->findOne();
        $event->setMetaData($metaData);
        if (null !== $metaData) {
            $metaData->delete();
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::META_DATA_CREATE => ['createOrUpdate', 128],
            TheliaEvents::META_DATA_UPDATE => ['createOrUpdate', 128],
            TheliaEvents::META_DATA_DELETE => ['delete', 128],
        ];
    }
}
