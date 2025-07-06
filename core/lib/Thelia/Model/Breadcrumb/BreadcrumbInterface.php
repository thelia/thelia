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

interface BreadcrumbInterface
{
    /**
     * Create a breadcrumb from the current object, that will be displayed to the file management UI.
     *
     * @param Router $router the router where to find routes
     * @param string $tab    the tab to return to (probably 'image' or 'document')
     * @param string $locale the current locale
     *
     * @return array an array of (label => URL)
     */
    public function getBreadcrumb(Router $router, $tab, $locale);
}
