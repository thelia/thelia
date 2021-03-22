<?php


namespace TheliaSmarty\Template;

use Smarty;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateDefinition;

class DataCollectorSmartyParser extends Smarty implements ParserInterface
{
    protected $smartyParser;

    protected $templates = [];

    public function __construct(SmartyParser $smartyParser)
    {
        $this->smartyParser = $smartyParser;
        parent::__construct();
    }

    public function render($realTemplateName, array $parameters = [], $compressOutput = true)
    {
        $render = $this->smartyParser->render($realTemplateName, $parameters, $compressOutput);
        $this->collectTemplates($realTemplateName, $parameters);

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

    private function collectTemplates(?string $templateName, ?array $parameters = [])
    {
        $this->templates[] = [
            "name" => $templateName,
            "parameters" => $parameters
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
