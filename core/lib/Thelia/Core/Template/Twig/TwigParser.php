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

namespace Thelia\Core\Template\Twig;

use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateDefinition;
use Twig\Environment;

/**
 * Class TwigParser.
 *
 * @author Alexandre Nozière - anoziere@openstudio.fr
 */
class TwigParser implements ParserInterface
{
    public function __construct(
        private readonly Environment $twig
    ) {
    }

    public function render($realTemplateName, array $parameters = [], $compressOutput = true): string
    {
        $realTwigName = $realTemplateName.$this->getFileExtension();

        return $this->twig->render($realTwigName, $parameters);
    }

    public function supportTemplateRender(?string $templateName): bool
    {
        if ($templateName === null) {
            return false;
        }

        $templatePath = $this->getFullPathByTemplateName($templateName);

        return file_exists($templatePath);
    }

    public function getFileExtension(): string
    {
        return '.html.twig';
    }

    public function renderString($templateText, array $parameters = [], $compressOutput = true): void
    {
        // TODO: Implement renderString() method.
    }

    public function getStatus(): void
    {
        // TODO: Implement getStatus() method.
    }

    public function setStatus($status): void
    {
        // TODO: Implement setStatus() method.
    }

    public function getRequest(): void
    {
        // TODO: Implement getRequest() method.
    }

    public function pushTemplateDefinition(TemplateDefinition $templateDefinition, $fallbackToDefaultTemplate = false): void
    {
        // TODO: Implement pushTemplateDefinition() method.
    }

    public function popTemplateDefinition(): void
    {
        // TODO: Implement popTemplateDefinition() method.
    }

    public function setTemplateDefinition(TemplateDefinition|string $templateDefinition, $fallbackToDefaultTemplate = false): void
    {
        // TODO: Implement setTemplateDefinition() method.
    }

    public function getTemplateDefinition($webAssetTemplateName = false): void
    {
        // TODO: Implement getTemplateDefinition() method.
    }

    public function hasTemplateDefinition(): void
    {
        // TODO: Implement hasTemplateDefinition() method.
    }

    public function getFallbackToDefaultTemplate(): void
    {
        // TODO: Implement getFallbackToDefaultTemplate() method.
    }

    public function addTemplateDirectory($templateType, $templateName, $templateDirectory, $key, $unshift = false): void
    {
        // TODO: Implement addTemplateDirectory() method.
    }

    public function getTemplateDirectories($templateType): void
    {
        // TODO: Implement getTemplateDirectories() method.
    }

    public function assign($variable, $value = null): void
    {
        // TODO: Implement assign() method.
    }

    public function getTemplateHelper(): void
    {
        // TODO: Implement getTemplateHelper() method.
    }

    private function getFullPathByTemplateName(string $templateName): string
    {
        return THELIA_ROOT.'templates/'.$templateName.$this->getFileExtension();
    }
}
