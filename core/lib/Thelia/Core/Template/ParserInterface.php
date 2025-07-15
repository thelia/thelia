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

namespace Thelia\Core\Template;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\Template\Exception\ResourceNotFoundException;

#[AutoconfigureTag('thelia.parser.template')]
/**
 * @author Manuel Raynaud <manu@raynaud.io>
 */
interface ParserInterface
{
    // A key to identify assets defined in a template. This will be the name of the directory in which the template
    // assets will be copied and generated in the web cache.
    public const TEMPLATE_ASSETS_KEY = 'template-assets';

    /**
     * Return a rendered template file.
     *
     * @param string $realTemplateName the template name (from the template directory)
     * @param array  $parameters       an associative array of names / value pairs
     * @param bool   $compressOutput   if true, te output is compressed using trimWhitespaces. If false, no compression occurs
     *
     * @return string the rendered template text
     *
     * @throws ResourceNotFoundException if the template cannot be found
     * @throws \Exception
     */
    public function render(string $realTemplateName, array $parameters = [], bool $compressOutput = true): string;

    /**
     * Return a rendered template text.
     *
     * @param string $templateText   the template text
     * @param array  $parameters     an associative array of names / value pairs
     * @param bool   $compressOutput if true, te output is compressed using trimWhitespaces. If false, no compression occurs
     *
     * @return string the rendered template text
     *
     * @throws \Exception
     */
    public function renderString(string $templateText, array $parameters = [], bool $compressOutput = true): string;

    /**
     * @return int the HTTP status of the response
     */
    public function getStatus(): int;

    /**
     * Sets the HTTP status of the response.
     *
     * status An HTTP status (200, 404, ...)
     */
    public function setStatus(int $status): self;

    /**
     * Returns the request used by the parser.
     */
    public function getRequest(): ?Request;

    /**
     * Set a new template definition, and save the current one.
     *
     * @param bool $fallbackToDefaultTemplate if true, resources will be also searched in the "default" template
     */
    public function pushTemplateDefinition(TemplateDefinition $templateDefinition, bool $fallbackToDefaultTemplate = false);

    /**
     * Restore the previous stored template definition, if one exists.
     */
    public function popTemplateDefinition();

    /**
     * Setup the parser with a template definition, which provides a template description.
     *
     * @param bool $fallbackToDefaultTemplate if true, also search files in hte "default" template
     */
    public function setTemplateDefinition(TemplateDefinition $templateDefinition, bool $fallbackToDefaultTemplate = false);

    /**
     * Get template definition.
     *
     * @param bool|string $webAssetTemplateName false to use the current template path, or a template name to
     *                                          load assets from this template instead of the current one
     */
    public function getTemplateDefinition(bool|string $webAssetTemplateName = false): TemplateDefinition;

    /**
     * Check if template definition is not null.
     */
    public function hasTemplateDefinition(): bool;

    /**
     * Get the current status of the fallback to "default" feature.
     */
    public function getFallbackToDefaultTemplate(): bool;

    /**
     * Add a template directory to the current template list.
     *
     * @param int    $templateType      the template type (
     * @param string $templateName      the template name
     * @param string $templateDirectory path to the template dirtectory
     * @param string $key               the template directory identifier
     * @param bool   $unshift           if true, add template at the top of the list
     */
    public function addTemplateDirectory(int $templateType, string $templateName, string $templateDirectory, string $key, bool $unshift = false);

    /**
     * Return the registeted template directories for a givent template type.
     *
     * @return array: an array of defined templates directories for the given template type
     *
     * @throws \InvalidArgumentException if the templateType is not defined
     */
    public function getTemplateDirectories(int $templateType);

    /**
     * Create a variable that will be available in the templates.
     *
     * @param string $variable the variable name
     * @param mixed  $value    the value of the variable
     */

    /**
     * assigns a parser variable. If $variable is an array, it is supposed to contains (variable_name => variable_value) pairs.
     *
     * @param array|string $variable the template variable name(s)
     * @param mixed        $value    the value to assign
     */
    public function assign(array|string $variable, mixed $value = null);

    /**
     * @return TemplateHelperInterface the parser template helper instance
     */
    public function getTemplateHelper(): TemplateHelperInterface;

    public function supportTemplateRender(string $templatePath, ?string $templateName): bool;

    public function getFileExtension(): string;

    public static function getDefaultPriority(): int;
}
