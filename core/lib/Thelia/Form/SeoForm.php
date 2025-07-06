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
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Class SeoForm.
 *
 * @author Christophe Laffont <claffont@openstudio.fr>
 */
class SeoForm extends BaseForm
{
    use SeoFieldsTrait;

    protected function buildForm(): void
    {
        $this->formBuilder
            ->add('id', HiddenType::class, [
                'required' => true,
                'constraints' => [
                    new GreaterThan(['value' => 0]),
                ],
            ])
            ->add('locale', HiddenType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                ],
            ])
        ;

        // Add SEO Fields
        $this->addSeoFields();
    }

    public static function getName(): string
    {
        return 'thelia_seo';
    }
}
