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

class FolderModificationForm extends FolderCreationForm
{
    use StandardDescriptionFieldsTrait;

    protected function buildForm(): void
    {
        parent::buildForm();

        $this->formBuilder
            ->add('id', HiddenType::class, ['constraints' => [new GreaterThan(['value' => 0])]]);

        // Add standard description fields, excluding title and locale, which a re defined in parent class
        $this->addStandardDescFields(['title', 'locale']);
    }

    public static function getName(): string
    {
        return 'thelia_folder_modification';
    }
}
