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

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Base\ModuleHookQuery;
use Thelia\Model\Hook;
use Thelia\Model\HookQuery;
use Thelia\Model\IgnoredModuleHookQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;

/**
 * Class HookCreationForm.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ModuleHookCreationForm extends BaseForm
{
    /** @var Translator */
    protected $translator;

    protected function buildForm(): void
    {
        $this->formBuilder
            ->add(
                'module_id',
                ChoiceType::class,
                [
                    'choices' => $this->getModuleChoices(),
                    'constraints' => [
                        new NotBlank(),
                    ],
                    'label' => $this->trans('Module'),
                    'label_attr' => [
                        'for' => 'module_id',
                        'help' => $this->trans(
                            'Only hookable modules are displayed in this menu.'
                        ),
                    ],
                ]
            )
            ->add(
                'hook_id',
                ChoiceType::class,
                [
                    'choices' => $this->getHookChoices(),
                    'constraints' => [
                        new NotBlank(),
                    ],
                    'label' => $this->trans('Hook'),
                    'label_attr' => ['for' => 'hook_id'],
                ]
            )
            ->add(
                'classname',
                TextType::class,
                [
                    'constraints' => [
                        new NotBlank(),
                    ],
                    'label' => $this->trans('Service ID'),
                    'label_attr' => [
                        'for' => 'classname',
                        'help' => $this->trans(
                            'The service id that will handle the hook (defined in the config.xml file of the module).'
                        ),
                    ],
                ]
            )
            ->add(
                'method',
                TextType::class,
                [
                    'label' => $this->trans('Method Name'),
                    'constraints' => [
                        new NotBlank(),
                    ],
                    'label_attr' => [
                        'for' => 'method',
                        'help' => $this->trans(
                            'The method name that will handle the hook event.'
                        ),
                    ],
                ]
            )
            ->add(
                'templates',
                TextType::class,
                [
                    'label' => $this->trans('Automatic rendered templates'),
                    'constraints' => [
                        new Callback(
                            [$this, 'verifyTemplates']
                        ),
                    ],
                    'label_attr' => [
                        'for' => 'templates',
                        'help' => $this->trans(
                            'When using the %method% method you can automatically render or dump templates or add CSS and JS files (e.g.: render:mytemplate.html;js:assets/js/myjs.js)',
                            ['%method%' => BaseHook::INJECT_TEMPLATE_METHOD_NAME]
                        ),
                    ],
                    'required' => false,
                ]
            )
        ;
    }

    protected function trans($id, $parameters = [])
    {
        if (null === $this->translator) {
            $this->translator = Translator::getInstance();
        }

        return $this->translator->trans($id, $parameters);
    }

    protected function getModuleChoices()
    {
        $choices = [];
        $modules = ModuleQuery::getActivated();
        $locale = $this->getRequest()->getSession()->getLang()->getLocale();

        /** @var Module $module */
        foreach ($modules as $module) {
            // Check if module defines a hook ID
            if (ModuleHookQuery::create()->filterByModuleId($module->getId())->count() > 0
                || IgnoredModuleHookQuery::create()->filterByModuleId($module->getId())->count() > 0
            ) {
                $title = $module->setLocale($locale)->getTitle();

                if (empty($title)) {
                    $title = $module->setLocale('en_US')->getTitle();
                }

                $title = $module->getCode().' - '.$title;

                $choices[$title] = $module->getId();
            }
        }

        ksort($choices);

        return $choices;
    }

    protected function getHookChoices()
    {
        $choices = [];
        $hooks = HookQuery::create()
            ->filterByActivate(true, Criteria::EQUAL)
            ->joinWithI18n($this->translator->getLocale())
            ->orderBy('HookI18n.title', Criteria::ASC)
            ->find();

        /** @var Hook $hook */
        foreach ($hooks as $hook) {
            $choices[$hook->getTitle().' (code '.$hook->getCode().')'] = $hook->getId();
        }

        return $choices;
    }

    /**
     * Check if method is the right one if we want to use automatic inserted templates .
     *
     * @return bool
     */
    public function verifyTemplates($value, ExecutionContextInterface $context): void
    {
        $data = $context->getRoot()->getData();

        if (!empty($data['templates']) && $data['method'] !== BaseHook::INJECT_TEMPLATE_METHOD_NAME) {
            $context->addViolation(
                $this->trans(
                    'If you use automatic insert templates, you should use the method %method%',
                    [
                        '%method%' => BaseHook::INJECT_TEMPLATE_METHOD_NAME,
                    ]
                )
            );
        }
    }

    public static function getName()
    {
        return 'thelia_module_hook_creation';
    }
}
