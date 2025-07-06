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

namespace Thelia\Core\Template\Parser;

use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Template\TemplateHelperInterface;

/**
 * Class ParserFallback.
 *
 * @author manuel raynaud <manu@raynaud.io>
 */
class ParserFallback implements ParserInterface
{
    public function render($realTemplateName, array $parameters = [], $compressOutput = true): void
    {
        $this->throwException();
    }

    public function renderString($templateText, array $parameters = [], $compressOutput = true): void
    {
        $this->throwException();
    }

    public function getStatus(): int
    {
        $this->throwException();
    }

    public function setStatus($status): self
    {
        $this->throwException();
    }

    /**
     * Setup the parser with a template definition, which provides a template description.
     */
    public function setTemplateDefinition(TemplateDefinition $templateDefinition, $fallbackToDefaultTemplate = false): void
    {
        $this->throwException();
    }

    /**
     * Get template definition.
     *
     * @param bool $webAssetTemplate Allow to load asset from another template
     *                               If the name of the template if provided
     */
    public function getTemplateDefinition($webAssetTemplate = false): void
    {
        $this->throwException();
    }

    /**
     * Check if template definition is not null.
     */
    public function hasTemplateDefinition(): void
    {
        $this->throwException();
    }

    /**
     * Add a template directory to the current template list.
     *
     * @param int    $templateType      the template type (
     * @param string $templateName      the template name
     * @param string $templateDirectory path to the template dirtectory
     * @param string $key               ???
     * @param bool   $unshift           ??? Etienne ?
     */
    public function addTemplateDirectory($templateType, $templateName, $templateDirectory, $key, $unshift = false): void
    {
        $this->throwException();
    }

    /**
     * Return the registered template directories for a given template type.
     *
     * @param int $templateType
     *
     * @throws \InvalidArgumentException if the templateType is not defined
     *
     * @return array: an array of defined templates directories for the given template type
     */
    public function getTemplateDirectories($templateType): void
    {
        $this->throwException();
    }

    /**
     * Create a variable that will be available in the templates.
     *
     * @param string $variable the variable name
     * @param mixed  $value    the value of the variable
     */
    public function assign($variable, $value = null): void
    {
        $this->throwException();
    }

    /**
     * @return TemplateHelperInterface the parser template helper instance
     */
    public function getTemplateHelper(): void
    {
        $this->throwException();
    }

    private function throwException(): void
    {
        throw new \RuntimeException('if you want to use a parser, please register one');
    }

    /**
     * Returns the request used by the parser.
     */
    public function getRequest(): void
    {
        $this->throwException();
    }

    /**
     * Set a new template definition, and save the current one.
     *
     * @param bool $fallbackToDefaultTemplate if true, resources will be also searched in the "default" template
     */
    public function pushTemplateDefinition(TemplateDefinition $templateDefinition, $fallbackToDefaultTemplate = false): void
    {
        $this->throwException();
    }

    /**
     * Restore the previous stored template definition, if one exists.
     */
    public function popTemplateDefinition(): void
    {
        $this->throwException();
    }

    /**
     * Get the current status of the fallback to "default" feature.
     */
    public function getFallbackToDefaultTemplate(): void
    {
        $this->throwException();
    }

    public function supportTemplateRender(string $templatePath, ?string $templateName): bool
    {
        return false;
    }

    public function getFileExtension(): string
    {
        $this->throwException();
    }

    public static function getDefaultPriority(): int
    {
        return -10;
    }
}
