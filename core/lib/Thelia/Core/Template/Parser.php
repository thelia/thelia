<?php

namespace Thelia\Core\Template;

use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Template\ParserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 *
 * Master class of Thelia's parser. The loop mechnism depends of this parser
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
     * This method must return a Symfony\Component\HttpFoudation\Response instance or the content of the response
     *
     */
    public function getContent()
    {
       $this->loadParser();
       
       $request = $this->container->get('request');
              
       $this->content = $request->get("test");
       
       var_dump($this->container->get("database"));
       
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

    public function loadParser()
    {
    }

}
