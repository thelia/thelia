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

/**
 * Class ParserFallback.
 *
 * @author manuel raynaud <manu@raynaud.io>
 */
class ParserFallback implements ParserInterface
{
    public function render($realTemplateName, array $parameters = [], $compressOutput = true): never
    {
        $this->throwException();
    }

    public function renderString($templateText, array $parameters = [], $compressOutput = true): never
    {
        $this->throwException();
    }

    public function getStatus(): never
    {
        $this->throwException();
    }

    public function setStatus($status): never
    {
        $this->throwException();
    }

    public function setTemplateDefinition(TemplateDefinition $templateDefinition, $fallbackToDefaultTemplate = false): never
    {
        $this->throwException();
    }

    public function getTemplateDefinition(bool|string $webAssetTemplateName = false): never
    {
        $this->throwException();
    }

    public function hasTemplateDefinition(): never
    {
        $this->throwException();
    }

    public function addTemplateDirectory(int $templateType, string $templateName, string $templateDirectory, string $key, bool $unshift = false): never
    {
        $this->throwException();
    }

    public function getTemplateDirectories(int $templateType): never
    {
        $this->throwException();
    }

    public function assign(string|array $variable, mixed $value = null): never
    {
        $this->throwException();
    }

    public function getTemplateHelper(): never
    {
        $this->throwException();
    }

    private function throwException(): never
    {
        throw new \RuntimeException('if you want to use a parser, please register one');
    }

    public function getRequest(): never
    {
        $this->throwException();
    }

    public function pushTemplateDefinition(TemplateDefinition $templateDefinition, bool $fallbackToDefaultTemplate = false): never
    {
        $this->throwException();
    }

    public function popTemplateDefinition(): never
    {
        $this->throwException();
    }

    public function getFallbackToDefaultTemplate(): never
    {
        $this->throwException();
    }

    public function supportTemplateRender(string $templatePath, ?string $templateName): bool
    {
        return false;
    }

    public function getFileExtension(): never
    {
        $this->throwException();
    }

    public static function getDefaultPriority(): int
    {
        return -10;
    }
}
