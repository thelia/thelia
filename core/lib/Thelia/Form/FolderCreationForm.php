<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Form;

use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;

class FolderCreationForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add("title", TextType::class, [
                "constraints" => [
                    new NotBlank(),
                ],
                "label" => Translator::getInstance()->trans("Folder title *"),
                "label_attr" => [
                    "for" => "title",
                ],
            ])
            ->add("parent", TextType::class, [
                "label" => Translator::getInstance()->trans("Parent folder *"),
                "constraints" => [
                    new NotBlank(),
                ],
                "label_attr" => ["for" => "parent_create"],
            ])
            ->add("locale", TextType::class, [
                "constraints" => [
                    new NotBlank(),
                ],
                "label_attr" => ["for" => "locale_create"],
            ])
            ->add("visible", IntegerType::class, [
                "label" => Translator::getInstance()->trans("This folder is online."),
                "label_attr" => ["for" => "visible_create"],
            ])
        ;
    }

    public static function getName()
    {
        return "thelia_folder_creation";
    }
}
