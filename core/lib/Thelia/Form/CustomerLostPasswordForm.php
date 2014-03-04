<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Form;

use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\CustomerQuery;

/**
 * Class CustomerLostPasswordForm
 * @package Thelia\Form
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class CustomerLostPasswordForm extends BaseForm
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
            ->add("email", "email", array(
                "constraints" => array(
                    new NotBlank(),
                    new Email(),
                    new Callback(array(
                        "methods" => array(
                            array($this,
                                "verifyExistingEmail")
                        )
                    ))
                ),
                "label" => Translator::getInstance()->trans("Please enter your email address"),
                "label_attr" => array(
                    "for" => "forgot-email"
                )
            ));
    }

    public function verifyExistingEmail($value, ExecutionContextInterface $context)
    {
        $customer = CustomerQuery::create()->findOneByEmail($value);
        if (null === $customer) {
            $context->addViolation(Translator::getInstance()->trans("This email does not exists"));
        }
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public function getName()
    {
        return "thelia_customer_creation";
    }
}
