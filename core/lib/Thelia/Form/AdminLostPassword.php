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

use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;

class AdminLostPassword extends BruteforceForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add("username_or_email", "text", array(
                "constraints" => array(
                    new NotBlank(),
                    new Length(array("min" => 3)),
                ),
                "label" => Translator::getInstance()->trans("Username or e-mail address *"),
                "label_attr" => array(
                    "for" => "username",
                ),
            ))
        ;
    }
}
