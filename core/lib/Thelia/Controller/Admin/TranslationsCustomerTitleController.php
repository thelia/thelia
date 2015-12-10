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


use Thelia\Form\TranslationsCustomerTitleForm;
use Thelia\Model\CustomerTitleQuery;

class TranslationsCustomerTitleController extends BaseAdminController
{

    public function defaultAction()
    {
        return $this->render('translations-customer-title');
    }

    public function updateAction()
    {
        $request = $this->getRequest();

        $myTranslationForm = new TranslationsCustomerTitleForm($request);
        $error_msg = false;

        try {
            $myForm = $this->validateForm($myTranslationForm);

            $myData = $myForm->getData();

            $local = $myData['locale'];

            $myCustomersTitle = CustomerTitleQuery::create()->find();

            foreach($myCustomersTitle as $aCustomerTitle){
                $aCustomerTitle->setLocale($local)
                    ->setShort($myData['short_title_'.$aCustomerTitle->getId()])
                    ->setLong($myData['long_title_'.$aCustomerTitle->getId()])
                    ->save();
            }

        } catch (FormValidationException $ex) {
            // Form cannot be validated
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            // Any other error
            $error_msg = $ex->getMessage();
        }

        if(false !== $error_msg){

            $this->setupFormErrorContext(
                "customer title i18n",
                $error_msg,
                $myTranslationForm,
                $ex
            );
            return $this->generateRedirectFromRoute('admin.configuration.translations-customers-title');
        }

        $redirect = $request->get('save_mode');

        if($request->get('save_mode')==='close'){
            return $this->generateRedirectFromRoute('admin.configuration.index');
        }
        return $this->generateRedirectFromRoute('admin.configuration.translations-customers-title');
    }
}