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
use Thelia\Form\Definition\AdminForm;
use Thelia\Model\ConfigQuery;

/**
 * Class ConfigStoreController.
 *
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
        if (null !== $response = $this->checkAuth(AdminResources::STORE, [], AccessManager::VIEW)) {
            return $response;
        }

        // The form is self-hydrated
        $configStoreForm = $this->createForm(AdminForm::CONFIG_STORE);

        $this->getParserContext()->addForm($configStoreForm);

        return $this->renderTemplate();
    }

    protected function getAndWriteStoreMediaFileInConfig($form, $inputName, $configKey, $storeMediaUploadDir): void
    {
        $file = $form->get($inputName)->getData();

        $locale = '';

        if (null !== $lang = $this->getCurrentEditionLang()) {
            $locale = '_'.$lang->getLocale();
        }

        if ($file != null) {
            // Delete the old file
            $fs = new \Symfony\Component\Filesystem\Filesystem();
            $oldFileName = ConfigQuery::read($configKey.$locale);

            if ($oldFileName === null) {
                $oldFileName = ConfigQuery::read($configKey);
            }

            if ($oldFileName !== null) {
                $oldFilePath = $storeMediaUploadDir.DS.$oldFileName;
                if ($fs->exists($oldFilePath)) {
                    $fs->remove($oldFilePath);
                }
            }

            // Write the new file
            $newFileName = uniqid().'-'.$file->getClientOriginalName();
            $file->move($storeMediaUploadDir, $newFileName);
            ConfigQuery::write($configKey.$locale, $newFileName, false);
        }
    }

    public function saveAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::STORE, [], AccessManager::UPDATE)) {
            return $response;
        }

        $error_msg = $ex = false;
        $response = null;
        $configStoreForm = $this->createForm(AdminForm::CONFIG_STORE);

        try {
            $form = $this->validateForm($configStoreForm);

            $storeMediaUploadDir = ConfigQuery::read('images_library_path');

            if ($storeMediaUploadDir === null) {
                $storeMediaUploadDir = THELIA_LOCAL_DIR.'media'.DS.'images';
            } else {
                $storeMediaUploadDir = THELIA_ROOT.$storeMediaUploadDir;
            }

            $storeMediaUploadDir .= DS.'store';

            // List of medias that can be uploaded through this form.
            //  [Name of the form input] => [Key in the config table]
            $storeMediaList = [
                'favicon_file' => 'favicon_file',
                'logo_file' => 'logo_file',
                'banner_file' => 'banner_file',
            ];

            foreach ($storeMediaList as $input_name => $config_key) {
                $this->getAndWriteStoreMediaFileInConfig($form, $input_name, $config_key, $storeMediaUploadDir);
            }

            $data = $form->getData();

            $locale = $this->getCurrentEditionLocale();

            // Update store
            foreach ($data as $name => $value) {
                if (!\array_key_exists($name, $storeMediaList) && !$configStoreForm->isTemplateDefinedHiddenFieldName($name)) {
                    ConfigQuery::write($name.'_'.$locale, $value, false);
                }
            }

            $this->adminLogAppend(AdminResources::STORE, AccessManager::UPDATE, 'Store configuration changed');

            if ($this->getRequest()->get('save_mode') == 'stay') {
                $response = $this->generateRedirectFromRoute(
                    'admin.configuration.store.default',
                    ['edit_language_id' => $this->getCurrentEditionLang()->getId()]
                );
            } else {
                $response = $this->generateSuccessRedirect($configStoreForm);
            }
        } catch (\Exception $ex) {
            $error_msg = $ex->getMessage();
        }

        if (false !== $error_msg) {
            $this->setupFormErrorContext(
                $this->getTranslator()->trans('Store configuration failed.'),
                $error_msg,
                $configStoreForm,
                $ex
            );

            $response = $this->renderTemplate();
        }

        return $response;
    }
}
