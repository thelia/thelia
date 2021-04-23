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

use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Thelia;

class TheliaCollector extends AbstractDataCollector
{
    public function collect(Request $request, Response $response, \Throwable $exception = null): void
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
