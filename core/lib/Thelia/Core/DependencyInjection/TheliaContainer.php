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
    /**
     * @inheritdoc
     */
    public function set($id, $service, $scope = self::SCOPE_CONTAINER)
    {
        if ($id === 'request'
            && php_sapi_name() === "cli"
        ) {
            if (!isset($this->services['request_stack'])) {
                $this->services['request_stack'] = new RequestStack();
            }

            /** @var RequestStack $requestStack */
            $requestStack = $this->services['request_stack'];

            if ($requestStack->getCurrentRequest() === null) {
                @trigger_error('Request is deprecated as a service since Thelia 2.3. Please inject your Request in the RequestStack service.', E_USER_DEPRECATED);
                /** @var Request $service */
                $requestStack->push($service);
            }
        }

        parent::set($id, $service, $scope);
    }
}
