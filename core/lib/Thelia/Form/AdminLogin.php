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

class AdminLogin extends BruteforceForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add("username", "text", array(
                "constraints" => array(
                    new NotBlank(),
                    new Length(array("min" => 3)),
                ),
                "label" => Translator::getInstance()->trans("Username or e-mail address *"),
                "label_attr" => array(
                    "for" => "username",
                ),
            ))
            ->add("password", "password", array(
                "constraints" => array(
                    new NotBlank(),
                ),
                "label" => Translator::getInstance()->trans("Password *"),
                "label_attr" => array(
                    "for" => "password",
                ),
            ))
            ->add("remember_me", "checkbox", array(
                    'value' => 'yes',
                    "label" => Translator::getInstance()->trans("Remember me ?"),
                    "label_attr" => array(
                        "for" => "remember_me",
                    ),
            ))
            ;
    }

    public function getName()
    {
        return "thelia_admin_login";
    }
}
