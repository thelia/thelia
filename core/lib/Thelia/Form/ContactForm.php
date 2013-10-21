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

use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;


/**
 * Class ContactForm
 * @package Thelia\Form
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class ContactForm extends BaseForm
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
            ->add('firstname', 'text', array(
                'constraints' => array(
                    new NotBlank()
                ),
                'label' => Translator::getInstance()->trans('firstname'),
                'label_attr' => array(
                    'for' => 'firstname_contact'
                )
            ))
            ->add('lastname', 'text', array(
                'constraints' => array(
                    new NotBlank()
                ),
                'label' => Translator::getInstance()->trans('lastname'),
                'label_attr' => array(
                    'for' => 'lastname_contact'
                )
            ))
            ->add('email', 'email', array(
                'constraints' => array(
                    new NotBlank(),
                    new Email()
                ),
                'label' => Translator::getInstance()->trans('email'),
                'label_attr' => array(
                    'for' => 'email_contact'
                )
            ))
            ->add('subject', 'text', array(
                'constraints' => array(
                    new NotBlank()
                ),
                'label' => Translator::getInstance()->trans('subject'),
                'label_attr' => array(
                    'for' => 'subject_contact'
                )
            ))
            ->add('message', 'text', array(
                'constraints' => array(
                    new NotBlank()
                ),
                'label' => Translator::getInstance()->trans('message'),
                'label_attr' => array(
                    'for' => 'message_contact'
                )

            ))
        ;
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public function getName()
    {
        return 'thelia_contact';
    }
}