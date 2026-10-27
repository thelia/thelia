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

use Thelia\Condition\Exception\InvalidConditionValueException;
use Thelia\Condition\Operators;
use Thelia\Domain\Customer\Service\CustomerGroupResolver;
use Thelia\Domain\Promotion\Coupon\FacadeInterface;
use Thelia\Model\CustomerGroup;
use Thelia\Model\CustomerGroupQuery;

/**
 * Check the customer group of the buyer against a list of groups.
 *
 * The group is the one {@see CustomerGroupResolver} answers, as everywhere else in the shop: a
 * visitor with no account and a guest belong to the default group, so the condition is evaluated
 * for them instead of being unmatchable, and a guest row put in another group gets nothing more.
 */
class ForSomeCustomerGroups extends ConditionAbstract
{
    public const CUSTOMER_GROUPS_LIST = 'customer_groups';

    private readonly CustomerGroupResolver $customerGroupResolver;

    /**
     * The resolver is optional in the signature only because ConditionInterface fixes the
     * constructor to the facade alone, and PHP refuses a longer one: it is required all the
     * same. The container passes the shared one, a resolver built here would have a memory
     * of its own that the kernel never resets.
     */
    public function __construct(FacadeInterface $facade, ?CustomerGroupResolver $customerGroupResolver = null)
    {
        $this->customerGroupResolver = $customerGroupResolver ?? throw new \InvalidArgumentException(\sprintf('%s needs the shared CustomerGroupResolver: take the condition from the container.', self::class));

        $this->availableOperators = [
            self::CUSTOMER_GROUPS_LIST => [
                Operators::IN,
                Operators::OUT,
            ],
        ];

        parent::__construct($facade);
    }

    public function getServiceId(): string
    {
        return 'thelia.condition.for_some_customer_groups';
    }

    public function setValidatorsFromForm(array $operators, array $values): static
    {
        $this->checkComparisonOperatorValue($operators, self::CUSTOMER_GROUPS_LIST);

        // Use default values if data is not defined.
        if (!isset($operators[self::CUSTOMER_GROUPS_LIST]) || !isset($values[self::CUSTOMER_GROUPS_LIST])) {
            $operators[self::CUSTOMER_GROUPS_LIST] = Operators::IN;
            $values[self::CUSTOMER_GROUPS_LIST] = [];
        }

        // Be sure that the value is an array, make one if required
        if (!\is_array($values[self::CUSTOMER_GROUPS_LIST])) {
            $values[self::CUSTOMER_GROUPS_LIST] = [$values[self::CUSTOMER_GROUPS_LIST]];
        }

        // Check that at least one group is selected
        if (empty($values[self::CUSTOMER_GROUPS_LIST])) {
            throw new InvalidConditionValueException(self::class, self::CUSTOMER_GROUPS_LIST);
        }

        $this->operators = [self::CUSTOMER_GROUPS_LIST => $operators[self::CUSTOMER_GROUPS_LIST]];
        // The back-office posts the ids as strings and the comparison is strict: store them as integers.
        $this->values = [self::CUSTOMER_GROUPS_LIST => array_map('\intval', $values[self::CUSTOMER_GROUPS_LIST])];

        return $this;
    }

    public function isMatching(): bool
    {
        $customerGroupId = $this->customerGroupResolver->groupIdOf($this->facade->getCustomer());

        return $this->conditionValidator->variableOpComparison(
            $customerGroupId,
            $this->operators[self::CUSTOMER_GROUPS_LIST],
            $this->values[self::CUSTOMER_GROUPS_LIST],
        );
    }

    public function getName(): string
    {
        return $this->translator->trans(
            'For one or more customer groups',
            [],
        );
    }

    public function getToolTip(): string
    {
        return $this->translator->trans(
            'The coupon applies to some customer groups only',
            [],
        );
    }

    public function getSummary(): string
    {
        $i18nOperator = Operators::getI18n(
            $this->translator,
            $this->operators[self::CUSTOMER_GROUPS_LIST],
        );

        $groupStrList = '';

        /** @var CustomerGroup $group */
        foreach (CustomerGroupQuery::create()->findPks($this->values[self::CUSTOMER_GROUPS_LIST]) as $group) {
            $title = $group->setLocale($this->getCurrentLocale())->getTitle();
            $groupStrList .= $this->escapeSummaryValue('' === (string) $title ? $group->getCode() : $title).', ';
        }

        return $this->translator->trans(
            'Customer group is %op% <strong>%customer_group_list%</strong>',
            [
                '%customer_group_list%' => rtrim($groupStrList, ', '),
                '%op%' => $i18nOperator,
            ],
        );
    }

    protected function generateInputs(): array
    {
        return [
            self::CUSTOMER_GROUPS_LIST => [
                'availableOperators' => $this->availableOperators[self::CUSTOMER_GROUPS_LIST],
                'value' => '',
                'selectedOperator' => Operators::IN,
            ],
        ];
    }

    public function drawBackOfficeInputs(): string
    {
        return $this->facade->getParser()->render(
            'coupon/condition-fragments/customer-groups-condition.html',
            [
                'operatorSelectHtml' => $this->drawBackOfficeInputOperators(self::CUSTOMER_GROUPS_LIST),
                'customer_groups_field_name' => self::CUSTOMER_GROUPS_LIST,
                'values' => $this->values[self::CUSTOMER_GROUPS_LIST] ?? [],
            ],
        );
    }
}
