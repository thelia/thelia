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

namespace Thelia\Controller\Api;

use Symfony\Component\HttpFoundation\Response;

/**
 *
 * This controller allow to test if api server is up or down
 *
 * Class IndexController
 * @package Thelia\Controller\Api
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class IndexController extends BaseApiController
{
    public function indexAction()
    {
        return Response::create("OK");
    }
}
