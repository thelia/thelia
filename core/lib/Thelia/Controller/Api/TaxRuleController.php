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

use Symfony\Component\HttpFoundation\JsonResponse;
use Thelia\Controller\Admin\ApiController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\Loop\TaxRule;

/**
 * Class TaxRuleController
 * @package Thelia\Controller\Api
 * @author manuel raynaud <mraynaud@openstudio.fr>
 */
class TaxRuleController extends BaseApiController
{

    public function listAction()
    {
        $this->checkAuth(AdminResources::TAX, [], AccessManager::VIEW);

        $request = $this->getRequest();
        $taxRuleLoop = new TaxRule($this->getContainer());

        $args = [];
        if ($request->query->has('lang')) {
            $args['lang'] = $request->query->get('lang');
        }
        $taxRuleLoop->initializeArgs($args);

        $page = 0;

        return JsonResponse::create($taxRuleLoop->exec($page));
    }
}
