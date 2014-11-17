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

interface BreadcrumbInterface
{
    /**
     * Create a breadcrumb from the current object, that will be displayed to the file management UI
     *
     * @param Router             $router    the router where to find routes
     * @param ContainerInterface $container the container
     * @param string             $tab       the tab to return to (probably 'image' or 'document')
     * @param string             $locale    the current locale
     *
     * @return array an array of (label => URL)
     */
    public function getBreadcrumb(Router $router, ContainerInterface $container, $tab, $locale);
}
