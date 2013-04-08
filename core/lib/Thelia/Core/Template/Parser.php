<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.     */
/*                                                                                   */
/*************************************************************************************/
namespace Thelia\Core\Template;

use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Template\ParserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Config\ConfigCache;

use Thelia\Tpex\Tpex;
use Thelia\Log\Tlog;

/**
 *
 * Master class of Thelia's parser. The loop mechanism depends of this parser
 *
 * From this class all the parser is lunch
 *
 *
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */


class Parser implements ParserInterface
{
    const PREFIXE = 'prx';

    const SHOW_TIME = true;
    const ALLOW_DEBUG = true;
    const USE_CACHE = true;
    

    /**
     *
     * @var Symfony\Component\DependencyInjection\ContainerInterface
     */
    protected $container;
    
    protected $content;
    protected $status = 200;
    
    /**
     *
     * @var Thelia\Tpex\Tpex
     */
    protected $tpex;
    
    protected $template = "default";
    
    /**
     *
     * @param type $container
     *
     * public function __construct(ContainerBuilder $container)
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }
    
    /**
     * 
     * @return Symfony\Component\HttpFoundation\Request
     */
    public function getRequest()
    {
        return $this->container->get('request');
    }
    
    /**
     * 
     * @return Symfony\Component\EventDispatcher\EventDispatcher
     */
    public function getDispatcher()
    {
        return $this->container->get('dispatcher');
    }

    /**
     *
     * This method must return a Symfony\Component\HttpFoudation\Response instance or the content of the response
     *
     */
    public function getContent()
    {
       $this->loadParser();
       
       return $this->content;
    }

    /**
     *
     * set $content with the body of the response or the Response object directly
     *
     * @param string|Symfony\Component\HttpFoundation\Response $content
     */
    public function setContent($content)
    {
        $this->content = $content;
    }

    /**
     *
     * @return type the status of the response
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     *
     * status HTTP of the response
     *
     * @param int $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * Main parser function, load the parser
     */
    public function loadParser()
    {
        $content = $this->openFile($this->getRequest());

        $tpex = $this->container->get("template");

        $tpex->setBaseDir(THELIA_TEMPLATE_DIR . rtrim($this->template, "/") . "/");
        $tpex->setContent($content);

        $this->setContent($tpex->execute());
    }
    
    protected function openFile(Request $request)
    {
        $file = $request->attributes->get('_view');
        $fileName = THELIA_TEMPLATE_DIR . rtrim($this->template, "/") . "/" . $file . ".html";
        if (file_exists($fileName)) {
            $content = file_get_contents($fileName);
        } else {
            throw new ResourceNotFoundException(sprintf("%s file not found in %s template", $file, $this->template));
        }
        
        return $content;
    }
}
