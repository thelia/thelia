<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia                                                                       */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*      along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace TheliaSmarty\Template\Plugins;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\SecurityContext;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\TaxEngine\TaxEngine;
use Thelia\Tools\AssetsManager;
use TheliaSmarty\Template\AbstractSmartyPlugin;
use TheliaSmarty\Template\SmartyPluginDescriptor;
use Thelia\Tools\URL;

class FrontAssets extends AbstractSmartyPlugin
{
    /** @var Request */
    protected $request;

    /** @var TaxEngine  */
    protected $taxEngine;

    /** @var SecurityContext  */
    protected $securityContext;

    protected $manifest;

    protected $entrypoints;

    public function __construct(
        RequestStack $requestStack,
        TaxEngine $taxEngine,
        SecurityContext  $securityContext
    ) {
        $this->request = $requestStack->getCurrentRequest();
        $this->taxEngine = $taxEngine;
        $this->securityContext = $securityContext;

        $assetsPublicPath = THELIA_FRONT_ASSETS_PUBLIC_DIR;

        if (!is_dir($assetsPublicPath)) {
            $fileSystem = new Filesystem();
            $origin = THELIA_TEMPLATE_DIR . 'frontOffice' . DS . ConfigQuery::read('active-front-template') . DS . THELIA_FRONT_ASSETS_BUILD_DIR_NAME;
            $fileSystem->symlink($origin, $assetsPublicPath);
        }

        if (file_exists(THELIA_FRONT_ASSETS_MANIFEST_PATH)) {
            $this->manifest = json_decode(file_get_contents(THELIA_FRONT_ASSETS_MANIFEST_PATH), true);
        }
        if (file_exists(THELIA_FRONT_ASSETS_ENTRYPOINTS_PATH)) {
            $json = json_decode(file_get_contents(THELIA_FRONT_ASSETS_ENTRYPOINTS_PATH), true);
            $this->entrypoints = $json['entrypoints'];
        }
    }

    public function getPluginDescriptors()
    {
        return array(
            new SmartyPluginDescriptor("function", "domain", $this, "domain"),
            new SmartyPluginDescriptor("function", "currentView", $this, "currentView"),
            new SmartyPluginDescriptor('function', 'renderIconSvg', $this, 'renderIconSvg'),
            new SmartyPluginDescriptor('function', 'renderSvg', $this, 'renderSvg'),
            new SmartyPluginDescriptor('function', 'getFileFromManifest', $this, 'getFileFromManifest'),
            new SmartyPluginDescriptor('function', 'getAssetsFromEntrypoints', $this, 'getAssetsFromEntrypoints'),
            new SmartyPluginDescriptor("function", "psesByProduct", $this, "psesByProduct"),
            new SmartyPluginDescriptor("function", "extractOptions", $this, "extractOptions")
        );
    }

    public function domain()
    {
        return  preg_replace('/\/index_*(\w+)*.php/', '', URL::getInstance()->absoluteUrl(URL::getInstance()->getBaseUrl()));
    }

    public function currentView()
    {
        $view = strtolower($this->request->get('_view'));

        if ($view != null) {
            return $view;
        }

        return str_replace('/', '', $this->request->getPathInfo());
    }

    public function renderIconSvg($params)
    {
        $class = "icon-" . $params['icon'];
        if ($params['class']) {
            $class = $params['class'];
        }

        return '<svg class="' . $class . '"><use xlink:href="#svg-icons-' . $params['icon'] . '"></use></svg>';
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


        if ($params['class']) {
            $svgTag = $matches[0];
            $existingClass = [];
            $hasClass = preg_match('/class="(.*?)"/', $svgTag, $existingClass);


            if ($hasClass) {
                $newSvgTag = preg_replace('/class="(.*?)"/', 'class="$1 ' . $params["class"]  . '"', $svgTag);
            } else {
                $newSvgTag = preg_replace('/(>)$/', ' class="' . $params["class"]  . '">', $svgTag);
            }

            $svg = preg_replace('/^<svg.*?>/', $newSvgTag, $svg);
        }

        return $svg;
    }

    public function getFileFromManifest($arg)
    {
        if (isset($arg['file']) && $this->manifest != null) {
            return $this->manifest[$arg['file']];
        }

        return '';
    }


    public function getAssetsFromEntrypoints($arg)
    {
        $result = "";

        if (!isset($arg['entry']) || !isset($arg['type'])) {
            return $result;
        }

        foreach (AssetsManager::getInstance()->getAssets($arg['entry'], $arg['type']) as $asset) {
            if ($arg['type'] === "js") {
                $result .= "<script src='$asset' defer></script>";
            }
            if ($arg['type'] === "css") {
                $result .= "<link rel='stylesheet' type='text/css' href='$asset'/>";
            }
        }

        return $result;
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
            $pse->setVirtualColumn('price_PRICE', (float)$basePrice);
            $pse->setVirtualColumn('price_PROMO_PRICE', (float)$promoPrice);

            foreach ($pse->getAttributeCombinations() as $attribute) {
                $attributes[$attribute->getAttributeId()] = $attribute->getAttributeAvId();
            }

            $result[] = [
                'id' => $pse->getId(),
                "isDefault" => $pse->isDefault(),
                "isPromo" => $pse->getPromo() ? true : false,
                "isNew" => $pse->getNewness() ? true : false,
                "ref" => $pse->getRef(),
                "ean" => $pse->getEanCode(),
                "quantity" => $pse->getQuantity(),
                "price" => $pse->getTaxedPrice($taxCountry),
                "promoPrice" => $pse->getTaxedPromoPrice($taxCountry),
                "combination" => $attributes
            ];
        }

        return json_encode($result);
    }

    public function extractOptions($params, $smarty)
    {
        $categoriesList = [];
        $categories = $params['categories'];

        $featuresList = [];
        $features = $params['features'];

        $brandsList = [];
        $brands = $params['brands'];

        foreach ($categories as $id) {
            if (isset($id) && $id !== "") {
                $categoriesList[] = $id;
            }
        }

        foreach ($features as $id => $list) {
            if (isset($list) && count($list) > 0 && $list !== "") {
                $featuresList[] = $id . ':(' . $this->check($list, '|') . ')' ;
            }
        }

        foreach ($brands as $id) {
            if (isset($id) && $id !== "") {
                $brandsList[] = $id;
            }
        }

        $features = $this->implodeFilter($featuresList, ',');

        $smarty->assign('categories', $this->implodeFilter($categoriesList, ','));
        $smarty->assign('features', $features ? $features : null);
        $smarty->assign('brands', $this->implodeFilter($brandsList, ','));
    }

    protected function check($param, $sep)
    {
        if (!isset($param)) {
            return null;
        }

        if (is_array($param)) {
            return implode($sep, $param);
        }

        return $param;
    }

    protected function implodeFilter($param, $sep)
    {
        if (is_array($param)) {
            return implode($sep, $param);
        }

        return $param;
    }
}
