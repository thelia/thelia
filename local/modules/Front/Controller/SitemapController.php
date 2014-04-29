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

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Serializer;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Log\Tlog;
use Thelia\Model\CategoryQuery;
use Thelia\Model\Category;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Content;
use Thelia\Model\ContentQuery;
use Thelia\Model\Folder;
use Thelia\Model\FolderQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\Lang;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Model\Tools\SitemapURL;
use Thelia\Tools\SitemapURLNormalizer;
use Thelia\Tools\URL;


/**
 * Class SitemapController
 * @package Front\Controller
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class SitemapController  extends BaseFrontController {


    /**
     * Folder name for sitemap cache
     */
    const SITEMAP_DIR = "sitemap";

    /**
     * String array of active locals : fr_FR, en_US, ...
     *
     * @var array
     */
    protected $locales = array();

    /**
     * Array of ` Thelia\Model\Tools\SitemapURL` object for categories
     *
     * @var array
     */
    protected $categoryUrls = array();

    /**
     * Array of ` Thelia\Model\Tools\SitemapURL` object for products
     *
     * @var array
     */
    protected $productUrls = array();

    /**
     * Array of ` Thelia\Model\Tools\SitemapURL` object for folders
     *
     * @var array
     */
    protected $folderUrls = array();

    /**
     * Array of ` Thelia\Model\Tools\SitemapURL` object for contents
     *
     * @var array
     */
    protected $contentUrls = array();

    /**
     * Array of ` Thelia\Model\Tools\SitemapURL` object for static contents
     *
     * @var array
     */
    protected $staticUrls = array();

    /**
     * @return Response
     */
    public function generateAction()
    {

        // check if already cached
        /** @var Request $request */
        $request = $this->getRequest();
        $locale = $request->query->get("locale");
        // todo: implement contextual sitemap : product, category, cms
        $context = $request->query->get("context", "");
        $flush = $request->query->get("flush", "");
        $expire = ConfigQuery::read("sitemap_ttl", '7200');

        // load locals
        $langs = LangQuery::create()->find();
        /** @var Lang $lang */
        foreach ($langs AS $lang){
            if (null !== $locale) {
                if ($locale === $lang->getLocale()){
                    $this->locales[] = $lang->getLocale();
                    break;
                }
            }
            else {
                $this->locales[] = $lang->getLocale();
            }
        }

        // check if sitemap already in cache
        $cacheDir = $this->getCacheDir();
        $sitemapHash = md5("sitemap." . implode($this->locales) . "." . $context);
        $expire = intval($expire) ?: 7200;
        $cacheFileURL = $cacheDir . $sitemapHash . '.xml';
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
            $encoders = array(new XmlEncoder("urlset"), new JsonEncoder());
            $normalizers = array(new SitemapURLNormalizer());
            $serializer = new Serializer($normalizers, $encoders);

            $this->findStaticUrls();
            $this->findCategoryUrls();
            $this->findFolderUrls();

            $map = array();
            $map['@xmlns'] = "http://www.sitemaps.org/schemas/sitemap/0.9";
            $map['url'] = array_merge(
                $this->staticUrls,
                $this->categoryUrls,
                $this->productUrls,
                $this->folderUrls,
                $this->contentUrls
            );

            $cacheContent = $serializer->serialize($map, 'xml');

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
     * Get all static URLs
     *
     * @param int $parent Parent category id
     */
    protected function findStaticUrls()
    {
        $url = URL::getInstance()->getIndexPage();
        $home = new SitemapURL($url);
        $home->setPriotity(1.0);
        $this->staticUrls[] = $home;
    }

    /**
     * Get all child visible categories of category id `$parent`
     * This function is recursive and is called for all child categories
     *
     * @param int $parent Parent category id
     */
    protected function findCategoryUrls($parent = 0)
    {
        $categoryQuery = CategoryQuery::create();
        $categoryQuery->filterByParent($parent);
        $categoryQuery->filterByVisible(true, Criteria::EQUAL);
        $categories = $categoryQuery->find();

        /** @var Category $category */
        foreach($categories AS $category){
            foreach ($this->locales AS $local){
                $loc = $category->getUrl($local);
                $this->categoryUrls[] = new SitemapURL($loc, $category->getUpdatedAt("c"));
            }
            // call sub categories
            $this->findCategoryUrls($category->getId());
            // call products
            $this->findProductUrls($category);
        }
    }

    /**
     * Get all visible product which have `category` as default category
     *
     * @param Category $category
     */
    protected function findProductUrls(Category $category = null)
    {
        $products = ProductQuery::create()
            //->filterByCategory($category)
            ->filterByVisible(true, Criteria::EQUAL)
            ->joinProductCategory()
            ->where('ProductCategory.default_category' . Criteria::EQUAL . '1')
            ->where('ProductCategory.category_id = ?', $category->getId())
            ->find();

        /** @var Product $product */
        foreach($products AS $product){
            foreach ($this->locales AS $local){
                $loc = $product->getUrl($local);
                $this->productUrls[] = new SitemapURL($loc, $product->getUpdatedAt("c"));
            }
        }
    }

    /**
     * Get all child visible folders of folder id `$parent`
     * This function is recursive and is called for all child folders
     *
     * @param int $parent Parent folder id
     */
    protected function findFolderUrls($parent = 0)
    {
        $folderQuery = FolderQuery::create();
        $folderQuery->filterByParent($parent);
        $folderQuery->filterByVisible(true, Criteria::EQUAL);
        $folders = $folderQuery->find();

        /** @var Folder $folders */
        foreach($folders AS $folder){
            foreach ($this->locales AS $local){
                $loc = $folder->getUrl($local);
                $this->folderUrls[] = new SitemapURL($loc, $folder->getUpdatedAt("c"));
            }
            // call sub folders
            $this->findFolderUrls($folder->getId());
            // call contents
            $this->findContentUrls($folder);
        }
    }

    /**
     * Get all visible content which have in `$folder` folder
     *
     * @param Folder $folder
     */
    protected function findContentUrls(Folder $folder=null)
    {
        $contents = ContentQuery::create()
            ->filterByVisible(true, Criteria::EQUAL)
            ->filterByFolder($folder)
            ->find();

        /** @var Content $content */
        foreach($contents AS $content){
            foreach ($this->locales AS $local){
                $loc = $content->getUrl($local);
                $this->contentUrls[] = new SitemapURL($loc, $content->getUpdatedAt("c"));
            }
        }
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
        $cacheDir = $this->container->getParameter("kernel.cache_dir") .
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