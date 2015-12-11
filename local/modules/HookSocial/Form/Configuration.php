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

namespace HookSocial\Form;

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

        $definitions = array(
            array(
                "id" => "twitter",
                "label" => Translator::getInstance()->trans("Twitter username", array(), 'hooksocial')
            ),
            array(
                "id" => "facebook",
                "label" => Translator::getInstance()->trans("Facebook username", array(), 'hooksocial')
            ),
            array(
                "id" => "google",
                "label" => Translator::getInstance()->trans("Google + username", array(), 'hooksocial')
            ),
            array(
                "id" => "instagram",
                "label" => Translator::getInstance()->trans("Instagram username", array(), 'hooksocial')
            ),
            array(
                "id" => "pinterest",
                "label" => Translator::getInstance()->trans("Pinterest username", array(), 'hooksocial')
            ),
            array(
                "id" => "youtube",
                "label" => Translator::getInstance()->trans("Youtube URL", array(), 'hooksocial')
            ),
            array(
                "id" => "rss",
                "label" => Translator::getInstance()->trans("RSS URL", array(), 'hooksocial')
            )
        );

        foreach ($definitions as $field){
            $value = ConfigQuery::read("hooksocial_" . $field["id"], "");
            $form->add(
                $field["id"],
                "text",
                array(
                    'data'  => $value,
                    'label' => $field["label"],
                    'label_attr' => array(
                        'for' => $field["id"]
                    ),
                )
            );
        }
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public function getName()
    {
        return "hooksocial";
    }


} 