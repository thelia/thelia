<?php

namespace WebProfiler\DataCollector;

use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Thelia;

class TheliaCollector extends AbstractDataCollector
{
    public function collect(Request $request, Response $response, \Throwable $exception = null)
    {
        $this->data = [
            'theliaVersion' => Thelia::THELIA_VERSION,
        ];
    }

    public function getTheliaVersion()
    {
        return $this->data['theliaVersion'];
    }

    public static function getTemplate(): ?string
    {
        return '@WebProfilerModule/debug/dataCollector/thelia.html.twig';
    }
}
