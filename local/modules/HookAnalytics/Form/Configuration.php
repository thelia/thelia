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

namespace HookAnalytics\Form;

use HookAnalytics\HookAnalytics;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

/**
 * Class Configuration
 * @package HookSocial\Form
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class Configuration extends BaseForm {

    protected function buildForm()
    {
        $form = $this->formBuilder;

        $lang = $this->getRequest()->getSession()->get("thelia.admin.edition.lang");

        $value = HookAnalytics::getConfigValue("hookanalytics_trackingcode", "", $lang->getLocale());
        $form->add(
            "trackingcode",
            "text",
            array(
                'data'  => $value,
                'label' => Translator::getInstance()->trans("Tracking Code"),
                'label_attr' => array(
                    'for' => "trackingcode"
                ),
            )
        );

    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public function getName()
    {
        return "hookanalytics";
    }


} 