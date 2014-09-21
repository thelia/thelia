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
use Thelia\Core\Event\Tax\TaxEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Tax as TaxModel;
use Thelia\Model\TaxQuery;

class Tax extends BaseAction implements EventSubscriberInterface
{
    /**
     * @param TaxEvent $event
     */
    public function create(TaxEvent $event)
    {
        $tax = new TaxModel();

        $tax
            ->setDispatcher($event->getDispatcher())
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
     * @param TaxEvent $event
     */
    public function update(TaxEvent $event)
    {
        if (null !== $tax = TaxQuery::create()->findPk($event->getId())) {
            $tax
                ->setDispatcher($event->getDispatcher())
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

    /**
     * @param TaxEvent $event
     */
    public function delete(TaxEvent $event)
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
        return array(
            TheliaEvents::TAX_CREATE            => array("create", 128),
            TheliaEvents::TAX_UPDATE            => array("update", 128),
            TheliaEvents::TAX_DELETE            => array("delete", 128),
        );
    }
}
