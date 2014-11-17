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
use Thelia\Core\Event\Api\ApiCreateEvent;
use Thelia\Core\Event\Api\ApiDeleteEvent;
use Thelia\Core\Event\Api\ApiUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Api as ApiModel;

/**
 * Class Api
 * @package Thelia\Action
 * @author Manuel Raynaud <manu@thelia.net>
 */
class Api extends BaseAction implements EventSubscriberInterface
{
    public function createApi(ApiCreateEvent $event)
    {
        $api = new ApiModel();

        $api->setLabel($event->getLabel())
            ->setProfileId($event->getProfile())
            ->save()
        ;
    }

    public function deleteApi(ApiDeleteEvent $event)
    {
        $api = $event->getApi();

        $api->delete();
    }

    public function updateApi(ApiUpdateEvent $event)
    {
        $api = $event->getApi();

        $api->setProfileId($event->getProfile())
            ->save();
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     *
     * @api
     */
    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::API_CREATE => ['createApi', 128],
            TheliaEvents::API_DELETE => ['deleteApi', 128],
            TheliaEvents::API_UPDATE => ['updateApi', 128],
        ];
    }
}
