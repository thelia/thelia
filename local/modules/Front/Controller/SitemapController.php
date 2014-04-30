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

use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;

/**
 * Controller uses to generate sitemap.xml
 *
 * A default cache of 2 hours is used to avoid attack. You can flush cache if you have `ADMIN` role and pass flush=1 in
 * query parameter.
 *
 * @package Front\Controller
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class SitemapController extends BaseFrontController {


    /**
     * Folder name for sitemap cache
     */
    const SITEMAP_DIR = "sitemap";

    /**
     * Folder name for sitemap cache
     */
    const SITEMAP_FILE = "sitemap";

    /**
     * @return Response
     */
    public function generateAction()
    {

        /** @var Request $request */
        $request = $this->getRequest();
        $flush = $request->query->get("flush", "");
        $expire = ConfigQuery::read("sitemap_ttl", '7200');

        // check if sitemap already in cache
        $cacheDir = $this->getCacheDir();
        $cacheFileURL = $cacheDir . self::SITEMAP_FILE . '.xml';
        $expire = intval($expire) ?: 7200;
        $cacheContent = null;

        if (!($this->checkAdmin() && "" !== $flush)){
            try {
                $cacheContent = $this->getCache($cacheFileURL, $expire);
            } catch (\RuntimeException $ex) {
                // Problem loading cache, permission errors ?
                Tlog::getInstance()->addAlert($ex->getMessage());
            }
        }

        if (null === $cacheContent){
            // render the view
            $cacheContent = $this->renderRaw("sitemap");

            // save cache
            try {
                $this->setCache($cacheFileURL, $cacheContent);
            } catch (\RuntimeException $ex) {
                // Problem loading cache, permission errors ?
                Tlog::getInstance()->addAlert($ex->getMessage());
            }

        }

        $response = new Response();
        $response->setContent($cacheContent);
        $response->headers->set('Content-Type', 'application/xml');

        return $response;
    }

    /**
     * Check if current user has ADMIN role
     *
     * @return bool
     */
    protected function checkAdmin(){
       return $this->getSecurityContext()->isGranted(array("ADMIN"), array(), array(), array());
    }

    /**
     * Get the content of the file if it exists and not expired?
     *
     * @param $fileURL path to the file
     * @param $expire TTL for the file
     * @return null|string  The content of the file if it exists and not expired
     * @throws \RuntimeException
     */
    protected function getCache($fileURL, $expire)
    {
        $content = null;
        if (is_file($fileURL)){
            $mtime = filemtime($fileURL);
            if ($mtime + $expire < time()){
                if (! @unlink($fileURL)){
                    throw new \RuntimeException(sprintf("Failed to remove %s file in cache directory", $fileURL));
                }
            } else {
                $content = file_get_contents($fileURL);
            }
        }
        return $content;
    }

    /**
     * Save content in the file specified by `$fileURL`
     *
     * @param $fileURL the path to the file
     * @param $content the content of the file
     * @throws \RuntimeException
     */
    protected function setCache($fileURL, $content)
    {
        if (! @file_put_contents($fileURL, $content)){
            throw new \RuntimeException(sprintf("Failed to save %s file in cache directory", $fileURL));
        }
    }

    /**
     * Retrieve the cache dir used for sitemaps
     *
     * @return string the path to the cache dir
     * @throws \RuntimeException
     */
    protected function getCacheDir()
    {
        $cacheDir = $this->container->getParameter("kernel.cache_dir");
        $cacheDir = rtrim($cacheDir, '/');
        $cacheDir .= '/' . self::SITEMAP_DIR . '/';
        if (! is_dir($cacheDir)){
            if (! @mkdir($cacheDir, 0777, true)) {
                throw new \RuntimeException(sprintf("Failed to create %s dir in cache directory",  self::SITEMAP_DIR));
            }
        }
        return $cacheDir;
    }

} 