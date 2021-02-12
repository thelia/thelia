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
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ProfileQuery;

/**
 * Class ProfileModificationForm.
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class ProfileModificationForm extends ProfileCreationForm
{
    protected function buildForm()
    {
        parent::buildForm();

        $this->formBuilder
            ->add('id', HiddenType::class, [
                'required' => true,
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Callback([$this, 'verifyProfileId']),
                ],
            ])
        ;

        $this->formBuilder->remove('code');
    }

    public static function getName()
    {
        return 'thelia_profile_modification';
    }

    public function verifyProfileId($value, ExecutionContextInterface $context)
    {
        $profile = ProfileQuery::create()
            ->findPk($value);

        if (null === $profile) {
            $context->addViolation(Translator::getInstance()->trans('Profile ID not found'));
        }
    }
}
