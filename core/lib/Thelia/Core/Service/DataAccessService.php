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

namespace Thelia\Core\Service;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\Element\BaseLoop;

readonly class DataAccessService
{
    public function __construct(
        private ParameterBagInterface $parameterBag,
        private ContainerInterface $container,
        private EventDispatcherInterface $eventDispatcher,
        private SecurityContext $securityContext,
        private TranslatorInterface $translator,
        private RequestStack $requestStack,
        private Security $security,
        private JWTTokenManagerInterface $jwtManager,
        private HttpKernelInterface $httpKernel,
        private CacheInterface $cache,
    ) {
    }

    /**
     * @throws \JsonException
     * @throws InvalidArgumentException
     */
    public function resources(string $path, array $params = [], bool $useCache = true): array
    {
        $cacheKey = md5($path.http_build_query($params));

        /**
         * @throws \JsonException
         * @throws \Exception
         */
        $fetchData = function () use ($path, $params) {
            $queryString = http_build_query($params);
            $fullUrl = $path.'?'.$queryString;

            $session = $this->requestStack->getSession();
            $request = Request::create($fullUrl);
            $request->setSession($session);
            $request->headers->set('Accept', 'application/json');

            $user = $this->security->getUser();
            if ($user) {
                $jwtToken = $this->jwtManager->create($user);
                $request->headers->set('Authorization', 'Bearer '.$jwtToken);
            }

            $response = $this->httpKernel->handle($request, HttpKernelInterface::SUB_REQUEST);
            if ($response->isSuccessful()) {
                $datas = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

                return $this->extractHydraMembers($datas);
            }

            throw new NotFoundHttpException('API route not found or error occurred');
        };

        if ($useCache) {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($fetchData) {
                $item->expiresAfter(3600);

                return $fetchData();
            });
        }

        return $fetchData();
    }

    private function extractHydraMembers(array $data): array
    {
        return $data['hydra:member'] ?? [];
    }

    /** @deprecated use new data access layer */
    public function loop(string $loopName, array $params = []): mixed
    {
        $loopNamespace = 'Thelia\\Core\\Template\\Loop\\';
        $className = ucfirst(strtolower($loopName));
        if (!class_exists($loopNamespace.$className)) {
            throw new \RuntimeException('Loop '.$className.' not found');
        }
        $fullClassName = $loopNamespace.$className;

        /** @var BaseLoop $instance */
        $instance = new $fullClassName();

        $instance->init(
            $this->container,
            $this->requestStack,
            $this->eventDispatcher,
            $this->securityContext,
            $this->translator,
            $this->parameterBag->get('Thelia.parser.loops'),
            $this->parameterBag->get('kernel.environment')
        );
        $instance->initializeArgs($params);
        $loopResults = $instance->exec($pagination);

        $datas = [];
        $count = 0;
        for ($loopResults->rewind(); $loopResults->valid(); $loopResults->next()) {
            $loopResult = $loopResults->current();

            foreach ($loopResult->getVars() as $key) {
                $datas[$count][$key] = $loopResult->get($key);
            }
            ++$count;
        }

        return $datas;
    }
}
