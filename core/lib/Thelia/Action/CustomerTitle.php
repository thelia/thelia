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

use Propel\Runtime\Propel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\CustomerTitle\CustomerTitleEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\CustomerTitle as CustomerTitleModel;
use Thelia\Model\Map\CustomerTitleTableMap;

/**
 * Class CustomerTitle.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class CustomerTitle extends BaseAction implements EventSubscriberInterface
{
    public function create(CustomerTitleEvent $event): void
    {
        $this->createOrUpdate($event, new CustomerTitleModel());
    }

    public function update(CustomerTitleEvent $event): void
    {
        $this->checkCustomerTitle($event);

        $this->createOrUpdate($event, $event->getCustomerTitle());
    }

    public function delete(CustomerTitleEvent $event): void
    {
        $this->checkCustomerTitle($event);

        $con = Propel::getConnection(CustomerTitleTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            $event->getCustomerTitle()->delete();

            $con->commit();
        } catch (\Exception $e) {
            $con->rollBack();

            throw $e;
        }

        $event->setCustomerTitle(null);
    }

    protected function checkCustomerTitle(CustomerTitleEvent $event): void
    {
        if (null === $event->getCustomerTitle()) {
            throw new \LogicException(
                'You must set the customer title before its update'
            );
        }
    }

    protected function createOrUpdate(CustomerTitleEvent $event, CustomerTitleModel $customerTitle): void
    {
        $con = Propel::getConnection(CustomerTitleTableMap::DATABASE_NAME);
        $con->beginTransaction();

        $i18n = $customerTitle->getTranslation($event->getLocale(), $con);

        try {
            $i18n
                ->setShort($event->getShort())
                ->setLong($event->getLong())
            ;

            $customerTitle->save($con);

            if ($event->isDefault()) {
                $customerTitle->toggleDefault($con);
                $event->setDefault(false);
            }

            $con->commit();
        } catch (\Exception $e) {
            $con->rollBack();

            throw $e;
        }

        $event->setCustomerTitle($customerTitle);
    }

    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::CUSTOMER_TITLE_CREATE => ['create'],
            TheliaEvents::CUSTOMER_TITLE_UPDATE => ['update'],
            TheliaEvents::CUSTOMER_TITLE_DELETE => ['delete'],
        ];
    }
}
