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

use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;
use Thelia\Model\ConfigQuery;

/**
 * Class Configuration
 * @package HookSocial\Form
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class Configuration extends BaseForm {

    protected function buildForm()
    {
        $form = $this->formBuilder;

        $value = ConfigQuery::read("hookanalytics_trackingcode", "");
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