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

namespace BackOfficeDefaultTwigBundle\Service\Coupon;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Implementation\AbstractMatchCountries;
use Thelia\Condition\Implementation\CartContainsCategories;
use Thelia\Condition\Implementation\CartContainsProducts;
use Thelia\Condition\Implementation\ConditionInterface;
use Thelia\Condition\Implementation\ForSomeCustomers;
use Thelia\Condition\Implementation\MatchForTotalAmount;
use Thelia\Condition\Implementation\MatchForXArticles;
use Thelia\Condition\Implementation\StartDate;
use Thelia\Model\CategoryQuery;
use Thelia\Model\CountryQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\ProductQuery;
use Twig\Environment;

/**
 * Renders the BO Twig fragments for coupon conditions and rebuilds a condition from request data.
 *
 * Native conditions get a dedicated Twig partial that mirrors the legacy Smarty fragment markup.
 * Unknown condition service ids fall back to a generic placeholder so the editor still loads.
 */
final readonly class CouponConditionsRenderer
{
    public function __construct(
        private Environment $twig,
        private TranslatorInterface $translator,
        #[Autowire(service: 'thelia.condition.factory')]
        private ConditionFactory $conditionFactory,
        #[Autowire(service: 'service_container')]
        private ContainerInterface $container,
    ) {
    }

    public function renderEmptyInputs(string $conditionId): string
    {
        if ($conditionId === '' || $conditionId === '0') {
            return $this->twig->render('@BackOfficeDefaultTwig/coupon/condition-input-ajax.html.twig', [
                'inputs_drawn' => '',
                'condition_service_id' => '',
                'condition_index' => '',
            ]);
        }

        if (!$this->container->has($conditionId)) {
            return $this->twig->render('@BackOfficeDefaultTwig/coupon/condition-input-ajax.html.twig', [
                'inputs_drawn' => '',
                'condition_service_id' => '',
                'condition_index' => '',
            ]);
        }

        $condition = $this->container->get($conditionId);
        if (!$condition instanceof ConditionInterface) {
            return $this->twig->render('@BackOfficeDefaultTwig/coupon/condition-input-ajax.html.twig', [
                'inputs_drawn' => '',
                'condition_service_id' => '',
                'condition_index' => '',
            ]);
        }

        $params = $this->renderInputs($condition);

        return $this->twig->render('@BackOfficeDefaultTwig/coupon/condition-input-ajax.html.twig', [
            'inputs_drawn' => $params,
            'condition_service_id' => $condition->getServiceId(),
            'condition_index' => '',
        ]);
    }

    public function renderExistingInputs(ConditionInterface $condition, int $index): string
    {
        return $this->twig->render('@BackOfficeDefaultTwig/coupon/condition-input-ajax.html.twig', [
            'inputs_drawn' => $this->renderInputs($condition),
            'condition_service_id' => $condition->getServiceId(),
            'condition_index' => $index,
        ]);
    }

    public function buildConditionFromRequest(Request $request): ConditionInterface
    {
        $serviceId = (string) $request->request->get('categoryCondition');
        $operators = [];
        $values = [];

        foreach ($request->request->all() as $key => $input) {
            if (\is_array($input) && isset($input['operator'], $input['value'])) {
                $operators[$key] = $input['operator'];
                $values[$key] = $input['value'];
            }
        }

        return $this->conditionFactory->build($serviceId, $operators, $values);
    }

    private function renderInputs(ConditionInterface $condition): string
    {
        $template = $this->resolveTemplate($condition);
        if ($template === null) {
            return $this->twig->render('@BackOfficeDefaultTwig/coupon/condition-fragments/_unsupported.html.twig', [
                'name' => $condition->getName(),
                'service_id' => $condition->getServiceId(),
            ]);
        }

        return $this->twig->render($template, $this->buildParams($condition));
    }

    private function resolveTemplate(ConditionInterface $condition): ?string
    {
        return match (true) {
            $condition instanceof StartDate => '@BackOfficeDefaultTwig/coupon/condition-fragments/start-date-condition.html.twig',
            $condition instanceof MatchForTotalAmount => '@BackOfficeDefaultTwig/coupon/condition-fragments/cart-total-amount-condition.html.twig',
            $condition instanceof MatchForXArticles => '@BackOfficeDefaultTwig/coupon/condition-fragments/cart-item-count-condition.html.twig',
            $condition instanceof ForSomeCustomers => '@BackOfficeDefaultTwig/coupon/condition-fragments/customers-condition.html.twig',
            $condition instanceof AbstractMatchCountries => '@BackOfficeDefaultTwig/coupon/condition-fragments/countries-condition.html.twig',
            $condition instanceof CartContainsCategories => '@BackOfficeDefaultTwig/coupon/condition-fragments/cart-contains-categories-condition.html.twig',
            $condition instanceof CartContainsProducts => '@BackOfficeDefaultTwig/coupon/condition-fragments/cart-contains-products-condition.html.twig',
            default => null,
        };
    }

    /** @return array<string, mixed> */
    private function buildParams(ConditionInterface $condition): array
    {
        $dateFormat = $this->resolveDateFormat();
        $locale = $this->defaultLocale();
        $validators = $condition->getValidators();
        $setValues = $validators['setValues'] ?? [];

        $params = [
            'condition' => $condition,
            'service_id' => $condition->getServiceId(),
            'name' => $condition->getName(),
            'tool_tip' => $condition->getToolTip(),
            'date_format' => $dateFormat,
            'currency_symbol' => $this->currencySymbol(),
            'available_operators' => $this->operatorsFromValidators($validators),
            'validators' => $validators,
        ];

        return match (true) {
            $condition instanceof CartContainsProducts => array_merge($params, [
                'field_name' => CartContainsProducts::PRODUCTS_LIST,
                'selected_values' => $this->normalizeIdList($setValues[CartContainsProducts::PRODUCTS_LIST] ?? []),
                'products_choices' => $this->productChoices($locale),
            ]),
            $condition instanceof CartContainsCategories => array_merge($params, [
                'field_name' => CartContainsCategories::CATEGORIES_LIST,
                'selected_values' => $this->normalizeIdList($setValues[CartContainsCategories::CATEGORIES_LIST] ?? []),
                'categories_choices' => $this->categoryTreeChoices($locale),
            ]),
            $condition instanceof AbstractMatchCountries => array_merge($params, [
                'field_name' => AbstractMatchCountries::COUNTRIES_LIST,
                'selected_values' => $this->normalizeIdList($setValues[AbstractMatchCountries::COUNTRIES_LIST] ?? []),
                'countries_choices' => $this->countryChoices($locale),
                'country_label' => $this->countryLabelFor($condition),
            ]),
            $condition instanceof ForSomeCustomers => array_merge($params, [
                'field_name' => ForSomeCustomers::CUSTOMERS_LIST,
                'selected_values' => $this->normalizeIdList($setValues[ForSomeCustomers::CUSTOMERS_LIST] ?? []),
                'customers_choices' => $this->customerChoices($setValues[ForSomeCustomers::CUSTOMERS_LIST] ?? []),
            ]),
            default => $params,
        };
    }

    /**
     * @param mixed $values
     * @return list<int>
     */
    private function normalizeIdList(mixed $values): array
    {
        if (!\is_array($values)) {
            return [];
        }

        return array_values(array_map(static fn ($value): int => (int) $value, $values));
    }

    /** @return list<array{id: int, label: string}> */
    private function productChoices(string $locale): array
    {
        $rows = ProductQuery::create()->orderByPosition()->find();
        $choices = [];
        foreach ($rows as $product) {
            $product->setLocale($locale);
            $choices[] = ['id' => (int) $product->getId(), 'label' => (string) $product->getTitle()];
        }

        usort($choices, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        return $choices;
    }

    /** @return list<array{id: int, label: string, level: int, indent: string}> */
    private function categoryTreeChoices(string $locale): array
    {
        $tree = [];
        $this->walkCategoryTree(0, 0, $locale, $tree);

        return $tree;
    }

    /**
     * @param list<array{id: int, label: string, level: int, indent: string}> $tree
     */
    private function walkCategoryTree(int $parentId, int $level, string $locale, array &$tree): void
    {
        $children = CategoryQuery::create()
            ->filterByParent($parentId)
            ->orderByPosition()
            ->find();

        foreach ($children as $category) {
            $category->setLocale($locale);
            $tree[] = [
                'id' => (int) $category->getId(),
                'label' => (string) $category->getTitle(),
                'level' => $level,
                'indent' => str_repeat('— ', $level),
            ];
            $this->walkCategoryTree((int) $category->getId(), $level + 1, $locale, $tree);
        }
    }

    /** @return list<array{id: int, label: string}> */
    private function countryChoices(string $locale): array
    {
        $rows = CountryQuery::create()->filterByVisible(1)->find();
        $choices = [];
        foreach ($rows as $country) {
            $country->setLocale($locale);
            $choices[] = ['id' => (int) $country->getId(), 'label' => (string) $country->getTitle()];
        }

        usort($choices, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        return $choices;
    }

    /**
     * @param list<int|string>|mixed $alreadySelected
     * @return list<array{id: int, label: string}>
     */
    private function customerChoices(mixed $alreadySelected): array
    {
        $ids = $this->normalizeIdList($alreadySelected);
        $selectedRows = $ids === []
            ? []
            : CustomerQuery::create()->filterById($ids, \Propel\Runtime\ActiveQuery\Criteria::IN)->find();

        $latestRows = CustomerQuery::create()->orderByCreatedAt(\Propel\Runtime\ActiveQuery\Criteria::DESC)->limit(50)->find();

        $choices = [];
        $seen = [];
        foreach ($selectedRows as $customer) {
            $id = (int) $customer->getId();
            $seen[$id] = true;
            $choices[] = ['id' => $id, 'label' => trim(\sprintf('%s %s (%s)', (string) $customer->getLastname(), (string) $customer->getFirstname(), (string) $customer->getRef()))];
        }
        foreach ($latestRows as $customer) {
            $id = (int) $customer->getId();
            if (isset($seen[$id])) {
                continue;
            }
            $choices[] = ['id' => $id, 'label' => trim(\sprintf('%s %s (%s)', (string) $customer->getLastname(), (string) $customer->getFirstname(), (string) $customer->getRef()))];
        }

        usort($choices, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        return $choices;
    }

    private function countryLabelFor(ConditionInterface $condition): string
    {
        $serviceId = $condition->getServiceId();
        if (str_contains($serviceId, 'billing')) {
            return $this->translator->trans('Billing country is :');
        }
        if (str_contains($serviceId, 'delivery')) {
            return $this->translator->trans('Delivery country is :');
        }

        return $this->translator->trans('Country is :');
    }

    private function defaultLocale(): string
    {
        $defaultLang = LangQuery::create()->findOneByByDefault(true);

        return $defaultLang?->getLocale() ?? 'en_US';
    }

    /**
     * @param array{inputs?: array<string, array{availableOperators: array<string, string>}>, setOperators?: array<string, string>, setValues?: array<string, mixed>} $validators
     *
     * @return array<string, list<array{value: string, label: string}>>
     */
    private function operatorsFromValidators(array $validators): array
    {
        $inputs = $validators['inputs'] ?? [];
        $labels = [
            '<' => $this->translator->trans('Less than'),
            '<=' => $this->translator->trans('Less than or equal'),
            '==' => $this->translator->trans('Strictly equal to'),
            '!=' => $this->translator->trans('Different from'),
            '>=' => $this->translator->trans('Greater than or equal'),
            '>' => $this->translator->trans('Greater than'),
            'in' => $this->translator->trans('In'),
            'out' => $this->translator->trans('Not in'),
        ];

        $result = [];
        foreach ($inputs as $field => $config) {
            $items = [];
            foreach (array_keys($config['availableOperators'] ?? []) as $op) {
                $items[] = ['value' => (string) $op, 'label' => $labels[(string) $op] ?? (string) $op];
            }
            $result[$field] = $items;
        }

        return $result;
    }

    private function resolveDateFormat(): string
    {
        $defaultLang = LangQuery::create()->findOneByByDefault(true);

        return $defaultLang?->getDatetimeFormat() ?? 'Y-m-d H:i:s';
    }

    private function currencySymbol(): string
    {
        $currency = CurrencyQuery::create()->findOneByByDefault(true);

        return $currency === null ? '$' : (string) $currency->getSymbol();
    }
}
