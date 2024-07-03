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

use Symfony\Component\HttpFoundation\RedirectResponse;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\SecurityContext;
use Thelia\Tools\URL;

class AdminController extends BaseAdminController
{
    public const RESOURCE_CODE = 'admin.home';

    public function indexAction(SecurityContext $securityContext)
    {
        if (!$securityContext->hasAdminUser()) {
            return new RedirectResponse(URL::getInstance()->absoluteUrl($this->getRoute('admin.login')));
        }

        if (null !== $response = $this->checkAuth(self::RESOURCE_CODE, [], AccessManager::VIEW)) {
            return $response;
        }

        return $this->render('home');
    }
}
