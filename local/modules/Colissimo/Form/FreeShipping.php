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

namespace Colissimo\Form;

use Colissimo\Colissimo;
use Colissimo\Model\Config\Base\ColissimoConfigValue;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

class FreeShipping extends BaseForm
{
    /**
     *
     * in this function you add all the fields you need for your Form.
     * Form this you have to call add method on $this->formBuilder attribute :
     *
     * $this->formBuilder->add("name", "text")
     *   ->add("email", "email", array(
     *           "attr" => array(
     *               "class" => "field"
     *           ),
     *           "label" => "email",
     *           "constraints" => array(
     *               new \Symfony\Component\Validator\Constraints\NotBlank()
     *           )
     *       )
     *   )
     *   ->add('age', 'integer');
     *
     * @return null
     */
    protected function buildForm()
    {
        $this->formBuilder
        ->add(
            "freeshipping",
            "checkbox",
            array(
                "label" => Translator::getInstance()->trans("Activate free shipping: ", [], Colissimo::DOMAIN_NAME),
                "value" => Colissimo::getConfigValue(ColissimoConfigValue::FREE_SHIPPING, false),
            )
        );
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public function getName()
    {
        return "colissimofreeshipping";
    }
}
