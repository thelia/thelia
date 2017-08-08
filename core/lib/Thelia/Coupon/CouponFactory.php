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

namespace Thelia\Coupon;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Condition\ConditionFactory;
use Thelia\Coupon\Type\CouponInterface;
use Thelia\Exception\CouponNotReleaseException;
use Thelia\Exception\InactiveCouponException;
use Thelia\Exception\CouponExpiredException;
use Thelia\Exception\CouponNoUsageLeftException;
use Thelia\Exception\InvalidConditionException;
use Thelia\Exception\UnmatchableConditionException;
use Thelia\Model\Coupon;

/**
 * Generate a CouponInterface
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class CouponFactory
{
    /** @var ContainerInterface Service Container */
    protected $container = null;

    /** @var  FacadeInterface Provide necessary value from Thelia*/
    protected $facade;

    /**
     * Constructor
     *
     * @param ContainerInterface $container Service container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->facade = $container->get('thelia.facade');
    }

    /**
     * Build a CouponInterface from its database data
     *
     * @param string $couponCode Coupon code ex: XMAS
     * @return CouponInterface
     * @throws CouponExpiredException
     * @throws CouponNoUsageLeftException
     * @throws CouponNotReleaseException
     */
    public function buildCouponFromCode($couponCode)
    {
        /** @var Coupon $couponModel */
        $couponModel = $this->facade->findOneCouponByCode($couponCode);
        if ($couponModel === null) {
            return false;
        }

        // check if coupon is enabled
        if (!$couponModel->getIsEnabled()) {
            throw new InactiveCouponException($couponCode);
        }

        $nowDateTime = new \DateTime();

        // Check coupon start date
        if ($couponModel->getStartDate() !== null && $couponModel->getStartDate() > $nowDateTime) {
            throw new CouponNotReleaseException($couponCode);
        }

        // Check coupon expiration date
        if ($couponModel->getExpirationDate() < $nowDateTime) {
            throw new CouponExpiredException($couponCode);
        }

        // Check coupon usage count
        if (! $couponModel->isUsageUnlimited()) {
            if (null === $customer = $this->facade->getCustomer()) {
                throw new UnmatchableConditionException($couponCode);
            }

            if ($couponModel->getUsagesLeft($customer->getId()) <= 0) {
                throw new CouponNoUsageLeftException($couponCode);
            }
        }

        /** @var CouponInterface $couponInterface */
        $couponInterface = $this->buildCouponFromModel($couponModel);

        if ($couponInterface && $couponInterface->getConditions()->count() == 0) {
            throw new InvalidConditionException(
                get_class($couponInterface)
            );
        }

        return $couponInterface;
    }

    /**
     * Build a CouponInterface from its Model data contained in the DataBase
     *
     * @param Coupon $model Database data
     *
     * @return CouponInterface ready to use CouponInterface object instance
     */
    public function buildCouponFromModel(Coupon $model)
    {
        $isCumulative = ($model->getIsCumulative() == 1 ? true : false);
        $isRemovingPostage = ($model->getIsRemovingPostage() == 1 ? true : false);

        if (!$this->container->has($model->getType())) {
            return false;
        }

        /** @var CouponInterface $couponManager*/
        $couponManager = $this->container->get($model->getType());
        $couponManager->set(
            $this->facade,
            $model->getCode(),
            $model->getTitle(),
            $model->getShortDescription(),
            $model->getDescription(),
            $model->getEffects(),
            $isCumulative,
            $isRemovingPostage,
            $model->getIsAvailableOnSpecialOffers(),
            $model->getIsEnabled(),
            $model->getMaxUsage(),
            $model->getExpirationDate(),
            $model->getFreeShippingForCountries(),
            $model->getFreeShippingForModules(),
            $model->getPerCustomerUsageCount()
        );

        /** @var ConditionFactory $conditionFactory */
        $conditionFactory = $this->container->get('thelia.condition.factory');
        $conditions = $conditionFactory->unserializeConditionCollection(
            $model->getSerializedConditions()
        );

        $couponManager->setConditions($conditions);

        return clone $couponManager;
    }
}
