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

namespace Thelia\Core\DependencyInjection;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Request;

/**
 * To override the methods of the symfony container
 *
 * Class TheliaContainer
 * @package Thelia\Core\DependencyInjection
 * @author Gilles Bourgeat <manu@raynaud.io>
 * @since 2.3
 */
class TheliaContainer extends Container
{
    public function set(string $id, ?object $service)
    {
        if ($id === 'request'
            && php_sapi_name() === "cli"
        ) {
            if (!isset($this->services['request_stack'])) {
                $this->services['request_stack'] = new RequestStack();
            }
        }

        parent::set($id, $service);
    }
}
