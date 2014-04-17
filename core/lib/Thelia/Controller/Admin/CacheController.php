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

namespace Thelia\Controller\Admin;

use Thelia\Core\Event\Cache\CacheEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Cache\CacheFlushForm;
use Thelia\Form\Exception\FormValidationException;

/**
 * Class CacheController
 * @package Thelia\Controller\Admin
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class CacheController extends BaseAdminController
{

    public function defaultAction()
    {
        if (null !== $result = $this->checkAuth(AdminResources::CACHE, [], AccessManager::VIEW)) {
            return $result;
        }

        return $this->render('cache');
    }

    public function flushAction()
    {
        if (null !== $result = $this->checkAuth(AdminResources::CACHE, [], AccessManager::UPDATE)) {
            return $result;
        }

        $form = new CacheFlushForm($this->getRequest());
        try {
            $this->validateForm($form);

            $event = new CacheEvent($this->container->getParameter("kernel.cache_dir"));
            $this->dispatch(TheliaEvents::CACHE_CLEAR, $event);

            $event = new CacheEvent(THELIA_WEB_DIR . "assets");
            $this->dispatch(TheliaEvents::CACHE_CLEAR, $event);

            $this->redirectToRoute('admin.configuration.cache');
        } catch (FormValidationException $e) {

        }
    }

}
