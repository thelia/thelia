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

namespace Thelia\Core\Template\Element;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\Element\Exception\LoopException;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Type\EnumListType;
use Thelia\Type\EnumType;
use Thelia\Type\TypeCollection;

/**
 *
 * Class BaseLoop
 * @package TThelia\Core\Template\Element
 */
abstract class BaseLoop
{
    /**
     * @var \Symfony\Component\HttpFoundation\Request
     */
    protected $request;

    /**
     * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * @var SecurityContext
     */
    protected $securityContext;

    /** @var ContainerInterface Service Container */
    protected $container = null;

    protected $args;

    protected $countable = true;
    protected $timestampable = false;
    protected $versionable = false;

    private static $cacheLoopResult = array();
    private static $cacheCount = array();

    /**
     * Create a new Loop
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->checkInterface();

        $this->container = $container;

        $this->request = $container->get('request');
        $this->dispatcher = $container->get('event_dispatcher');
        $this->securityContext = $container->get('thelia.securityContext');

        $this->args = $this->getArgDefinitions()->addArguments($this->getDefaultArgs(), false);
    }

    /**
     * Define common loop arguments
     *
     * @return Argument[]
     */
    protected function getDefaultArgs()
    {
        $defaultArgs = array(
            Argument::createBooleanTypeArgument('backend_context', false),
            Argument::createBooleanTypeArgument('force_return', false),
            Argument::createAnyTypeArgument('type'),
        );

        if (true === $this->countable) {
            $defaultArgs = array_merge($defaultArgs, array(
                Argument::createIntTypeArgument('offset', 0),
                Argument::createIntTypeArgument('page'),
                Argument::createIntTypeArgument('limit', PHP_INT_MAX),
            ));
        }

        if ($this instanceof SearchLoopInterface) {
            $defaultArgs = array_merge($defaultArgs, array(
                Argument::createAnyTypeArgument('search_term'),
                new Argument(
                    'search_in',
                    new TypeCollection(
                        new EnumListType($this->getSearchIn())
                    )
                ),
                new Argument(
                    'search_mode',
                    new TypeCollection(
                        new EnumType(array(
                            SearchLoopInterface::MODE_ANY_WORD,
                            SearchLoopInterface::MODE_SENTENCE,
                            SearchLoopInterface::MODE_STRICT_SENTENCE,
                        ))
                    ),
                    SearchLoopInterface::MODE_STRICT_SENTENCE
                )
            ));
        }

        return $defaultArgs;
    }

    /**
     * Provides a getter to loop parameters
     *
     * @param string $name the methode name (only getArgname is supported)
     * @param $arguments this parameter is ignored
     *
     * @return null
     * @throws \InvalidArgumentException if the parameter is unknown or the method name is not supported.
     */
    public function __call($name, $arguments)
    {
        if (substr($name, 0, 3) == 'get') {

            // camelCase to underscore: getNotEmpty -> not_empty
            $argName = strtolower(preg_replace('/([^A-Z])([A-Z])/', "$1_$2", substr($name, 3)));

            return $this->getArg($argName)->getValue();
        }

        throw new \InvalidArgumentException(sprintf("Unsupported magic method %s. only getArgname() is supported.", $name));
    }

    /**
     * Initialize the loop arguments.
     *
     * @param array $nameValuePairs a array of name => value pairs. The name is the name of the argument.
     *
     * @throws \InvalidArgumentException if somùe argument values are missing, or invalid
     */
    public function initializeArgs(array $nameValuePairs)
    {
        $faultActor = array();
        $faultDetails = array();

        $loopType = isset($nameValuePairs['type']) ? $nameValuePairs['type'] : "undefined";
        $loopName = isset($nameValuePairs['name']) ? $nameValuePairs['name'] : "undefined";

        $this->args->rewind();
        while (($argument = $this->args->current()) !== false) {
            $this->args->next();

            $value = isset($nameValuePairs[$argument->name]) ? $nameValuePairs[$argument->name] : null;

            /* check if mandatory */
            if ($value === null && $argument->mandatory) {
                $faultActor[] = $argument->name;
                $faultDetails[] = sprintf('"%s" parameter is missing in loop type: %s, name: %s', $argument->name, $loopType, $loopName);
            } else  if ($value === '') {
                if (!$argument->empty) {
                    /* check if empty */
                    $faultActor[] = $argument->name;
                    $faultDetails[] = sprintf('"%s" parameter cannot be empty in loop type: %s, name: %s', $argument->name, $loopType, $loopName);
                }
            } elseif ($value !== null && !$argument->type->isValid($value)) {
                /* check type */
                $faultActor[] = $argument->name;
                $faultDetails[] = sprintf('Invalid value "%s" for "%s" argument in loop type: %s, name: %s', $value, $argument->name, $loopType, $loopName);
            } else {
                /* set default */
                /* did it as last checking for we consider default value is acceptable no matter type or empty restriction */
                if ($value === null) {
                    $value = $argument->default;
                }

                $argument->setValue($value);
            }
        }

        if (!empty($faultActor)) {

            $complement = sprintf('[%s]', implode(', ', $faultDetails));
            throw new \InvalidArgumentException($complement);
        }
    }

    /**
     * Return a loop argument
     *
     * @param string $argumentName the argument name
     *
     * @throws \InvalidArgumentException if argument is not found in loop argument list
     * @return Argument                  the loop argument.
     */
    protected function getArg($argumentName)
    {
        $arg = $this->args->get($argumentName);

        if ($arg === null)
            throw new \InvalidArgumentException("Undefined loop argument '$argumentName'");

        return $arg;
    }

    /**
     * Return a loop argument value
     *
     * @param string $argumentName the argument name
     *
     * @throws \InvalidArgumentException if argument is not found in loop argument list
     * @return Argument                  the loop argument.
     */
    protected function getArgValue($argumentName)
    {
        return $this->getArg($argumentName)->getValue();
    }

    /**
     * @param ModelCriteria $search
     * @param null          $pagination
     *
     * @return array|mixed|\PropelModelPager|\PropelObjectCollection
     */
    protected function search(ModelCriteria $search, &$pagination = null)
    {
        if (false === $this->countable) {
            return $search->find();
        }
        if ($this instanceof SearchLoopInterface) {
            $searchTerm = $this->getSearch_term();
            $searchIn = $this->getSearch_in();
            $searchMode = $this->getSearch_mode();
            if (null !== $searchTerm && null !== $searchIn) {

                switch ($searchMode) {
                    case SearchLoopInterface::MODE_ANY_WORD:
                        $searchCriteria = Criteria::IN;
                        $searchTerm = explode(' ', $searchTerm);
                        break;
                    case SearchLoopInterface::MODE_SENTENCE:
                        $searchCriteria = Criteria::LIKE;
                        $searchTerm = '%' . $searchTerm . '%';
                        break;
                    case SearchLoopInterface::MODE_STRICT_SENTENCE:
                        $searchCriteria = Criteria::EQUAL;
                        break;
                }

                $this->doSearch($search, $searchTerm, $searchIn, $searchCriteria);
            }
        }

        if ($this->getArgValue('page') !== null) {
            return $this->searchWithPagination($search, $pagination);
        } else {
            return $this->searchWithOffset($search);
        }
    }

    protected function searchArray(array $search, &$pagination = null)
    {
        if (false === $this->countable) {
            return $search;
        }
        if ($this->getArgValue('page') !== null) {

            $nbPage = ceil(count($search)/$this->getArgValue('limit'));
            if ($this->getArgValue('page') > $nbPage || $this->getArgValue('page') <= 0) {
                return array();
            }

            $firstItem = ($this->getArgValue('page')-1) * $this->getArgValue('limit') + 1;

            return array_slice($search, $firstItem, $firstItem + $this->getArgValue('limit'), false);

        } else {
            return array_slice($search, $this->getArgValue('offset'), $this->getArgValue('limit'), false);

        }
    }

    /**
     * @param ModelCriteria $search
     *
     * @return array|mixed|\PropelObjectCollection
     */
    protected function searchWithOffset(ModelCriteria $search)
    {
        if ($this->getArgValue('limit') >= 0) {
            $search->limit($this->getArgValue('limit'));
        }
        $search->offset($this->getArgValue('offset'));

        return $search->find();
    }

    /**
     * @param ModelCriteria $search
     * @param               $pagination
     *
     * @return array|\Propel\Runtime\Util\PropelModelPager
     */
    protected function searchWithPagination(ModelCriteria $search, &$pagination)
    {
        $pagination = $search->paginate($this->getArgValue('page'), $this->getArgValue('limit'));

        if ($this->getArgValue('page') > $pagination->getLastPage()) {
            return array();
        } else {
            return $pagination;
        }
    }

    public function count()
    {
        $hash = $this->args->getHash();
        if (false === isset(self::$cacheCount[$hash])) {
            $count = 0;
            if ($this instanceof PropelSearchLoopInterface) {
                $searchModelCriteria = $this->buildModelCriteria();
                if (null === $searchModelCriteria) {
                    $count = 0;
                } else {
                    $count = $searchModelCriteria->count();
                }
            } elseif ($this instanceof ArraySearchLoopInterface) {
                $searchArray = $this->buildArray();
                if (null === $searchArray) {
                    $count = 0;
                } else {
                    $count = count($searchArray);
                }
            }
            self::$cacheCount[$hash] = $count;
        }

        return self::$cacheCount[$hash];
    }

    /**
     * @param $pagination
     * @return LoopResult
     */
    public function exec(&$pagination)
    {
        $hash = $this->args->getHash();
        if (false === isset(self::$cacheLoopResult[$hash])) {
            if ($this instanceof PropelSearchLoopInterface) {
                $searchModelCriteria = $this->buildModelCriteria();
                if (null === $searchModelCriteria) {
                    $results = array();
                } else {
                    $results = $this->search(
                        $searchModelCriteria,
                        $pagination
                    );
                }
            } elseif ($this instanceof ArraySearchLoopInterface) {
                $searchArray = $this->buildArray();
                if (null === $searchArray) {
                    $results = array();
                } else {
                    $results = $this->searchArray(
                        $searchArray,
                        $pagination
                    );
                }
            }

            $loopResult = new LoopResult($results);

            if (true === $this->countable) {
                $loopResult->setCountable();
            }
            if (true === $this->timestampable) {
                $loopResult->setTimestamped();
            }
            if (true === $this->versionable) {
                $loopResult->setVersioned();
            }

            self::$cacheLoopResult[$hash] = $this->parseResults($loopResult);
        }

        return self::$cacheLoopResult[$hash];

    }

    protected function checkInterface()
    {
        /* Must implement either :
         *  - PropelSearchLoopInterface
         *  - ArraySearchLoopInterface
        */
        $searchInterface = false;
        if ($this instanceof PropelSearchLoopInterface) {
            if (true === $searchInterface) {
                throw new LoopException('Loop cannot implements multiple Search Interfaces : `PropelSearchLoopInterface`, `ArraySearchLoopInterface`', LoopException::MULTIPLE_SEARCH_INTERFACE);
            }
            $searchInterface = true;
        }
        if ($this instanceof ArraySearchLoopInterface) {
            if (true === $searchInterface) {
                throw new LoopException('Loop cannot implements multiple Search Interfaces : `PropelSearchLoopInterface`, `ArraySearchLoopInterface`', LoopException::MULTIPLE_SEARCH_INTERFACE);
            }
            $searchInterface = true;
        }

        if (false === $searchInterface) {
            throw new LoopException('Loop must implements one of the following interfaces : `PropelSearchLoopInterface`, `ArraySearchLoopInterface`', LoopException::SEARCH_INTERFACE_NOT_FOUND);
        }

        /* Only PropelSearch allows timestamp and version */
        if (!$this instanceof PropelSearchLoopInterface) {
            if (true === $this->timestampable) {
                throw new LoopException("Loop must implements 'PropelSearchLoopInterface' to be timestampable", LoopException::NOT_TIMESTAMPED);
            }
            if (true === $this->versionable) {
                throw new LoopException("Loop must implements 'PropelSearchLoopInterface' to be versionable", LoopException::NOT_VERSIONED);
            }
        }
    }

    /**
     * @param LoopResult $loopResult
     *
     * @return LoopResult
     */
    abstract public function parseResults(LoopResult $loopResult);

    /**
     *
     * define all args used in your loop
     *
     *
     * example :
     *
     * public function getArgDefinitions()
     * {
     *  return new ArgumentCollection(
     *       Argument::createIntListTypeArgument('id'),
     *           new Argument(
     *           'ref',
     *           new TypeCollection(
     *               new Type\AlphaNumStringListType()
     *           )
     *       ),
     *       Argument::createIntListTypeArgument('category'),
     *       Argument::createBooleanTypeArgument('new'),
     *       Argument::createBooleanTypeArgument('promo'),
     *       Argument::createFloatTypeArgument('min_price'),
     *       Argument::createFloatTypeArgument('max_price'),
     *       Argument::createIntTypeArgument('min_stock'),
     *       Argument::createFloatTypeArgument('min_weight'),
     *       Argument::createFloatTypeArgument('max_weight'),
     *       Argument::createBooleanTypeArgument('current'),
     *
     *   );
     * }
     *
     * @return \Thelia\Core\Template\Loop\Argument\ArgumentCollection
     */
    abstract protected function getArgDefinitions();

}
