<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Controller\Api;

use Propel\Runtime\Propel;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementCreateEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementDeleteEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\JsonResponse;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Definition\AdminForm;
use Thelia\Model\Country;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Product;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Core\Template\Loop\ProductSaleElements as ProductSaleElementsLoop;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\TaxRuleQuery;
use Thelia\TaxEngine\Calculator;
use Thelia\Form\Definition\ApiForm;

/**
 * Class ProductSaleElementsController
 * @package Thelia\Controller\Api
 * @author Benjamin Perche <bperche@openstudio.fr>
 *
 * API Controller for Product sale elements management
 */
class ProductSaleElementsController extends BaseApiController
{
    protected $product;

    /**
     * Read actions
     */

    /**
     * @param $productId
     * @return JsonResponse
     *
     * List a product pses
     */
    public function listAction($productId)
    {
        $this->checkAuth(AdminResources::PRODUCT, [], AccessManager::VIEW);

        if (null !== $response = $this->checkProduct($productId)) {
            return $response;
        }

        $request = $this->getRequest();

        if ($request->query->has('id')) {
            $request->query->remove('id');
        }

        $params = array_merge(
            array(
                "limit" => 10,
                "offset" => 0,
            ),
            $request->query->all(),
            array(
                "product" => $productId,
            )
        );

        return new JsonResponse($this->getProductSaleElements($params));
    }

    /**
     * @param $pseId
     * @return JsonResponse
     *
     * Get a pse details
     */
    public function getPseAction($pseId)
    {
        $this->checkAuth(AdminResources::PRODUCT, [], AccessManager::VIEW);

        $request = $this->getRequest();

        $params = array_merge(
            $request->query->all(),
            [
                'id' => $pseId,
            ]
        );

        $results = $this->getProductSaleElements($params);

        if ($results->getCount() == 0) {
            return new JsonResponse(
                sprintf(
                    "The product sale elements id '%d' doesn't exist",
                    $pseId
                ),
                404
            );
        }

        return new JsonResponse($results);
    }

    /**
     * Create action
     */

    /**
     * @return JsonResponse
     *
     * Create product sale elements
     */
    public function createAction()
    {
        $this->checkAuth(AdminResources::PRODUCT, [], AccessManager::CREATE);

        $baseForm = $this->createForm(ApiForm::PRODUCT_SALE_ELEMENTS, "form", [], [
            "validation_groups" => ["create", "Default"],
            'csrf_protection' => false,
            "cascade_validation" => true,
        ]);

        $con = Propel::getConnection(ProductSaleElementsTableMap::DATABASE_NAME);
        $con->beginTransaction();

        $createdIds = array();

        try {
            $form = $this->validateForm($baseForm);

            $entries = $form->getData();

            foreach ($entries["pse"] as $entry) {
                $createEvent = new ProductSaleElementCreateEvent(
                    ProductQuery::create()->findPk($entry["product_id"]),
                    $entry["attribute_av"],
                    $entry["currency_id"]
                );

                $createEvent->bindForm($form);

                $this->dispatch(TheliaEvents::PRODUCT_ADD_PRODUCT_SALE_ELEMENT, $createEvent);

                $this->processUpdateAction(
                    $entry,
                    $pse = $createEvent->getProductSaleElement(),
                    $createEvent->getProduct()
                );

                $createdIds[] = $pse->getId();
            }

            $con->commit();
        } catch (\Exception $e) {
            $con->rollBack();

            return new JsonResponse(["error" => $e->getMessage()], 500);
        }

        return new JsonResponse(
            $this->getProductSaleElements(
                array_merge(
                    $this->getRequest()->query->all(),
                    ["id" => implode(",", $createdIds)]
                )
            ),
            201
        );
    }

    /**
     * @return JsonResponse
     *
     * Create product sale elements
     */
    public function updateAction()
    {
        $this->checkAuth(AdminResources::PRODUCT, [], AccessManager::UPDATE);

        $baseForm = $this->createForm(ApiForm::PRODUCT_SALE_ELEMENTS, "form", [], [
            "validation_groups" => ["update", "Default"],
            'csrf_protection' => false,
            "cascade_validation" => true,
            "method" => "PUT",
        ]);

        $baseForm->getFormBuilder()
            ->addEventListener(
                FormEvents::PRE_SUBMIT,
                [$this, "loadProductSaleElements"],
                192
            );

        $updatedId = array();

        $con = Propel::getConnection(ProductSaleElementsTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            $form = $this->validateForm($baseForm);

            $entries = $form->getData();

            foreach ($entries["pse"] as $entry) {
                $this->processUpdateAction(
                    $entry,
                    $pse = ProductSaleElementsQuery::create()->findPk($entry["id"]),
                    $pse->getProduct()
                );

                $updatedId[] = $pse->getId();
            }

            $con->commit();
        } catch (\Exception $e) {
            $con->rollBack();

            return new JsonResponse(["error" => $e->getMessage()], 500);
        }

        return new JsonResponse(
            $this->getProductSaleElements(
                array_merge(
                    $this->getRequest()->query->all(),
                    [
                        "id" => implode(",", $updatedId),
                        "limit" => count($updatedId),
                    ]
                )
            ),
            201
        );
    }

    /**
     * Delete Action
     */

    /**
     * @param $pseId
     * @return JsonResponse|\Thelia\Core\HttpFoundation\Response
     *
     * Delete a pse
     */
    public function deleteAction($pseId)
    {
        $this->checkAuth(AdminResources::PRODUCT, [], AccessManager::VIEW);

        $results = $this->getProductSaleElements([
            'id' => $pseId
        ]);

        if ($results->getCount() == 0) {
            return new JsonResponse(
                sprintf(
                    "The product sale elements id '%d' doesn't exist",
                    $pseId
                ),
                404
            );
        }

        $event = new ProductSaleElementDeleteEvent($pseId, null);

        try {
            $this->dispatch(TheliaEvents::PRODUCT_DELETE_PRODUCT_SALE_ELEMENT, $event);
        } catch (\Exception $e) {
            return new JsonResponse(array("error" => $e->getMessage()), 500);
        }

        return $this->nullResponse(204);
    }

    /**
     * @param array $data
     * @param ProductSaleElements $pse
     * @param Product $product
     *
     * Process update on product sale elements values
     */
    protected function processUpdateAction(
        array $data,
        ProductSaleElements $pse,
        Product $product
    ) {
        list($price, $salePrice) = $this->extractPrices($data);

        $event = new ProductSaleElementUpdateEvent($product, $pse->getId());

        $event
            ->setWeight($data["weight"])
            ->setTaxRuleId($data["tax_rule_id"])
            ->setEanCode($data["ean_code"])
            ->setOnsale($data["onsale"])
            ->setReference($data["reference"])
            ->setIsdefault($data["isdefault"])
            ->setIsnew($data["isnew"])
            ->setCurrencyId($data["currency_id"])
            ->setPrice($price)
            ->setSalePrice($salePrice)
            ->setQuantity($data["quantity"])
            ->setFromDefaultCurrency($data["use_exchange_rate"])
        ;

        $this->dispatch(TheliaEvents::PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT, $event);
    }

    /**
     * @param array $data
     * @return array
     *
     * Return the untaxed prices to store
     */
    protected function extractPrices(array $data)
    {
        $calculator = new Calculator();

        $calculator->loadTaxRuleWithoutProduct(
            TaxRuleQuery::create()->findPk($data["tax_rule_id"]),
            Country::getShopLocation()
        );

        $price = null === $data["price_with_tax"] ?
            $data["price"] :
            $calculator->getUntaxedPrice($data["price_with_tax"])
        ;

        $salePrice = null === $data["sale_price_with_tax"] ?
            $data["sale_price"] :
            $calculator->getUntaxedPrice($data["sale_price_with_tax"])
        ;

        return [$price, $salePrice];
    }

    protected function retrievePrices(ProductSaleElements $pse)
    {
        $query = ProductPriceQuery::create()
            ->useCurrencyQuery()
                ->orderByByDefault()
            ->endUse()
        ;

        $prices = $pse->getProductPrices($query);

        if ($prices->count() === 0) {
            return array(null, null, null, null);
        }

        /** @var \Thelia\Model\ProductPrice $currentPrices */
        $currentPrices = $prices->get(0);

        return [
            $currentPrices->getPrice(),
            $currentPrices->getPromoPrice(),
            $currentPrices->getCurrencyId(),
            $currentPrices->getFromDefaultCurrency()
        ];
    }

    /**
     * @param FormEvent $event
     *
     * Loads initial pse data into a form.
     * It is used in for a form event on pse update
     */
    public function loadProductSaleElements(FormEvent $event)
    {
        $productSaleElementIds = array();
        $data = array();

        foreach ($event->getData()["pse"] as $entry) {
            $productSaleElementIds[$entry["id"]] = $entry;
        }

        $productSaleElements = ProductSaleElementsQuery::create()
            ->findPks(array_keys($productSaleElementIds))
        ;

        /** @var ProductSaleElements $productSaleElement */
        foreach ($productSaleElements as $productSaleElement) {
            $product = $productSaleElement->getProduct();

            list($price, $salePrice, $currencyId, $fromDefaultCurrency) = $this->retrievePrices($productSaleElement);

            $data["pse"][$productSaleElement->getId()] = array_merge(
                [
                    "id" => $productSaleElement->getId(),
                    "reference" => $productSaleElement->getRef(),
                    "tax_rule_id" => $product->getTaxRuleId(),
                    "ean_code" => $productSaleElement->getEanCode(),
                    "onsale" => $productSaleElement->getPromo(),
                    "isdefault" => $productSaleElement->getIsDefault(),
                    "isnew" => $productSaleElement->getNewness(),
                    "quantity" => $productSaleElement->getQuantity(),
                    "weight" => $productSaleElement->getWeight(),
                    "price" => $price,
                    "sale_price" => $salePrice,
                    "currency_id" => $currencyId,
                    "use_exchange_rate" => $fromDefaultCurrency
                ],
                $productSaleElementIds[$productSaleElement->getId()]
            );
        }

        $event->setData($data);
    }

    /**
     * @param $productId
     * @return null|JsonResponse
     *
     * Checks if a productId exists
     */
    protected function checkProduct($productId)
    {
        $this->product = ProductQuery::create()
            ->findPk($productId)
        ;

        if (null === $this->product) {
            return new JsonResponse(
                [
                    "error" => sprintf(
                        "The product id '%d' doesn't exist",
                        $productId
                    )
                ],
                404
            );
        }

        return null;
    }

    /**
     * @param $params
     * @return \Thelia\Core\Template\Element\LoopResult
     *
     * Return loop results for a product sale element
     */
    protected function getProductSaleElements($params)
    {
        $loop = new ProductSaleElementsLoop($this->container);
        $loop->initializeArgs($params);

        return $loop->exec($pagination);
    }
}
