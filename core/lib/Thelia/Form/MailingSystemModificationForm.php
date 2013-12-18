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

use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ProfileQuery;

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
                "label_attr" => array("for" => "encryption_field"),
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
                "label_attr" => array("for" => "authmode_field"),
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
