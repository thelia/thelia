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

namespace Thelia\Api\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Thelia\Api\Resource\Address;
use Thelia\Api\Resource\Attribute;
use Thelia\Api\Resource\AttributeAv;
use Thelia\Api\Resource\AttributeCombination;
use Thelia\Api\Resource\AttributeTemplate;
use Thelia\Api\Resource\Brand;
use Thelia\Api\Resource\BrandDocument;
use Thelia\Api\Resource\BrandImage;
use Thelia\Api\Resource\Cart;
use Thelia\Api\Resource\CartAddress;
use Thelia\Api\Resource\CartItem;
use Thelia\Api\Resource\Category;
use Thelia\Api\Resource\CategoryDocument;
use Thelia\Api\Resource\CategoryImage;
use Thelia\Api\Resource\Config;
use Thelia\Api\Resource\Content;
use Thelia\Api\Resource\ContentDocument;
use Thelia\Api\Resource\ContentFolder;
use Thelia\Api\Resource\ContentImage;
use Thelia\Api\Resource\Country;
use Thelia\Api\Resource\Currency;
use Thelia\Api\Resource\Customer;
use Thelia\Api\Resource\CustomerTitle;
use Thelia\Api\Resource\Feature;
use Thelia\Api\Resource\FeatureAv;
use Thelia\Api\Resource\FeatureProduct;
use Thelia\Api\Resource\FeatureTemplate;
use Thelia\Api\Resource\Folder;
use Thelia\Api\Resource\FolderDocument;
use Thelia\Api\Resource\FolderImage;
use Thelia\Api\Resource\Lang;
use Thelia\Api\Resource\Module;
use Thelia\Api\Resource\ModuleConfig;
use Thelia\Api\Resource\ModuleImage;
use Thelia\Api\Resource\NewsLetter;
use Thelia\Api\Resource\Order;
use Thelia\Api\Resource\OrderAddress;
use Thelia\Api\Resource\OrderCoupon;
use Thelia\Api\Resource\OrderProduct;
use Thelia\Api\Resource\OrderProductTax;
use Thelia\Api\Resource\OrderStatus;
use Thelia\Api\Resource\Product;
use Thelia\Api\Resource\ProductAssociatedContent;
use Thelia\Api\Resource\ProductCategory;
use Thelia\Api\Resource\ProductDocument;
use Thelia\Api\Resource\ProductImage;
use Thelia\Api\Resource\ProductPrice;
use Thelia\Api\Resource\ProductSaleElements;
use Thelia\Api\Resource\ProductSaleElementsProductImage;
use Thelia\Api\Resource\State;
use Thelia\Api\Resource\Tax;
use Thelia\Api\Resource\TaxRule;
use Thelia\Api\Resource\TaxRuleCountry;
use Thelia\Api\Resource\Template;
use Thelia\Core\Security\Resource\AdminResources;

/**
 * Tells which AdminResources code guards an API resource exposed under /api/admin.
 *
 * Every resource is mapped to the code the back-office already checks for the same
 * data, so a profile keeps the exact same reach through the API and through the
 * admin screens. A resource missing from the map is refused to every admin but the
 * superadministrator; a module declares its own resources by appending to the
 * "thelia.api.admin_resources" container parameter.
 */
final readonly class AdminApiResourcePermissions
{
    private const array CORE_RESOURCES = [
        Address::class => AdminResources::ADDRESS,
        Attribute::class => AdminResources::ATTRIBUTE,
        AttributeAv::class => AdminResources::ATTRIBUTE,
        AttributeCombination::class => AdminResources::PRODUCT,
        AttributeTemplate::class => AdminResources::TEMPLATE,
        Brand::class => AdminResources::BRAND,
        BrandDocument::class => AdminResources::BRAND,
        BrandImage::class => AdminResources::BRAND,
        Cart::class => AdminResources::ORDER,
        CartAddress::class => AdminResources::ORDER,
        CartItem::class => AdminResources::ORDER,
        Category::class => AdminResources::CATEGORY,
        CategoryDocument::class => AdminResources::CATEGORY,
        CategoryImage::class => AdminResources::CATEGORY,
        Config::class => AdminResources::CONFIG,
        Content::class => AdminResources::CONTENT,
        ContentDocument::class => AdminResources::CONTENT,
        ContentFolder::class => AdminResources::CONTENT,
        ContentImage::class => AdminResources::CONTENT,
        Country::class => AdminResources::COUNTRY,
        Currency::class => AdminResources::CURRENCY,
        Customer::class => AdminResources::CUSTOMER,
        CustomerTitle::class => AdminResources::TITLE,
        Feature::class => AdminResources::FEATURE,
        FeatureAv::class => AdminResources::FEATURE,
        FeatureProduct::class => AdminResources::PRODUCT,
        FeatureTemplate::class => AdminResources::TEMPLATE,
        Folder::class => AdminResources::FOLDER,
        FolderDocument::class => AdminResources::FOLDER,
        FolderImage::class => AdminResources::FOLDER,
        Lang::class => AdminResources::LANGUAGE,
        Module::class => AdminResources::MODULE,
        ModuleConfig::class => AdminResources::MODULE,
        ModuleImage::class => AdminResources::MODULE,
        NewsLetter::class => AdminResources::CUSTOMER,
        Order::class => AdminResources::ORDER,
        OrderAddress::class => AdminResources::ORDER,
        OrderCoupon::class => AdminResources::ORDER,
        OrderProduct::class => AdminResources::ORDER,
        OrderProductTax::class => AdminResources::ORDER,
        OrderStatus::class => AdminResources::ORDER_STATUS,
        Product::class => AdminResources::PRODUCT,
        ProductAssociatedContent::class => AdminResources::PRODUCT,
        ProductCategory::class => AdminResources::PRODUCT,
        ProductDocument::class => AdminResources::PRODUCT,
        ProductImage::class => AdminResources::PRODUCT,
        ProductPrice::class => AdminResources::PRODUCT,
        ProductSaleElements::class => AdminResources::PRODUCT,
        ProductSaleElementsProductImage::class => AdminResources::PRODUCT,
        State::class => AdminResources::STATE,
        Tax::class => AdminResources::TAX,
        TaxRule::class => AdminResources::TAX,
        TaxRuleCountry::class => AdminResources::TAX,
        Template::class => AdminResources::TEMPLATE,
    ];

    /** @var array<class-string, string> */
    private array $resources;

    /**
     * @param array<class-string, string> $moduleResources
     */
    public function __construct(
        #[Autowire(param: 'thelia.api.admin_resources')]
        array $moduleResources = [],
    ) {
        $this->resources = array_merge(self::CORE_RESOURCES, $moduleResources);
    }

    public function resolve(?string $resourceClass): ?string
    {
        if (null === $resourceClass) {
            return null;
        }

        return $this->resources[$resourceClass] ?? null;
    }
}
