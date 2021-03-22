<?php

namespace WebProfiler\DataCollector;

use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TheliaSmarty\Template\DataCollectorSmartyParser;

class SmartyDataCollector extends AbstractDataCollector
{
    private $smartyParser;

    public function __construct(DataCollectorSmartyParser $smartyParser)
    {
        $this->smartyParser = $smartyParser;
    }

    public function collect(Request $request, Response $response, \Throwable $exception = null)
    {
        $this->data['templates'] = $this->smartyParser->getCollectedTemplates();
    }

    public function getTemplates()
    {
        return $this->data['templates'];
    }

    public static function getTemplate(): ?string
    {
        return '@WebProfilerModule/debug/dataCollector/smarty.html.twig';
    }
}
