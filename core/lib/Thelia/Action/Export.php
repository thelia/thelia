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
use Thelia\Handler\ExportHandler;
use Thelia\Model\ExportCategoryQuery;
use Thelia\Model\ExportQuery;

/**
 * Class Export
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class Export extends BaseAction implements EventSubscriberInterface
{
    /**
     * @var \Thelia\Handler\ExportHandler The export handler
     */
    protected $handler;

    /**
     * @param \Thelia\Handler\ExportHandler $exportHandler The export handler
     */
    public function __construct(ExportHandler $exportHandler)
    {
        $this->handler = $exportHandler;
    }

    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::EXPORT_CHANGE_POSITION => [
                ['exportChangePosition', 128]
            ],
            TheliaEvents::EXPORT_CATEGORY_CHANGE_POSITION => [
                ['exportCategoryChangePosition', 128]
            ]
        ];
    }

    /**
     * Handle export change position event
     *
     * @param UpdatePositionEvent $updatePositionEvent
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     */
    public function exportChangePosition(UpdatePositionEvent $updatePositionEvent, $eventName, EventDispatcherInterface $dispatcher)
    {
        $this->handler->getExport($updatePositionEvent->getObjectId(), true);
        $this->genericUpdatePosition(new ExportQuery, $updatePositionEvent, $dispatcher);
    }

    /**
     * Handle export category change position event
     *
     * @param UpdatePositionEvent $updatePositionEvent
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     */
    public function exportCategoryChangePosition(UpdatePositionEvent $updatePositionEvent, $eventName, EventDispatcherInterface $dispatcher)
    {
        $this->handler->getCategory($updatePositionEvent->getObjectId(), true);
        $this->genericUpdatePosition(new ExportCategoryQuery, $updatePositionEvent, $dispatcher);
    }
}
