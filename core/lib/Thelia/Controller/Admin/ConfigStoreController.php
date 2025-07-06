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

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
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
    protected function renderTemplate(): Response
    {
        return $this->render('config-store');
    }

    public function defaultAction()
    {
        if (($response = $this->checkAuth(AdminResources::STORE, [], AccessManager::VIEW)) instanceof Response) {
            return $response;
        }

        // The form is self-hydrated
        $configStoreForm = $this->createForm(AdminForm::CONFIG_STORE);

        $this->getParserContext()->addForm($configStoreForm);

        return $this->renderTemplate();
    }

    protected function getAndWriteStoreMediaFileInConfig($form, $inputName, $configKey, string $storeMediaUploadDir): void
    {
        $file = $form->get($inputName)->getData();

        if ($file != null) {
            // Delete the old file
            $fs = new Filesystem();
            $oldFileName = ConfigQuery::read($configKey);

            if ($oldFileName !== null) {
                $oldFilePath = $storeMediaUploadDir.DS.$oldFileName;
                if ($fs->exists($oldFilePath)) {
                    $fs->remove($oldFilePath);
                }
            }

            // Write the new file
            $newFileName = uniqid().'-'.$file->getClientOriginalName();
            $file->move($storeMediaUploadDir, $newFileName);
            ConfigQuery::write($configKey, $newFileName, false);
        }
    }

    public function saveAction(): Response|RedirectResponse|null
    {
        if (($response = $this->checkAuth(AdminResources::STORE, [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        $error_msg = false;
        $response = null;
        $configStoreForm = $this->createForm(AdminForm::CONFIG_STORE);

        $exception = null;
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

            // Update store
            foreach ($data as $name => $value) {
                if (!\array_key_exists($name, $storeMediaList) && !$configStoreForm->isTemplateDefinedHiddenFieldName($name)) {
                    ConfigQuery::write($name, $value, false);
                }
            }

            $this->adminLogAppend(AdminResources::STORE, AccessManager::UPDATE, 'Store configuration changed');

            if ($this->getRequest()->get('save_mode') == 'stay') {
                $response = $this->generateRedirectFromRoute('admin.configuration.store.default');
            } else {
                $response = $this->generateSuccessRedirect($configStoreForm);
            }
        } catch (\Exception $exception) {
            $error_msg = $exception->getMessage();
        }

        if (false !== $error_msg) {
            $this->setupFormErrorContext(
                $this->getTranslator()->trans('Store configuration failed.'),
                $error_msg,
                $configStoreForm,
                $exception
            );

            $response = $this->renderTemplate();
        }

        return $response;
    }
}
