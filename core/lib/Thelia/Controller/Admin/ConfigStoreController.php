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
            'store_description'      => ConfigQuery::read("store_description"),
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

            if ($this->getRequest()->get('save_mode') == 'stay') {
                $this->redirectToRoute('admin.configuration.store.default');
            }

            // Redirect to the success URL
            $this->redirect($configStoreForm->getSuccessUrl());

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
