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

namespace TheliaSmarty\Template;

use Smarty;
use Symfony\Component\Stopwatch\Stopwatch;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateDefinition;

class DataCollectorSmartyParser extends \Smarty implements ParserInterface
{
    protected $smartyParser;

    private $stopwatch;

    protected $templates = [];

    public function __construct(SmartyParser $smartyParser, Stopwatch $stopwatch)
    {
        $this->smartyParser = $smartyParser;
        $this->stopwatch = $stopwatch;
        parent::__construct();
    }

    public function render($realTemplateName, array $parameters = [], $compressOutput = true)
    {
        $this->stopwatch->start($realTemplateName, 'template');
        $timeStart = hrtime(true);
        foreach ($this->getTemplateVars() as $name => $value) {
            $this->smartyParser->assign($name, $value);
        }
        $render = $this->smartyParser->render($realTemplateName, $parameters, $compressOutput);
        $timeEnd = hrtime(true);
        $this->stopwatch->stop($realTemplateName);
        $executionTime = round(($timeEnd - $timeStart) / 1e+6);
        $source = \Smarty_Template_Source::load(null, $this, $realTemplateName);
        $this->collectTemplates($realTemplateName, $parameters, $executionTime);

        return $render;
    }

    public function renderString($templateText, array $parameters = [], $compressOutput = true)
    {
        return $this->smartyParser->renderString($templateText, $parameters, $compressOutput);
    }

    /**
     * @return array
     */
    public function getCollectedTemplates()
    {
        return $this->templates;
    }

    private function collectTemplates(?string $templateName, ?array $parameters = [], $executionTime = 0): void
    {
        $this->templates[] = [
            'name' => $templateName,
            'parameters' => $parameters,
            'executionTime' => $executionTime,
        ];
    }

    public function getStatus()
    {
        return $this->smartyParser->getStatus();
    }

    public function setStatus($status): void
    {
        $this->smartyParser->setStatus($status);
    }

    public function getRequest()
    {
        return $this->smartyParser->getRequest();
    }

    public function pushTemplateDefinition(TemplateDefinition $templateDefinition, $fallbackToDefaultTemplate = false): void
    {
        $this->smartyParser->pushTemplateDefinition($templateDefinition, $fallbackToDefaultTemplate);
    }

    public function popTemplateDefinition(): void
    {
        $this->smartyParser->popTemplateDefinition();
    }

    public function setTemplateDefinition(TemplateDefinition $templateDefinition, $fallbackToDefaultTemplate = false): void
    {
        $this->smartyParser->setTemplateDefinition($templateDefinition, $fallbackToDefaultTemplate);
    }

    public function getTemplateDefinition($webAssetTemplateName = false)
    {
        return $this->smartyParser->getTemplateDefinition();
    }

    public function hasTemplateDefinition()
    {
        return $this->smartyParser->hasTemplateDefinition();
    }

    public function getFallbackToDefaultTemplate()
    {
        return $this->smartyParser->getFallbackToDefaultTemplate();
    }

    public function addTemplateDirectory($templateType, $templateName, $templateDirectory, $key, $unshift = false): void
    {
        $this->smartyParser->addTemplateDirectory($templateType, $templateName, $templateDirectory, $key, $unshift);
    }

    public function getTemplateDirectories($templateType)
    {
        return $this->smartyParser->getTemplateDirectories($templateType);
    }

    public function getTemplateHelper()
    {
        return $this->smartyParser->getTemplateHelper();
    }

    /**
     * Passes through all unknown calls onto the smarty parser object.
     */
    public function __call($method, $args)
    {
        return $this->translator->{$method}(...$args);
    }

    public function supportTemplateRender(?string $templateName): bool
    {
        return $this->smartyParser->supportTemplateRender($templateName);
    }

    public function getFileExtension(): string
    {
        return 'html';
    }
}
