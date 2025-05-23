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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Model\ExportCategoryQuery;
use Thelia\Model\ExportQuery;
use Thelia\Service\Handler\ExportHandler;

/**
 * Class Export.
 *
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class Export extends BaseAction implements EventSubscriberInterface
{
    /**
     * @var \Thelia\Service\Handler\ExportHandler The export handler
     */
    protected $handler;

    /**
     * @param \Thelia\Service\Handler\ExportHandler $exportHandler The export handler
     */
    public function __construct(ExportHandler $exportHandler)
    {
        $this->handler = $exportHandler;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::EXPORT_CHANGE_POSITION => [
                ['exportChangePosition', 128],
            ],
            TheliaEvents::EXPORT_CATEGORY_CHANGE_POSITION => [
                ['exportCategoryChangePosition', 128],
            ],
        ];
    }

    /**
     * Handle export change position event.
     */
    public function exportChangePosition(UpdatePositionEvent $updatePositionEvent, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $this->handler->getExport($updatePositionEvent->getObjectId(), true);
        $this->genericUpdatePosition(new ExportQuery(), $updatePositionEvent, $dispatcher);
    }

    /**
     * Handle export category change position event.
     */
    public function exportCategoryChangePosition(UpdatePositionEvent $updatePositionEvent, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $this->handler->getCategory($updatePositionEvent->getObjectId(), true);
        $this->genericUpdatePosition(new ExportCategoryQuery(), $updatePositionEvent, $dispatcher);
    }
}
