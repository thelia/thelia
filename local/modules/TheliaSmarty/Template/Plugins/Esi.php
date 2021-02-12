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

namespace TheliaSmarty\Template\Plugins;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Fragment\EsiFragmentRenderer;
use Thelia\Core\Template\Smarty\Plugins\an;
use TheliaSmarty\Template\AbstractSmartyPlugin;
use TheliaSmarty\Template\SmartyPluginDescriptor;

/**
 * Class Esi.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class Esi extends AbstractSmartyPlugin
{
    /** @var EsiFragmentRenderer */
    protected $esiFragmentRender;

    /** @var RequestStack */
    protected $requestStack;

    public function __construct(EsiFragmentRenderer $esiFragmentRenderer, RequestStack $requestStack)
    {
        $this->esiFragmentRender = $esiFragmentRenderer;
        $this->requestStack = $requestStack;
    }

    public function renderEsi($params, $template = null)
    {
        $path = $this->getParam($params, 'path');
        $alt = $this->getParam($params, 'alt');
        $ignore_errors = $this->getParam($params, 'ignore_errors');
        $comment = $this->getParam($params, 'comment');

        if (null === $path) {
            return;
        }

        $response = $this->esiFragmentRender->render($path, $this->requestStack->getCurrentRequest(), [
            'alt' => $alt,
            'ignore_errors' => $ignore_errors,
            'comment' => $comment,
        ]);

        if (!$response->isSuccessful()) {
            return null;
        }

        return $response->getContent();
    }

    /**
     * @return array an array of SmartyPluginDescriptor
     */
    public function getPluginDescriptors()
    {
        return [
            new SmartyPluginDescriptor('function', 'render_esi', $this, 'renderEsi'),
        ];
    }
}
