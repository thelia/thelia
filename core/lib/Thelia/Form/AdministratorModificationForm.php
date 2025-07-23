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
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\AdminQuery;

class AdministratorModificationForm extends AdministratorCreationForm
{
    protected function buildForm(): void
    {
        parent::buildForm();

        $this->formBuilder
            ->add('id', HiddenType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new Callback(
                        $this->verifyAdministratorId(...),
                    ),
                ],
                'attr' => [
                    'id' => 'administrator_update_id',
                ],
            ]);

        $this->formBuilder->get('password')->setRequired(false);
        $this->formBuilder->get('password_confirm')->setRequired(false);
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public static function getName(): string
    {
        return 'thelia_admin_administrator_modification';
    }

    public function verifyAdministratorId($value, ExecutionContextInterface $context): void
    {
        $administrator = AdminQuery::create()
            ->findPk($value);

        if (null === $administrator) {
            $context->addViolation(Translator::getInstance()->trans('Administrator ID not found'));
        }
    }

    public function verifyExistingLogin($value, ExecutionContextInterface $context): void
    {
        $data = $context->getRoot()->getData();

        $administrator = AdminQuery::create()->findOneByLogin($value);

        if (null !== $administrator && $administrator->getId() !== $data['id']) {
            $context->addViolation($this->translator->trans('This administrator login already exists'));
        }
    }

    public function verifyExistingEmail($value, ExecutionContextInterface $context): void
    {
        $data = $context->getRoot()->getData();

        $administrator = AdminQuery::create()->findOneByEmail($value);

        if (null !== $administrator && $administrator->getId() !== $data['id']) {
            $context->addViolation($this->translator->trans('An administrator with this email address already exists'));
        }
    }

    public function verifyPasswordField($value, ExecutionContextInterface $context): void
    {
        $data = $context->getRoot()->getData();

        if (!empty($data['password'])) {
            if ($data['password'] !== $data['password_confirm']) {
                $context->addViolation(Translator::getInstance()->trans('password confirmation is not the same as password field'));
            }

            if ('' !== $data['password'] && \strlen((string) $data['password']) < 4) {
                $context->addViolation(Translator::getInstance()->trans('password must be composed of at least 4 characters'));
            }
        }
    }
}
