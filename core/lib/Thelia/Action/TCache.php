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
use Thelia\Cache\Driver\CacheDriverInterface;
use Thelia\Core\Event\ActionEvent;
use Thelia\Core\Event\Cache\CacheEvent;
use Thelia\Core\Event\Cache\TCacheDiscardKeyEvent;
use Thelia\Core\Event\Cache\TCacheDiscardRefEvent;
use Thelia\Core\Event\Cache\TCacheFlushEvent;
use Thelia\Core\Event\Cache\TCacheUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\ConfigQuery;


/**
 * Class TCache
 * @package Thelia\Action
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class TCache extends BaseAction implements EventSubscriberInterface
{
    /** @var CacheDriverInterface */
    protected $cache;

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
            // cache
            TheliaEvents::TCACHE_UPDATE      => ['update', 128],
            TheliaEvents::TCACHE_DISCARD_REF => ['discardByRef', 128],
            TheliaEvents::TCACHE_DISCARD_KEY => ['discardByKey', 128],
            TheliaEvents::TCACHE_FLUSH       => ['flush', 128],

            // dependencies
            // cache
            TheliaEvents::CACHE_CLEAR        => ['flushCache', 128],

            // product
            TheliaEvents::PRODUCT_UPDATE     => ['discardProduct', 128],
            TheliaEvents::PRODUCT_DELETE     => ['discardProduct', 128]

            // sale element

        ];

    }


    function __construct(CacheDriverInterface $cache)
    {
        $this->cache = $cache;
    }

    public function update(TCacheUpdateEvent $event)
    {
        $dispatcher = $event->getDispatcher();

        $flushEvent = new TCacheFlushEvent();

        $dispatcher->dispatch(TheliaEvents::TCACHE_FLUSH, $flushEvent);

        foreach ($event->all() as $key => $value) {
            ConfigQuery::write($key, $value);
        }

    }

    public function discardByKey(TCacheDiscardKeyEvent $event)
    {
        $event->setResponse($this->cache->delete($event->getKey()));
    }

    public function discardByRef(TCacheDiscardRefEvent $event)
    {
        $cache = TCacheManager::getInstance();
        $event->setResponse($cache->deleteRef($event->getRef()));
    }

    public function flushCache(CacheEvent $event)
    {
        $eventFlush = new TCacheFlushEvent();
        $this->flush($eventFlush);
    }


    public function flush(TCacheFlushEvent $event)
    {
        $event->setResponse($this->cache->deleteAll());
    }

    public function discardProduct(ActionEvent $ev)
    {
        $dispatcher = $ev->getDispatcher();
        $dispatcher->dispatch(TheliaEvents::TCACHE_DISCARD_REF,
            $this->getDeleteRefEvent("product", $ev->getProductId()));

    }

    protected function getDeleteRefEvent($ns, $key)
    {
        $event = new TCacheDiscardRefEvent();
        $event->setRef("$ns::$key");

        return $event;
    }

} 