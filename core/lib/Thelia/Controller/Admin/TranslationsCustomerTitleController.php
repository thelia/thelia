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
use Exception;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\CustomerTitleQuery;

class TranslationsCustomerTitleController extends BaseAdminController
{
    public function defaultAction(): Response
    {
        return $this->render('translations-customer-title');
    }

    public function updateAction(): RedirectResponse
    {
        $request = $this->getRequest();

        $translationForm = $this->createForm('thelia.admin.translations.customer_title');

        try {
            $form = $this->validateForm($translationForm);

            $data = $form->getData();

            $local = $data['locale'];

            $myCustomersTitle = CustomerTitleQuery::create()->find();

            foreach ($myCustomersTitle as $aCustomerTitle) {
                $aCustomerTitle->setLocale($local)
                    ->setShort($data['short_title_'.$aCustomerTitle->getId()])
                    ->setLong($data['long_title_'.$aCustomerTitle->getId()])
                    ->save();
            }

            if ($request->get('save_mode') === 'close') {
                return $this->generateRedirectFromRoute('admin.configuration.index');
            }

            return $this->generateRedirectFromRoute('admin.configuration.translations-customers-title');
        } catch (FormValidationException $ex) {
            // Form cannot be validated
            $errorMessage = $this->createStandardFormValidationErrorMessage($ex);
        } catch (Exception $ex) {
            // Any other error
            $errorMessage = $ex->getMessage();
        }

        $this->setupFormErrorContext(
            'customer title i18n',
            $errorMessage,
            $translationForm,
            $ex
        );

        return $this->generateRedirectFromRoute('admin.configuration.translations-customers-title');
    }
}
