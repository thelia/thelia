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

namespace HookAdminHome\Hook;

use HookAdminHome\HookAdminHome;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Thelia\Core\Event\Hook\HookRenderBlockEvent;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

/**
 * Class AdminHook.
 *
 * @author Gilles Bourgeat <gilles@thelia.net>
 */
class AdminHook extends BaseHook
{
    protected $theliaCache;

    public function __construct(AdapterInterface $theliaCache = null)
    {
        $this->theliaCache = $theliaCache;
    }

    public function blockStatistics(HookRenderEvent $event): void
    {
        if (1 == HookAdminHome::getConfigValue(HookAdminHome::ACTIVATE_STATS, 1)) {
            $event->add($this->render('block-statistics.html'));
        }

        $event->add($this->render('hook-admin-home-config.html'));
    }

    public function blockStatisticsJs(HookRenderEvent $event): void
    {
        if (1 == HookAdminHome::getConfigValue(HookAdminHome::ACTIVATE_STATS, 1)) {
            $event->add($this->render('block-statistics-js.html'));
        }
    }

    public function blockSalesStatistics(HookRenderBlockEvent $event): void
    {
        if (1 == HookAdminHome::getConfigValue(HookAdminHome::ACTIVATE_SALES, 1)) {
            $content = trim($this->render('block-sales-statistics.html'));
            if (!empty($content)) {
                $event->add([
                    'id' => 'block-sales-statistics',
                    'title' => $this->trans('Sales statistics', [], HookAdminHome::DOMAIN_NAME),
                    'content' => $content,
                ]);
            }
        }
    }

    public function blockNews(HookRenderBlockEvent $event): void
    {
        if (1 == HookAdminHome::getConfigValue(HookAdminHome::ACTIVATE_NEWS, 1)) {
            $content = trim($this->render('block-news.html'));
            if (!empty($content)) {
                $event->add([
                    'id' => 'block-news',
                    'title' => $this->trans('Thelia Github activity', [], HookAdminHome::DOMAIN_NAME),
                    'content' => $content,
                ]);
            }
        }
    }

    public function blockTheliaInformation(HookRenderBlockEvent $event): void
    {
        $releases = $this->getGithubReleases();
        if (1 == HookAdminHome::getConfigValue(HookAdminHome::ACTIVATE_INFO, 1)) {
            $content = trim(
                $this->render(
                    'block-thelia-information.html',
                    [
                        'latestStableRelease' => $releases['latestStableRelease'],
                        'latestPreRelease' => $releases['latestPreRelease'],
                    ]
                )
            );
            if (!empty($content)) {
                $event->add([
                    'id' => 'block-thelia-information',
                    'title' => $this->trans('Thelia news', [], HookAdminHome::DOMAIN_NAME),
                    'content' => $content,
                ]);
            }
        }
    }

    private function getGithubReleases(): array
    {
        $cachedReleases = $this->theliaCache->getItem('thelia_github_releases');
        if (!$cachedReleases->isHit()) {
            try {
                $resource = curl_init();

                curl_setopt($resource, \CURLOPT_URL, 'https://api.github.com/repos/thelia/thelia/releases');
                curl_setopt($resource, \CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($resource, \CURLOPT_HTTPHEADER, ['accept: application/vnd.github.v3+json']);
                curl_setopt($resource, \CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');

                $results = curl_exec($resource);

                curl_close($resource);

                $theliaReleases = json_decode($results, true);

                $publishedAtSort = function ($a, $b) {return (new \DateTime($a['published_at'])) < (new \DateTime($b['published_at'])); };

                $stableReleases = array_filter($theliaReleases, function ($theliaRelease) { return !$theliaRelease['prerelease']; });
                usort($stableReleases, $publishedAtSort);
                $latestStableRelease = $stableReleases[0] ?? null;

                $preReleases = array_filter($theliaReleases, function ($theliaRelease) { return $theliaRelease['prerelease']; });
                usort($preReleases, $publishedAtSort);
                $latestPreRelease = $preReleases[0] ?? null;

                // Don't display pre-release if they are < than stable release
                if (version_compare($latestPreRelease['tag_name'], $latestStableRelease['tag_name'], '<')) {
                    $latestPreRelease = null;
                }
            } catch (\Exception $exception) {
                $latestPreRelease = null;
                $latestStableRelease = null;
            }

            $cachedReleases->expiresAfter(3600);
            $cachedReleases->set(
                [
                    'latestStableRelease' => $latestStableRelease,
                    'latestPreRelease' => $latestPreRelease,
                ]
            );
            $this->theliaCache->save($cachedReleases);
        }

        return $cachedReleases->get();
    }
}
