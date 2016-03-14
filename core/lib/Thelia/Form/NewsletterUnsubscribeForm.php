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
class NewsletterUnsubscribeForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add('email', 'email', array(
                'constraints' => array(
                    new NotBlank(),
                    new Email(),
                    new Callback(array(
                        "methods" => array(
                            array($this,
                                "verifyExistingEmail", ),
                        ),
                    )),
                ),
                'label' => Translator::getInstance()->trans('Email address'),
                'label_attr' => array(
                    'for' => 'email_newsletter',
                ),
            ))
        ;
    }

    public function verifyExistingEmail($value, ExecutionContextInterface $context)
    {
        if (null === NewsletterQuery::create()->filterByUnsubscribed(false)->findOneByEmail($value)) {
            $context->addViolation(
                Translator::getInstance()->trans(
                    "The email address \"%mail\" was not found.",
                    [ '%mail' => $value ]
                )
            );
        }
    }
}
