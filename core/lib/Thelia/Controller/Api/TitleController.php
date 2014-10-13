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
use Symfony\Component\HttpKernel\Exception\HttpException;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\Loop\Title;

/**
 * Class TitleController
 * @package Thelia\Controller\Api
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class TitleController extends BaseApiController
{
    public function listAction()
    {
        $this->checkAuth(AdminResources::TITLE, [], AccessManager::VIEW);
        $request = $this->getRequest();

        $titleLoop = new Title($this->getContainer());
        $args = [];
        if ($request->query->has('lang')) {
            $args['lang'] = $request->query->get('lang');
        }
        $titleLoop->initializeArgs($args);

        $page = 0;

        return JsonResponse::create($titleLoop->exec($page));
    }

    public function getTitleAction($title_id)
    {
        $this->checkAuth(AdminResources::TITLE, [], AccessManager::VIEW);
        $request = $this->getRequest();

        $titleLoop = new Title($this->getContainer());
        $args = [];
        if ($request->query->has('lang')) {
            $args['lang'] = $request->query->get('lang');
        }
        $args['id'] = $title_id;
        $titleLoop->initializeArgs($args);

        $page = 0;

        $result = $titleLoop->exec($page);

        if ($result->isEmpty()) {
            throw new HttpException(404, sprintf('{"error": "title with id %d not found"}', $title_id));
        }

        return JsonResponse::create($result);
    }
}
