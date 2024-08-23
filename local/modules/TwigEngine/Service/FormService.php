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

namespace TwigEngine\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormView;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Core\Template\Element\Exception\ElementNotFoundException;
use Thelia\Core\Template\ParserContext;

class FormService
{
    /**
     * @throws \Exception
     */
    public function __construct(
        private readonly TheliaFormFactory $formFactory,
        private readonly ParserContext $parserContext,
        private readonly ParameterBagInterface $parameterBag,
    ) {
    }

    public function getFormByName(
        ?string $name,
        array $data = []
    ): FormView {
        $formConfigs = $this->parameterBag->get('Thelia.parser.forms');
        if (null === $name) {
            $name = 'thelia.empty';
        }
        if (!isset($formConfigs[$name])) {
            throw new ElementNotFoundException(sprintf('%s form does not exists', $name));
        }

        $formClass = $formConfigs[$name];
        $form = $this->parserContext->getForm($name, $formClass, FormType::class);
        if (null === $form) {
            $form = $this->formFactory->createForm($name, FormType::class, $data);
        }

        $this->parserContext->pushCurrentForm($form);

        return $form->getForm()->createView();
    }
}
