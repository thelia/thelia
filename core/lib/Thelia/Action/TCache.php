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
use Thelia\Core\Event\ActionEvent;
use Thelia\Core\Event\Cache\TCacheDiscardKeyEvent;
use Thelia\Core\Event\Cache\TCacheDiscardRefEvent;
use Thelia\Core\Event\Cache\TCacheFlushEvent;
use Thelia\Core\Event\TheliaEvents;
use \Thelia\Cache\TCache as TCacheManager;

/**
 * Class TCache
 * @package Thelia\Action
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class TCache extends BaseAction implements EventSubscriberInterface
{


    public function discardByKey(TCacheDiscardKeyEvent $event)
    {
        $cache = TCacheManager::getInstance();
        $event->setResponse($cache->delete($event->getKey()));
    }

    public function discardByRef(TCacheDiscardRefEvent $event)
    {
        $cache = TCacheManager::getInstance();
        $event->setResponse($cache->deleteRef($event->getRef()));
    }

    public function flush(TCacheFlushEvent $event)
    {
        $cache = TCacheManager::getInstance();
        $event->setResponse($cache->deleteAll());
    }

    public function discardProduct(ActionEvent $ev)
    {
        $dispatcher = $ev->getDispatcher();
        $dispatcher->dispatch(TheliaEvents::TCACHE_DISCARD_REF,
                              $this->getDeleteRefEvent($event->getProductId()));

    }

    protected function getDeleteRefEvent($ns, $key){
        $event = new TCacheDiscardRefEvent();
        $event->setRefs("$ns::$key");
        return $event;
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
        return array(
            // cache
            TheliaEvents::TCACHE_DISCARD_REF => array('discardByRef', 128),
            TheliaEvents::TCACHE_DISCARD_KEY => array('discardByKey', 128),
            TheliaEvents::TCACHE_FLUSH       => array('flush', 128),

            // dependencies
            // cache
            TheliaEvents::CACHE_CLEAR => array('flush', 128),

            // product
            TheliaEvents::PRODUCT_UPDATE => array('discardProduct', 128),
            TheliaEvents::PRODUCT_DELETE => array('discardProduct', 128)

            // sale element

        );
    }

} 