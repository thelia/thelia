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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Cache\CacheEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Model\ImportCategoryQuery;
use Thelia\Model\ImportQuery;

/**
 * Class Import
 * @package Thelia\Action
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class Import extends BaseAction implements EventSubscriberInterface
{
    protected $environment;

    public function __construct($environment)
    {
        $this->environment = $environment;
    }

    public function changeCategoryPosition(UpdatePositionEvent $event)
    {
        $this->genericUpdatePosition(new ImportCategoryQuery(), $event);

        $this->cacheClear($event->getDispatcher());
    }

    public function changeImportPosition(UpdatePositionEvent $event)
    {
        $this->genericUpdatePosition(new ImportQuery(), $event);

        $this->cacheClear($event->getDispatcher());
    }

    protected function cacheClear(EventDispatcherInterface $dispatcher)
    {
        $cacheEvent = new CacheEvent(
            $this->environment
        );

        $dispatcher->dispatch(TheliaEvents::CACHE_CLEAR, $cacheEvent);
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
            TheliaEvents::IMPORT_CATEGORY_CHANGE_POSITION => array("changeCategoryPosition", 128),
            TheliaEvents::IMPORT_CHANGE_POSITION => array("changeImportPosition", 128),
        );
    }

}
