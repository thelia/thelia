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

namespace Thelia\Core\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
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
    protected $container;

    /** @var RequestStack */
    protected $requestStack;

    /** @var EventDispatcherInterface */
    protected $eventDispatcher;

    /**
     * @var ValidatorBuilder
     */
    protected $validatorBuilder;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var FormFactoryBuilderInterface
     */
    protected $formFactoryBuilder;

    /** @var array */
    protected $formDefinition;

    /** @var TokenStorageInterface */
    protected $tokenStorage;

    public function __construct(
        ContainerInterface $container,
        RequestStack $requestStack,
        EventDispatcherInterface $eventDispatcher,
        TranslatorInterface $translator,
        FormFactoryBuilderInterface $formFactoryBuilder,
        ValidatorBuilder $validationBuilder,
        TokenStorageInterface $tokenStorage,
        array $formDefinition
    ) {
        $this->container = $container;
        $this->requestStack = $requestStack;
        $this->eventDispatcher = $eventDispatcher;
        $this->translator = $translator;
        $this->formFactoryBuilder = $formFactoryBuilder;
        $this->validatorBuilder = $validationBuilder;
        $this->formDefinition = $formDefinition;
        $this->tokenStorage = $tokenStorage;
    }

    public function createForm(
        string $name,
        $type = "Symfony\Component\Form\Extension\Core\Type\FormType",
        array $data = [],
        array $options = []
    ): BaseForm {
        $formId = null;
        if (isset($this->formDefinition[$name])) {
            $formId = $this->formDefinition[$name];
        }

        if (false !== array_search($name, $this->formDefinition, true)) {
            $formId = $name;
        }

        if (null === $formId) {
            throw new \OutOfBoundsException(
                sprintf("The form '%s' doesn't exist", $formId)
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
