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
namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Thelia\Condition\ConditionEvaluator;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Implementation\CartContainsCategories;
use Thelia\Condition\Implementation\CartContainsProducts;
use Thelia\Condition\Implementation\ForSomeCustomers;
use Thelia\Condition\Implementation\MatchBillingCountries;
use Thelia\Condition\Implementation\MatchDeliveryCountries;
use Thelia\Condition\Implementation\MatchForEveryone;
use Thelia\Condition\Implementation\MatchForTotalAmount;
use Thelia\Condition\Implementation\MatchForXArticles;
use Thelia\Condition\Implementation\MatchForXArticlesIncludeQuantity;
use Thelia\Condition\Implementation\StartDate;
use Thelia\Coupon\BaseFacade;
use Thelia\Coupon\CouponFactory;
use Thelia\Coupon\CouponManager;
use Thelia\Coupon\Type\FreeProduct;
use Thelia\Coupon\Type\RemoveAmountOnAttributeValues;
use Thelia\Coupon\Type\RemoveAmountOnCategories;
use Thelia\Coupon\Type\RemoveAmountOnProducts;
use Thelia\Coupon\Type\RemovePercentageOnAttributeValues;
use Thelia\Coupon\Type\RemovePercentageOnCategories;
use Thelia\Coupon\Type\RemovePercentageOnProducts;
use Thelia\Coupon\Type\RemoveXAmount;
use Thelia\Coupon\Type\RemoveXPercent;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    // Base services
    $services->alias('thelia.facade', BaseFacade::class)->public();
    $services->alias('thelia.coupon.manager', CouponManager::class);
    $services->alias('thelia.coupon.factory', CouponFactory::class);
    $services->alias('thelia.condition.factory', ConditionFactory::class)->public();

    // Coupon types only aliases for BC (autoconfigured by CouponInterface)
    $services->alias('thelia.coupon.type.remove_x_amount', RemoveXAmount::class);
    $services->alias('thelia.coupon.type.remove_x_percent', RemoveXPercent::class);
    $services->alias('thelia.coupon.type.remove_amount_on_categories', RemoveAmountOnCategories::class);
    $services->alias('thelia.coupon.type.remove_percentage_on_categories', RemovePercentageOnCategories::class);
    $services->alias('thelia.coupon.type.remove_amount_on_products', RemoveAmountOnProducts::class);
    $services->alias('thelia.coupon.type.remove_percentage_on_products', RemovePercentageOnProducts::class);
    $services->alias('thelia.coupon.type.remove_amount_on_attribute_av', RemoveAmountOnAttributeValues::class);
    $services->alias('thelia.coupon.type.remove_percentage_on_attribute_av', RemovePercentageOnAttributeValues::class);
    $services->alias('thelia.coupon.type.free_product', FreeProduct::class);

    // Conditions only aliases for BC (autoconfigured by ConditionInterface)
    $services->alias('thelia.condition.validator', ConditionEvaluator::class);
    $services->alias('thelia.condition.match_for_everyone', MatchForEveryone::class);
    $services->alias('thelia.condition.match_for_total_amount', MatchForTotalAmount::class);
    $services->alias('thelia.condition.match_for_x_articles', MatchForXArticles::class);
    $services->alias('thelia.condition.match_for_x_articles_include_quantity', MatchForXArticlesIncludeQuantity::class);
    $services->alias('thelia.condition.match_delivery_countries', MatchDeliveryCountries::class);
    $services->alias('thelia.condition.match_billing_countries', MatchBillingCountries::class);
    $services->alias('thelia.condition.start_date', StartDate::class);
    $services->alias('thelia.condition.cart_contains_categories', CartContainsCategories::class);
    $services->alias('thelia.condition.cart_contains_products', CartContainsProducts::class);
    $services->alias('thelia.condition.for_some_customers', ForSomeCustomers::class);
};
