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
use Thelia\Handler\ImportHandler;
use Thelia\Model\ImportCategoryQuery;
use Thelia\Model\ImportQuery;

/**
 * Class Import.
 *
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

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::IMPORT_CHANGE_POSITION => [
                ['importChangePosition', 128],
            ],
            TheliaEvents::IMPORT_CATEGORY_CHANGE_POSITION => [
                ['importCategoryChangePosition', 128],
            ],
        ];
    }

    /**
     * Handle import change position event.
     */
    public function importChangePosition(UpdatePositionEvent $updatePositionEvent, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $this->handler->getImport($updatePositionEvent->getObjectId(), true);
        $this->genericUpdatePosition(new ImportQuery(), $updatePositionEvent, $dispatcher);
    }

    /**
     * Handle import category change position event.
     */
    public function importCategoryChangePosition(UpdatePositionEvent $updatePositionEvent, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $this->handler->getCategory($updatePositionEvent->getObjectId(), true);
        $this->genericUpdatePosition(new ImportCategoryQuery(), $updatePositionEvent, $dispatcher);
    }
}
