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
use Thelia\Model\BrandQuery;
use Thelia\Model\FolderQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;

/**
 * Controller uses to generate RSS Feeds
 *
 * A default cache of 2 hours is used to avoid attack. You can flush cache if you have `ADMIN` role and pass flush=1 in
 * query string parameter.
 *
 * @package Front\Controller
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class FeedController extends BaseFrontController {


    /**
     * Folder name for feeds cache
     */
    const FEED_CACHE_DIR = "feeds";

    /**
     * Key prefix for feed cache
     */
    const FEED_CACHE_KEY = "feed";


    /**
     * render the RSS feed
     *
     * @param $context string   The context of the feed : catalog, content. default: catalog
     * @param $lang string      The lang of the feed : fr_FR, en_US, ... default: default language of the site
     * @param $id string        The id of the parent element. The id of the main parent category for catalog context.
     *                          The id of the content folder for content context
     * @return Response
     * @throws \RuntimeException
     */
    public function generateAction($context, $lang, $id)
    {

        /** @var Request $request */
        $request = $this->getRequest();

        // context
        if ("" === $context){
            $context = "catalog";
        } else if (! in_array($context, array("catalog", "content", "brand")) ){
            $this->pageNotFound();
        }

        // the locale : fr_FR, en_US,
        if ("" !== $lang) {
            if (! $this->checkLang($lang)){
                $this->pageNotFound();
            }
        } else {
            try{
                $lang = Lang::getDefaultLanguage();
                $lang = $lang->getLocale();
            } catch (\RuntimeException $ex){
                // @todo generate error page
                throw new \RuntimeException("No default language is defined. Please define one.");
            }
        }
        if (null === $lang = LangQuery::create()->findOneByLocale($lang)){
            $this->pageNotFound();
        }
        $lang = $lang->getId();

        // check if element exists and is visible
        if ("" !== $id){
            if (false === $this->checkId($context, $id)){
                $this->pageNotFound();
            }
        }

        $flush = $request->query->get("flush", "");

        // check if feed already in cache
        $cacheContent = false;

        $cacheDir = $this->getCacheDir();
        $cacheKey = self::FEED_CACHE_KEY . $lang . $context . $id;
        $cacheExpire = intval(ConfigQuery::read("feed_ttl", '7200')) ?: 7200;

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
                "feed",
                array(
                    "_context_" => $context,
                    "_lang_"    => $lang,
                    "_id_"      => $id
                )
            );
            // save cache
            $cacheDriver->save($cacheKey, $cacheContent, $cacheExpire);
        }

        $response = new Response();
        $response->setContent($cacheContent);
        $response->headers->set('Content-Type', 'application/rss+xml');

        return $response;
    }


    /**
     * get the cache directory for feeds
     *
     * @return mixed|string
     */
    protected function getCacheDir()
    {
        $cacheDir = $this->container->getParameter("kernel.cache_dir");
        $cacheDir = rtrim($cacheDir, '/');
        $cacheDir .= '/' . self::FEED_CACHE_DIR . '/';

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
     * @param $lang string  The lang code. e.g.: fr
     * @return bool         true if the language is used, otherwise false
     */
    private function checkLang($lang)
    {
        // load locals
        $lang = LangQuery::create()
            ->findOneByLocale($lang);

        return (null !== $lang);
    }


    /**
     * Check if the element exists and is visible
     *
     * @param $context string   catalog or content
     * @param $id string        id of the element
     * @return bool
     */
    private function checkId($context, $id)
    {
        $ret = false;
        if (is_numeric($id)){
            if ("catalog" === $context){
                $cat = CategoryQuery::create()->findPk($id);
                $ret = (null !== $cat && $cat->getVisible());
            } elseif ("brand" === $context) {
                $brand = BrandQuery::create()->findPk($id);
                $ret = (null !== $brand && $brand->getVisible());
            } else {
                $folder = FolderQuery::create()->findPk($id);
                $ret = (null !== $folder && $folder->getVisible());
            }
        }
        return $ret;
    }
}