<?php

namespace HookAdminHome\Form;

use HookAdminHome\HookAdminHome;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

class Configuration extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder->add(
            "enabled-news",
            "checkbox",
            [
                "label" => "Enabled News",
                "label_attr" => [
                    "for" => "enabled-news",
                    "help" => Translator::getInstance()->trans(
                        'Check if you want show news',
                        [],
                        HookAdminHome::DOMAIN_NAME
                    )
                ],
                "required" => false,
                "value" => HookAdminHome::getConfigValue(HookAdminHome::ACTIVATE_NEWS, 0),
            ]
        );

        $this->formBuilder->add(
            "enabled-info",
            "checkbox",
            [
                "label" => "Enabled Info",
                "label_attr" => [
                    "for" => "enabled-info",
                    "help" => Translator::getInstance()->trans(
                        'Check if you want show info',
                        [],
                        HookAdminHome::DOMAIN_NAME
                    )
                ],
                "required" => false,
                "value" => HookAdminHome::getConfigValue(HookAdminHome::ACTIVATE_INFO, 0),
            ]
        );

        $this->formBuilder->add(
            "enabled-stats",
            "checkbox",
            [
                "label" => "Enabled default Home Stats",
                "label_attr" => [
                    "for" => "enabled-stats",
                    "help" => Translator::getInstance()->trans(
                        'Check if you want show default Home Stats',
                        [],
                        HookAdminHome::DOMAIN_NAME
                    )
                ],
                "required" => false,
                "value" => HookAdminHome::getConfigValue(HookAdminHome::ACTIVATE_STATS, 0),
            ]
        );

        $this->formBuilder->add(
            "enabled-sales",
            "checkbox",
            [
                "label" => "Enabled Sales Statistics",
                "label_attr" => [
                    "for" => "enabled-sales",
                    "help" => Translator::getInstance()->trans(
                        'Check if you want show sales stats',
                        [],
                        HookAdminHome::DOMAIN_NAME
                    )
                ],
                "required" => false,
                "value" => HookAdminHome::getConfigValue(HookAdminHome::ACTIVATE_SALES, 0),
            ]
        );
    }

    public function getName()
    {
        return "hookadminhomeconfigform";
    }
}