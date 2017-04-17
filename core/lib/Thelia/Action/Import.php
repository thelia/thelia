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
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Handler\ImportHandler;
use Thelia\Model\ImportCategoryQuery;
use Thelia\Model\ImportQuery;

/**
 * Class Import
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class Import extends BaseAction implements EventSubscriberInterface
{
    /**
     * @var \Thelia\Handler\ImportHandler The import handler
     */
    protected $handler;

    /**
     * @param \Thelia\Handler\ImportHandler $importHandler The import handler
     */
    public function __construct(ImportHandler $importHandler)
    {
        $this->handler = $importHandler;
    }

    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::IMPORT_CHANGE_POSITION => [
                ['importChangePosition', 128]
            ],
            TheliaEvents::IMPORT_CATEGORY_CHANGE_POSITION => [
                ['importCategoryChangePosition', 128]
            ]
        ];
    }

    /**
     * Handle import change position event
     *
     * @param UpdatePositionEvent $updatePositionEvent
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     */
    public function importChangePosition(UpdatePositionEvent $updatePositionEvent, $eventName, EventDispatcherInterface $dispatcher)
    {
        $this->handler->getImport($updatePositionEvent->getObjectId(), true);
        $this->genericUpdatePosition(new ImportQuery, $updatePositionEvent, $dispatcher);
    }

    /**
     * Handle import category change position event
     *
     * @param UpdatePositionEvent $updatePositionEvent
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     */
    public function importCategoryChangePosition(UpdatePositionEvent $updatePositionEvent, $eventName, EventDispatcherInterface $dispatcher)
    {
        $this->handler->getCategory($updatePositionEvent->getObjectId(), true);
        $this->genericUpdatePosition(new ImportCategoryQuery, $updatePositionEvent, $dispatcher);
    }
}
