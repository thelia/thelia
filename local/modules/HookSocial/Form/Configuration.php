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

        $definitions = [
            [
                "id" => "twitter",
                "label" => Translator::getInstance()->trans("Twitter username", [], 'hooksocial')
            ],
            [
                "id" => "facebook",
                "label" => Translator::getInstance()->trans("Facebook username", [], 'hooksocial')
            ],
            [
                "id" => "google",
                "label" => Translator::getInstance()->trans("Google + username", [], 'hooksocial')
            ],
            [
                "id" => "instagram",
                "label" => Translator::getInstance()->trans("Instagram username", [], 'hooksocial')
            ],
            [
                "id" => "pinterest",
                "label" => Translator::getInstance()->trans("Pinterest username", [], 'hooksocial')
            ],
            [
                "id" => "youtube",
                "label" => Translator::getInstance()->trans("Youtube URL", [], 'hooksocial')
            ],
            [
                "id" => "rss",
                "label" => Translator::getInstance()->trans("RSS URL", [], 'hooksocial')
            ]
        ];

        foreach ($definitions as $field){
            $value = ConfigQuery::read("hooksocial_" . $field["id"], "");
            $form->add(
                $field["id"],
                "text",
                [
                    'data'  => $value,
                    'label' => $field["label"],
                    'label_attr' => [
                        'for' => $field["id"]
                    ],
                ]
            );
        }
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public static function getName()
    {
        return "hooksocial";
    }
}
