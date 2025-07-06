<?php

declare(strict_types=1);

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

use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;
use Thelia\Model\CustomerTitleQuery;

class TranslationsCustomerTitleForm extends BaseForm
{
    protected function buildForm(): void
    {
        $this->formBuilder->add('locale', TextType::class, [
            'required' => true,
            'constraints' => [
                new NotBlank(),
            ],
        ]);

        $allTitle = CustomerTitleQuery::create()->find();

        foreach ($allTitle as $aTitle) {
            $id = $aTitle->getId();
            $this->formBuilder
                ->add('title_id_'.$id, HiddenType::class, [
                    'required' => true,
                    'constraints' => [
                        new GreaterThan(['value' => 0]),
                    ],
                    'data' => $id,
                ])
                ->add('short_title_'.$id, TextType::class, [
                    'label' => Translator::getInstance()->trans('Change short title for'),
                    'required' => true,
                    'constraints' => [
                        new NotBlank(),
                    ],
                ])
                ->add('long_title_'.$id, TextType::class, [
                    'label' => Translator::getInstance()->trans('Change long title for'),
                    'required' => true,
                    'constraints' => [
                        new NotBlank(),
                    ],
                ]);
        }
    }

    public static function getName(): string
    {
        return 'thelia_translation_customer_title';
    }
}
