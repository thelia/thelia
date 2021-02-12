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
use Thelia\Core\Event\Tax\TaxEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Tax as TaxModel;
use Thelia\Model\TaxQuery;

class Tax extends BaseAction implements EventSubscriberInterface
{
    /**
     * @param $eventName
     */
    public function create(TaxEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $tax = new TaxModel();

        $tax

            ->setRequirements($event->getRequirements())
            ->setType($event->getType())
            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())
            ->setDescription($event->getDescription())
        ;

        $tax->save();

        $event->setTax($tax);
    }

    /**
     * @param $eventName
     */
    public function update(TaxEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        if (null !== $tax = TaxQuery::create()->findPk($event->getId())) {
            $tax

                ->setRequirements($event->getRequirements())
                ->setType($event->getType())
                ->setLocale($event->getLocale())
                ->setTitle($event->getTitle())
                ->setDescription($event->getDescription())
            ;

            $tax->save();

            $event->setTax($tax);
        }
    }

    public function delete(TaxEvent $event): void
    {
        if (null !== $tax = TaxQuery::create()->findPk($event->getId())) {
            $tax
                ->delete()
            ;

            $event->setTax($tax);
        }
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::TAX_CREATE => ['create', 128],
            TheliaEvents::TAX_UPDATE => ['update', 128],
            TheliaEvents::TAX_DELETE => ['delete', 128],
        ];
    }
}
