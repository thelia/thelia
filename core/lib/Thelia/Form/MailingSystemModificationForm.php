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

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Thelia\Core\Translation\Translator;

/**
 * Class MailingSystemModificationForm
 * @package Thelia\Form
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class MailingSystemModificationForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add("enabled", CheckboxType::class, array(
                "required" => false,
                "label" => Translator::getInstance()->trans("Enable remote SMTP use"),
                "label_attr" => array("for" => "enabled_field"),
            ))
            ->add("host", TextType::class, array(
                "required" => false,
                "label" => Translator::getInstance()->trans("Host"),
                "label_attr" => array("for" => "host_field"),
            ))
            ->add("port", TextType::class, array(
                "required" => false,
                "label" => Translator::getInstance()->trans("Port"),
                "label_attr" => array("for" => "port_field"),
            ))
            ->add("encryption", TextType::class, array(
                "required" => false,
                "label" => Translator::getInstance()->trans("Encryption"),
                "label_attr" => array(
                    "for" => "encryption_field",
                    "help" => Translator::getInstance()->trans("ssl, tls or empty"),
                ),
            ))
            ->add("username", TextType::class, array(
                "required" => false,
                "label" => Translator::getInstance()->trans("Username"),
                "label_attr" => array("for" => "username_field"),
            ))
            ->add("password", TextType::class, array(
                "required" => false,
                "label" => Translator::getInstance()->trans("Password"),
                "label_attr" => array("for" => "password_field"),
            ))
            ->add("authmode", TextType::class, array(
                "required" => false,
                "label" => Translator::getInstance()->trans("Auth mode"),
                "label_attr" => array(
                    "for" => "authmode_field",
                    "help" => Translator::getInstance()->trans("plain, login, cram-md5 or empty"),
                ),
            ))
            ->add("timeout", TextType::class, array(
                "required" => false,
                "label" => Translator::getInstance()->trans("Timeout"),
                "label_attr" => array("for" => "timeout_field"),
            ))
            ->add("sourceip", TextType::class, array(
                "required" => false,
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
