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

namespace BackOfficeDefaultTwigBundle\Controller\Catalog;

use BackOfficeDefaultTwigBundle\Service\Admin\AdminAccessChecker;
use BackOfficeDefaultTwigBundle\Service\Admin\AdminFormAction;
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\FeatureProduct\FeatureProductDeleteEvent;
use Thelia\Core\Event\FeatureProduct\FeatureProductUpdateEvent;
use Thelia\Core\Event\MetaData\MetaDataCreateOrUpdateEvent;
use Thelia\Core\Event\MetaData\MetaDataDeleteEvent;
use Thelia\Core\Event\Product\ProductCombinationGenerationEvent;
use Thelia\Core\Event\Product\ProductSetTemplateEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementCreateEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementDeleteEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementToggleVisibilityEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Attribute;
use Thelia\Model\AttributeAvQuery;
use Thelia\Model\AttributeQuery;
use Thelia\Model\AttributeTemplateQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Feature;
use Thelia\Model\FeatureAv;
use Thelia\Model\FeatureAvQuery;
use Thelia\Model\FeatureProductQuery;
use Thelia\Model\FeatureQuery;
use Thelia\Model\FeatureTemplateQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\Product;
use Thelia\Model\MetaData;
use Thelia\Model\MetaDataQuery;
use Thelia\Model\ProductDocumentQuery;
use Thelia\Model\ProductImageQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsProductDocument;
use Thelia\Model\ProductSaleElementsProductDocumentQuery;
use Thelia\Model\ProductSaleElementsProductImage;
use Thelia\Model\ProductSaleElementsProductImageQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\Template;
use Thelia\Model\TemplateQuery;
use Twig\Environment;

final class ProductAdvancedController
{
    private const RESOURCE = AdminResources::PRODUCT;
    private const EDIT_ROUTE = 'admin.products.update';

    public function __construct(
        private readonly AdminFormAction $action,
        private readonly AdminAccessChecker $access,
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    #[Route('/admin/product/{productId}/available-content/{folderId}.{_format}', name: 'admin.product.available-related-content', methods: ['GET'], requirements: ['productId' => '\d+', 'folderId' => '\d+'])]
    public function availableRelatedContent(int $productId, int $folderId, string $_format): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $locale = $this->defaultLocale();
        $alreadyAssigned = \Thelia\Model\ProductAssociatedContentQuery::create()
            ->select('content_id')
            ->findByProductId($productId)
            ->toArray();

        $query = ContentQuery::create()
            ->useContentFolderQuery()
            ->filterByFolderId($folderId)
            ->endUse()
            ->filterById($alreadyAssigned, \Propel\Runtime\ActiveQuery\Criteria::NOT_IN)
            ->orderByPosition();

        $items = [];
        foreach ($query->find() as $content) {
            $content->setLocale($locale);
            $items[] = ['id' => (int) $content->getId(), 'title' => (string) $content->getTitle()];
        }

        return $_format === 'json' ? new JsonResponse($items) : new Response('');
    }

    #[Route('/admin/product/{productId}/available-accessories/{categoryId}.{_format}', name: 'admin.product.accessories-content', methods: ['GET'], requirements: ['productId' => '\d+', 'categoryId' => '\d+'])]
    public function availableAccessories(int $productId, int $categoryId, string $_format): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $locale = $this->defaultLocale();
        $alreadyAssigned = \Thelia\Model\AccessoryQuery::create()
            ->select('accessory')
            ->findByProductId($productId)
            ->toArray();
        $excluded = array_merge([$productId], array_map('intval', $alreadyAssigned));

        $query = ProductQuery::create()
            ->useProductCategoryQuery()
            ->filterByCategoryId($categoryId)
            ->endUse()
            ->filterById($excluded, \Propel\Runtime\ActiveQuery\Criteria::NOT_IN)
            ->orderByPosition();

        $items = [];
        foreach ($query->find() as $product) {
            $product->setLocale($locale);
            $items[] = ['id' => (int) $product->getId(), 'title' => (string) $product->getTitle(), 'ref' => (string) $product->getRef()];
        }

        return $_format === 'json' ? new JsonResponse($items) : new Response('');
    }

    #[Route('/admin/product/update-content-position', name: 'admin.product.update-content-position', methods: ['GET', 'POST'])]
    public function updateContentPosition(Request $request): Response
    {
        $event = new UpdatePositionEvent(
            (int) $request->get('content_id', 0),
            (int) $request->get('mode', UpdatePositionEvent::POSITION_ABSOLUTE),
            (int) $request->get('position', 0),
        );

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: $event,
            eventName: TheliaEvents::PRODUCT_UPDATE_CONTENT_POSITION,
            actionLabel: 'Product content reorder',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['product_id' => (int) $request->get('product_id', 0), 'current_tab' => 'related'],
        );
    }

    #[Route('/admin/product/update-accessory-position', name: 'admin.product.update-accessory-position', methods: ['GET', 'POST'])]
    public function updateAccessoryPosition(Request $request): Response
    {
        $event = new UpdatePositionEvent(
            (int) $request->get('accessory_id', 0),
            (int) $request->get('mode', UpdatePositionEvent::POSITION_ABSOLUTE),
            (int) $request->get('position', 0),
        );

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: $event,
            eventName: TheliaEvents::PRODUCT_UPDATE_ACCESSORY_POSITION,
            actionLabel: 'Product accessory reorder',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['product_id' => (int) $request->get('product_id', 0), 'current_tab' => 'related'],
        );
    }

    #[Route('/admin/product/calculate-price', name: 'admin.product.calculate-price', methods: ['GET'])]
    public function calculatePrice(Request $request): JsonResponse
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return new JsonResponse([], Response::HTTP_FORBIDDEN);
        }

        $price = (float) $request->query->get('price', 0);
        $taxRuleId = (int) $request->query->get('tax_rule_id', 0);
        $action = (string) $request->query->get('action', 'untaxed_to_taxed');

        $result = $price;
        $taxRule = \Thelia\Model\TaxRuleQuery::create()->findPk($taxRuleId);
        $country = \Thelia\Model\CountryQuery::create()->findOneByByDefault(1);

        if ($taxRule !== null && $country !== null) {
            $calculator = new \Thelia\Domain\Taxation\TaxEngine\Calculator();
            try {
                $calculator->loadTaxRuleWithoutProduct($taxRule, $country);
                $result = match ($action) {
                    'taxed_to_untaxed' => $calculator->getUntaxedPrice($price),
                    default => $calculator->getTaxedPrice($price),
                };
            } catch (\Throwable) {
                $result = $price;
            }
        }

        return new JsonResponse([
            'price' => $price,
            'tax_rule_id' => $taxRuleId,
            'action' => $action,
            'result' => round((float) $result, 6),
        ]);
    }

    #[Route('/admin/product/calculate-raw-price', name: 'admin.product.calculate-raw-price', methods: ['GET'])]
    public function calculateRawPrice(Request $request): JsonResponse
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return new JsonResponse([], Response::HTTP_FORBIDDEN);
        }

        $price = (float) $request->query->get('price', 0);
        $taxRuleId = (int) $request->query->get('tax_rule_id', 0);

        $taxRule = \Thelia\Model\TaxRuleQuery::create()->findPk($taxRuleId);
        $country = \Thelia\Model\CountryQuery::create()->findOneByByDefault(1);

        $untaxed = $price;
        if ($taxRule !== null && $country !== null) {
            $calculator = new \Thelia\Domain\Taxation\TaxEngine\Calculator();
            try {
                $calculator->loadTaxRuleWithoutProduct($taxRule, $country);
                $untaxed = $calculator->getUntaxedPrice($price);
            } catch (\Throwable) {
                $untaxed = $price;
            }
        }

        return new JsonResponse([
            'price' => $price,
            'tax_rule_id' => $taxRuleId,
            'result' => round((float) $untaxed, 6),
        ]);
    }

    #[Route('/admin/product/load-converted-prices', name: 'admin.product.load-converted-prices', methods: ['GET'])]
    public function loadConvertedPrices(Request $request): JsonResponse
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return new JsonResponse([], Response::HTTP_FORBIDDEN);
        }

        $price = (float) $request->query->get('price', 0);
        $defaultCurrencyId = (int) $request->query->get('default_currency_id', 0);

        $base = \Thelia\Model\CurrencyQuery::create()->findPk($defaultCurrencyId)
            ?? \Thelia\Model\CurrencyQuery::create()->findOneByByDefault(1);

        $prices = [];
        foreach (\Thelia\Model\CurrencyQuery::create()->find() as $currency) {
            $rate = (float) $currency->getRate();
            $baseRate = $base ? (float) $base->getRate() : 1.0;
            $converted = $baseRate > 0 ? ($price / $baseRate) * $rate : $price;
            $prices[(int) $currency->getId()] = round($converted, 6);
        }

        return new JsonResponse(['prices' => $prices]);
    }

    #[Route('/admin/product/product-sale-element-visibility', name: 'admin.product.product-sale-element-visibility', methods: ['GET', 'POST'])]
    public function pseToggleVisibility(Request $request): Response
    {
        $pseId = (int) $request->get('product_sale_element_id', 0);

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: new ProductSaleElementToggleVisibilityEvent($pseId),
            eventName: TheliaEvents::PRODUCT_PRODUCT_SALE_ELEMENT_TOGGLE_VISIBILITY,
            actionLabel: 'PSE visibility',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['product_id' => (int) $request->get('product_id', 0), 'current_tab' => 'pse'],
        );
    }

    #[Route('/admin/product/product-sale-element-position', name: 'admin.product.product-sale-element-position', methods: ['GET', 'POST'])]
    public function psePosition(Request $request): Response
    {
        $event = new UpdatePositionEvent(
            (int) $request->get('product_sale_element_id', 0),
            (int) $request->get('mode', UpdatePositionEvent::POSITION_ABSOLUTE),
            (int) $request->get('position', 0),
        );

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: $event,
            eventName: TheliaEvents::PRODUCT_PRODUCT_SALE_ELEMENT_UPDATE_POSITION,
            actionLabel: 'PSE reorder',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['product_id' => (int) $request->get('product_id', 0), 'current_tab' => 'pse'],
        );
    }

    #[Route('/admin/products/attributes/tab', name: 'admin.products.attributes.tab', methods: ['GET'])]
    public function attributesTab(Request $request): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $productId = (int) $request->get('product_id', 0);
        $product = ProductQuery::create()->findPk($productId);
        if ($product === null) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        return new Response($this->twig->render(
            '@BackOfficeDefaultTwig/catalog/product/_attributes_tab.html.twig',
            $this->attributesTabContext($product),
        ));
    }

    #[Route('/admin/product/{productId}/set-product-template', name: 'admin.products.set-product-template', methods: ['POST', 'GET'], requirements: ['productId' => '\d+'])]
    public function setProductTemplate(int $productId, Request $request): Response
    {
        $product = ProductQuery::create()->findPk($productId);
        if ($product === null) {
            return new RedirectResponse($this->urls->generate('admin.products.default'));
        }

        $event = new ProductSetTemplateEvent($product, (int) $request->get('template_id', 0), $this->defaultCurrencyId());

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: $event,
            eventName: TheliaEvents::PRODUCT_SET_TEMPLATE,
            actionLabel: 'Product template set',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['product_id' => $productId],
        );
    }

    #[Route('/admin/product/{productId}/update-attributes-and-features', name: 'admin.products.update-attributes-and-features', methods: ['POST', 'GET'], requirements: ['productId' => '\d+'])]
    public function updateAttributesAndFeatures(int $productId, Request $request, EventDispatcherInterface $events): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::UPDATE)) {
            return $denied;
        }

        $product = ProductQuery::create()->findPk($productId);
        if ($product !== null) {
            $this->dispatchFeatureUpdates($product, $request, $events);
        }

        return new RedirectResponse($this->urls->generate(self::EDIT_ROUTE, [
            'product_id' => $productId,
            'current_tab' => 'attributes',
        ]));
    }

    #[Route('/admin/product/{productId}/attribute-values/{attributeId}.{_format}', name: 'admin.product.attribute-values', methods: ['GET'], requirements: ['productId' => '\d+', 'attributeId' => '\d+'])]
    public function attributeValues(int $productId, int $attributeId, string $_format): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $locale = $this->defaultLocale();
        $items = [];
        foreach (AttributeAvQuery::create()->filterByAttributeId($attributeId)->orderByPosition()->find() as $av) {
            $av->setLocale($locale);
            $items[] = ['id' => (int) $av->getId(), 'title' => (string) $av->getTitle()];
        }

        return $_format === 'json' ? new JsonResponse($items) : new Response('');
    }

    #[Route('/admin/product/{productId}/add-attribute-value-to-combination/{attributeAvId}/{combination}.{_format}', name: 'admin.product.add-attribute-value-to-combination', methods: ['GET'], requirements: ['productId' => '\d+', 'attributeAvId' => '\d+'])]
    public function addAttributeValueToCombination(int $productId, int $attributeAvId, string $combination, string $_format): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::UPDATE)) {
            return $denied;
        }

        $combinationIds = $combination !== '' ? array_filter(array_map('intval', explode(',', $combination))) : [];
        $combinationIds[] = $attributeAvId;
        $combinationIds = array_unique($combinationIds);

        $items = [];
        foreach (AttributeAvQuery::create()->filterById($combinationIds, Criteria::IN)->find() as $av) {
            $av->setLocale($this->defaultLocale());
            $items[] = ['id' => (int) $av->getId(), 'title' => (string) $av->getTitle()];
        }

        return $_format === 'json' ? new JsonResponse(['combination' => implode(',', $combinationIds), 'attributes' => $items]) : new Response('');
    }

    #[Route('/admin/product/combination/add', name: 'admin.product.combination.add', methods: ['POST', 'GET'])]
    public function combinationAdd(Request $request): Response
    {
        $product = ProductQuery::create()->findPk((int) $request->get('product_id', 0));
        if ($product === null) {
            return new RedirectResponse($this->urls->generate('admin.products.default'));
        }

        $combination = (string) $request->get('combination_attributes', '');
        $attributeAvList = $combination !== '' ? array_filter(array_map('intval', explode(',', $combination))) : [];
        $event = new ProductSaleElementCreateEvent($product, $attributeAvList, $this->defaultCurrencyId());

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: $event,
            eventName: TheliaEvents::PRODUCT_ADD_PRODUCT_SALE_ELEMENT,
            actionLabel: 'PSE created',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['product_id' => (int) $product->getId(), 'current_tab' => 'pse'],
        );
    }

    #[Route('/admin/product/combination/delete', name: 'admin.product.combination.delete', methods: ['POST', 'GET'])]
    public function combinationDelete(Request $request): Response
    {
        $event = new ProductSaleElementDeleteEvent(
            (int) $request->get('product_sale_element_id', 0),
            $this->defaultCurrencyId(),
        );

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: $event,
            eventName: TheliaEvents::PRODUCT_DELETE_PRODUCT_SALE_ELEMENT,
            actionLabel: 'PSE deleted',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['product_id' => (int) $request->get('product_id', 0), 'current_tab' => 'pse'],
        );
    }

    #[Route('/admin/product/combinations/update', name: 'admin.product.combination.update', methods: ['POST', 'GET'])]
    public function combinationUpdate(Request $request, EventDispatcherInterface $events): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::UPDATE)) {
            return $denied;
        }

        $productId = (int) $request->get('product_id', 0);
        $product = ProductQuery::create()->findPk($productId);
        if ($product === null) {
            return new RedirectResponse($this->urls->generate('admin.products.default'));
        }

        $currency = $this->defaultCurrencyId();
        $taxRule = (int) $request->get('tax_rule', (int) $product->getTaxRuleId());
        $useExchangeRate = (int) $request->get('use_exchange_rate', 0);
        $defaultPse = (int) $request->get('default_pse', 0);

        $ids = (array) $request->get('product_sale_element_id', []);
        $refs = (array) $request->get('reference', []);
        $prices = (array) $request->get('price', []);
        $weights = (array) $request->get('weight', []);
        $quantities = (array) $request->get('quantity', []);
        $salePrices = (array) $request->get('sale_price', []);
        $eans = (array) $request->get('ean_code', []);
        $onsale = (array) $request->get('onsale', []);
        $isnew = (array) $request->get('isnew', []);

        foreach ($ids as $index => $rawId) {
            $pseId = (int) $rawId;
            if ($pseId <= 0) {
                continue;
            }

            $event = new ProductSaleElementUpdateEvent($product, $pseId);
            $event
                ->setReference((string) ($refs[$index] ?? ''))
                ->setPrice((float) ($prices[$index] ?? 0))
                ->setCurrencyId($currency)
                ->setWeight((float) ($weights[$index] ?? 0))
                ->setQuantity((float) ($quantities[$index] ?? 0))
                ->setSalePrice((float) ($salePrices[$index] ?? 0))
                ->setOnsale(isset($onsale[$index]) ? 1 : 0)
                ->setIsnew(isset($isnew[$index]) ? 1 : 0)
                ->setIsdefault($defaultPse === $pseId)
                ->setEanCode((string) ($eans[$index] ?? ''))
                ->setTaxRuleId($taxRule)
                ->setFromDefaultCurrency($useExchangeRate);

            $events->dispatch($event, TheliaEvents::PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT);
        }

        return new RedirectResponse($this->urls->generate(self::EDIT_ROUTE, ['product_id' => $productId, 'current_tab' => 'pse']));
    }

    #[Route('/admin/products/combinations/tab', name: 'admin.product.combinations.tab', methods: ['GET'])]
    public function combinationsTab(Request $request): Response
    {
        $productId = (int) $request->get('product_id', 0);
        $product = ProductQuery::create()->findPk($productId);
        if ($product === null) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $pseRecords = ProductSaleElementsQuery::create()
            ->filterByProductId($productId)
            ->orderById()
            ->find();

        $rows = [];
        foreach ($pseRecords as $pse) {
            $price = $pse->getPricesByCurrency(CurrencyQuery::create()->findPk($this->defaultCurrencyId()));

            $combinationLabels = [];
            foreach ($pse->getAttributeCombinations() as $combination) {
                $attribute = $combination->getAttribute();
                $attributeAv = $combination->getAttributeAv();
                if ($attribute === null || $attributeAv === null) {
                    continue;
                }
                $attribute->setLocale($this->defaultLocale());
                $attributeAv->setLocale($this->defaultLocale());
                $combinationLabels[] = (string) $attribute->getTitle().': '.(string) $attributeAv->getTitle();
            }

            $rows[] = [
                'id' => (int) $pse->getId(),
                'label' => $combinationLabels === [] ? 'default' : implode(' / ', $combinationLabels),
                'ref' => (string) $pse->getRef(),
                'price' => $price ? (float) $price->getPrice() : 0.0,
                'sale_price' => $price ? (float) $price->getPromoPrice() : 0.0,
                'quantity' => (float) $pse->getQuantity(),
                'weight' => (float) $pse->getWeight(),
                'ean_code' => (string) $pse->getEanCode(),
                'onsale' => (bool) $pse->getPromo(),
                'isnew' => (bool) $pse->getNewness(),
                'isdefault' => (bool) $pse->getIsDefault(),
            ];
        }

        return new Response($this->twig->render('@BackOfficeDefaultTwig/catalog/product/_combinations_tab.html.twig', [
            'product' => $product,
            'pse_rows' => $rows,
            'tax_rule_id' => (int) $product->getTaxRuleId(),
            'currency_id' => $this->defaultCurrencyId(),
        ]));
    }

    #[Route('/admin/product/combination/build', name: 'admin.product.combination.build', methods: ['POST', 'GET'])]
    public function combinationBuild(Request $request): Response
    {
        $product = ProductQuery::create()->findPk((int) $request->get('product_id', 0));
        if ($product === null) {
            return new RedirectResponse($this->urls->generate('admin.products.default'));
        }

        $event = new ProductCombinationGenerationEvent($product, $this->defaultCurrencyId(), []);

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: $event,
            eventName: TheliaEvents::PRODUCT_COMBINATION_GENERATION,
            actionLabel: 'PSE combinations built',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['product_id' => (int) $product->getId(), 'current_tab' => 'pse'],
        );
    }

    #[Route('/admin/product/default-price/update', name: 'admin.product.combination.defaut-price.update', methods: ['POST', 'GET'])]
    public function combinationDefaultPriceUpdate(Request $request): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::UPDATE)) {
            return $denied;
        }

        return new RedirectResponse($this->urls->generate(self::EDIT_ROUTE, ['product_id' => (int) $request->get('product_id', 0), 'current_tab' => 'pse']));
    }

    #[Route('/admin/product_sale_elements/{pseId}/{type}/{typeId}', name: 'admin.product_sale_elements.document_image_assoc', methods: ['GET'], requirements: ['pseId' => '\d+', 'typeId' => '\d+', 'type' => 'image|document|virtual'])]
    public function pseDocumentImageAssoc(int $pseId, string $type, int $typeId, EventDispatcherInterface $events): JsonResponse
    {
        if ($this->access->check(self::RESOURCE, [], AccessManager::UPDATE)) {
            return new JsonResponse(['error' => 'forbidden'], Response::HTTP_FORBIDDEN);
        }

        $pse = ProductSaleElementsQuery::create()->findPk($pseId);
        if ($pse === null) {
            return new JsonResponse(['error' => 'pse not found'], Response::HTTP_NOT_FOUND);
        }

        $response = [
            'product_sale_elements_id' => $pseId,
            'type' => $type,
            'type_id' => $typeId,
        ];

        if ($type === 'image') {
            if (ProductImageQuery::create()->findPk($typeId) === null) {
                return new JsonResponse(['error' => 'image not found'], Response::HTTP_NOT_FOUND);
            }

            $assoc = ProductSaleElementsProductImageQuery::create()
                ->filterByProductSaleElementsId($pseId)
                ->findOneByProductImageId($typeId);

            if ($assoc === null) {
                $assoc = new ProductSaleElementsProductImage();
                $assoc->setProductSaleElementsId($pseId)->setProductImageId($typeId)->save();
                $response['is_associated'] = 1;
            } else {
                $assoc->delete();
                $response['is_associated'] = 0;
            }
            $response['product_image_id'] = $typeId;
        } elseif ($type === 'document') {
            if (ProductDocumentQuery::create()->findPk($typeId) === null) {
                return new JsonResponse(['error' => 'document not found'], Response::HTTP_NOT_FOUND);
            }

            $assoc = ProductSaleElementsProductDocumentQuery::create()
                ->filterByProductSaleElementsId($pseId)
                ->findOneByProductDocumentId($typeId);

            if ($assoc === null) {
                $assoc = new ProductSaleElementsProductDocument();
                $assoc->setProductSaleElementsId($pseId)->setProductDocumentId($typeId)->save();
                $response['is_associated'] = 1;
            } else {
                $assoc->delete();
                $response['is_associated'] = 0;
            }
            $response['product_document_id'] = $typeId;
        } elseif ($type === 'virtual') {
            if (ProductDocumentQuery::create()->findPk($typeId) === null) {
                return new JsonResponse(['error' => 'document not found'], Response::HTTP_NOT_FOUND);
            }

            $currentId = (int) MetaDataQuery::getVal('virtual', MetaData::PSE_KEY, $pseId);
            if ($currentId === $typeId) {
                $events->dispatch(
                    new MetaDataDeleteEvent('virtual', MetaData::PSE_KEY, $pseId),
                    TheliaEvents::META_DATA_DELETE,
                );
                $response['is_associated'] = 0;
            } else {
                $events->dispatch(
                    new MetaDataCreateOrUpdateEvent('virtual', MetaData::PSE_KEY, $pseId, $typeId),
                    TheliaEvents::META_DATA_UPDATE,
                );
                $response['is_associated'] = 1;
            }
            $response['product_document_id'] = $typeId;
        } else {
            return new JsonResponse(['error' => 'unsupported type for this endpoint'], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($response);
    }

    #[Route('/admin/product_sale_elements/ajax/{type}/{id}', name: 'admin.product_sale_elements.document_image_assoc.get_assoc', methods: ['GET'], requirements: ['id' => '\d+', 'type' => 'image|document|virtual'])]
    public function pseDocumentImageAssocGet(string $type, int $id): JsonResponse
    {
        if ($this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return new JsonResponse(['error' => 'forbidden'], Response::HTTP_FORBIDDEN);
        }

        $pse = ProductSaleElementsQuery::create()->findPk($id);
        if ($pse === null) {
            return new JsonResponse(['error' => 'pse not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'type' => $type,
            'pse_id' => $id,
            'items' => match ($type) {
                'image' => $this->pseImageItems($pse),
                'document' => $this->pseDocumentItems($pse, includeVisible: true),
                'virtual' => $this->pseDocumentItems($pse, includeVisible: false),
                default => [],
            },
        ]);
    }

    /**
     * @return list<array{id: int, title: string, url: string, filename: string, is_associated: bool}>
     */
    private function pseImageItems(ProductSaleElements $pse): array
    {
        $locale = $this->defaultLocale();
        $images = ProductImageQuery::create()
            ->filterByProductId((int) $pse->getProductId())
            ->orderByPosition()
            ->find();

        $assoc = ProductSaleElementsProductImageQuery::create()
            ->filterByProductSaleElementsId((int) $pse->getId())
            ->find();
        $assocIds = [];
        foreach ($assoc as $a) {
            $assocIds[(int) $a->getProductImageId()] = true;
        }

        $items = [];
        foreach ($images as $image) {
            $image->setLocale($locale);
            $items[] = [
                'id' => (int) $image->getId(),
                'title' => (string) $image->getTitle(),
                'url' => (string) $image->getFile(),
                'filename' => (string) $image->getFile(),
                'is_associated' => isset($assocIds[(int) $image->getId()]),
            ];
        }

        return $items;
    }

    /**
     * @return list<array{id: int, title: string, url: string, filename: string, is_associated: bool}>
     */
    private function pseDocumentItems(ProductSaleElements $pse, bool $includeVisible): array
    {
        $locale = $this->defaultLocale();
        $query = ProductDocumentQuery::create()
            ->filterByProductId((int) $pse->getProductId())
            ->orderByPosition();

        if (!$includeVisible) {
            $query->filterByVisible(0);
        }

        $documents = $query->find();

        $assoc = ProductSaleElementsProductDocumentQuery::create()
            ->filterByProductSaleElementsId((int) $pse->getId())
            ->find();
        $assocIds = [];
        foreach ($assoc as $a) {
            $assocIds[(int) $a->getProductDocumentId()] = true;
        }

        $items = [];
        foreach ($documents as $document) {
            $document->setLocale($locale);
            $items[] = [
                'id' => (int) $document->getId(),
                'title' => (string) $document->getTitle(),
                'url' => (string) $document->getFile(),
                'filename' => (string) $document->getFile(),
                'is_associated' => isset($assocIds[(int) $document->getId()]),
            ];
        }

        return $items;
    }

    #[Route('/admin/product/virtual-documents/{productId}/{pseId}', name: 'admin.product.virtual_documents', methods: ['GET'], requirements: ['productId' => '\d+', 'pseId' => '\d+'])]
    public function virtualDocuments(int $productId, int $pseId): JsonResponse
    {
        if ($this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return new JsonResponse(['error' => 'forbidden'], Response::HTTP_FORBIDDEN);
        }

        $selectedId = (int) MetaDataQuery::getVal('virtual', MetaData::PSE_KEY, $pseId);

        $documents = ProductDocumentQuery::create()
            ->filterByProductId($productId)
            ->filterByVisible(0)
            ->orderByPosition()
            ->find();

        $results = [];
        foreach ($documents as $document) {
            $results[] = [
                'id' => (int) $document->getId(),
                'title' => (string) $document->getTitle(),
                'file' => (string) $document->getFile(),
                'selected' => ((int) $document->getId() === $selectedId),
            ];
        }

        return new JsonResponse($results);
    }

    private function defaultLocale(): string
    {
        $defaultLang = LangQuery::create()->findOneByByDefault(true);

        return $defaultLang?->getLocale() ?? 'en_US';
    }

    private function defaultCurrencyId(): int
    {
        $defaultCurrency = CurrencyQuery::create()->findOneByByDefault(true);

        return (int) ($defaultCurrency?->getId() ?? 1);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributesTabContext(Product $product): array
    {
        $locale = $this->defaultLocale();
        $templates = [];
        foreach (TemplateQuery::create()->orderById()->find() as $template) {
            \assert($template instanceof Template);
            $template->setLocale($locale);
            $templates[] = [
                'id' => (int) $template->getId(),
                'name' => $template->getName() ?? \sprintf('#%d', (int) $template->getId()),
            ];
        }

        $templateId = (int) ($product->getTemplateId() ?? 0);

        return [
            'product' => $product,
            'product_template_id' => $templateId,
            'product_templates' => $templates,
            'product_attribute_rows' => $this->attributeRowsForTemplate($templateId, $locale),
            'product_feature_rows' => $this->featureRowsForProduct($product, $templateId, $locale),
            'attributes_form_action' => $this->urls->generate('admin.products.update-attributes-and-features', ['productId' => (int) $product->getId()]),
            'set_template_action' => $this->urls->generate('admin.products.set-product-template', ['productId' => (int) $product->getId()]),
        ];
    }

    /**
     * @return array<int, array{id: int, title: string}>
     */
    private function attributeRowsForTemplate(int $templateId, string $locale): array
    {
        if ($templateId <= 0) {
            return [];
        }

        $rows = [];
        $attributeTemplates = AttributeTemplateQuery::create()
            ->filterByTemplateId($templateId)
            ->orderByPosition()
            ->find();
        $attributeIds = [];
        foreach ($attributeTemplates as $entry) {
            $attributeIds[] = (int) $entry->getAttributeId();
        }
        if ($attributeIds === []) {
            return [];
        }

        $attributes = AttributeQuery::create()
            ->filterById($attributeIds, Criteria::IN)
            ->find();
        $byId = [];
        foreach ($attributes as $attribute) {
            \assert($attribute instanceof Attribute);
            $attribute->setLocale($locale);
            $byId[(int) $attribute->getId()] = $attribute;
        }

        foreach ($attributeIds as $id) {
            if (!isset($byId[$id])) {
                continue;
            }
            $attribute = $byId[$id];
            $rows[] = [
                'id' => $id,
                'title' => $attribute->getTitle() ?? \sprintf('#%d', $id),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{
     *     id: int,
     *     title: string,
     *     options: list<array{id: int, title: string, selected: bool}>,
     *     free_text: bool,
     *     free_text_value: string,
     * }>
     */
    private function featureRowsForProduct(Product $product, int $templateId, string $locale): array
    {
        if ($templateId <= 0) {
            return [];
        }

        $featureTemplates = FeatureTemplateQuery::create()
            ->filterByTemplateId($templateId)
            ->orderByPosition()
            ->find();
        $featureIds = [];
        foreach ($featureTemplates as $entry) {
            $featureIds[] = (int) $entry->getFeatureId();
        }
        if ($featureIds === []) {
            return [];
        }

        $features = FeatureQuery::create()
            ->filterById($featureIds, Criteria::IN)
            ->find();
        $byId = [];
        foreach ($features as $feature) {
            \assert($feature instanceof Feature);
            $feature->setLocale($locale);
            $byId[(int) $feature->getId()] = $feature;
        }

        $rows = [];
        $productId = (int) $product->getId();

        foreach ($featureIds as $featureId) {
            if (!isset($byId[$featureId])) {
                continue;
            }
            $feature = $byId[$featureId];

            $featureAvs = FeatureAvQuery::create()
                ->filterByFeatureId($featureId)
                ->orderByPosition()
                ->find();
            $options = [];
            foreach ($featureAvs as $av) {
                \assert($av instanceof FeatureAv);
                $av->setLocale($locale);
                $options[] = [
                    'id' => (int) $av->getId(),
                    'title' => $av->getTitle() ?? \sprintf('#%d', (int) $av->getId()),
                ];
            }

            $selectedAvIds = [];
            $freeTextValue = '';
            $freeTextOnly = $options === [];

            $featureProducts = FeatureProductQuery::create()
                ->filterByProductId($productId)
                ->filterByFeatureId($featureId)
                ->find();
            foreach ($featureProducts as $fp) {
                if ($fp->getIsFreeText()) {
                    $av = $fp->getFeatureAv();
                    $av?->setLocale($locale);
                    $freeTextValue = $av?->getTitle() ?? '';
                    continue;
                }
                $selectedAvIds[] = (int) $fp->getFeatureAvId();
            }

            foreach ($options as &$option) {
                $option['selected'] = \in_array($option['id'], $selectedAvIds, true);
            }
            unset($option);

            $rows[] = [
                'id' => $featureId,
                'title' => $feature->getTitle() ?? \sprintf('#%d', $featureId),
                'options' => $options,
                'free_text' => $freeTextOnly,
                'free_text_value' => $freeTextValue,
            ];
        }

        return $rows;
    }

    private function dispatchFeatureUpdates(Product $product, Request $request, EventDispatcherInterface $events): void
    {
        $productId = (int) $product->getId();
        $templateId = (int) ($product->getTemplateId() ?? 0);
        if ($templateId <= 0) {
            return;
        }

        $featureTemplates = FeatureTemplateQuery::create()
            ->filterByTemplateId($templateId)
            ->find();
        $allFeatureIds = [];
        foreach ($featureTemplates as $entry) {
            $allFeatureIds[] = (int) $entry->getFeatureId();
        }

        $updated = [];
        $featureValues = (array) $request->request->all()['feature_value'] ?? [];
        foreach ($featureValues as $featureId => $values) {
            $featureId = (int) $featureId;
            $events->dispatch(new FeatureProductDeleteEvent($productId, $featureId), TheliaEvents::PRODUCT_FEATURE_DELETE_VALUE);

            foreach ((array) $values as $value) {
                $events->dispatch(
                    new FeatureProductUpdateEvent($productId, $featureId, $value),
                    TheliaEvents::PRODUCT_FEATURE_UPDATE_VALUE,
                );
            }
            $updated[] = $featureId;
        }

        $featureTextValues = (array) $request->request->all()['feature_text_value'] ?? [];
        foreach ($featureTextValues as $featureId => $value) {
            $featureId = (int) $featureId;
            $value = (string) $value;
            $existing = FeatureProductQuery::create()
                ->filterByProductId($productId)
                ->filterByIsFreeText(true)
                ->findOneByFeatureId($featureId);
            if ($existing === null && $value === '') {
                continue;
            }

            $event = new FeatureProductUpdateEvent($productId, $featureId, $value, true);
            $event->setLocale($this->defaultLocale());
            $events->dispatch($event, TheliaEvents::PRODUCT_FEATURE_UPDATE_VALUE);
            $updated[] = $featureId;
        }

        foreach ($allFeatureIds as $featureId) {
            if (\in_array($featureId, $updated, true)) {
                continue;
            }
            $events->dispatch(new FeatureProductDeleteEvent($productId, $featureId), TheliaEvents::PRODUCT_FEATURE_DELETE_VALUE);
        }
    }
}
