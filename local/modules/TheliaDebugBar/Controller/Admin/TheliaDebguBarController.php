<?php
namespace TheliaDebugBar\Controller\Admin;

use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;

class TheliaDebugBarController extends BaseAdminController
{
    const RESOURCE_CODE = 'module.TheliaDebugBar';

    public function indexAction()
    {
        if (null !== $response = $this->checkAuth(self::RESOURCE_CODE, array(), AccessManager::VIEW)) return $response;

        // Render the edition template.
        return $this->render('tdb-index');
    }
}