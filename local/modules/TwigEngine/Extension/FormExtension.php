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

namespace TwigEngine\Extension;

use Symfony\Component\Form\FormView;
use TwigEngine\Service\FormService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class FormExtension extends AbstractExtension
{
    public function __construct(
        private readonly FormService $formService
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('getForm', [$this, 'getForm'], ['is_safe' => ['html']]),
        ];
    }

    public function getForm(string $name, array $data = []): FormView
    {
        return $this->formService->getFormByName($name, $data);
    }
}
