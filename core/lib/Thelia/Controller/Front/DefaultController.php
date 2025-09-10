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

namespace Thelia\Controller\Front;

use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpKernel\Exception\RedirectException;
use Thelia\Core\TheliaHttpKernel;
use Thelia\Core\View\ViewRenderer;
use Thelia\Model\ConfigQuery;
use Thelia\Tools\URL;

/**
 * This is the default Thelia controller, which is called when no controller was found to process the request.
 *
 * @author Manuel Raynaud <mraynadu@openstudio.fr>
 */
class DefaultController extends BaseFrontController
{
    public function noAction(Request $request): void
    {
        if (true === $request->attributes->get(TheliaHttpKernel::IGNORE_THELIA_VIEW, false)) {
            return;
        }

        $view = $request->attributes->get('_view');
        if (null === $view || '' === $view) {
            $viewFromQuery = $request->query->get('view');
            $viewFromBody = $request->request->get('view');
            $view = $viewFromQuery ?: ($viewFromBody ?: 'index');
            $request->attributes->set('_view', $view);
        }

        // Init {view}_id if missing
        if (!$request->attributes->has($view.'_id')) {
            $id = $request->query->getInt($view.'_id') ?: $request->request->getInt($view.'_id');
            if ($id) {
                $request->attributes->set($view.'_id', $id);
            }
        }

        if (ConfigQuery::isRewritingEnable() && false === $request->attributes->get('_rewritten', false)) {
            $rewritten = URL::getInstance()->retrieveCurrent($request);
            if (null !== $rewritten->rewrittenUrl) {
                throw new RedirectException($rewritten->rewrittenUrl, 301);
            }
        }
    }

    public function indexAction(Request $request, ViewRenderer $viewRenderer): Response
    {
        $this->noAction($request);

        return $viewRenderer->render($request);
    }
}
