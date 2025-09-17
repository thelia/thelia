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
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ModuleQuery;

class ModuleModificationForm extends BaseForm
{
    use StandardDescriptionFieldsTrait;

    protected function buildForm(): void
    {
        $this->addStandardDescFields();

        $this->formBuilder
            ->add('id', HiddenType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new Callback(
                        $this->verifyModuleId(...),
                    ),
                ],
                'attr' => [
                    'id' => 'module_update_id',
                ],
            ]);
        $this->formBuilder->get('id')->addEventListener(
            FormEvents::SUBMIT,
            function (FormEvent $event): void {
                $value = $event->getData();
                $event->setData($value === null || $value === '' ? null : (int) $value);
            }
        );
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public static function getName(): string
    {
        return 'thelia_admin_module_modification';
    }

    public function verifyModuleId($value, ExecutionContextInterface $context): void
    {
        $module = ModuleQuery::create()
            ->findPk($value);

        if (null === $module) {
            $context->addViolation(Translator::getInstance()->trans('Module ID not found'));
        }
    }
}
