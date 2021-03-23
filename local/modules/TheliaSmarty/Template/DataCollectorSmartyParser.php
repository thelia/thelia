<?php


namespace TheliaSmarty\Template;

use Smarty;
use Symfony\Component\Stopwatch\Stopwatch;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateDefinition;

class DataCollectorSmartyParser extends Smarty implements ParserInterface
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
        $render = $this->smartyParser->render($realTemplateName, $parameters, $compressOutput);
        $timeEnd = hrtime(true);
        $this->stopwatch->stop($realTemplateName);
        $executionTime = round(($timeEnd - $timeStart) /1e+6);
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

    private function collectTemplates(?string $templateName, ?array $parameters = [], $executionTime = 0)
    {
        $this->templates[] = [
            "name" => $templateName,
            "parameters" => $parameters,
            "executionTime" => $executionTime
        ];
    }

    public function getStatus()
    {
        return $this->smartyParser->getStatus();
    }

    public function setStatus($status)
    {
        $this->smartyParser->setStatus($status);
    }

    public function getRequest()
    {
        return $this->smartyParser->getRequest();
    }

    public function pushTemplateDefinition(TemplateDefinition $templateDefinition, $fallbackToDefaultTemplate = false)
    {
        $this->smartyParser->pushTemplateDefinition($templateDefinition, $fallbackToDefaultTemplate);
    }

    public function popTemplateDefinition()
    {
        $this->smartyParser->popTemplateDefinition();
    }

    public function setTemplateDefinition(TemplateDefinition $templateDefinition, $fallbackToDefaultTemplate = false)
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

    public function addTemplateDirectory($templateType, $templateName, $templateDirectory, $key, $unshift = false)
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
}
