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

namespace Thelia\Form;

use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;

/**
 * Class ContactForm
 * @package Thelia\Form
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class ContactForm extends FirewallForm
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
            ->add('name', 'text', array(
                'constraints' => array(
                    new NotBlank(),
                ),
                'label' => Translator::getInstance()->trans('Full Name'),
                'label_attr' => array(
                    'for' => 'name_contact',
                ),
            ))
            ->add('email', 'email', array(
                'constraints' => array(
                    new NotBlank(),
                    new Email(),
                ),
                'label' => Translator::getInstance()->trans('Your Email Address'),
                'label_attr' => array(
                    'for' => 'email_contact',
                ),
            ))
            ->add('subject', 'text', array(
                'constraints' => array(
                    new NotBlank(),
                ),
                'label' => Translator::getInstance()->trans('Subject'),
                'label_attr' => array(
                    'for' => 'subject_contact',
                ),
            ))
            ->add('message', 'text', array(
                'constraints' => array(
                    new NotBlank(),
                ),
                'label' => Translator::getInstance()->trans('Your Message'),
                'label_attr' => array(
                    'for' => 'message_contact',
                ),

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
