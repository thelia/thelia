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

namespace Thelia\Condition\Implementation;

use Thelia\Condition\Exception\InvalidConditionOperatorException;
use Thelia\Condition\Exception\InvalidConditionValueException;
use Thelia\Condition\SerializableCondition;
use Thelia\Domain\Promotion\Coupon\FacadeInterface;

/**
 * Manage how the application checks its state in order to check if it matches the implemented condition.
 *
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
interface ConditionInterface
{
    /**
     * Constructor.
     *
     * @param FacadeInterface $adapter Service adapter
     */
    public function __construct(FacadeInterface $adapter);

    /**
     * Get Condition Service id.
     */
    public function getServiceId(): string;

    /**
     * Check validators relevancy and store them.
     *
     * @param array $operators an array of operators (greater than, less than, etc.) entered in the condition parameter input form, one for each condition defined by the Condition
     * @param array $values    an array of values entered in in the condition parameter input form, one for each condition defined by the Condition
     *
     * @return $this
     *
     * @throws InvalidConditionOperatorException
     * @throws InvalidConditionValueException
     */
    public function setValidatorsFromForm(array $operators, array $values);

    /**
     * Test if the current application state matches conditions.
     */
    public function isMatching(): bool;

    /**
     * Get I18n name.
     */
    public function getName(): string;

    /**
     * Get I18n tooltip
     * Explain in detail what the Condition checks.
     */
    public function getToolTip(): string;

    /**
     * Get I18n summary
     * Explain briefly the condition with given values.
     */
    public function getSummary(): string;

    /**
     * Return all validators.
     */
    public function getValidators(): array;

    /**
     * Return a serializable Condition.
     */
    public function getSerializableCondition(): SerializableCondition;

    /**
     * Draw the input displayed in the BackOffice
     * allowing Admin to set its Coupon Conditions.
     *
     * @return string HTML string
     */
    public function drawBackOfficeInputs(): string;
}
