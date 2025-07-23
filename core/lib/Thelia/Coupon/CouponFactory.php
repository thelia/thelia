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

namespace Thelia\Coupon;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Condition\ConditionFactory;
use Thelia\Coupon\Type\CouponInterface;
use Thelia\Exception\CouponExpiredException;
use Thelia\Exception\CouponNotReleaseException;
use Thelia\Exception\CouponNoUsageLeftException;
use Thelia\Exception\InactiveCouponException;
use Thelia\Exception\InvalidConditionException;
use Thelia\Exception\UnmatchableConditionException;
use Thelia\Model\Coupon;
use Thelia\Model\Customer;

/**
 * Generate a CouponInterface.
 *
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
class CouponFactory
{
    /**
     * Constructor.
     */
    public function __construct(
        /** @var ContainerInterface Service Container */
        protected ContainerInterface $container,
        protected FacadeInterface $facade,
        /** @var ConditionFactory Provide necessary value from Thelia */
        protected ConditionFactory $conditionFactory,
    ) {
    }

    /**
     * Build a CouponInterface from its database data.
     *
     * @param string $couponCode Coupon code ex: XMAS
     *
     * @throws CouponExpiredException
     * @throws CouponNoUsageLeftException
     * @throws CouponNotReleaseException
     */
    public function buildCouponFromCode(string $couponCode): CouponInterface
    {
        $couponModel = $this->facade->findOneCouponByCode($couponCode);

        // check if coupon is enabled
        if (!$couponModel->getIsEnabled()) {
            throw new InactiveCouponException($couponCode);
        }

        $nowDateTime = new \DateTime();

        // Check coupon start date
        if (null !== $couponModel->getStartDate() && $couponModel->getStartDate() > $nowDateTime) {
            throw new CouponNotReleaseException($couponCode);
        }

        // Check coupon expiration date
        if ($couponModel->getExpirationDate() < $nowDateTime) {
            throw new CouponExpiredException($couponCode);
        }

        // Check coupon usage count
        if (!$couponModel->isUsageUnlimited()) {
            if (!($customer = $this->facade->getCustomer()) instanceof Customer) {
                throw new UnmatchableConditionException(UnmatchableConditionException::getMissingCustomerMessage());
            }

            if ($couponModel->getUsagesLeft($customer->getId()) <= 0) {
                throw new CouponNoUsageLeftException($couponCode);
            }
        }

        /** @var CouponInterface $couponInterface */
        $couponInterface = $this->buildCouponFromModel($couponModel);

        if ($couponInterface && 0 === $couponInterface->getConditions()->count()) {
            throw new InvalidConditionException($couponInterface::class);
        }

        return $couponInterface;
    }

    /**
     * Build a CouponInterface from its Model data contained in the DataBase.
     *
     * @param Coupon $model Database data
     *
     * @return CouponInterface ready to use CouponInterface object instance
     */
    public function buildCouponFromModel(Coupon $model): CouponInterface
    {
        $isCumulative = 1 === $model->getIsCumulative();
        $isRemovingPostage = 1 === $model->getIsRemovingPostage();

        if (!$this->container->has($model->getType())) {
            return false;
        }

        /** @var CouponInterface $couponManager */
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
            $model->getPerCustomerUsageCount(),
        );

        $conditions = $this->conditionFactory->unserializeConditionCollection(
            $model->getSerializedConditions(),
        );

        $couponManager->setConditions($conditions);

        return clone $couponManager;
    }
}
