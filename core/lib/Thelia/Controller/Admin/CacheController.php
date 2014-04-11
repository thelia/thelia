<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
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
