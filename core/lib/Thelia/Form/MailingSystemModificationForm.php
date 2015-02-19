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

use Thelia\Core\Translation\Translator;

/**
 * Class MailingSystemModificationForm
 * @package Thelia\Form
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class MailingSystemModificationForm extends BaseForm
{
    protected function buildForm($change_mode = false)
    {
        $this->formBuilder
            ->add("enabled", "choice", array(
                "choices" => array(1 => "Yes", 0 => "No"),
                "label" => Translator::getInstance()->trans("Enable remote SMTP use"),
                "label_attr" => array("for" => "enabled_field"),
            ))
            ->add("host", "text", array(
                "label" => Translator::getInstance()->trans("Host"),
                "label_attr" => array("for" => "host_field"),
            ))
            ->add("port", "text", array(
                "label" => Translator::getInstance()->trans("Port"),
                "label_attr" => array("for" => "port_field"),
            ))
            ->add("encryption", "text", array(
                "label" => Translator::getInstance()->trans("Encryption"),
                "label_attr" => array(
                    "for" => "encryption_field",
                    "help" => Translator::getInstance()->trans("ssl, tls or empty"),
                ),
            ))
            ->add("username", "text", array(
                "label" => Translator::getInstance()->trans("Username"),
                "label_attr" => array("for" => "username_field"),
            ))
            ->add("password", "text", array(
                "label" => Translator::getInstance()->trans("Password"),
                "label_attr" => array("for" => "password_field"),
            ))
            ->add("authmode", "text", array(
                "label" => Translator::getInstance()->trans("Auth mode"),
                "label_attr" => array(
                    "for" => "authmode_field",
                    "help" => Translator::getInstance()->trans("plain, login, cram-md5 or empty"),
                ),
            ))
            ->add("timeout", "text", array(
                "label" => Translator::getInstance()->trans("Timeout"),
                "label_attr" => array("for" => "timeout_field"),
            ))
            ->add("sourceip", "text", array(
                "label" => Translator::getInstance()->trans("Source IP"),
                "label_attr" => array("for" => "sourceip_field"),
            ))
        ;
    }

    public function getName()
    {
        return "thelia_mailing_system_modification";
    }

    /*public function verifyCode($value, ExecutionContextInterface $context)
    {
        $profile = ProfileQuery::create()
            ->findOneByCode($value);

        if (null !== $profile) {
            $context->addViolation("Profile `code` already exists");
        }
    }*/
}
