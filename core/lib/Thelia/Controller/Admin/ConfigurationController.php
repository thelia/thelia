<?php

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

use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;

/**
 * Class ConfigurationController.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class ConfigurationController extends BaseAdminController
{
    public function indexAction()
    {
        if (null !== $response = $this->checkAuth([AdminResources::CONFIG], [], [AccessManager::VIEW])) {
            return $response;
        }

        return $this->render('configuration');
    }
}
