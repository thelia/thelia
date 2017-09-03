<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\RequestContext;
use Thelia\Core\Application;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Tools\URL;

/**
 * Command.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Gilles Bourgeat <gbourgeat@openstudio.fr>
 */
class ContainerAwareCommand extends Command implements ContainerAwareInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @return ContainerInterface
     */
    protected function getContainer()
    {
        if (null === $this->container) {
            /** @var Application $application */
            $application = $this->getApplication();
            $this->container = $application->getKernel()->getContainer();
        }

        return $this->container;
    }

    /**
     * @see ContainerAwareInterface::setContainer()
     * @param ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @return \Symfony\Component\EventDispatcher\EventDispatcher
     */
    public function getDispatcher()
    {
        $container = $this->getContainer();

        // Initialize Thelia translator, if not already done.
        try {
            Translator::getInstance();
        } catch (\Exception $ex) {
            $this->container->get('thelia.translator');
        }

        return $container->get('event_dispatcher');
    }

    /**
     * For init an Request, if your command has need an Request
     * @param Lang|null $lang
     * @since 2.3
     */
    protected function initRequest(Lang $lang = null)
    {
        $container = $this->getContainer();

        $request = Request::create($this->getBaseUrl($lang));
        $request->setSession(new Session(new MockArraySessionStorage()));
        $container->set("request_stack", new RequestStack());
        $container->get('request_stack')->push($request);

        $requestContext = new RequestContext();
        $requestContext->fromRequest($request);
        $url = $container->get('thelia.url.manager');
        $url->setRequestContext($requestContext);
        $this->getContainer()->get('router.admin')->setContext($requestContext);
    }

    /**
     * @param Lang|null $lang
     * @return string
     * @since 2.3
     */
    protected function getBaseUrl(Lang $lang = null)
    {
        $baseUrl = '';

        if ((int) ConfigQuery::read('one_domain_foreach_lang') === 1) {
            if ($lang === null) {
                $lang = LangQuery::create()->findOneByByDefault(true);
            }

            $baseUrl = $lang->getUrl();
        }

        $baseUrl = trim($baseUrl);

        if (empty($baseUrl)) {
            $baseUrl = ConfigQuery::read('url_site');
        }

        if (empty($baseUrl)) {
            $baseUrl = 'http://localhost';
        }

        return $baseUrl;
    }
}
