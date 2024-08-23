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

use Thelia\Core\Template\TemplateHelperInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SvgExtension extends AbstractExtension
{
    private string $svgDirectory; // Directory where SVG files are stored

    public function __construct(
        private TemplateHelperInterface $templateHelper
    ) {
        $this->svgDirectory = $this->templateHelper->getActiveFrontTemplate()->getAbsolutePath()
           .DS.'assets'.DS.'icons';
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('svg', [$this, 'renderSvg'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * @throws \Exception
     */
    public function renderSvg(string $filename): string
    {
        $svgPath = $this->svgDirectory.DS.$filename.'.svg';

        if (!file_exists($svgPath)) {
            throw new \Exception(sprintf('SVG file "%s" not found.', $filename));
        }

        $svgContent = file_get_contents($svgPath);

        if ($svgContent === false) {
            throw new \Exception(sprintf('Unable to read SVG file "%s".', $filename));
        }

        return $svgContent;
    }
}
