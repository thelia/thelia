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

interface BreadcrumbInterface {

    /**
     *
     * return the complete breadcrumb for a given resource.
     *
     * @return array
     */
    public function getBreadcrumb(Router $router, ContainerInterface $container, $tab);
}