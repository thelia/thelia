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
    use Thelia\Core\HttpFoundation\Request;
    use Thelia\Core\Security\SecurityContext;
    use Thelia\Model\ProductPriceQuery;
    use Thelia\Model\ProductSaleElementsQuery;
    use Thelia\TaxEngine\TaxEngine;
    use Thelia\Tools\URL;
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

        public function __construct(
            RequestStack $requestStack,
            TaxEngine $taxEngine,
            SecurityContext $securityContext,
        ) {
            $this->request = $requestStack->getCurrentRequest();
            $this->taxEngine = $taxEngine;
            $this->securityContext = $securityContext;
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
            ];
        }

        public function domain()
        {
            return preg_replace('/\/index_*(\w+)*.php/', '', URL::getInstance()->absoluteUrl(URL::getInstance()->getBaseUrl()));
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
            $class = 'icon-'.$params['icon'];
            if ($params['class']) {
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

            if ($params['class']) {
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
    }
