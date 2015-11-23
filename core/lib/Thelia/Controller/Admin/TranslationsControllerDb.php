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

use Symfony\Component\HttpFoundation\Request;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Model\CustomerTitleQuery;
use Thelia\Model\CustomerTitleI18nQuery;
use Thelia\Form\TranslationsDbForm;

class TranslationsControllerDb extends BaseAdminController
{
    public function __contruct()
    {

    }
    public function defaultAction()
    {
        return $this->render('translations-db');
    }

    public function updateAction()
    {
        $request = $this->getRequest();

        $myTranslationForm = new TranslationsDbForm($request);
        $error_msg = false;

        try {
            $myForm = $this->validateForm($myTranslationForm);

            $myData = $myForm->getData();

            $local = $myData['locale'];

            $CustomerTitle = CustomerTitleQuery::create()->findPk($myData['title_id_1']);
            $CustomerTitle->setLocale($local)
                ->setShort($myData['short_title_1'])
                ->setLong($myData['long_title_1'])
                ->save();

            $CustomerTitle = CustomerTitleQuery::create()->findPk($myData['title_id_2']);
            $CustomerTitle->setLocale($local)
                ->setShort($myData['short_title_2'])
                ->setLong($myData['long_title_2'])
                ->save();

            $CustomerTitle = CustomerTitleQuery::create()->findPk($myData['title_id_3']);
            $CustomerTitle->setLocale($local)
                ->setShort($myData['short_title_3'])
                ->setLong($myData['long_title_3'])
                ->save();


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
            //return $this->generateRedirectFromRoute('admin.configuration.translations-db',array('error_msg'=>$error_msg));
            return $this->generateRedirectFromRoute('admin.configuration.translations-db');
        }

        $redirect = $request->get('save_mode');

        if($request->get('save_mode')==='close'){
            return $this->generateRedirectFromRoute('admin.configuration.index');
        }
        return $this->generateRedirectFromRoute('admin.configuration.translations-db');
    }
}