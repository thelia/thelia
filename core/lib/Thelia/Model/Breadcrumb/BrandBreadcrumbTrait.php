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

namespace Thelia\Model\Breadcrumb;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Router;
use Thelia\Core\Translation\Translator;
use Thelia\Model\BrandQuery;

trait BrandBreadcrumbTrait
{
    /**
     * @inheritdoc
     */
    public function getBreadcrumb(Router $router, /** @noinspection PhpUnusedParameterInspection */ ContainerInterface $container, $tab, $locale)
    {
        $breadcrumb = [
            Translator::getInstance()->trans('Home') => $router->generate('admin.home.view', [], Router::ABSOLUTE_URL),
            Translator::getInstance()->trans('Brand') => $router->generate('admin.brand.default', [], Router::ABSOLUTE_URL)
        ];

        if (null !== $brand = BrandQuery::create()->findPk($this->getBrandId())) {
            $breadcrumb[$brand->setLocale($locale)->getTitle()] = sprintf(
                "%s?current_tab=%s",
                $router->generate('admin.brand.update', ['brand_id' => $brand->getId()], Router::ABSOLUTE_URL),
                $tab
            );
        }

        return $breadcrumb;
    }
}
