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

use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Domain\Promotion\Coupon\Type\AbstractRemoveOnCategories;
use Thelia\Domain\Promotion\Coupon\Type\AbstractRemoveOnProducts;
use Thelia\Domain\Promotion\Coupon\Type\AmountAndPercentageCouponInterface;
use Thelia\Domain\Promotion\Coupon\Type\CouponInterface;
use Thelia\Domain\Promotion\Coupon\Type\FreeProduct;
use Thelia\Model\CategoryQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\ProductQuery;
use Twig\Environment;

/**
 * Renders BO Twig inputs for the active coupon type.
 *
 * For native types, a dedicated Twig partial is rendered with explicit parameters.
 * For third-party types (unknown service ids), a generic amount-only fallback is rendered
 * so the field name expected by the legacy {@see CouponInterface::getEffects()} is preserved.
 */
final readonly class CouponInputsRenderer
{
    private const SERVICE_AMOUNT_AND_PERCENTAGE = [
        'thelia.coupon.type.remove_x_amount' => true,
    ];

    private const SERVICE_PERCENTAGE_ONLY = [
        'thelia.coupon.type.remove_x_percent' => true,
    ];

    private const SERVICE_ON_CATEGORIES_AMOUNT = [
        'thelia.coupon.type.remove_amount_on_categories' => true,
    ];

    private const SERVICE_ON_CATEGORIES_PERCENT = [
        'thelia.coupon.type.remove_percentage_on_categories' => true,
    ];

    private const SERVICE_ON_PRODUCTS_AMOUNT = [
        'thelia.coupon.type.remove_amount_on_products' => true,
    ];

    private const SERVICE_ON_PRODUCTS_PERCENT = [
        'thelia.coupon.type.remove_percentage_on_products' => true,
    ];

    public function __construct(
        private Environment $twig,
        private TranslatorInterface $translator,
    ) {
    }

    public function renderForServiceId(string $serviceId, ?CouponInterface $manager): string
    {
        if ($serviceId === '' || $serviceId === '0') {
            return '';
        }

        $params = [
            'currency_symbol' => $this->currencySymbol(),
            'amount_value' => $this->amountValue($manager),
            'percentage_value' => $this->percentageValue($manager),
        ];

        $template = $this->resolveTemplate($serviceId);
        $params['service_id'] = $serviceId;

        if ($this->needsCategoriesPicker($serviceId)) {
            $params['categories'] = $this->categoryChoices();
            $params['selected_categories'] = $this->selectedCategories($manager);
        }

        if ($this->needsProductsPicker($serviceId) || $serviceId === 'thelia.coupon.type.free_product') {
            $params['categories'] = $params['categories'] ?? $this->categoryChoices();
            $params['products_by_category'] = $this->productChoicesByCategory();
            $params['selected_products'] = $this->selectedProducts($manager);
        }

        if ($manager instanceof FreeProduct) {
            $params['offered_product_id'] = $this->reflectProperty($manager, 'offeredProductId');
            $params['offered_category_id'] = $this->reflectProperty($manager, 'offeredCategoryId');
        }

        return $this->twig->render($template, $params);
    }

    private function needsCategoriesPicker(string $serviceId): bool
    {
        return isset(self::SERVICE_ON_CATEGORIES_AMOUNT[$serviceId])
            || isset(self::SERVICE_ON_CATEGORIES_PERCENT[$serviceId]);
    }

    private function needsProductsPicker(string $serviceId): bool
    {
        return isset(self::SERVICE_ON_PRODUCTS_AMOUNT[$serviceId])
            || isset(self::SERVICE_ON_PRODUCTS_PERCENT[$serviceId]);
    }

    /**
     * @return list<array{id: int, title: string}>
     */
    private function categoryChoices(): array
    {
        $locale = $this->defaultLocale();
        $items = [];
        foreach (CategoryQuery::create()->orderByPosition()->find() as $category) {
            $category->setLocale($locale);
            $items[] = ['id' => (int) $category->getId(), 'title' => (string) $category->getTitle()];
        }

        return $items;
    }

    /**
     * @return array<int, list<array{id: int, title: string}>>
     */
    private function productChoicesByCategory(): array
    {
        $locale = $this->defaultLocale();
        $grouped = [];
        foreach (ProductQuery::create()->orderByPosition()->find() as $product) {
            $product->setLocale($locale);
            $defaultCategory = $product->getDefaultCategoryId();
            $grouped[$defaultCategory][] = ['id' => (int) $product->getId(), 'title' => (string) $product->getTitle()];
        }

        return $grouped;
    }

    /**
     * @return list<int>
     */
    private function selectedCategories(?CouponInterface $manager): array
    {
        if (!$manager instanceof AbstractRemoveOnCategories) {
            return [];
        }

        return array_map('intval', (array) $this->reflectArray($manager, 'category_list'));
    }

    /**
     * @return list<int>
     */
    private function selectedProducts(?CouponInterface $manager): array
    {
        if (!$manager instanceof AbstractRemoveOnProducts) {
            return [];
        }

        return array_map('intval', (array) $this->reflectArray($manager, 'product_list'));
    }

    private function reflectProperty(object $obj, string $name): string
    {
        try {
            $reflection = new \ReflectionClass($obj);
            if ($reflection->hasProperty($name)) {
                $property = $reflection->getProperty($name);

                return (string) ($property->getValue($obj) ?? '');
            }
        } catch (\ReflectionException) {
        }

        return '';
    }

    private function reflectArray(object $obj, string $name): array
    {
        try {
            $reflection = new \ReflectionClass($obj);
            if ($reflection->hasProperty($name)) {
                $property = $reflection->getProperty($name);
                $value = $property->getValue($obj);

                return \is_array($value) ? $value : [];
            }
        } catch (\ReflectionException) {
        }

        return [];
    }

    private function defaultLocale(): string
    {
        $lang = LangQuery::create()->findOneByByDefault(true);

        return $lang?->getLocale() ?? 'en_US';
    }

    private function resolveTemplate(string $serviceId): string
    {
        return match (true) {
            isset(self::SERVICE_AMOUNT_AND_PERCENTAGE[$serviceId]) => '@BackOfficeDefaultTwig/coupon/type-fragments/remove-x-amount.html.twig',
            isset(self::SERVICE_PERCENTAGE_ONLY[$serviceId]) => '@BackOfficeDefaultTwig/coupon/type-fragments/remove-x-percent.html.twig',
            isset(self::SERVICE_ON_CATEGORIES_AMOUNT[$serviceId]) => '@BackOfficeDefaultTwig/coupon/type-fragments/remove-amount-on-categories.html.twig',
            isset(self::SERVICE_ON_CATEGORIES_PERCENT[$serviceId]) => '@BackOfficeDefaultTwig/coupon/type-fragments/remove-percentage-on-categories.html.twig',
            isset(self::SERVICE_ON_PRODUCTS_AMOUNT[$serviceId]) => '@BackOfficeDefaultTwig/coupon/type-fragments/remove-amount-on-products.html.twig',
            isset(self::SERVICE_ON_PRODUCTS_PERCENT[$serviceId]) => '@BackOfficeDefaultTwig/coupon/type-fragments/remove-percentage-on-products.html.twig',
            $serviceId === 'thelia.coupon.type.free_product' => '@BackOfficeDefaultTwig/coupon/type-fragments/free-product.html.twig',
            default => '@BackOfficeDefaultTwig/coupon/type-fragments/remove-x.html.twig',
        };
    }

    private function amountValue(?CouponInterface $manager): string
    {
        if ($manager === null) {
            return '';
        }

        try {
            $reflection = new \ReflectionClass($manager);
            if ($reflection->hasProperty('amount')) {
                $property = $reflection->getProperty('amount');

                return (string) ($property->getValue($manager) ?? '');
            }
        } catch (\ReflectionException) {
            // fallthrough
        }

        return '';
    }

    private function percentageValue(?CouponInterface $manager): string
    {
        if (!$manager instanceof AmountAndPercentageCouponInterface && $manager !== null) {
            try {
                $reflection = new \ReflectionClass($manager);
                if ($reflection->hasProperty('percentage')) {
                    $property = $reflection->getProperty('percentage');

                    return (string) ($property->getValue($manager) ?? '');
                }
            } catch (\ReflectionException) {
                // fallthrough
            }
        }

        return '';
    }

    private function currencySymbol(): string
    {
        $currency = CurrencyQuery::create()->findOneByByDefault(true);

        return $currency === null ? '$' : (string) $currency->getSymbol();
    }
}
