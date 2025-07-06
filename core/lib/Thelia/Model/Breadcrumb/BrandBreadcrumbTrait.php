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

namespace Thelia\Model\Breadcrumb;

use Symfony\Component\Routing\Router;
use Thelia\Core\Translation\Translator;
use Thelia\Model\BrandQuery;
use Thelia\Tools\URL;

trait BrandBreadcrumbTrait
{
    /**
     * @return mixed[]
     */
    public function getBreadcrumb(Router $router, $tab, $locale): array
    {
        $breadcrumb = [
            Translator::getInstance()->trans('Home') => URL::getInstance()->absoluteUrl('/admin'),
            Translator::getInstance()->trans('Brand') => $router->generate('admin.brand.default', [], Router::ABSOLUTE_URL),
        ];

        if (null !== $brand = BrandQuery::create()->findPk($this->getBrandId())) {
            $breadcrumb[$brand->setLocale($locale)->getTitle()] = \sprintf(
                '%s?current_tab=%s',
                $router->generate('admin.brand.update', ['brand_id' => $brand->getId()], Router::ABSOLUTE_URL),
                $tab
            );
        }

        return $breadcrumb;
    }
}
