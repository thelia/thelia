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
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\Event\Cache\CacheEvent;
use Thelia\Core\Event\TheliaEvents;

/**
 * Class Cache
 * @package Thelia\Action
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class Cache extends BaseAction implements EventSubscriberInterface
{
    public function cacheClear(CacheEvent $event)
    {
        $dir = $event->getDir();

        $fs = new Filesystem();
        $fs->remove($dir);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::CACHE_CLEAR => array('cacheClear', 128)
        );
    }
}
