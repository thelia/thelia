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
use Thelia\Condition\Exception\UnmatchableConditionException;
use Thelia\Condition\Operators;
use Thelia\Domain\Promotion\Coupon\FacadeInterface;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;

/**
 * Check the cart against the delivery module the customer has chosen.
 */
class MatchDeliveryModules extends ConditionAbstract
{
    public const MODULES_LIST = 'delivery_modules';

    public function __construct(FacadeInterface $facade)
    {
        $this->availableOperators = [
            self::MODULES_LIST => [
                Operators::IN,
                Operators::OUT,
            ],
        ];

        parent::__construct($facade);
    }

    public function getServiceId(): string
    {
        return 'thelia.condition.match_delivery_modules';
    }

    public function setValidatorsFromForm(array $operators, array $values): self|static
    {
        $this->checkComparisonOperatorValue($operators, self::MODULES_LIST);

        if (!isset($operators[self::MODULES_LIST]) || !isset($values[self::MODULES_LIST])) {
            $operators[self::MODULES_LIST] = Operators::IN;
            $values[self::MODULES_LIST] = [];
        }

        if (!\is_array($values[self::MODULES_LIST])) {
            $values[self::MODULES_LIST] = [$values[self::MODULES_LIST]];
        }

        if (empty($values[self::MODULES_LIST])) {
            throw new InvalidConditionValueException(self::class, self::MODULES_LIST);
        }

        $this->operators = [self::MODULES_LIST => $operators[self::MODULES_LIST]];
        // The comparison against Cart::getDeliveryModuleId() is strict: store ids as integers.
        $this->values = [
            self::MODULES_LIST => array_map('\intval', $values[self::MODULES_LIST]),
        ];

        return $this;
    }

    public function isMatching(): bool
    {
        $deliveryModuleId = $this->facade->getCart()?->getDeliveryModuleId();

        if (null === $deliveryModuleId) {
            throw new UnmatchableConditionException($this->translator->trans('You must choose a delivery method before using this coupon.'));
        }

        return $this->conditionValidator->variableOpComparison(
            (int) $deliveryModuleId,
            $this->operators[self::MODULES_LIST],
            $this->values[self::MODULES_LIST],
        );
    }

    public function getName(): string
    {
        return $this->translator->trans(
            'Delivery method',
            [],
        );
    }

    public function getToolTip(): string
    {
        return $this->translator->trans(
            'The coupon applies to the selected delivery methods',
            [],
        );
    }

    public function getSummary(): string
    {
        $i18nOperator = Operators::getI18n(
            $this->translator,
            $this->operators[self::MODULES_LIST] ?? Operators::IN,
        );

        $moduleStrList = '';

        if (null !== $moduleList = ModuleQuery::create()->findPks($this->values[self::MODULES_LIST] ?? [])) {
            /** @var Module $module */
            foreach ($moduleList as $module) {
                // The summary is rendered as HTML in the back-office: a module title is
                // shopkeeper data, not markup.
                $moduleStrList .= htmlspecialchars((string) $module->setLocale($this->getCurrentLocale())->getTitle()).', ';
            }

            $moduleStrList = rtrim($moduleStrList, ', ');
        }

        return $this->translator->trans(
            'Only if the order is shipped with %op% <strong>%modules_list%</strong>',
            [
                '%modules_list%' => $moduleStrList,
                '%op%' => $i18nOperator,
            ],
        );
    }

    protected function generateInputs(): array
    {
        return [
            self::MODULES_LIST => [
                'availableOperators' => $this->availableOperators[self::MODULES_LIST],
                'value' => '',
                'selectedOperator' => Operators::IN,
            ],
        ];
    }

    public function drawBackOfficeInputs(): string
    {
        return $this->facade->getParser()->render(
            'coupon/condition-fragments/delivery-modules-condition.html',
            [
                'operatorSelectHtml' => $this->drawBackOfficeInputOperators(self::MODULES_LIST),
                'modules_field_name' => self::MODULES_LIST,
                'values' => $this->values[self::MODULES_LIST] ?? [],
                'moduleLabel' => $this->translator->trans('Delivery method is'),
            ],
        );
    }
}
