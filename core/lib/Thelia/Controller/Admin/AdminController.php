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
use Symfony\Component\HttpFoundation\RedirectResponse;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\SecurityContext;
use Thelia\Tools\URL;

class AdminController extends BaseAdminController
{
    public const RESOURCE_CODE = 'admin.home';

    public function indexAction(SecurityContext $securityContext): RedirectResponse|Response
    {
        if (!$securityContext->hasAdminUser()) {
            return new RedirectResponse(URL::getInstance()->absoluteUrl($this->getRoute('admin.login')));
        }

        if (($response = $this->checkAuth(self::RESOURCE_CODE, [], AccessManager::VIEW)) instanceof Response) {
            return $response;
        }

        return $this->render('home');
    }
}
