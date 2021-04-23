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

namespace WebProfiler\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use TheliaSmarty\Template\DataCollectorSmartyParser;

class SmartyDataCollector extends DataCollector
{
    private $smartyParser;

    public function __construct(DataCollectorSmartyParser $smartyParser)
    {
        $this->smartyParser = $smartyParser;
    }

    public function collect(Request $request, Response $response, \Throwable $exception = null): void
    {
        $this->data['templates'] = $this->smartyParser->getCollectedTemplates();
    }

    public function getTemplates()
    {
        return $this->data['templates'];
    }

    public function getTemplateCount()
    {
        return \count($this->data['templates']);
    }

    public function getTotalExecutionTime()
    {
        return array_reduce($this->data['templates'], function ($carry, $template) { return $carry + $template['executionTime']; }, 0);
    }

    public function getName()
    {
        return 'smarty';
    }

    public function reset(): void
    {
        $this->data['templates'] = [];
    }
}
