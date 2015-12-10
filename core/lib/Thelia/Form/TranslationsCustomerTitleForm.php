<?php
/**
 * Created by PhpStorm.
 * User: EF-FRANCOIS
 * Date: 23/11/2015
 * Time: 13:49
 */

namespace Thelia\Form;

use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Thelia\Core\Translation\Translator;
use Thelia\Model\CustomerTitleQuery;


class TranslationsCustomerTitleForm extends BaseForm
{
    public function buildForm()
    {

        $this->formBuilder->add("locale", "text", array(
            "required" => true,
            "constraints" => array(
                new NotBlank(),
            ),
        ));

        $allTitle = CustomerTitleQuery::create()->find();

        foreach($allTitle as $aTitle)
        {
            $id = $aTitle->getId();
            $this->formBuilder
                ->add("title_id_".$id,"hidden",array(
                    "required" => true,
                    "constraints" => array(
                        new GreaterThan(array('value' => 0))
                    ),
                    "data" => $id
                ))
                ->add("short_title_".$id,"text",array(
                    "label" => Translator::getInstance()->trans("Change short title for"),
                    "required" => true,
                    "constraints" => array(
                        new NotBlank()
                    )
                ))
                ->add("long_title_".$id,"text",array(
                    "label" => Translator::getInstance()->trans("Change long title for"),
                    "required" => true,
                    "constraints" => array(
                        new NotBlank()
                    )
                ));
        }
    }
    public function getName()
    {
        return 'thelia_translation_customer_title';
    }
}