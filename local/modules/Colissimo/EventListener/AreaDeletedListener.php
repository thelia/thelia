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

namespace Colissimo\EventListener;

use Colissimo\Colissimo;
use Colissimo\Model\Config\ColissimoConfigValue;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Area\AreaDeleteEvent;
use Thelia\Core\Event\TheliaEvents;

/**
 * Class AreaDeletedListener
 * @package AreaDeletedListener\EventListener
 * @author Thomas Arnaud <tarnaud@openstudio.fr>
 */
class AreaDeletedListener implements EventSubscriberInterface
{
    /**
     * @param AreaDeleteEvent $event
     */
    public function updateConfig(AreaDeleteEvent $event)
    {
        if (null !== $data = Colissimo::getConfigValue(ColissimoConfigValue::PRICES, null)) {
            $areaId = $event->getAreaId();
            $json_data = json_decode($data, true);
            unset($json_data[$areaId]);

            Colissimo::setConfigValue(ColissimoConfigValue::PRICES, json_encode($json_data, true));
        }
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::AREA_DELETE => [
                'updateConfig', 128
            ]
        ];
    }
}
