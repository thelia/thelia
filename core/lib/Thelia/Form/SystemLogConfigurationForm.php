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

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints;
use Thelia\Log\Tlog;
use Thelia\Core\Translation\Translator;

class SystemLogConfigurationForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add("level", ChoiceType::class, array(
                'choices' => array(
                    Tlog::MUET      => Translator::getInstance()->trans("Disabled"),
                    Tlog::DEBUG     => Translator::getInstance()->trans("Debug"),
                    Tlog::INFO      => Translator::getInstance()->trans("Information"),
                    Tlog::NOTICE    => Translator::getInstance()->trans("Notices"),
                    Tlog::WARNING   => Translator::getInstance()->trans("Warnings"),
                    Tlog::ERROR     => Translator::getInstance()->trans("Errors"),
                    Tlog::CRITICAL  => Translator::getInstance()->trans("Critical"),
                    Tlog::ALERT     => Translator::getInstance()->trans("Alerts"),
                    Tlog::EMERGENCY => Translator::getInstance()->trans("Emergency"),
                ),

                "label" => Translator::getInstance()->trans('Log level *'),
                "label_attr" => array(
                    "for" => "level_field",
                ),
            ))
            ->add("format", TextType::class, array(
                "label" => Translator::getInstance()->trans('Log format *'),
                "label_attr" => array(
                    "for" => "format_field",
                ),
            ))
            ->add("show_redirections", IntegerType::class, array(
                    "constraints" => array(new Constraints\NotBlank()),
                    "label" => Translator::getInstance()->trans('Show redirections *'),
                    "label_attr" => array(
                            "for" => "show_redirections_field",
                    ),
            ))
            ->add("files", TextType::class, array(
                    "label" => Translator::getInstance()->trans('Activate logs only for these files'),
                    "label_attr" => array(
                            "for" => "files_field",
                    ),
            ))
            ->add("ip_addresses", TextType::class, array(
                    "label" => Translator::getInstance()->trans('Activate logs only for these IP Addresses'),
                    "label_attr" => array(
                            "for" => "files_field",
                    ),
            ))
            ;
    }

    public function getName()
    {
        return "thelia_system_log_configuration";
    }
}
