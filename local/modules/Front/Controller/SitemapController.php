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


namespace Front\Controller;

use Doctrine\Common\Cache\FilesystemCache;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Model\ConfigQuery;
use Thelia\Model\LangQuery;

/**
 * Controller uses to generate sitemap.xml
 *
 * A default cache of 2 hours is used to avoid attack. You can flush cache if you have `ADMIN` role and pass flush=1 in
 * query string parameter.
 *
 * You can generate sitemap according to specific language and/or specific context (catalog/content). You have to
 * use ```lang``` and ```context``` query string parameters to do so. If a language is not used in website or if the
 * context is not supported the page not found is displayed.
 *
 * {url}/sitemap?lang=fr&context=catalog will generate a sitemap for catalog (categories and products)
 * for french language.
 *
 * @package Front\Controller
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class SitemapController extends BaseFrontController {


    /**
     * Folder name for sitemap cache
     */
    const SITEMAP_CACHE_DIR = "sitemap";

    /**
     * Key prefix for sitemap cache
     */
    const SITEMAP_CACHE_KEY = "sitemap";

    /**
     * @return Response
     */
    public function generateAction()
    {
        /** @var Request $request */
        $request = $this->getRequest();

        // the locale : fr, en,
        $lang = $request->query->get("lang", "");
        if ("" !== $lang) {
            if (! $this->checkLang($lang)){
                $this->pageNotFound();
            }
        }
        // specific content : product, category, cms
        $context = $request->query->get("context", "");
        if (! in_array($context, array("", "catalog", "content")) ){
            $this->pageNotFound();
        }

        $flush = $request->query->get("flush", "");

        // check if sitemap already in cache
        $cacheContent = false;

        $cacheDir = $this->getCacheDir();
        $cacheKey = self::SITEMAP_CACHE_KEY . $lang . $context;
        $cacheExpire = intval(ConfigQuery::read("sitemap_ttl", '7200')) ?: 7200;

        $cacheDriver = new FilesystemCache($cacheDir);
        if (!($this->checkAdmin() && "" !== $flush)){
            $cacheContent = $cacheDriver->fetch($cacheKey);
        } else {
            $cacheDriver->delete($cacheKey);
        }

        // if not in cache
        if (false === $cacheContent){
            // render the view
            $cacheContent = $this->renderRaw(
                "sitemap",
                array(
                    "_lang_" => $lang,
                    "_context_" => $context
                )
            );
            // save cache
            $cacheDriver->save($cacheKey, $cacheContent, $cacheExpire);
        }

        $response = new Response();
        $response->setContent($cacheContent);
        $response->headers->set('Content-Type', 'application/xml');

        return $response;
    }


    /**
     * get the cache directory for sitemap
     *
     * @return mixed|string
     */
    protected function getCacheDir()
    {
        $cacheDir = $this->container->getParameter("kernel.cache_dir");
        $cacheDir = rtrim($cacheDir, '/');
        $cacheDir .= '/' . self::SITEMAP_CACHE_DIR . '/';

        return $cacheDir;
    }

    /**
     * Check if current user has ADMIN role
     *
     * @return bool
     */
    protected function checkAdmin(){
       return $this->getSecurityContext()->hasAdminUser();
    }


    /**
     * Check if a lang is used
     *
     * @param $lang The lang code. e.g.: fr
     * @return bool true if the language is used, otherwise false
     */
    private function checkLang($lang)
    {
        // load locals
        $lang = LangQuery::create()
            ->findOneByCode($lang);

        return (null !== $lang);
    }

} 