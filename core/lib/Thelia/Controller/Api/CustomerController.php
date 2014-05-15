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

use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;


/**
 * Class CustomerController
 * @package Thelia\Controller\Api
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class CustomerController extends BaseApiController
{

    public function listAction()
    {
        $this->checkAuth(AdminResources::CUSTOMER, [], AccessManager::VIEW);
    }
}