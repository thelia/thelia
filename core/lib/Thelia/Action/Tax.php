<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
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
            ->setDispatcher($this->getDispatcher())
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
                ->setDispatcher($this->getDispatcher())
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
