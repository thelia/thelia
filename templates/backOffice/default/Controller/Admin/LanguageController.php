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

namespace Thelia\Controller\Admin;

use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;

/**
 * Class LanguageController.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class LanguageController extends BaseAdminController
{
    public function defaultAction()
    {
        if (($response = $this->checkAuth(AdminResources::LANGUAGE, [], AccessManager::VIEW)) instanceof Response) {
            return $response;
        }

        return $this->render('languages');
    }
}
