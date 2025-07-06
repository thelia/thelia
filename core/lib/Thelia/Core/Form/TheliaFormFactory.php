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

namespace Thelia\Core\Form;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface;
use Symfony\Component\Validator\ValidatorBuilder;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Form\BaseForm;

/**
 * Class TheliaFormFactory.
 *
 * @author Benjamin Perche <benjamin@thelia.net>
 */
class TheliaFormFactory
{
    public function __construct(
        protected ContainerInterface $container,
        protected RequestStack $requestStack,
        protected EventDispatcherInterface $eventDispatcher,
        protected TranslatorInterface $translator,
        protected FormFactoryBuilderInterface $formFactoryBuilder,
        protected ValidatorBuilder $validatorBuilder,
        protected TokenStorageInterface $tokenStorage,
        #[Autowire(param: 'Thelia.parser.forms')]
        protected array $formDefinition,
    ) {
    }

    public function createForm(
        string $name,
        string $type = FormType::class,
        array $data = [],
        array $options = [],
    ): BaseForm {
        $formId = $this->formDefinition[$name] ?? null;

        if (\in_array($name, $this->formDefinition, true)) {
            $formId = $name;
        }

        if (null === $formId) {
            throw new \OutOfBoundsException(
                \sprintf("The form '%s' doesn't exist", $formId)
            );
        }

        /** @var BaseForm $form */
        $form = $this->container->has($formId) ? $this->container->get($formId) : new $formId();

        $form->init(
            $this->requestStack->getCurrentRequest(),
            $this->eventDispatcher,
            $this->translator,
            $this->formFactoryBuilder,
            $this->validatorBuilder,
            $this->tokenStorage,
            $type,
            $data,
            $options
        );

        return $form;
    }
}
