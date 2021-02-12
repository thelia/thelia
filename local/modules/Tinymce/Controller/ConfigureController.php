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

namespace Tinymce\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Exception\TheliaProcessException;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Tools\URL;
use Tinymce\Form\ConfigurationForm;
use Tinymce\Tinymce;

/**
 * Class SetTransferConfig.
 *
 * @author Thelia <info@thelia.net>
 */
class ConfigureController extends BaseAdminController
{
    public function configure()
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'Tinymce', AccessManager::UPDATE)) {
            return $response;
        }

        // Initialize the potential exception
        $ex = null;

        // Create the Form from the request
        $configurationForm = $this->createForm(ConfigurationForm::class);

        try {
            // Check the form against constraints violations
            $form = $this->validateForm($configurationForm, 'POST');

            // Get the form field values
            $data = $form->getData();

            Tinymce::setConfigValue('product_summary', $data['product_summary']);
            Tinymce::setConfigValue('product_conclusion', $data['product_conclusion']);

            Tinymce::setConfigValue('content_summary', $data['content_summary']);
            Tinymce::setConfigValue('content_conclusion', $data['content_conclusion']);

            Tinymce::setConfigValue('category_summary', $data['category_summary']);
            Tinymce::setConfigValue('category_conclusion', $data['category_conclusion']);

            Tinymce::setConfigValue('folder_summary', $data['folder_summary']);
            Tinymce::setConfigValue('folder_conclusion', $data['folder_conclusion']);

            Tinymce::setConfigValue('brand_summary', $data['brand_summary']);
            Tinymce::setConfigValue('brand_conclusion', $data['brand_conclusion']);

            Tinymce::setConfigValue('show_menu_bar', $data['show_menu_bar']);
            Tinymce::setConfigValue('force_pasting_as_text', $data['force_pasting_as_text']);
            Tinymce::setConfigValue('editor_height', $data['editor_height']);
            Tinymce::setConfigValue('custom_css', $data['custom_css']);

            // Save Custom CSS in default assets
            $customCss = __DIR__.DS.'..'.DS.'templates'.DS.'backOffice'.DS.'default'.DS.'assets'.DS.'css'.DS.'custom-css.less';

            if (false === file_put_contents($customCss, $data['custom_css'])) {
                throw new TheliaProcessException(
                    $this->getTranslator()->trans(
                        'Failed to update custom CSS file "%file". Please check this file or parent folder write permissions.',
                        ['%file' => $customCss]
                    )
                );
            }

            // Log configuration modification
            $this->adminLogAppend(
                'tinymce.configuration.message',
                AccessManager::UPDATE,
                sprintf('Tinymce configuration updated')
            );

            // Everything is OK.
            return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/Tinymce'));
        } catch (FormValidationException $ex) {
            // Form cannot be validated. Create the error message using
            // the BaseAdminController helper method.
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            // Any other error
            $error_msg = $ex->getMessage();
        }

        // At this point, the form has errors, and should be redisplayed. We don not redirect,
        // just redisplay the same template.
        // Setup the Form error context, to make error information available in the template.
        $this->setupFormErrorContext(
            $this->getTranslator()->trans('Tinymce configuration', [], Tinymce::MODULE_DOMAIN),
            $error_msg,
            $configurationForm,
            $ex
        );

        // Do not redirect at this point, or the error context will be lost.
        // Just redisplay the current template.
        return $this->render('module-configure', ['module_code' => 'Tinymce']);
    }
}
