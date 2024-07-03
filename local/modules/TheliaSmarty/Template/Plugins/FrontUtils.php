<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TheliaSmarty\Template\Plugins;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\SecurityContext;
use Thelia\Model\CategoryQuery;
use Thelia\Model\FolderQuery;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\TaxEngine\TaxEngine;
use Thelia\Tools\URL;
use TheliaSmarty\Events\PseByProductEvent;
use TheliaSmarty\Template\AbstractSmartyPlugin;
use TheliaSmarty\Template\SmartyPluginDescriptor;

class FrontUtils extends AbstractSmartyPlugin
{
    /** @var Request */
    protected $request;

    /** @var TaxEngine */
    protected $taxEngine;

    /** @var SecurityContext */
    protected $securityContext;

    protected $manifest;

    protected $entrypoints;

    /** @var string */
    protected $assetsPublicPath;

    /** @var EventDispatcherInterface */
    protected $eventDispatcher;

    public function __construct(
        RequestStack $requestStack,
        TaxEngine $taxEngine,
        SecurityContext $securityContext,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->request = $requestStack->getCurrentRequest();
        $this->taxEngine = $taxEngine;
        $this->securityContext = $securityContext;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function getPluginDescriptors()
    {
        return [
            new SmartyPluginDescriptor('function', 'domain', $this, 'domain'),
            new SmartyPluginDescriptor('function', 'currentView', $this, 'currentView'),
            new SmartyPluginDescriptor('function', 'renderIconSvg', $this, 'renderIconSvg'),
            new SmartyPluginDescriptor('function', 'renderSvg', $this, 'renderSvg'),
            new SmartyPluginDescriptor('function', 'psesByProduct', $this, 'psesByProduct'),
            new SmartyPluginDescriptor('function', 'extractOptions', $this, 'extractOptions'),
            new SmartyPluginDescriptor('function', 'isInCategory', $this, 'isInCategory'),
            new SmartyPluginDescriptor('function', 'isInFolder', $this, 'isInFolder'),
        ];
    }

    public function domain()
    {
        return preg_replace('/\/index_*(\w+)*.php/', '', URL::getInstance()->absoluteUrl(URL::getInstance()->getBaseUrl()));
    }

    public function currentView()
    {
        $view = htmlentities(strtolower($this->request->get('_view')));

        if ($view != null) {
            return $view;
        }

        return str_replace('/', '', $this->request->getPathInfo());
    }

    public function renderIconSvg($params)
    {
        $class = 'icon-'.$params['icon'];
        if (isset($params['class'])) {
            $class = $params['class'];
        }

        return '<svg class="'.$class.'"><use xlink:href="#svg-icons-'.$params['icon'].'"></use></svg>';
    }

    public function renderSvg($params)
    {
        $path = $this->manifest[$params['file']];

        if (!$path) {
            return false;
        }

        $svg = file_get_contents(substr($path, 1));

        $matches = [];
        preg_match('/^<svg.*?>/', $svg, $matches);

        if (isset($params['class'])) {
            $svgTag = $matches[0];
            $existingClass = [];
            $hasClass = preg_match('/class="(.*?)"/', $svgTag, $existingClass);

            if ($hasClass) {
                $newSvgTag = preg_replace('/class="(.*?)"/', 'class="$1 '.$params['class'].'"', $svgTag);
            } else {
                $newSvgTag = preg_replace('/(>)$/', ' class="'.$params['class'].'">', $svgTag);
            }

            $svg = preg_replace('/^<svg.*?>/', $newSvgTag, $svg);
        }

        return $svg;
    }

    public function psesByProduct($params)
    {
        $productId = $params['product_id'];
        $result = [];

        if (!$productId) {
            return [];
        }

        $discount = 0;
        $taxCountry = $this->taxEngine->getDeliveryCountry();

        if ($this->securityContext->hasCustomerUser()) {
            $discount = $this->securityContext->getCustomerUser()->getDiscount();
        }

        foreach (ProductSaleElementsQuery::create()->findByProductId($productId) as $pse) {
            $attributes = [];
            $price = ProductPriceQuery::create()->filterByProductSaleElements($pse)->findOne();

            $basePrice = $price->getPrice() * (1 - ($discount / 100));
            $promoPrice = $price->getPromoPrice() * (1 - ($discount / 100));
            $pse->setVirtualColumn('price_PRICE', (float) $basePrice);
            $pse->setVirtualColumn('price_PROMO_PRICE', (float) $promoPrice);

            foreach ($pse->getAttributeCombinations() as $attribute) {
                $attributes[$attribute->getAttributeId()] = $attribute->getAttributeAvId();
            }

            $this->eventDispatcher->dispatch(new PseByProductEvent($pse));

            $result[] = [
                'id' => $pse->getId(),
                'isDefault' => $pse->isDefault(),
                'isPromo' => $pse->getPromo() ? true : false,
                'isNew' => $pse->getNewness() ? true : false,
                'ref' => $pse->getRef(),
                'ean' => $pse->getEanCode(),
                'quantity' => $pse->getQuantity(),
                'weight' => $pse->getWeight(),
                'price' => $pse->getTaxedPrice($taxCountry),
                'untaxedPrice' => $pse->getPrice(),
                'promoPrice' => $pse->getTaxedPromoPrice($taxCountry),
                'promoUntaxedPrice' => $pse->getPromoPrice(),
                'combination' => $attributes,
            ];
        }

        return json_encode($result);
    }

    public function extractOptions($params, $smarty): void
    {
        $categoriesList = [];
        $categories = $params['categories'];

        $featuresList = [];
        $features = $params['features'];

        $brandsList = [];
        $brands = $params['brands'];

        foreach ($categories as $id) {
            if (isset($id) && $id !== '') {
                $categoriesList[] = $id;
            }
        }

        foreach ($features as $id => $list) {
            if (!isset($list)) {
                continue;
            }
            $featuresAvs = $this->check($list, '|');
            if (!$featuresAvs) {
                continue;
            }
            $featuresList[] = $id.':('.$featuresAvs.')';
        }

        foreach ($brands as $id) {
            if (isset($id) && $id !== '') {
                $brandsList[] = $id;
            }
        }

        $features = $this->implodeFilter($featuresList, ',');

        $smarty->assign('categories', $this->implodeFilter($categoriesList, ','));
        $smarty->assign('features', $features ?: null);
        $smarty->assign('brands', $this->implodeFilter($brandsList, ','));
    }

    protected function check($param, $sep)
    {
        if (!isset($param)) {
            return null;
        }

        if (\is_array($param)) {
            return implode($sep, $param);
        }

        return $param;
    }

    protected function implodeFilter($param, $sep)
    {
        if (\is_array($param)) {
            return implode($sep, $param);
        }

        return $param;
    }

    /**
     * @param array $params
     */
    public function isInFolder($params)
    {
        if (!isset($params['folder']) || !isset($params['current_folder'])) {
            return '';
        }

        $res = $this->iterateFolders($params['current_folder']);

        return \in_array($params['folder'], array_reverse($res));
    }

    private function iterateFolders($folderId, $list = [])
    {
        if ($folderId === 0) {
            return [...$list, 0];
        }

        if (null === $folderId) {
            return $list;
        }

        $folder = FolderQuery::create()->findOneById($folderId);

        if ($folder === null) {
            return $this->iterateCategories(null, $list);
        }

        $list[] = $folder->getId();

        return $this->iterateFolders($folder->getParent(), $list);
    }

    /**
     * @param array $params
     */
    public function isInCategory($params)
    {
        if (!isset($params['category']) || !isset($params['current_category'])) {
            return '';
        }

        $res = $this->iterateCategories($params['current_category']);

        return \in_array($params['category'], array_reverse($res));
    }

    private function iterateCategories($categoryId, $list = [])
    {
        if ($categoryId === 0) {
            return [...$list, 0];
        }

        if (null === $categoryId) {
            return $list;
        }

        $category = CategoryQuery::create()->findOneById($categoryId);

        if ($category === null) {
            return $this->iterateCategories(null, $list);
        }

        $list[] = $category->getId();

        return $this->iterateCategories($category->getParent(), $list);
    }
}
