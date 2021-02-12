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

namespace Thelia\Form;

/**
 * Class EmptyForm.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class EmptyForm extends BaseForm
{
    /**
     * in this function you add all the fields you need for your Form.
     * Form this you have to call add method on $this->formBuilder attribute :.
     *
     * $this->formBuilder->add("name", TextType::class)
     *   ->add("email", EmailType::class, array(
     *           "attr" => array(
     *               "class" => "field"
     *           ),
     *           "label" => "email",
     *           "constraints" => array(
     *               new \Symfony\Component\Validator\Constraints\NotBlank()
     *           )
     *       )
     *   )
     *   ->add('age', IntegerType::class);
     *
     * @return null
     */
    protected function buildForm()
    {
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public static function getName()
    {
        return 'empty';
    }
}
