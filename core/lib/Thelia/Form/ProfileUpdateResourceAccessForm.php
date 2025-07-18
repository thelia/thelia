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

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ProfileQuery;
use Thelia\Model\ResourceQuery;

/**
 * Class ProfileUpdateResourceAccessForm.
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class ProfileUpdateResourceAccessForm extends BaseForm
{
    public const RESOURCE_ACCESS_FIELD_PREFIX = 'resource';

    protected function buildForm(): void
    {
        $this->formBuilder
            ->add('id', HiddenType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new Callback(
                        $this->verifyProfileId(...),
                    ),
                ],
            ]);

        foreach (ResourceQuery::create()->find() as $resource) {
            $this->formBuilder->add(
                self::RESOURCE_ACCESS_FIELD_PREFIX.':'.str_replace('.', ':', $resource->getCode()),
                ChoiceType::class,
                [
                    'choices' => [
                        AccessManager::VIEW => AccessManager::VIEW,
                        AccessManager::CREATE => AccessManager::CREATE,
                        AccessManager::UPDATE => AccessManager::UPDATE,
                        AccessManager::DELETE => AccessManager::DELETE,
                    ],
                    'attr' => [
                        'tag' => 'resources',
                        'resource_code' => $resource->getCode(),
                    ],
                    'multiple' => true,
                    'constraints' => [
                    ],
                ],
            );
        }
    }

    public static function getName(): string
    {
        return 'thelia_profile_resource_access_modification';
    }

    public function verifyProfileId($value, ExecutionContextInterface $context): void
    {
        $profile = ProfileQuery::create()
            ->findPk($value);

        if (null === $profile) {
            $context->addViolation(Translator::getInstance()->trans('Profile ID not found'));
        }
    }
}
