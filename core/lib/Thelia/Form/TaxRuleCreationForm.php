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

use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Validator\Constraints\NotBlank;

class TaxRuleCreationForm extends BaseForm
{
    use StandardDescriptionFieldsTrait;

    protected function buildForm()
    {
        $this->formBuilder
            ->add('locale', HiddenType::class, [
                'constraints' => [new NotBlank()],
            ])
        ;

        $this->addStandardDescFields(['postscriptum', 'chapo', 'locale']);
    }

    public static function getName()
    {
        return 'thelia_tax_rule_creation';
    }
}
