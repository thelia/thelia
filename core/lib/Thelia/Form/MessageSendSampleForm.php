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

use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;

class MessageSendSampleForm extends BaseForm
{
    public function getName()
    {
        return "thelia_message_send_sample";
    }

    protected function buildForm()
    {
        $this->formBuilder
            ->add(
                "recipient_email",
                "email",
                [
                    "constraints" => array(new NotBlank()),
                    "label" => Translator::getInstance()->trans('Send test e-mail to:'),
                    "attr" => [
                        'placeholder' => Translator::getInstance()->trans('Recipient e-mail address')
                    ]
                ]
            );
        ;
    }
}
