<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia                                                                       */
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
/*      along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Controller\Admin;

use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Security\AccessManager;
use Thelia\Form\ConfigStoreForm;
use Thelia\Model\ConfigQuery;
/**
 * Class ConfigStoreController
 * @package Thelia\Controller\Admin
 * @author Christophe Laffont <claffont@openstudio.fr>
 */
class ConfigStoreController extends BaseAdminController
{

    protected function renderTemplate()
    {
        return $this->render('config-store');
    }

    public function defaultAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::STORE, array(), AccessManager::VIEW)) return $response;

        // Hydrate the store configuration form
        $configStoreForm = new ConfigStoreForm($this->getRequest(), 'form', array(
            'store_name'             => ConfigQuery::read("store_name"),
            'store_email'            => ConfigQuery::read("store_email"),
            'store_business_id'      => ConfigQuery::read("store_business_id"),
            'store_phone'            => ConfigQuery::read("store_phone"),
            'store_fax'              => ConfigQuery::read("store_fax"),
            'store_address1'         => ConfigQuery::read("store_address1"),
            'store_address2'         => ConfigQuery::read("store_address2"),
            'store_address3'         => ConfigQuery::read("store_address3"),
            'store_zipcode'          => ConfigQuery::read("store_zipcode"),
            'store_city'             => ConfigQuery::read("store_city"),
            'store_country'          => ConfigQuery::read("store_country")
        ));

        $this->getParserContext()->addForm($configStoreForm);

        return $this->renderTemplate();
    }

    public function saveAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::STORE, array(), AccessManager::UPDATE)) return $response;

        $error_msg = false;

        $configStoreForm = new ConfigStoreForm($this->getRequest());

        try {
            $form = $this->validateForm($configStoreForm);

            $data = $form->getData();

            // Update store
            foreach ($data as $name => $value) {
                if(! in_array($name , array('success_url', 'error_message')))
                    ConfigQuery::write($name, $value, false);
            }

            $this->adminLogAppend(AdminResources::STORE, AccessManager::UPDATE, "Store configuration changed");

            $this->redirectToRoute('admin.configuration.store.default');

        } catch (\Exception $ex) {
            $error_msg = $ex->getMessage();
        }

        $this->setupFormErrorContext(
                $this->getTranslator()->trans("Store configuration failed."),
                $error_msg,
                $configStoreForm,
                $ex
        );

        return $this->renderTemplate();
    }
}
