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
use Thelia\Core\Event\MetaData\MetaDataCreateOrUpdateEvent;
use Thelia\Core\Event\MetaData\MetaDataDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\MetaData as MetaDataModel;
use Thelia\Model\MetaDataQuery;

/**
 * Class MetaData
 * @package Thelia\Action
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class MetaData extends BaseAction implements EventSubscriberInterface
{
    public function createOrUpdate(MetaDataCreateOrUpdateEvent $event)
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

    public function delete(MetaDataDeleteEvent $event)
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

    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::META_DATA_CREATE => array('createOrUpdate', 128),
            TheliaEvents::META_DATA_UPDATE => array('createOrUpdate', 128),
            TheliaEvents::META_DATA_DELETE => array('delete', 128),
        );
    }
}
