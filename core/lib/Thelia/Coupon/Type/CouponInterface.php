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

namespace Thelia\Coupon\Type;

use Propel\Runtime\Collection\ObjectCollection;
use Thelia\Condition\ConditionCollection;
use Thelia\Coupon\FacadeInterface;
use Thelia\Exception\InvalidConditionException;

/**
 * Represents a Coupon ready to be processed in a Checkout process.
 *
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
interface CouponInterface
{
    /**
     * Get I18n name.
     */
    public function getName(): string;

    /**
     * Get I18n tooltip.
     */
    public function getToolTip(): string;

    /**
     * Get Coupon Manager service Id.
     */
    public function getServiceId(): string;

    /**
     * Set Coupon.
     *
     * @param FacadeInterface  $facade                     Provides necessary value from Thelia
     * @param string           $code                       Coupon code (ex: XMAS)
     * @param string           $title                      Coupon title (ex: Coupon for XMAS)
     * @param string           $shortDescription           Coupon short description
     * @param string           $description                Coupon description
     * @param array            $effects                    Coupon effects params
     * @param bool             $isCumulative               If Coupon is cumulative
     * @param bool             $isRemovingPostage          If Coupon is removing postage
     * @param bool             $isAvailableOnSpecialOffers If available on Product already
     *                                                     on special offer price
     * @param bool             $isEnabled                  False if Coupon is disabled by admin
     * @param int              $maxUsage                   How many usage left
     * @param Datetime         $expirationDate             When the Code is expiring
     * @param ObjectCollection $freeShippingForCountries   list of countries which shipping is free. All if empty
     * @param ObjectCollection $freeShippingForModules     list of modules for which shipping is free. All if empty
     * @param bool             $perCustomerUsageCount      true if usage count is per customer only
     */
    public function set(
        FacadeInterface $facade,
        string $code,
        string $title,
        string $shortDescription,
        string $description,
        array $effects,
        bool $isCumulative,
        bool $isRemovingPostage,
        bool $isAvailableOnSpecialOffers,
        bool $isEnabled,
        int $maxUsage,
        DateTime $expirationDate,
        ObjectCollection $freeShippingForCountries,
        ObjectCollection $freeShippingForModules,
        bool $perCustomerUsageCount,
    );

    /**
     * Return Coupon code (ex: XMAS).
     */
    public function getCode(): string;

    /**
     * Return Coupon title (ex: Coupon for XMAS).
     */
    public function getTitle(): string;

    /**
     * Return Coupon short description.
     */
    public function getShortDescription(): string;

    /**
     * Return Coupon description.
     */
    public function getDescription(): string;

    /**
     * If Coupon is cumulative or prevent any accumulation
     * If is cumulative you can sum Coupon effects
     * If not cancel all other Coupon and take the last given.
     */
    public function isCumulative(): bool;

    /**
     * If Coupon is removing Checkout Postage.
     */
    public function isRemovingPostage(): bool;

    /**
     * Return condition to validate the Coupon or not.
     *
     * @return ConditionCollection A set of ConditionInterface
     */
    public function getConditions(): ConditionCollection;

    /**
     * Replace the existing Conditions by those given in parameter
     * If one Condition is badly implemented, no Condition will be added.
     *
     * @param ConditionCollection $conditions ConditionInterface to add
     *
     * @return $this
     *
     * @throws InvalidConditionException
     */
    public function setConditions(ConditionCollection $conditions);

    /**
     * Return Coupon expiration date.
     */
    public function getExpirationDate(): DateTime;

    /**
     * Check if the Coupon can be used against a
     * product already with a special offer price.
     */
    public function isAvailableOnSpecialOffers(): bool;

    /**
     * Check if the Coupon can be used against a
     * product already with a special offer price.
     */
    public function getPerCustomerUsageCount(): bool;

    /**
     * Check if Coupon has been disabled by admin.
     */
    public function isEnabled(): bool;

    /**
     * Return how many time the Coupon can be used again
     * Ex : -1 unlimited.
     */
    public function getMaxUsage(): int;

    /**
     * Check if the Coupon is already Expired.
     */
    public function isExpired(): bool;

    /**
     * Return an amount thant will be subtracted to the cart total, or zero.
     *
     * This method could also perform something else than the calculating an amount to subtract from the cart. It may
     * add a product to the cart, for example. In this case, an amount of 0 will be returned.
     *
     * WARNING: this method could be called several times, so perform suitable checks before performing cart
     * manipulations, so that the coupon effect will not be applied several times.
     *
     * @return float Amount removed from the cart total
     */
    public function exec(): float;

    /**
     * Check if the current Coupon is matching its conditions
     * Thelia variables are given by the FacadeInterface.
     */
    public function isMatching(): bool;

    /**
     * Draw the input displayed in the BackOffice
     * allowing Admin to set its Coupon effect.
     *
     * @return string HTML string
     */
    public function drawBackOfficeInputs(): string;

    /**
     * @return array list of country IDs for which shipping is free. All if empty
     */
    public function getFreeShippingForCountries(): array;

    /**
     * @return array list of module IDs for which shipping is free. All if empty
     */
    public function getFreeShippingForModules(): array;

    /**
     * Create the effect array from the list of fields.
     *
     * @param array $data the input form data (e.g. $form->getData())
     *
     * @return array a filedName => fieldValue array
     */
    public function getEffects(array $data): array;

    /**
     * Clear all the data the coupon may have stored, called after an order is completed.
     */
    public function clear();

    /**
     * @return bool true if the coupon is currently in use in the current order process, false otherwise
     */
    public function isInUse(): bool;
}
