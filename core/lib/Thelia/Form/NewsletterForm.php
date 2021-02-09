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

use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\NewsletterQuery;

/**
 * Class NewsletterForm
 * @package Thelia\Form
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class NewsletterForm extends BaseForm
{
    /**
     *
     * in this function you add all the fields you need for your Form.
     * Form this you have to call add method on $this->formBuilder attribute :
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
        $this->formBuilder
            ->add('email', EmailType::class, [
                'constraints' => [
                    new NotBlank(),
                    new Email(),
                    new Callback(
                            [$this, "verifyExistingEmail"]
                        ),
                ],
                'label' => Translator::getInstance()->trans('Email address'),
                'label_attr' => [
                    'for' => 'email_newsletter',
                ],
            ])
            ->add('firstname', TextType::class, [
                'label' => Translator::getInstance()->trans('Firstname'),
                'label_attr' => [
                    'for' => 'firstname_newsletter',
                ],
            ])
            ->add('lastname', TextType::class, [
                'label' => Translator::getInstance()->trans('Lastname'),
                'label_attr' => [
                    'for' => 'lastname_newsletter',
                ],
            ]);
    }

    public function verifyExistingEmail($value, ExecutionContextInterface $context)
    {
        $customer = NewsletterQuery::create()->filterByUnsubscribed(false)->findOneByEmail($value);
        if ($customer) {
            $context->addViolation(Translator::getInstance()->trans("You are already registered!"));
        }
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public function getName()
    {
        return 'thelia_newsletter';
    }
}
