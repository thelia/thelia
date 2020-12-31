<?php
/*************************************************************************************/
/* This file is part of the Thelia package.                                          */
/*                                                                                   */
/* Copyright (c) OpenStudio                                                          */
/* email : dev@thelia.net                                                            */
/* web : http://www.thelia.net                                                       */
/*                                                                                   */
/* For the full copyright and license information, please view the LICENSE.txt       */
/* file that was distributed with this source code.                                  */
/*************************************************************************************/

namespace Thelia\Core\Form;

use Symfony\Component\Validator\ValidatorBuilder;
use Symfony\Component\Form\FormFactoryBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\EventDispatcher\EventDispatcher;
use Thelia\Form\BaseForm;

/**
 * Class TheliaFormFactory
 * @package Thelia\Core\Form
 * @author Benjamin Perche <benjamin@thelia.net>
 */
class TheliaFormFactory implements TheliaFormFactoryInterface
{
    /** @var RequestStack  */
    protected $requestStack;

    /** @var EventDispatcher  */
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

    public function __construct(
        RequestStack $requestStack,
        EventDispatcher $eventDispatcher,
        TranslatorInterface $translator,
        FormFactoryBuilderInterface $formFactoryBuilder,
        ValidatorBuilder $validationBuilder,
        array $formDefinition
    ) {
        $this->requestStack = $requestStack;
        $this->eventDispatcher = $eventDispatcher;
        $this->translator = $translator;
        $this->formFactoryBuilder = $formFactoryBuilder;
        $this->validatorBuilder = $validationBuilder;
        $this->formDefinition = $formDefinition;
    }

    /**
     * @param  string                $name
     * @param  string                $type
     * @param  array                 $data
     * @param  array                 $options
     * @return BaseForm
     */
    public function createForm(
        string $name,
        $type = "Symfony\Component\Form\Extension\Core\Type\FormType",
        array $data = array(),
        array $options = array()
    ): BaseForm {
        $formClass = null;
        if (isset($this->formDefinition[$name])) {
            $formClass = $this->formDefinition[$name];
        }

        if (false !== array_search($name, $this->formDefinition, true)) {
            $formClass = $name;
        }

        if (null === $formClass) {
            throw new \OutOfBoundsException(
                sprintf("The form '%s' doesn't exist", $name)
            );
        }

        return new $formClass(
            $this->requestStack->getCurrentRequest(),
            $this->eventDispatcher,
            $this->translator,
            $this->formFactoryBuilder,
            $this->validatorBuilder,
            $type,
            $data,
            $options
        );
    }
}
