<?php

declare(strict_types=1);

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
use Thelia\Model\ImportCategoryQuery;
use Thelia\Model\ImportQuery;
use Thelia\Service\DataTransfer\ImportHandler;

/**
 * Class Import.
 *
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class Import extends BaseAction implements EventSubscriberInterface
{
    /**
     * @param ImportHandler $handler The import handler
     */
    public function __construct(protected ImportHandler $handler)
    {
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
