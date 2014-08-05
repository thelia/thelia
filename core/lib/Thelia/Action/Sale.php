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
use Thelia\Core\Event\Sale\SaleCreateEvent;
use Thelia\Core\Event\Sale\SaleDeleteEvent;
use Thelia\Core\Event\Sale\SaleToggleActivityEvent;
use Thelia\Core\Event\Sale\SaleUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Sale as SaleModel;
use Thelia\Model\SaleQuery;

/**
 * Class Sale
 *
 * @package Thelia\Action
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class Sale extends BaseAction implements EventSubscriberInterface
{

    public function create(SaleCreateEvent $event)
    {
        $sale = new SaleModel();

        $sale
            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())
            ->setSaleLabel($event->getSaleLabel())
            ->save()
        ;

        $event->setSale($sale);
    }

    /**
     * process update sale
     *
     * @param SaleUpdateEvent $event
     */
    public function update(SaleUpdateEvent $event)
    {
        if (null !== $sale = SaleQuery::create()->findPk($event->getSaleId())) {
            $sale->setDispatcher($event->getDispatcher());

            // TODO : update product status on activity change

            $sale
                ->setActive($event->getActive())
                ->setStartDate($event->getStartDate())
                ->setEndDate($event->getEndDate())
                ->setPriceOffsetType($event->getPriceOffsetType())
                ->setDisplayInitialPrice($event->getDisplayInitialPrice())
                ->setLocale($event->getLocale())
                ->setSaleLabel($event->getSaleLabel())
                ->setTitle($event->getTitle())
                ->setDescription($event->getDescription())
                ->setChapo($event->getChapo())
                ->setPostscriptum($event->getPostscriptum())
                ->save()
            ;

            $event->setSale($sale);
        }
    }

    /**
     * Toggle Sale activity
     *
     * @param SaleToggleActivityEvent $event
     */
    public function toggleActivity(SaleToggleActivityEvent $event)
    {
        $sale = $event->getSale();

        $sale
            ->setDispatcher($event->getDispatcher())
            ->setActive(!$sale->getActive())
            ->save();

        $event->setSale($sale);

        // TODO : update product status
    }

    public function delete(SaleDeleteEvent $event)
    {
        if (null !== $sale = SaleQuery::create()->findPk($event->getSaleId())) {

            $sale->setDispatcher($event->getDispatcher())->delete();

            $event->setSale($sale);
        }

        // TODO : update product status
    }

    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::SALE_CREATE     => array('create', 128),
            TheliaEvents::SALE_UPDATE     => array('update', 128),
            TheliaEvents::SALE_DELETE     => array('delete', 128),

            TheliaEvents::SALE_TOGGLE_ACTIVITY => array('toggleActivity', 128),
        );
    }
}
