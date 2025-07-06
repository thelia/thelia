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

use InvalidArgumentException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionEvaluator;
use Thelia\Condition\ConditionOrganizerInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Coupon\FacadeInterface;
use Thelia\Exception\InvalidConditionException;
use Thelia\Form\CouponCreationForm;
use Thelia\Model\CouponModule;

/**
 * Assist in writing a CouponInterface.
 *
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
abstract class CouponAbstract implements CouponInterface
{
    /**
     * The dataset name for all coupon specific input fields, that do not appear in the CouPonCreationForm form.
     *
     * In the input form, these fields have to be created like:
     *
     *    thelia_coupon_specific[my_field, thelia_coupon_creation_extended[my_other_field]
     *
     * use the makeCouponField() method to do that safely.
     */
    public const COUPON_DATASET_NAME = 'coupon_specific';

    /**
     * A standard 'amount' filed name, thant can be used in coupons which extends this class.
     */
    public const AMOUNT_FIELD_NAME = 'amount';

    protected Translator|TranslatorInterface $translator;

    protected ConditionOrganizerInterface $organizer;

    protected ConditionCollection $conditions;

    protected ConditionEvaluator $conditionEvaluator;

    protected string $serviceId;

    protected int|float $amount = 0;

    protected array $effects = ['amount' => 0];

    protected string $code;

    protected string $title;

    protected string $shortDescription;

    protected string $description;

    protected bool $isEnabled = false;

    protected DateTime $startDate;

    protected DateTime $expirationDate;

    protected bool $isCumulative = false;

    protected bool $isRemovingPostage = false;

    protected int $maxUsage = -1;

    protected bool $isAvailableOnSpecialOffers = false;

    protected array $freeShippingForCountries = [];

    /** @var CouponModule[] */
    protected array $freeShippingForModules = [];

    protected bool $perCustomerUsageCount;

    /**
     * Constructor.
     *
     * @param FacadeInterface $facade Service facade
     */
    public function __construct(protected FacadeInterface $facade)
    {
        $this->translator = $this->facade->getTranslator();
        $this->conditionEvaluator = $this->facade->getConditionEvaluator();
    }

    /**
     * Set Condition Organizer.
     */
    public function setOrganizer(ConditionOrganizerInterface $organizer): static
    {
        $this->organizer = $organizer;

        return $this;
    }

    public function set(
        FacadeInterface $facade,
        $code,
        $title,
        $shortDescription,
        $description,
        array $effects,
        $isCumulative,
        $isRemovingPostage,
        $isAvailableOnSpecialOffers,
        $isEnabled,
        $maxUsage,
        DateTime $expirationDate,
        $freeShippingForCountries,
        $freeShippingForModules,
        $perCustomerUsageCount,
    ): static {
        $this->code = $code;
        $this->title = $title;
        $this->shortDescription = $shortDescription;
        $this->description = $description;

        $this->isCumulative = $isCumulative;
        $this->isRemovingPostage = $isRemovingPostage;

        $this->isAvailableOnSpecialOffers = $isAvailableOnSpecialOffers;
        $this->isEnabled = $isEnabled;
        $this->maxUsage = $maxUsage;
        $this->expirationDate = $expirationDate;
        $this->facade = $facade;

        $this->effects = $effects;
        // Amount is now optional.
        $this->amount = $effects[self::AMOUNT_FIELD_NAME] ?? 0;

        $this->freeShippingForCountries = $freeShippingForCountries;
        $this->freeShippingForModules = $freeShippingForModules;
        $this->perCustomerUsageCount = $perCustomerUsageCount;

        return $this;
    }

    public function setPerCustomerUsageCount(bool $perCustomerUsageCount): static
    {
        $this->perCustomerUsageCount = $perCustomerUsageCount;

        return $this;
    }

    public function getPerCustomerUsageCount(): bool
    {
        return $this->perCustomerUsageCount;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getShortDescription(): string
    {
        return $this->shortDescription;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * If Coupon is cumulative or prevent any accumulation
     * If is cumulative you can sum Coupon effects
     * If not cancel all other Coupon and take the last given.
     */
    public function isCumulative(): bool
    {
        return $this->isCumulative;
    }

    /**
     * If Coupon is removing Checkout Postage.
     */
    public function isRemovingPostage(): bool
    {
        return $this->isRemovingPostage;
    }

    public function getFreeShippingForCountries(): array
    {
        return $this->freeShippingForCountries;
    }

    public function getFreeShippingForModules(): array
    {
        return $this->freeShippingForModules;
    }

    public function exec(): float|int
    {
        return $this->amount;
    }

    public function getConditions(): ConditionCollection
    {
        return clone $this->conditions;
    }

    /**
     * Replace the existing Conditions by those given in parameter
     * If one Condition is badly implemented, no Condition will be added.
     *
     * @throws InvalidConditionException
     */
    public function setConditions(ConditionCollection $conditions): static
    {
        $this->conditions = $conditions;

        return $this;
    }

    /**
     * Return Coupon expiration date.
     */
    public function getExpirationDate(): DateTime
    {
        return clone $this->expirationDate;
    }

    /**
     * Check if the Coupon can be used against a
     * product already with a special offer price.
     */
    public function isAvailableOnSpecialOffers(): bool
    {
        return $this->isAvailableOnSpecialOffers;
    }

    /**
     * Check if Coupon has been disabled by admin.
     */
    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    /**
     * Return how many time the Coupon can be used again
     * Ex : -1 unlimited.
     */
    public function getMaxUsage(): int
    {
        return $this->maxUsage;
    }

    /**
     * Check if the Coupon is already Expired.
     */
    public function isExpired(): bool
    {
        $ret = true;

        $now = new \DateTime();
        if ($this->expirationDate > $now) {
            $ret = false;
        }

        return $ret;
    }

    /**
     * Get Coupon Manager service Id.
     */
    public function getServiceId(): string
    {
        return $this->serviceId ?? static::class;
    }

    /**
     * Check if the current state of the application is matching this Coupon conditions
     * Thelia variables are given by the FacadeInterface.
     */
    public function isMatching(): bool
    {
        return $this->conditionEvaluator->isMatching($this->conditions);
    }

    /**
     * This is the field label than will be displayed in the form.
     * This method should be overridden to be useful.
     *
     * For backward compatibility only.
     */
    public function getInputName(): string
    {
        return 'Please override getInputName() method';
    }

    /**
     * Draw the input displayed in the BackOffice
     * allowing Admin to set its Coupon effect
     * Override this method to do something useful.
     */
    public function drawBackOfficeInputs(): string
    {
        return $this->facade->getParser()->render('coupon/type-fragments/remove-x.html', [
            'label' => $this->getInputName(),
            'fieldId' => self::AMOUNT_FIELD_NAME,
            'fieldName' => $this->makeCouponFieldName(self::AMOUNT_FIELD_NAME),
            'value' => $this->amount,
        ]);
    }

    /**
     * This methods checks a field value. If the field has a correct value, this value is returned
     * Otherwise, an InvalidArgumentException describing the problem should be thrown.
     *
     * This method should be overriden to be useful.
     *
     * @throws \InvalidArgumentException if the field value is not valid
     */
    protected function checkCouponFieldValue(string $fieldName, string $fieldValue): string
    {
        return $fieldValue;
    }

    /**
     * A helper to get the value of a standard field name.
     *
     * @throws \InvalidArgumentException if the field is not found, and no default value has been defined
     */
    protected function getCouponFieldValue(string $fieldName, array $data, mixed $defaultValue = null): mixed
    {
        $couponSpecificData = json_decode((string) $data[self::COUPON_DATASET_NAME], true);
        if (isset($couponSpecificData[$fieldName])) {
            return $this->checkCouponFieldValue(
                $fieldName,
                $couponSpecificData[$fieldName]
            );
        }

        if (null !== $defaultValue) {
            return $defaultValue;
        }

        throw new \InvalidArgumentException(\sprintf('The coupon field name %s was not found in the coupon form', $fieldName));
    }

    /**
     * A helper to create an standard field name that will be used in the coupon form.
     */
    protected function makeCouponFieldName(string $fieldName): string
    {
        return \sprintf('%s[%s][%s]', CouponCreationForm::COUPON_CREATION_FORM_NAME, self::COUPON_DATASET_NAME, $fieldName);
    }

    /**
     * Return a list of the fields name for this coupon.
     */
    protected function getFieldList(): array
    {
        return [self::AMOUNT_FIELD_NAME];
    }

    /**
     * Create the effect array from the list of fields.
     */
    public function getEffects($data): array
    {
        $effects = [];

        foreach ($this->getFieldList() as $fieldName) {
            $effects[$fieldName] = $this->getCouponFieldValue($fieldName, $data);
        }

        return $effects;
    }

    public function clear(): void
    {
        // Does nothing. Override this function as needed.
    }

    public function isInUse(): bool
    {
        return \in_array($this->code, $this->facade->getRequest()->getSession()->getConsumedCoupons(), true);
    }
}
