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

namespace Thelia\Core\Template\Loop;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Exception\PropelException;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Element\SearchLoopInterface;
use Thelia\Core\Template\Element\StandardI18nFieldsSearchTrait;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Exception\TaxEngineException;
use Thelia\Log\Tlog;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency as CurrencyModel;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Map\FeatureAvI18nTableMap;
use Thelia\Model\Map\ProductPriceTableMap;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Map\SaleTableMap;
use Thelia\Model\Product as ProductModel;
use Thelia\Model\ProductCategoryQuery;
use Thelia\Model\ProductQuery;
use Thelia\TaxEngine\TaxEngine;
use Thelia\Type\BooleanOrBothType;
use Thelia\Type\EnumListType;
use Thelia\Type\EnumType;
use Thelia\Type\IntToCombinedIntsListType;
use Thelia\Type\IntToCombinedStringsListType;
use Thelia\Type\TypeCollection;

/**
 * Product loop.
 *
 * Class Product
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int[]       getId()
 * @method bool        getComplex()
 * @method string[]    getRef()
 * @method int[]       getCategory()
 * @method int[]       getBrand()
 * @method int[]       getSale()
 * @method int[]       getCategoryDefault()
 * @method int[]       getContent()
 * @method bool        getNew()
 * @method bool        getPromo()
 * @method float       getMinPrice()
 * @method float       getMaxPrice()
 * @method int         getMinStock()
 * @method float       getMinWeight()
 * @method float       getMaxWeight()
 * @method bool        getWithPrevNextInfo()
 * @method bool|string getWithPrevNextVisible()
 * @method bool        getCurrent()
 * @method bool        getCurrentCategory()
 * @method bool        getDepth()
 * @method bool|string getVirtual()
 * @method bool|string getVisible()
 * @method int         getCurrency()
 * @method string      getTitle()
 * @method string[]    getOrder()
 * @method int[]       getExclude()
 * @method int[]       getExcludeCategory()
 * @method int[]       getFeatureAvailability()
 * @method string[]    getFeatureValues()
 * @method string[]    getAttributeNonStrictMatch()
 * @method int[]       getTemplateId()
 * @method int[]       getTaxRuleId()
 * @method int[]       getExcludeTaxRuleId()
 */
class Product extends BaseI18nLoop implements PropelSearchLoopInterface, SearchLoopInterface
{
    use StandardI18nFieldsSearchTrait;

    protected $timestampable = true;
    protected $versionable = true;

    public function __construct(
        protected readonly TaxEngine $taxEngine,
    ) {
    }

    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createBooleanTypeArgument('complex', false),
            Argument::createIntListTypeArgument('id'),
            Argument::createAnyListTypeArgument('ref'),
            Argument::createIntListTypeArgument('category'),
            Argument::createIntListTypeArgument('brand'),
            Argument::createIntListTypeArgument('sale'),
            Argument::createIntListTypeArgument('category_default'),
            Argument::createIntListTypeArgument('content'),
            Argument::createBooleanTypeArgument('new'),
            Argument::createBooleanTypeArgument('promo'),
            Argument::createFloatTypeArgument('min_price'),
            Argument::createFloatTypeArgument('max_price'),
            Argument::createIntTypeArgument('min_stock'),
            Argument::createFloatTypeArgument('min_weight'),
            Argument::createFloatTypeArgument('max_weight'),
            Argument::createBooleanTypeArgument('with_prev_next_info', false),
            Argument::createBooleanOrBothTypeArgument('with_prev_next_visible', BooleanOrBothType::ANY),
            Argument::createBooleanTypeArgument('current'),
            Argument::createBooleanTypeArgument('current_category'),
            Argument::createIntTypeArgument('depth', 1),
            Argument::createBooleanOrBothTypeArgument('virtual', BooleanOrBothType::ANY),
            Argument::createBooleanOrBothTypeArgument('visible', 1),
            Argument::createIntTypeArgument('currency'),
            Argument::createAnyTypeArgument('title'),
            Argument::createIntListTypeArgument('template_id'),
            Argument::createIntListTypeArgument('tax_rule_id'),
            Argument::createIntListTypeArgument('exclude_tax_rule_id'),
            new Argument(
                'order',
                new TypeCollection(
                    new EnumListType(
                        [
                            'id', 'id_reverse',
                            'alpha', 'alpha_reverse',
                            'min_price', 'max_price',
                            'manual', 'manual_reverse',
                            'created', 'created_reverse',
                            'updated', 'updated_reverse',
                            'ref', 'ref_reverse',
                            'visible', 'visible_reverse',
                            'position', 'position_reverse',
                            'promo',
                            'new',
                            'random',
                            'given_id',
                        ],
                    ),
                ),
                'alpha',
            ),
            Argument::createIntListTypeArgument('exclude'),
            Argument::createIntListTypeArgument('exclude_category'),
            new Argument(
                'feature_availability',
                new TypeCollection(
                    new IntToCombinedIntsListType(),
                ),
            ),
            new Argument(
                'feature_values',
                new TypeCollection(
                    new IntToCombinedStringsListType(),
                ),
            ),
            /*
            * promo, new, quantity, weight or price may differ depending on the different attributes
            * by default, product loop will look for at least 1 attribute which matches all the loop criteria : attribute_non_strict_match="none"
            * you can also provide a list of non-strict attributes.
            * ie : attribute_non_strict_match="promo,new"
            * loop will return the product if he has at least an attribute in promo and at least an attribute as new ; even if it's not the same attribute.
            * you can set all the attributes as non strict : attribute_non_strict_match="*"
            *
            * In order to allow such a process, we will have to make a LEFT JOIN foreach of the following case.
            */
            new Argument(
                'attribute_non_strict_match',
                new TypeCollection(
                    new EnumListType(
                        ['min_stock', 'promo', 'new', 'min_weight', 'max_weight', 'min_price', 'max_price'],
                    ),
                    new EnumType(['*', 'none']),
                ),
                'none',
            ),
        );
    }

    public function getSearchIn(): array
    {
        return array_merge(
            ['id', 'ref'],
            $this->getStandardI18nSearchFields(),
        );
    }

    public function doSearch(ModelCriteria $search, $searchTerm, $searchIn, $searchCriteria): void
    {
        $search->_and();

        foreach ($searchIn as $index => $searchInElement) {
            if ($index > 0) {
                $search->_or();
            }

            switch ($searchInElement) {
                case 'ref':
                    $search->filterByRef($searchTerm, $searchCriteria);
                    break;
                case 'id':
                    $search->where(
                        \sprintf('`product`.`id` %s ?', $searchCriteria),
                        $searchTerm,
                        \PDO::PARAM_STR,
                    );
                    break;
            }
        }

        $this->addStandardI18nSearch($search, $searchTerm, $searchCriteria, $searchIn);
    }

    /**
     * @throws PropelException
     */
    public function parseResults(LoopResult $loopResult): LoopResult
    {
        $complex = $this->getComplex();

        if (true === $complex) {
            return $this->parseComplexResults($loopResult);
        }

        return $this->parseSimpleResults($loopResult);
    }

    /**
     * @throws PropelException
     */
    public function parseSimpleResults(LoopResult $loopResult): LoopResult
    {
        $taxCountry = $this->taxEngine->getDeliveryCountry();
        $taxState = $this->taxEngine->getDeliveryState();

        $securityContext = $this->securityContext;

        /** @var ProductModel $product */
        foreach ($loopResult->getResultDataCollection() as $product) {
            $loopResultRow = new LoopResultRow($product);

            $price = $product->getVirtualColumn('price');

            if (!$this->getBackendContext()
                && $securityContext->hasCustomerUser()
                && $securityContext->getCustomerUser()?->getDiscount() > 0) {
                $price *= (1 - ($securityContext->getCustomerUser()?->getDiscount() / 100));
            }

            try {
                $taxedPrice = $product->getTaxedPrice(
                    $taxCountry,
                    $price,
                    $taxState,
                );
            } catch (TaxEngineException) {
                $taxedPrice = null;
            }

            $promoPrice = $product->getVirtualColumn('promo_price');

            if (!$this->getBackendContext()
                && $securityContext->hasCustomerUser()
                && $securityContext->getCustomerUser()?->getDiscount() > 0) {
                $promoPrice *= (1 - ($securityContext->getCustomerUser()?->getDiscount() / 100));
            }

            try {
                $taxedPromoPrice = $product->getTaxedPromoPrice(
                    $taxCountry,
                    $promoPrice,
                    $taxState,
                );
            } catch (TaxEngineException) {
                $taxedPromoPrice = null;
            }

            $defaultCategoryId = $this->getDefaultCategoryId($product);

            $loopResultRow
                ->set('WEIGHT', $product->getVirtualColumn('weight'))
                ->set('QUANTITY', $product->getVirtualColumn('quantity'))
                ->set('EAN_CODE', $product->getVirtualColumn('ean_code'))
                ->set('BEST_PRICE', $product->getVirtualColumn('is_promo') ? $promoPrice : $price)
                ->set('BEST_PRICE_TAX', $taxedPrice - $product->getVirtualColumn('is_promo') ? $taxedPromoPrice - $promoPrice : $taxedPrice - $price)
                ->set('BEST_TAXED_PRICE', $product->getVirtualColumn('is_promo') ? $taxedPromoPrice : $taxedPrice)
                ->set('PRICE', $price)
                ->set('PRICE_TAX', $taxedPrice - $price)
                ->set('TAXED_PRICE', $taxedPrice)
                ->set('PROMO_PRICE', $promoPrice)
                ->set('PROMO_PRICE_TAX', $taxedPromoPrice - $promoPrice)
                ->set('TAXED_PROMO_PRICE', $taxedPromoPrice)
                ->set('IS_PROMO', $product->getVirtualColumn('is_promo'))
                ->set('IS_NEW', $product->getVirtualColumn('is_new'))
                ->set('PRODUCT_SALE_ELEMENT', $product->getVirtualColumn('pse_id'))
                ->set('PSE_COUNT', $product->getVirtualColumn('pse_count'));

            $this->associateValues($loopResultRow, $product, $defaultCategoryId);

            $this->addOutputFields($loopResultRow, $product);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }

    /**
     * @throws PropelException
     */
    public function parseComplexResults(LoopResult $loopResult): LoopResult
    {
        $taxCountry = $this->taxEngine->getDeliveryCountry();
        $taxState = $this->taxEngine->getDeliveryState();

        /** @var SecurityContext $securityContext */
        $securityContext = $this->container->get('thelia.securityContext');

        /** @var ProductModel $product */
        foreach ($loopResult->getResultDataCollection() as $product) {
            $loopResultRow = new LoopResultRow($product);

            $price = $product->getRealLowestPrice();

            if ($securityContext->hasCustomerUser() && $securityContext->getCustomerUser()->getDiscount() > 0) {
                $price *= (1 - ($securityContext->getCustomerUser()->getDiscount() / 100));
            }

            try {
                $taxedPrice = $product->getTaxedPrice(
                    $taxCountry,
                    $price,
                    $taxState,
                );
            } catch (TaxEngineException) {
                $taxedPrice = null;
            }

            $defaultCategoryId = $this->getDefaultCategoryId($product);

            $loopResultRow
                ->set('BEST_PRICE', $price)
                ->set('BEST_PRICE_TAX', $taxedPrice - $price)
                ->set('BEST_TAXED_PRICE', $taxedPrice)
                ->set('IS_PROMO', $product->getVirtualColumn('main_product_is_promo'))
                ->set('IS_NEW', $product->getVirtualColumn('main_product_is_new'));

            $this->associateValues($loopResultRow, $product, $defaultCategoryId);

            $this->addOutputFields($loopResultRow, $product);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }

    /**
     * @param LoopResultRow $loopResultRow the current result row
     *
     * @throws PropelException
     */
    private function associateValues(LoopResultRow $loopResultRow, ProductModel $product, ?int $defaultCategoryId): LoopResultRow
    {
        $display_initial_price = $product->getVirtualColumn('display_initial_price');

        if (null === $display_initial_price) {
            $display_initial_price = 1;
        }

        $loopResultRow
            ->set('ID', $product->getId())
            ->set('REF', $product->getRef())
            ->set('IS_TRANSLATED', $product->getVirtualColumn('IS_TRANSLATED'))
            ->set('LOCALE', $this->locale)
            ->set('TITLE', $product->getVirtualColumn('i18n_TITLE'))
            ->set('CHAPO', $product->getVirtualColumn('i18n_CHAPO'))
            ->set('DESCRIPTION', $product->getVirtualColumn('i18n_DESCRIPTION'))
            ->set('POSTSCRIPTUM', $product->getVirtualColumn('i18n_POSTSCRIPTUM'))
            ->set('URL', $this->getReturnUrl() ? $product->getUrl($this->locale) : null)
            ->set('META_TITLE', $product->getVirtualColumn('i18n_META_TITLE'))
            ->set('META_DESCRIPTION', $product->getVirtualColumn('i18n_META_DESCRIPTION'))
            ->set('META_KEYWORDS', $product->getVirtualColumn('i18n_META_KEYWORDS'))
            ->set('POSITION', $product->getVirtualColumn('position_delegate'))
            ->set('VIRTUAL', $product->getVirtual() ? '1' : '0')
            ->set('VISIBLE', $product->getVisible() ? '1' : '0')
            ->set('TEMPLATE', $product->getTemplateId())
            ->set('DEFAULT_CATEGORY', $defaultCategoryId)
            ->set('TAX_RULE_ID', $product->getTaxRuleId())
            ->set('BRAND_ID', $product->getBrandId() ?: 0)
            ->set('SHOW_ORIGINAL_PRICE', $display_initial_price);

        $this->findNextPrev($loopResultRow, $product, $defaultCategoryId);

        return $loopResultRow;
    }

    private function findNextPrev(LoopResultRow $loopResultRow, ProductModel $product, ?int $defaultCategoryId): void
    {
        if ($this->getWithPrevNextInfo()) {
            $currentPosition = ProductCategoryQuery::create()
                ->filterByCategoryId($defaultCategoryId)
                ->filterByProductId($product->getId())
                ->findOne()->getPosition();

            // Find previous and next product
            $previousQuery = ProductCategoryQuery::create()
                ->filterByCategoryId($defaultCategoryId)
                ->filterByPosition($currentPosition, Criteria::LESS_THAN);

            $nextQuery = ProductCategoryQuery::create()
                ->filterByCategoryId($defaultCategoryId)
                ->filterByPosition($currentPosition, Criteria::GREATER_THAN);

            if (!$this->getBackendContext()) {
                $previousQuery->useProductQuery()
                    ->filterByVisible(true)
                    ->endUse();

                $previousQuery->useProductQuery()
                    ->filterByVisible(true)
                    ->endUse();
            }

            $previous = $previousQuery
                ->orderByPosition(Criteria::DESC)
                ->findOne();

            $next = $nextQuery
                ->orderByPosition(Criteria::ASC)
                ->findOne();

            $loopResultRow
                ->set('HAS_PREVIOUS', null !== $previous ? 1 : 0)
                ->set('HAS_NEXT', null !== $next ? 1 : 0)
                ->set('PREVIOUS', null !== $previous ? $previous->getProductId() : -1)
                ->set('NEXT', null !== $next ? $next->getProductId() : -1);
        }
    }

    /**
     * @throws PropelException
     */
    protected function manageFeatureAv(ProductQuery $search, array $feature_availability): void
    {
        foreach ($feature_availability as $feature => $feature_choice) {
            foreach ($feature_choice['values'] as $feature_av) {
                $featureAlias = 'fa_' . $feature;

                if ('*' !== $feature_av) {
                    $featureAlias .= '_' . $feature_av;
                }

                $search->joinFeatureProduct($featureAlias, Criteria::LEFT_JOIN)
                    ->addJoinCondition($featureAlias, \sprintf('`%s`.FEATURE_ID = ?', $featureAlias), $feature, null, \PDO::PARAM_INT);

                if ('*' !== $feature_av) {
                    $search->addJoinCondition($featureAlias, \sprintf('`%s`.FEATURE_AV_ID = ?', $featureAlias), $feature_av, null, \PDO::PARAM_INT);
                }
            }

            /* format for mysql */
            $sqlWhereString = $feature_choice['expression'];

            if ('*' === $sqlWhereString) {
                $sqlWhereString = 'NOT ISNULL(`fa_' . $feature . '`.ID)';
            } else {
                $sqlWhereString = preg_replace('#(\d+)#', 'NOT ISNULL(`fa_' . $feature . '_\1`.ID)', (string) $sqlWhereString);
                $sqlWhereString = str_replace('&', ' AND ', $sqlWhereString);
                $sqlWhereString = str_replace('|', ' OR ', $sqlWhereString);
            }

            $search->where('(' . $sqlWhereString . ')');
        }
    }

    /**
     * @throws PropelException
     */
    protected function manageFeatureValue(ProductQuery &$search, array $feature_values): void
    {
        foreach ($feature_values as $feature => $feature_choice) {
            $aliasMatches = [];

            foreach ($feature_choice['values'] as $feature_value) {
                $featureAlias = 'fv_' . $feature;

                if ('*' !== $feature_value) {
                    // Generate a unique alias for this value
                    $featureAlias .= '_' . hash('crc32', (string) $feature_value) . '_' . preg_replace('/[^[:alnum:]_]/', '_', (string) $feature_value);
                }

                $search->joinFeatureProduct($featureAlias, Criteria::LEFT_JOIN)
                    ->addJoinCondition($featureAlias, \sprintf('`%s`.FEATURE_ID = ?', $featureAlias), $feature, null, \PDO::PARAM_INT);

                if ('*' !== $feature_value) {
                    $featureAliasI18n = $featureAlias . '_i18n';
                    $featureAliasI18nJoin = $featureAlias . '_i18n_join';

                    $featureAvValueJoin = new Join();
                    $featureAvValueJoin->setJoinType(Criteria::LEFT_JOIN);
                    $featureAvValueJoin->addExplicitCondition(
                        $featureAlias,
                        'FEATURE_AV_ID',
                        null,
                        FeatureAvI18nTableMap::TABLE_NAME,
                        'ID',
                        $featureAliasI18n,
                    );

                    $search
                        ->addJoinObject($featureAvValueJoin, $featureAliasI18nJoin)
                        ->addJoinCondition($featureAliasI18nJoin, \sprintf('`%s`.LOCALE = ?', $featureAliasI18n), $this->locale, null, \PDO::PARAM_STR)
                        ->addJoinCondition($featureAliasI18nJoin, \sprintf('`%s`.TITLE = ?', $featureAliasI18n), $feature_value, null, \PDO::PARAM_STR);

                    $aliasMatches[$feature_value] = $featureAliasI18n;
                }
            }

            /* format for mysql */
            $sqlWhereString = $feature_choice['expression'];

            if ('*' === $sqlWhereString) {
                $sqlWhereString = 'NOT ISNULL(`fv_' . $feature . '`.ID)';
            } else {
                foreach ($aliasMatches as $value => $alias) {
                    $sqlWhereString = str_replace($value, 'NOT ISNULL(`' . $alias . '`.ID)', $sqlWhereString);
                }

                $sqlWhereString = str_replace(['&', '|'], [' AND ', ' OR '], $sqlWhereString);
            }

            $search->where('(' . $sqlWhereString . ')');
        }
    }

    /**
     * @throws PropelException
     */
    public function buildModelCriteria(): ModelCriteria|ProductQuery
    {
        Tlog::getInstance()->debug('-- Starting new product build criteria');

        $currencyId = $this->getCurrency();

        if (null !== $currencyId) {
            $currency = CurrencyQuery::create()->findOneById($currencyId);

            if (null === $currency) {
                throw new \InvalidArgumentException('Cannot found currency id: `' . $currency . '` in product_sale_elements loop');
            }
        } else {
            $currency = $this->getCurrentRequest()->getSession()->getCurrency();
        }

        $defaultCurrency = CurrencyModel::getDefaultCurrency();
        $defaultCurrencySuffix = '_default_currency';

        $priceToCompareAsSQL = '';
        $isPSELeftJoinList = [];
        $isProductPriceFirstLeftJoin = [];

        $search = ProductQuery::create();

        $complex = $this->getComplex();

        if (!$complex) {
            $search->innerJoinProductSaleElements('pse');
            $search->addJoinCondition('pse', '`pse`.IS_DEFAULT=1');

            $search->innerJoinProductSaleElements('pse_count');

            $priceJoin = new Join();
            $priceJoin->addExplicitCondition(ProductSaleElementsTableMap::TABLE_NAME, 'ID', 'pse', ProductPriceTableMap::TABLE_NAME, 'PRODUCT_SALE_ELEMENTS_ID', 'price');
            $priceJoin->setJoinType(Criteria::LEFT_JOIN);

            $search->addJoinObject($priceJoin, 'price_join')
                ->addJoinCondition('price_join', '`price`.`currency_id` = ?', $currency->getId(), null, \PDO::PARAM_INT);

            if ($defaultCurrency?->getId() !== $currency->getId()) {
                $priceJoinDefaultCurrency = new Join();
                $priceJoinDefaultCurrency->addExplicitCondition(ProductSaleElementsTableMap::TABLE_NAME, 'ID', 'pse', ProductPriceTableMap::TABLE_NAME, 'PRODUCT_SALE_ELEMENTS_ID', 'price' . $defaultCurrencySuffix);
                $priceJoinDefaultCurrency->setJoinType(Criteria::LEFT_JOIN);

                $search->addJoinObject($priceJoinDefaultCurrency, 'price_join' . $defaultCurrencySuffix)
                    ->addJoinCondition('price_join' . $defaultCurrencySuffix, '`price' . $defaultCurrencySuffix . '`.`currency_id` = ?', $defaultCurrency->getId(), null, \PDO::PARAM_INT);

                /**
                 * rate value is checked as a float in overloaded getRate method.
                 */
                $priceToCompareAsSQL = 'CASE WHEN ISNULL(`price`.PRICE) OR `price`.FROM_DEFAULT_CURRENCY = 1 THEN
                    CASE WHEN `pse`.PROMO=1 THEN `price' . $defaultCurrencySuffix . '`.PROMO_PRICE ELSE `price' . $defaultCurrencySuffix . '`.PRICE END * ' . $currency->getRate() . '
                ELSE
                    CASE WHEN `pse`.PROMO=1 THEN `price`.PROMO_PRICE ELSE `price`.PRICE END
                END';

                $search->withColumn($priceToCompareAsSQL, 'real_price');
                $search->withColumn('CASE WHEN ISNULL(`price`.PRICE) OR `price`.FROM_DEFAULT_CURRENCY = 1 THEN `price' . $defaultCurrencySuffix . '`.PRICE * ' . $currency->getRate() . ' ELSE `price`.PRICE END', 'price');
                $search->withColumn('CASE WHEN ISNULL(`price`.PRICE) OR `price`.FROM_DEFAULT_CURRENCY = 1 THEN `price' . $defaultCurrencySuffix . '`.PROMO_PRICE * ' . $currency->getRate() . ' ELSE `price`.PROMO_PRICE END', 'promo_price');
            } else {
                $priceToCompareAsSQL = 'CASE WHEN `pse`.PROMO=1 THEN `price`.PROMO_PRICE ELSE `price`.PRICE END';

                $search->withColumn($priceToCompareAsSQL, 'real_price');
                $search->withColumn('`price`.PRICE', 'price');
                $search->withColumn('`price`.PROMO_PRICE', 'promo_price');
            }
        }

        /* manage translations */
        $this->configureI18nProcessing($search, ['TITLE', 'CHAPO', 'DESCRIPTION', 'POSTSCRIPTUM', 'META_TITLE', 'META_DESCRIPTION', 'META_KEYWORDS']);

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $ref = $this->getRef();

        if (null !== $ref) {
            $search->filterByRef($ref, Criteria::IN);
        }

        $title = $this->getTitle();

        if (null !== $title) {
            $this->addSearchInI18nColumn($search, 'TITLE', Criteria::LIKE, '%' . $title . '%');
        }

        $templateIdList = $this->getTemplateId();

        if (null !== $templateIdList) {
            $search->filterByTemplateId($templateIdList, Criteria::IN);
        }

        $manualOrderAllowed = false;

        if (null !== $categoryDefault = $this->getCategoryDefault()) {
            // Select the products which have $categoryDefault as the default category.
            $search
                ->useProductCategoryQuery('CategorySelect')
                ->filterByDefaultCategory(true)
                ->filterByCategoryId($categoryDefault, Criteria::IN)
                ->endUse();
            // We can only sort by position if we have a single category ID
            $manualOrderAllowed = (1 === \count($categoryDefault));
        } elseif (null !== $categoryIdList = $this->getCategory()) {
            // Select all products which have one of the required categories as the default one, or an associated one
            $depth = $this->getDepth();

            $allCategoryIDs = CategoryQuery::getCategoryTreeIds($categoryIdList, (int) $depth);

            $search
                ->useProductCategoryQuery('CategorySelect')
                ->filterByCategoryId($allCategoryIDs, Criteria::IN)
                ->endUse();
            // We can only sort by position if we have a single category ID, with a depth of 1
            $manualOrderAllowed = (1 === (int) $depth && 1 === \count($categoryIdList));
        } else {
            $search
                ->leftJoinProductCategory('CategorySelect')
                ->addJoinCondition('CategorySelect', '`CategorySelect`.DEFAULT_CATEGORY = 1');
        }

        $search->withColumn(
            'CASE WHEN ISNULL(`CategorySelect`.POSITION) THEN ' . PHP_INT_MAX . ' ELSE CAST(`CategorySelect`.POSITION as SIGNED) END',
            'position_delegate',
        );
        $search->withColumn('`CategorySelect`.CATEGORY_ID', 'default_category_id');
        $search->withColumn('`CategorySelect`.DEFAULT_CATEGORY', 'is_default_category');

        $current = $this->getCurrent();

        if (true === $current) {
            $search->filterById($this->getCurrentRequest()->get('product_id'), Criteria::EQUAL);
        } elseif (false === $current) {
            $search->filterById($this->getCurrentRequest()->get('product_id'), Criteria::NOT_IN);
        }

        $brand_id = $this->getBrand();

        if (null !== $brand_id) {
            $search->filterByBrandId($brand_id, Criteria::IN);
        }

        $contentId = $this->getContent();

        if (null !== $contentId) {
            $search->useProductAssociatedContentQuery()
                ->filterByContentId($contentId, Criteria::IN)
                ->endUse();
        }

        $sale_id = $this->getSale();

        if (null !== $sale_id) {
            $search->useSaleProductQuery('SaleProductSelect')
                ->filterBySaleId($sale_id)
                ->groupByProductId()
                ->endUse();
        }

        $current_category = $this->getCurrentCategory();

        if (true === $current_category) {
            $search->filterByCategory(
                CategoryQuery::create()->filterByProduct(
                    ProductCategoryQuery::create()->findPk($this->getCurrentRequest()->get('product_id')),
                    Criteria::IN,
                )->find(),
                Criteria::IN,
            );
        } elseif (false === $current_category) {
            $search->filterByCategory(
                CategoryQuery::create()->filterByProduct(
                    ProductCategoryQuery::create()->findPk($this->getCurrentRequest()->get('product_id')),
                    Criteria::IN,
                )->find(),
                Criteria::NOT_IN,
            );
        }

        $visible = $this->getVisible();

        if (BooleanOrBothType::ANY !== $visible) {
            $search->filterByVisible($visible ? 1 : 0);
        }

        $virtual = $this->getVirtual();

        if (BooleanOrBothType::ANY !== $virtual) {
            $search->filterByVirtual($virtual ? 1 : 0);
        }

        $exclude = $this->getExclude();

        if (null !== $exclude) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $exclude_category = $this->getExcludeCategory();

        if (null !== $exclude_category) {
            $search
                ->useProductCategoryQuery('ExcludeCategorySelect')
                ->filterByCategoryId($exclude_category, Criteria::NOT_IN)
                ->endUse();
        }

        if (null !== $taxRuleIdList = $this->getTaxRuleId()) {
            $search->filterByTaxRuleId($taxRuleIdList, Criteria::IN);
        }

        if (null !== $taxRuleIdList = $this->getExcludeTaxRuleId()) {
            $search->filterByTaxRuleId($taxRuleIdList, Criteria::NOT_IN);
        }

        $new = $this->getNew();
        $promo = $this->getPromo();
        $min_stock = $this->getMinStock();
        $min_weight = $this->getMinWeight();
        $max_weight = $this->getMaxWeight();
        $min_price = $this->getMinPrice();
        $max_price = $this->getMaxPrice();

        if ($complex) {
            if (true === $new) {
                $isPSELeftJoinList[] = 'is_new';
                $search->joinProductSaleElements('is_new', Criteria::LEFT_JOIN)
                    ->where('`is_new`.NEWNESS' . Criteria::EQUAL . '1')
                    ->where('NOT ISNULL(`is_new`.ID)');
            } elseif (false === $new) {
                $isPSELeftJoinList[] = 'is_new';
                $search->joinProductSaleElements('is_new', Criteria::LEFT_JOIN)
                    ->where('`is_new`.NEWNESS' . Criteria::EQUAL . '0')
                    ->where('NOT ISNULL(`is_new`.ID)');
            }

            if (true === $promo) {
                $isPSELeftJoinList[] = 'is_promo';
                $search->joinProductSaleElements('is_promo', Criteria::LEFT_JOIN)
                    ->where('`is_promo`.PROMO' . Criteria::EQUAL . '1')
                    ->where('NOT ISNULL(`is_promo`.ID)');
            } elseif (false === $promo) {
                $isPSELeftJoinList[] = 'is_promo';
                $search->joinProductSaleElements('is_promo', Criteria::LEFT_JOIN)
                    ->where('`is_promo`.PROMO' . Criteria::EQUAL . '0')
                    ->where('NOT ISNULL(`is_promo`.ID)');
            }

            if (null !== $min_stock) {
                $isPSELeftJoinList[] = 'is_min_stock';
                $search->joinProductSaleElements('is_min_stock', Criteria::LEFT_JOIN)
                    ->where('`is_min_stock`.QUANTITY' . Criteria::GREATER_EQUAL . '?', $min_stock, \PDO::PARAM_INT)
                    ->where('NOT ISNULL(`is_min_stock`.ID)');
            }

            if (null !== $min_weight) {
                $isPSELeftJoinList[] = 'is_min_weight';
                $search->joinProductSaleElements('is_min_weight', Criteria::LEFT_JOIN)
                    ->where('`is_min_weight`.WEIGHT' . Criteria::GREATER_EQUAL . '?', $min_weight, \PDO::PARAM_STR)
                    ->where('NOT ISNULL(`is_min_weight`.ID)');
            }

            if (null !== $max_weight) {
                $isPSELeftJoinList[] = 'is_max_weight';
                $search->joinProductSaleElements('is_max_weight', Criteria::LEFT_JOIN)
                    ->where('`is_max_weight`.WEIGHT' . Criteria::LESS_EQUAL . '?', $max_weight, \PDO::PARAM_STR)
                    ->where('NOT ISNULL(`is_max_weight`.ID)');
            }

            $attributeNonStrictMatch = $this->getAttributeNonStrictMatch();

            if ('*' !== $attributeNonStrictMatch) {
                if ('none' === $attributeNonStrictMatch) {
                    $actuallyUsedAttributeNonStrictMatchList = $isPSELeftJoinList;
                } else {
                    $actuallyUsedAttributeNonStrictMatchList = array_values(array_intersect($isPSELeftJoinList, $attributeNonStrictMatch));
                }

                foreach ($actuallyUsedAttributeNonStrictMatchList as $key => $actuallyUsedAttributeNonStrictMatch) {
                    if (0 === $key) {
                        continue;
                    }

                    $search->where('`' . $actuallyUsedAttributeNonStrictMatch . '`.ID=`' . $actuallyUsedAttributeNonStrictMatchList[$key - 1] . '`.ID');
                }
            }

            if (null !== $min_price) {
                if (false === ConfigQuery::useTaxFreeAmounts()) {
                    // @todo
                }

                $isPSELeftJoinList[] = 'is_min_price';
                $isProductPriceFirstLeftJoin = ['is_min_price', 'min_price_data'];

                $minPriceJoin = new Join();
                $minPriceJoin->addExplicitCondition(ProductSaleElementsTableMap::TABLE_NAME, 'ID', 'is_min_price', ProductPriceTableMap::TABLE_NAME, 'PRODUCT_SALE_ELEMENTS_ID', 'min_price_data');
                $minPriceJoin->setJoinType(Criteria::LEFT_JOIN);

                $search->joinProductSaleElements('is_min_price', Criteria::LEFT_JOIN)
                    ->addJoinObject($minPriceJoin, 'is_min_price_join')
                    ->addJoinCondition('is_min_price_join', '`min_price_data`.`currency_id` = ?', $currency->getId(), null, \PDO::PARAM_INT);

                if ($defaultCurrency?->getId() !== $currency->getId()) {
                    $minPriceJoinDefaultCurrency = new Join();
                    $minPriceJoinDefaultCurrency->addExplicitCondition(ProductSaleElementsTableMap::TABLE_NAME, 'ID', 'is_min_price', ProductPriceTableMap::TABLE_NAME, 'PRODUCT_SALE_ELEMENTS_ID', 'min_price_data' . $defaultCurrencySuffix);
                    $minPriceJoinDefaultCurrency->setJoinType(Criteria::LEFT_JOIN);

                    $search->addJoinObject($minPriceJoinDefaultCurrency, 'is_min_price_join' . $defaultCurrencySuffix)
                        ->addJoinCondition('is_min_price_join' . $defaultCurrencySuffix, '`min_price_data' . $defaultCurrencySuffix . '`.`currency_id` = ?', $defaultCurrency->getId(), null, \PDO::PARAM_INT);

                    /**
                     * In propel we trust : $currency->getRate() always returns a float.
                     * Or maybe not : rate value is checked as a float in overloaded getRate method.
                     */
                    $MinPriceToCompareAsSQL = 'CASE WHEN ISNULL(CASE WHEN `is_min_price`.PROMO=1 THEN `min_price_data`.PROMO_PRICE ELSE `min_price_data`.PRICE END) OR `min_price_data`.FROM_DEFAULT_CURRENCY = 1 THEN
                    CASE WHEN `is_min_price`.PROMO=1 THEN `min_price_data' . $defaultCurrencySuffix . '`.PROMO_PRICE ELSE `min_price_data' . $defaultCurrencySuffix . '`.PRICE END * ' . $currency->getRate() . '
                ELSE
                    CASE WHEN `is_min_price`.PROMO=1 THEN `min_price_data`.PROMO_PRICE ELSE `min_price_data`.PRICE END
                END';
                } else {
                    $MinPriceToCompareAsSQL = 'CASE WHEN `is_min_price`.PROMO=1 THEN `min_price_data`.PROMO_PRICE ELSE `min_price_data`.PRICE END';
                }

                $search->where($MinPriceToCompareAsSQL . ' >= ?', $min_price, \PDO::PARAM_STR);
            }

            if (null !== $max_price) {
                $isPSELeftJoinList[] = 'is_max_price';
                $isProductPriceFirstLeftJoin = ['is_max_price', 'max_price_data'];

                $maxPriceJoin = new Join();
                $maxPriceJoin->addExplicitCondition(ProductSaleElementsTableMap::TABLE_NAME, 'ID', 'is_max_price', ProductPriceTableMap::TABLE_NAME, 'PRODUCT_SALE_ELEMENTS_ID', 'max_price_data');
                $maxPriceJoin->setJoinType(Criteria::LEFT_JOIN);

                $search->joinProductSaleElements('is_max_price', Criteria::LEFT_JOIN)
                    ->addJoinObject($maxPriceJoin, 'is_max_price_join')
                    ->addJoinCondition('is_max_price_join', '`max_price_data`.`currency_id` = ?', $currency->getId(), null, \PDO::PARAM_INT);

                if ($defaultCurrency?->getId() !== $currency->getId()) {
                    $maxPriceJoinDefaultCurrency = new Join();
                    $maxPriceJoinDefaultCurrency->addExplicitCondition(ProductSaleElementsTableMap::TABLE_NAME, 'ID', 'is_max_price', ProductPriceTableMap::TABLE_NAME, 'PRODUCT_SALE_ELEMENTS_ID', 'max_price_data' . $defaultCurrencySuffix);
                    $maxPriceJoinDefaultCurrency->setJoinType(Criteria::LEFT_JOIN);

                    $search->addJoinObject($maxPriceJoinDefaultCurrency, 'is_max_price_join' . $defaultCurrencySuffix)
                        ->addJoinCondition('is_max_price_join' . $defaultCurrencySuffix, '`max_price_data' . $defaultCurrencySuffix . '`.`currency_id` = ?', $defaultCurrency->getId(), null, \PDO::PARAM_INT);

                    /**
                     * In propel we trust : $currency->getRate() always returns a float.
                     * Or maybe not : rate value is checked as a float in overloaded getRate method.
                     */
                    $MaxPriceToCompareAsSQL = 'CASE WHEN ISNULL(CASE WHEN `is_max_price`.PROMO=1 THEN `max_price_data`.PROMO_PRICE ELSE `max_price_data`.PRICE END) OR `min_price_data`.FROM_DEFAULT_CURRENCY = 1 THEN
                    CASE WHEN `is_max_price`.PROMO=1 THEN `max_price_data' . $defaultCurrencySuffix . '`.PROMO_PRICE ELSE `max_price_data' . $defaultCurrencySuffix . '`.PRICE END * ' . $currency->getRate() . '
                ELSE
                    CASE WHEN `is_max_price`.PROMO=1 THEN `max_price_data`.PROMO_PRICE ELSE `max_price_data`.PRICE END
                END';
                } else {
                    $MaxPriceToCompareAsSQL = 'CASE WHEN `is_max_price`.PROMO=1 THEN `max_price_data`.PROMO_PRICE ELSE `max_price_data`.PRICE END';
                }

                $search->where($MaxPriceToCompareAsSQL . '<=?', $max_price, \PDO::PARAM_STR);
            }

            /*
             * for ordering and outputs, the product will be :
             * - new if at least one the criteria matching PSE is new
             * - in promo if at least one the criteria matching PSE is in promo
             */

            /* if we don't have any join yet, let's make a global one */
            if ([] === $isProductPriceFirstLeftJoin) {
                if ([] === $isPSELeftJoinList) {
                    $joiningTable = 'global';
                    $isPSELeftJoinList[] = $joiningTable;
                    $search->joinProductSaleElements('global', Criteria::LEFT_JOIN);
                } else {
                    $joiningTable = $isPSELeftJoinList[0];
                }

                $isProductPriceFirstLeftJoin = [$joiningTable, 'global_price_data'];

                $globalPriceJoin = new Join();
                $globalPriceJoin->addExplicitCondition(ProductSaleElementsTableMap::TABLE_NAME, 'ID', $joiningTable, ProductPriceTableMap::TABLE_NAME, 'PRODUCT_SALE_ELEMENTS_ID', 'global_price_data');
                $globalPriceJoin->setJoinType(Criteria::LEFT_JOIN);

                $search->addJoinObject($globalPriceJoin, 'global_price_join')
                    ->addJoinCondition('global_price_join', '`global_price_data`.`currency_id` = ?', $currency->getId(), null, \PDO::PARAM_INT);

                if ($defaultCurrency?->getId() !== $currency->getId()) {
                    $globalPriceJoinDefaultCurrency = new Join();
                    $globalPriceJoinDefaultCurrency->addExplicitCondition(ProductSaleElementsTableMap::TABLE_NAME, 'ID', $joiningTable, ProductPriceTableMap::TABLE_NAME, 'PRODUCT_SALE_ELEMENTS_ID', 'global_price_data' . $defaultCurrencySuffix);
                    $globalPriceJoinDefaultCurrency->setJoinType(Criteria::LEFT_JOIN);
                    $search->addJoinObject($globalPriceJoinDefaultCurrency, 'global_price_join' . $defaultCurrencySuffix)
                        ->addJoinCondition('global_price_join' . $defaultCurrencySuffix, '`global_price_data' . $defaultCurrencySuffix . '`.`currency_id` = ?', $defaultCurrency->getId(), null, \PDO::PARAM_INT);
                }
            }

            /*
             * we need to test all promo field from our previous conditions. Indeed ie:
             * product P0, attributes color : red
             * P0red is in promo and is the only attribute combinaton availability.
             * so the product might be consider as in promo (in outputs and ordering)
             * We got the following loop to display in promo AND new product but we don't care it's the same attribute which is new and in promo :
             * {loop type="product" promo="1" new="1" attribute_non_strict_match="promo,new"} {/loop}
             * our request will so far returns 1 line
             *
             * is_promo.ID | is_promo.PROMO | is_promo.NEWNESS | is_new.ID | is_new.PROMO | is_new.NEWNESS
             *      NULL            NULL              NULL        red_id         1               0
             *
             * So that we can say the product is in global promo only with is_promo.PROMO, we must acknowledge it with (is_promo.PROMO OR is_new.PROMO)
             */
            $booleanMatchedPromoList = [];
            $booleanMatchedNewnessList = [];

            foreach ($isPSELeftJoinList as $isPSELeftJoin) {
                $booleanMatchedPromoList[] = '`' . $isPSELeftJoin . '`.PROMO';
                $booleanMatchedNewnessList[] = '`' . $isPSELeftJoin . '`.NEWNESS';
            }

            $search->withColumn('(' . implode(' OR ', $booleanMatchedPromoList) . ')', 'main_product_is_promo');
            $search->withColumn('(' . implode(' OR ', $booleanMatchedNewnessList) . ')', 'main_product_is_new');

            $booleanMatchedPrice =
                'CASE WHEN `' . $isProductPriceFirstLeftJoin[0] . '`.PROMO=1 THEN `'
                . $isProductPriceFirstLeftJoin[1] . '`.PROMO_PRICE ELSE `'
                . $isProductPriceFirstLeftJoin[1] . '`.PRICE END';
            $booleanMatchedPriceDefaultCurrency = 'CASE WHEN `' . $isProductPriceFirstLeftJoin[0] . '`.PROMO=1 THEN `' . $isProductPriceFirstLeftJoin[1] . $defaultCurrencySuffix . '`.PROMO_PRICE ELSE `' . $isProductPriceFirstLeftJoin[1] . $defaultCurrencySuffix . '`.PRICE END';

            if ($defaultCurrency?->getId() !== $currency->getId()) {
                /**
                 * In propel we trust : $currency->getRate() always returns a float.
                 * Or maybe not : rate value is checked as a float in overloaded getRate method.
                 */
                $priceToCompareAsSQL =
                    'CASE WHEN ISNULL(' . $booleanMatchedPrice . ') THEN '
                    . $booleanMatchedPriceDefaultCurrency . ' * ' . $currency->getRate()
                    . ' ELSE ' . $booleanMatchedPrice . ' END';
            } else {
                $priceToCompareAsSQL = $booleanMatchedPrice;
            }

            $search->withColumn('MAX(' . $priceToCompareAsSQL . ')', 'real_highest_price');
            $search->withColumn('MIN(' . $priceToCompareAsSQL . ')', 'real_lowest_price');
        } else {
            if (true === $new) {
                $search->where('`pse`.NEWNESS' . Criteria::EQUAL . '1');
            } elseif (false === $new) {
                $search->where('`pse`.NEWNESS' . Criteria::EQUAL . '0');
            }

            if (true === $promo) {
                $search->where('`pse`.PROMO' . Criteria::EQUAL . '1');
            } elseif (false === $promo) {
                $search->where('`pse`.PROMO' . Criteria::EQUAL . '0');
            }

            if (null !== $min_stock) {
                $search->where('`pse`.QUANTITY' . Criteria::GREATER_EQUAL . '?', $min_stock, \PDO::PARAM_INT);
            }

            if (null !== $min_weight) {
                $search->where('`pse`.WEIGHT' . Criteria::GREATER_EQUAL . '?', $min_weight, \PDO::PARAM_STR);
            }

            if (null !== $max_weight) {
                $search->where('`is_max_weight`.WEIGHT' . Criteria::LESS_EQUAL . '?', $max_weight, \PDO::PARAM_STR);
            }

            if (null !== $min_price) {
                if (false === ConfigQuery::useTaxFreeAmounts()) {
                    // @todo
                }

                $search->where($priceToCompareAsSQL . '>=?', $min_price, \PDO::PARAM_STR);
            }

            if (null !== $max_price) {
                if (false === ConfigQuery::useTaxFreeAmounts()) {
                    // @todo
                }

                $search->where($priceToCompareAsSQL . '<=?', $max_price, \PDO::PARAM_STR);
            }
        }

        // First join sale_product table...
        $search
            ->leftJoinSaleProduct('SaleProductPriceDisplay');

        // ... then the sale table...
        $salesJoin = new Join();
        $salesJoin->addExplicitCondition(
            'SaleProductPriceDisplay',
            'SALE_ID',
            null,
            SaleTableMap::TABLE_NAME,
            'ID',
            'SalePriceDisplay',
        );
        $salesJoin->setJoinType(Criteria::LEFT_JOIN);

        $search
            ->addJoinObject($salesJoin, 'SalePriceDisplay')
            ->addJoinCondition('SalePriceDisplay', '`SalePriceDisplay`.`active` = 1');

        // ... to get the DISPLAY_INITIAL_PRICE column !
        $search->withColumn('`SalePriceDisplay`.DISPLAY_INITIAL_PRICE', 'display_initial_price');

        $feature_availability = $this->getFeatureAvailability();

        $this->manageFeatureAv($search, $feature_availability ?? []);

        $feature_values = $this->getFeatureValues();

        $this->manageFeatureValue($search, $feature_values ?? []);

        $search->groupBy(ProductTableMap::COL_ID);

        if (!$complex) {
            $search->withColumn('`pse`.ID', 'pse_id');

            $search->withColumn('`pse`.NEWNESS', 'is_new');
            $search->withColumn('`pse`.PROMO', 'is_promo');
            $search->withColumn('`pse`.QUANTITY', 'quantity');
            $search->withColumn('`pse`.WEIGHT', 'weight');
            $search->withColumn('`pse`.EAN_CODE', 'ean_code');

            $search->withColumn('COUNT(distinct(`pse_count`.ID))', 'pse_count');
        }

        $orders = $this->getOrder();

        foreach ($orders as $order) {
            switch ($order) {
                case 'id':
                    $search->orderById(Criteria::ASC);
                    break;
                case 'id_reverse':
                    $search->orderById(Criteria::DESC);
                    break;
                case 'alpha':
                    $search->addAscendingOrderByColumn('i18n_TITLE');
                    break;
                case 'alpha_reverse':
                    $search->addDescendingOrderByColumn('i18n_TITLE');
                    break;
                case 'min_price':
                    if ($complex) {
                        $search->addAscendingOrderByColumn('real_lowest_price');
                    } else {
                        $search->addAscendingOrderByColumn('real_price');
                    }

                    break;
                case 'max_price':
                    if ($complex) {
                        $search->addDescendingOrderByColumn('real_lowest_price');
                    } else {
                        $search->addDescendingOrderByColumn('real_price');
                    }

                    break;
                case 'manual':
                    if (!$manualOrderAllowed) {
                        throw new \InvalidArgumentException('Manual order require a *single* category ID or category_default ID, and a depth <= 1');
                    }

                    $search->addAscendingOrderByColumn('position_delegate');
                    break;
                case 'manual_reverse':
                    if (!$manualOrderAllowed) {
                        throw new \InvalidArgumentException('Manual reverse order require a *single* category ID or category_default ID, and a depth <= 1');
                    }

                    $search->addDescendingOrderByColumn('position_delegate');
                    break;
                case 'ref':
                    $search->orderByRef(Criteria::ASC);
                    break;
                case 'ref_reverse':
                    $search->orderByRef(Criteria::DESC);
                    break;
                case 'visible':
                    $search->orderByVisible(Criteria::ASC);
                    break;
                case 'visible_reverse':
                    $search->orderByVisible(Criteria::DESC);
                    break;
                case 'promo':
                    if ($complex) {
                        $search->addDescendingOrderByColumn('main_product_is_promo');
                    } else {
                        $search->addDescendingOrderByColumn('is_promo');
                    }

                    break;
                case 'new':
                    if ($complex) {
                        $search->addDescendingOrderByColumn('main_product_is_new');
                    } else {
                        $search->addDescendingOrderByColumn('is_new');
                    }

                    break;
                case 'created':
                    $search->addAscendingOrderByColumn('created_at');
                    break;
                case 'created_reverse':
                    $search->addDescendingOrderByColumn('created_at');
                    break;
                case 'updated':
                    $search->addAscendingOrderByColumn('updated_at');
                    break;
                case 'updated_reverse':
                    $search->addDescendingOrderByColumn('updated_at');
                    break;
                case 'position':
                    $search->addAscendingOrderByColumn('position_delegate');
                    break;
                case 'position_reverse':
                    $search->addDescendingOrderByColumn('position_delegate');
                    break;
                case 'given_id':
                    if (null === $id) {
                        throw new \InvalidArgumentException('Given_id order cannot be set without `id` argument');
                    }

                    foreach ($id as $singleId) {
                        $givenIdMatched = 'given_id_matched_' . $singleId;
                        $search->withColumn(ProductTableMap::COL_ID . \sprintf("='%d'", $singleId), $givenIdMatched);
                        $search->orderBy($givenIdMatched, Criteria::DESC);
                    }

                    break;
                case 'random':
                    $search->clearOrderByColumns();
                    $search->addAscendingOrderByColumn('RAND()');
                    break 2;
            }
        }

        return $search;
    }

    /**
     * @throws PropelException
     */
    protected function getDefaultCategoryId(ProductModel $product): ?int
    {
        $defaultCategoryId = null;

        if ((bool) $product->getVirtualColumn('is_default_category')) {
            $defaultCategoryId = $product->getVirtualColumn('default_category_id');
        } else {
            $defaultCategoryId = $product->getDefaultCategoryId();
        }

        return $defaultCategoryId;
    }
}
