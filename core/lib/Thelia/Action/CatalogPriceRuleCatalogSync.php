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

namespace Thelia\Action;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Attribute\AttributeAvDeleteEvent;
use Thelia\Core\Event\Brand\BrandDeleteEvent;
use Thelia\Core\Event\Category\CategoryDeleteEvent;
use Thelia\Core\Event\Category\CategoryEvent;
use Thelia\Core\Event\Feature\FeatureAvDeleteEvent;
use Thelia\Core\Event\FeatureProduct\FeatureProductEvent;
use Thelia\Core\Event\Product\ProductCloneEvent;
use Thelia\Core\Event\Product\ProductCombinationGenerationEvent;
use Thelia\Core\Event\Product\ProductEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementEvent;
use Thelia\Core\Event\Template\TemplateDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Pricing\PricingActivityChecker;
use Thelia\Domain\Pricing\Rule\RuleRepricer;
use Thelia\Model\CatalogPriceRule;

/**
 * Keeps the catalog price rules in step with the catalog.
 *
 * The scope of a rule follows the catalog: a product filed in the Winter category
 * on January 15th enters the January rule that day, a product whose price changes
 * is repriced by every rule covering it. Each catalog write the back office makes
 * lands here, after the action that made it, and re-evaluates the one product it
 * touched - or, when a category, a brand or a value a criterion names disappears
 * or moves, the rules naming it.
 *
 * A change that moves every price at once - a tax, a currency rate - is not
 * replayed inline: every turned-on rule is marked dirty and the recompute command
 * finishes the job.
 *
 * Every handler is a no-op in a shop with no rule turned on.
 */
class CatalogPriceRuleCatalogSync implements EventSubscriberInterface
{
    /**
     * After the action writing the catalog (128), before nothing in particular.
     */
    private const PRIORITY = 64;

    public function __construct(
        private readonly RuleRepricer $repricer,
        private readonly PricingActivityChecker $activityChecker,
    ) {
    }

    public function afterProductChanged(ProductEvent $event): void
    {
        if (!$event->hasProduct() || !$this->hasRules()) {
            return;
        }

        $this->repricer->afterProductChanged((int) $event->getProduct()->getId());
    }

    public function afterProductCloned(ProductCloneEvent $event): void
    {
        if (!$this->hasRules()) {
            return;
        }

        $this->repricer->afterProductChanged((int) $event->getClonedProduct()->getId());
    }

    public function afterFeatureValueChanged(FeatureProductEvent $event): void
    {
        if (!$this->hasRules()) {
            return;
        }

        $this->repricer->afterProductChanged((int) $event->getProductId());
    }

    public function afterSaleElementChanged(ProductSaleElementEvent $event): void
    {
        if (!$this->hasRules()) {
            return;
        }

        $product = $event->getProduct();

        if (null !== $product) {
            $this->repricer->afterProductChanged((int) $product->getId());
        }
    }

    public function afterCombinationsGenerated(ProductCombinationGenerationEvent $event): void
    {
        if (!$this->hasRules()) {
            return;
        }

        $this->repricer->afterProductChanged((int) $event->getProduct()->getId());
    }

    public function afterCategoryChanged(CategoryEvent $event): void
    {
        if (!$this->hasRules()) {
            return;
        }

        // A category edit may have moved it under another parent, which moves the
        // whole tree below it: every rule naming any category is re-evaluated. The
        // id of the category itself is not enough to know which ones.
        $this->repricer->afterCriterionTargetChanged(CatalogPriceRule::CRITERION_CATEGORY, null);
    }

    public function afterCategoryDeleted(CategoryDeleteEvent $event): void
    {
        if (!$this->hasRules()) {
            return;
        }

        $this->repricer->afterCriterionTargetChanged(CatalogPriceRule::CRITERION_CATEGORY, null);
    }

    public function afterBrandDeleted(BrandDeleteEvent $event): void
    {
        if (!$this->hasRules()) {
            return;
        }

        $this->repricer->afterCriterionTargetChanged(CatalogPriceRule::CRITERION_BRAND, (int) $event->getBrandId());
    }

    public function afterTemplateDeleted(TemplateDeleteEvent $event): void
    {
        if (!$this->hasRules()) {
            return;
        }

        $this->repricer->afterCriterionTargetChanged(CatalogPriceRule::CRITERION_TEMPLATE, (int) $event->getTemplateId());
    }

    public function afterFeatureValueDeleted(FeatureAvDeleteEvent $event): void
    {
        if (!$this->hasRules()) {
            return;
        }

        $this->repricer->afterCriterionTargetChanged(CatalogPriceRule::CRITERION_FEATURE_AV, (int) $event->getFeatureAvId());
    }

    public function afterAttributeValueDeleted(AttributeAvDeleteEvent $event): void
    {
        if (!$this->hasRules()) {
            return;
        }

        $this->repricer->afterCriterionTargetChanged(CatalogPriceRule::CRITERION_ATTRIBUTE_AV, (int) $event->getAttributeAvId());
    }

    public function afterEveryPriceMoved(): void
    {
        if (!$this->hasRules()) {
            return;
        }

        $this->repricer->markAllDirty();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::PRODUCT_CREATE => ['afterProductChanged', self::PRIORITY],
            TheliaEvents::PRODUCT_UPDATE => ['afterProductChanged', self::PRIORITY],
            TheliaEvents::PRODUCT_ADD_CATEGORY => ['afterProductChanged', self::PRIORITY],
            TheliaEvents::PRODUCT_REMOVE_CATEGORY => ['afterProductChanged', self::PRIORITY],
            TheliaEvents::PRODUCT_SET_TEMPLATE => ['afterProductChanged', self::PRIORITY],
            TheliaEvents::PRODUCT_CLONE => ['afterProductCloned', self::PRIORITY],

            TheliaEvents::PRODUCT_FEATURE_UPDATE_VALUE => ['afterFeatureValueChanged', self::PRIORITY],
            TheliaEvents::PRODUCT_FEATURE_DELETE_VALUE => ['afterFeatureValueChanged', self::PRIORITY],

            TheliaEvents::PRODUCT_ADD_PRODUCT_SALE_ELEMENT => ['afterSaleElementChanged', self::PRIORITY],
            TheliaEvents::PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT => ['afterSaleElementChanged', self::PRIORITY],
            TheliaEvents::PRODUCT_COMBINATION_GENERATION => ['afterCombinationsGenerated', self::PRIORITY],
            // The clone of a sale element travels on the product clone event: the
            // cloned product is what has to be re-evaluated.
            TheliaEvents::PSE_CLONE => ['afterProductCloned', self::PRIORITY],

            TheliaEvents::CATEGORY_UPDATE => ['afterCategoryChanged', self::PRIORITY],
            TheliaEvents::CATEGORY_DELETE => ['afterCategoryDeleted', self::PRIORITY],
            TheliaEvents::BRAND_DELETE => ['afterBrandDeleted', self::PRIORITY],
            TheliaEvents::TEMPLATE_DELETE => ['afterTemplateDeleted', self::PRIORITY],
            TheliaEvents::FEATURE_AV_DELETE => ['afterFeatureValueDeleted', self::PRIORITY],
            TheliaEvents::ATTRIBUTE_AV_DELETE => ['afterAttributeValueDeleted', self::PRIORITY],

            TheliaEvents::TAX_UPDATE => ['afterEveryPriceMoved', self::PRIORITY],
            TheliaEvents::TAX_RULE_UPDATE => ['afterEveryPriceMoved', self::PRIORITY],
            TheliaEvents::TAX_RULE_TAXES_UPDATE => ['afterEveryPriceMoved', self::PRIORITY],
            TheliaEvents::CURRENCY_CREATE => ['afterEveryPriceMoved', self::PRIORITY],
            TheliaEvents::CURRENCY_UPDATE => ['afterEveryPriceMoved', self::PRIORITY],
            TheliaEvents::CURRENCY_SET_DEFAULT => ['afterEveryPriceMoved', self::PRIORITY],
            TheliaEvents::CURRENCY_UPDATE_RATES => ['afterEveryPriceMoved', self::PRIORITY],
        ];
    }

    private function hasRules(): bool
    {
        return $this->activityChecker->hasActivePublicRule() || $this->activityChecker->hasActiveNamedRule();
    }
}
