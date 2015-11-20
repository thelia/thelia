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
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Util\PropelModelPager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\Element\Exception\LoopException;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Translation\Translator;
use Thelia\Type\EnumListType;
use Thelia\Type\EnumType;
use Thelia\Type\TypeCollection;

/**
 *
 * Class BaseLoop
 * @package TThelia\Core\Template\Element
 *
 * @method string getType()
 * @method bool getForceReturn()
 * @method bool getBackendContext()
 * @method int getOffset() available if countable is true
 * @method int getPage() available if countable is true
 * @method int getLimit() available if countable is true
 */
abstract class BaseLoop
{
    /** @var String|null The loop name  */
    protected $loopName = null;

    /** @var String|null The loop name  */
    protected static $loopDefinitions = [];

    /**
     * @var \Thelia\Core\HttpFoundation\Request
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

    /** @var ArgumentCollection */
    protected $args;

    protected $countable = true;
    protected $timestampable = false;
    protected $versionable = false;

    /** @var Translator  */
    protected $translator = null;

    private static $cacheLoopResult = [];
    private static $cacheCount = [];

    /**
     * Create a new Loop
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        $this->translator = $container->get("thelia.translator");

        $this->checkInterface();

        $this->request = $container->get('request');
        $this->dispatcher = $container->get('event_dispatcher');
        $this->securityContext = $container->get('thelia.securityContext');

        $this->initialize();
    }

    /**
     * Initialize the loop
     *
     * First it will get the loop name according to the loop class.
     * Then argument definitions is initialized
     *
     */
    protected function initialize()
    {
        if (0 === count(self::$loopDefinitions)) {
            self::$loopDefinitions = array_flip($this->container->getParameter('thelia.parser.loops'));
        }

        if (array_key_exists(get_class($this), self::$loopDefinitions)) {
            $this->loopName = self::$loopDefinitions[get_class($this)];
        }

        $this->args = $this->getArgDefinitions()->addArguments($this->getDefaultArgs(), false);
    }

    /**
     * Define common loop arguments
     *
     * @return Argument[]
     */
    protected function getDefaultArgs()
    {
        $defaultArgs = [
            Argument::createBooleanTypeArgument('backend_context', false),
            Argument::createBooleanTypeArgument('force_return', false),
            Argument::createAnyTypeArgument('type'),
            Argument::createBooleanTypeArgument('no-cache', false)
        ];

        if (true === $this->countable) {
            $defaultArgs = array_merge(
                $defaultArgs,
                [
                    Argument::createIntTypeArgument('offset', 0),
                    Argument::createIntTypeArgument('page'),
                    Argument::createIntTypeArgument('limit', PHP_INT_MAX),
                ]
            );
        }

        if ($this instanceof SearchLoopInterface) {
            $defaultArgs = array_merge(
                $defaultArgs,
                [
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
                            new EnumType(
                                [
                                    SearchLoopInterface::MODE_ANY_WORD,
                                    SearchLoopInterface::MODE_SENTENCE,
                                    SearchLoopInterface::MODE_STRICT_SENTENCE,
                                ]
                            )
                        ),
                        SearchLoopInterface::MODE_STRICT_SENTENCE
                    )
                ]
            );
        }

        return $defaultArgs;
    }

    /**
     * Provides a getter to loop parameter values
     *
     * @param string $name      the method name (only getArgname is supported)
     * @param mixed  $arguments this parameter is ignored
     *
     * @return mixed                     the argument value
     * @throws \InvalidArgumentException if the parameter is unknown or the method name is not supported.
     */
    public function __call($name, $arguments)
    {
        if (substr($name, 0, 3) == 'get') {
            // camelCase to underscore: getNotEmpty -> not_empty
            $argName = strtolower(preg_replace('/([^A-Z])([A-Z])/', "$1_$2", substr($name, 3)));

            return $this->getArgValue($argName);
        }

        throw new \InvalidArgumentException(
            $this->translator->trans("Unsupported magic method %name. only getArgname() is supported.", ['%name' => $name])
        );
    }

    /**
     * Initialize the loop arguments.
     *
     * @param array $nameValuePairs a array of name => value pairs. The name is the name of the argument.
     *
     * @throws \InvalidArgumentException if some argument values are missing, or invalid
     */
    public function initializeArgs(array $nameValuePairs)
    {
        $faultActor = [];
        $faultDetails = [];

        $loopType = isset($nameValuePairs['type']) ? $nameValuePairs['type'] : "undefined";
        $loopName = isset($nameValuePairs['name']) ? $nameValuePairs['name'] : "undefined";

        $this->args->rewind();
        while (($argument = $this->args->current()) !== false) {
            $this->args->next();

            $value = isset($nameValuePairs[$argument->name]) ? $nameValuePairs[$argument->name] : null;

            /* check if mandatory */
            if ($value === null && $argument->mandatory) {
                $faultActor[] = $argument->name;
                $faultDetails[] = $this->translator->trans(
                    '"%param" parameter is missing in loop type: %type, name: %name',
                    [
                        '%param' => $argument->name,
                        '%type' => $loopType,
                        '%name' => $loopName
                    ]
                );
            } elseif ($value === '') {
                if (!$argument->empty) {
                    /* check if empty */
                    $faultActor[] = $argument->name;
                    $faultDetails[] = $this->translator->trans(
                        '"%param" parameter cannot be empty in loop type: %type, name: %name',
                        [
                            '%param' => $argument->name,
                            '%type' => $loopType,
                            '%name' => $loopName
                        ]
                    );
                }
            } elseif ($value !== null && !$argument->type->isValid($value)) {
                /* check type */
                $faultActor[] = $argument->name;
                $faultDetails[] = $this->translator->trans(
                    'Invalid value "%value" for "%param" parameter in loop type: %type, name: %name',
                    [
                        '%value' => $value,
                        '%param' => $argument->name,
                        '%type' => $loopType,
                        '%name' => $loopName
                    ]
                );
            } else {
                /* set default value */
                /* did it as last checking for we consider default value is acceptable no matter type or empty restriction */
                if ($value === null) {
                    $value = $argument->default;
                }

                $argument->setValue($value);
            }
        }

        if (! empty($faultActor)) {
            $complement = sprintf('[%s]', implode(', ', $faultDetails));

            throw new \InvalidArgumentException($complement);
        }
    }

    /**
     * Return a loop argument
     *
     * @param string $argumentName the argument name
     *
     * @return Argument                  the loop argument.
     * @throws \InvalidArgumentException if argument is not found in loop argument list
     */
    protected function getArg($argumentName)
    {
        $arg = $this->args->get($argumentName);

        if ($arg === null) {
            throw new \InvalidArgumentException(
                $this->translator->trans('Undefined loop argument "%name"', ['%name' => $argumentName])
            );
        }

        return $arg;
    }

    /**
     * Return a loop argument value
     *
     * @param string $argumentName the argument name
     *
     * @throws \InvalidArgumentException if argument is not found in loop argument list
     * @return mixed                     the loop argument value
     */
    protected function getArgValue($argumentName)
    {
        return $this->getArg($argumentName)->getValue();
    }

    /**
     * @param ModelCriteria    $search     the search request
     * @param PropelModelPager $pagination the pagination part
     *
     * @return array|PropelModelPager|ObjectCollection
     * @throws \InvalidArgumentException               if the search mode is undefined.
     */
    protected function search(ModelCriteria $search, &$pagination = null)
    {
        if (false === $this->countable) {
            return $search->find();
        }

        if ($this instanceof SearchLoopInterface) {
            $searchTerm = $this->getArgValue('search_term');
            $searchIn   = $this->getArgValue('search_in');
            $searchMode = $this->getArgValue('search_mode');

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
                    default:
                        throw new \InvalidArgumentException(
                            $this->translator->trans("Undefined search mode '%mode'", ['%mode' => $searchMode])
                        );
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

    protected function searchArray(array $search)
    {
        if (false === $this->countable) {
            return $search;
        }

        $limit  = intval($this->getArgValue('limit'));
        $offset = intval($this->getArgValue('offset'));

        if ($this->getArgValue('page') !== null) {
            $pageNum = intval($this->getArgValue('page'));

            $totalPageCount = ceil(count($search) / $limit);

            if ($pageNum > $totalPageCount || $pageNum <= 0) {
                return [];
            }

            $firstItem = ($pageNum - 1) * $limit + 1;

            return array_slice($search, $firstItem, $firstItem + $limit, false);
        } else {
            return array_slice($search, $offset, $limit, false);
        }
    }

    /**
     * @param ModelCriteria $search
     *
     * @return ObjectCollection
     */
    protected function searchWithOffset(ModelCriteria $search)
    {
        $limit = intval($this->getArgValue('limit'));

        if ($limit >= 0) {
            $search->limit($limit);
        }

        $search->offset(intval($this->getArgValue('offset')));

        return $search->find();
    }

    /**
     * @param ModelCriteria    $search
     * @param PropelModelPager $pagination
     *
     * @return array|PropelModelPager
     */
    protected function searchWithPagination(ModelCriteria $search, &$pagination)
    {
        $page  = intval($this->getArgValue('page'));
        $limit = intval($this->getArgValue('limit'));

        $pagination = $search->paginate($page, $limit);

        if ($page > $pagination->getLastPage()) {
            return [];
        } else {
            return $pagination;
        }
    }

    public function count()
    {
        $hash = $this->args->getHash();

        if (($isCaching = $this->isCaching()) && isset(self::$cacheCount[$hash])) {
            return self::$cacheCount[$hash];
        }

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

        if ($isCaching) {
            self::$cacheCount[$hash] = $count;
        }

        return $count;
    }

    /**
     * @param PropelModelPager $pagination
     *
     * @return LoopResult
     */
    public function exec(&$pagination)
    {
        $hash = $this->args->getHash();

        if (($isCaching = $this->isCaching()) && isset(self::$cacheLoopResult[$hash])) {
            return self::$cacheLoopResult[$hash];
        }

        $results = [];

        if ($this instanceof PropelSearchLoopInterface) {
            $searchModelCriteria = $this->buildModelCriteria();

            if (null !== $searchModelCriteria) {
                $results = $this->search(
                    $searchModelCriteria,
                    $pagination
                );
            }
        } elseif ($this instanceof ArraySearchLoopInterface) {
            $searchArray = $this->buildArray();

            if (null !== $searchArray) {
                $results = $this->searchArray($searchArray);
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

        $parsedResults = $this->parseResults($loopResult);

        if ($isCaching) {
            self::$cacheLoopResult[$hash] = $parsedResults;
        }

        return $parsedResults;
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
                throw new LoopException(
                    $this->translator->trans(
                        'Loop cannot implements multiple Search Interfaces : `PropelSearchLoopInterface`, `ArraySearchLoopInterface`'
                    ),
                    LoopException::MULTIPLE_SEARCH_INTERFACE
                );
            }
            $searchInterface = true;
        }

        if ($this instanceof ArraySearchLoopInterface) {
            if (true === $searchInterface) {
                throw new LoopException(
                    $this->translator->trans(
                        'Loop cannot implements multiple Search Interfaces : `PropelSearchLoopInterface`, `ArraySearchLoopInterface`'
                    ),
                    LoopException::MULTIPLE_SEARCH_INTERFACE
                );
            }
            $searchInterface = true;
        }

        if (false === $searchInterface) {
            throw new LoopException(
                $this->translator->trans(
                    'Loop must implements one of the following interfaces : `PropelSearchLoopInterface`, `ArraySearchLoopInterface`'
                ),
                LoopException::SEARCH_INTERFACE_NOT_FOUND
            );
        }

        /* Only PropelSearch allows timestamp and version */
        if (!$this instanceof PropelSearchLoopInterface) {
            if (true === $this->timestampable) {
                throw new LoopException(
                    $this->translator->trans("Loop must implements 'PropelSearchLoopInterface' to be timestampable"),
                    LoopException::NOT_TIMESTAMPED
                );
            }

            if (true === $this->versionable) {
                throw new LoopException(
                    $this->translator->trans("Loop must implements 'PropelSearchLoopInterface' to be versionable"),
                    LoopException::NOT_VERSIONED
                );
            }
        }
    }

    protected function isCaching()
    {
        return !$this->getArg("no-cache")->getValue();
    }

    /**
     * @param LoopResult $loopResult
     *
     * @return LoopResult
     */
    abstract public function parseResults(LoopResult $loopResult);

    /**
     * Definition of loop arguments
     *
     * example :
     *
     * public function getArgDefinitions()
     * {
     *  return new ArgumentCollection(
     *
     *       Argument::createIntListTypeArgument('id'),
     *           new Argument(
     *           'ref',
     *           new TypeCollection(
     *               new Type\AlphaNumStringListType()
     *           )
     *       ),
     *       Argument::createIntListTypeArgument('category'),
     *       Argument::createBooleanTypeArgument('new'),
     *       ...
     *   );
     * }
     *
     * @return \Thelia\Core\Template\Loop\Argument\ArgumentCollection
     */
    abstract protected function getArgDefinitions();

    /**
     * Use this method in order to add fields in sub-classes
     * @param LoopResultRow $loopResultRow
     * @param object|array $item
     *
     */
    protected function addOutputFields(LoopResultRow $loopResultRow, $item)
    {
    }

    /**
     * @return null|String
     */
    public function getLoopName()
    {
        return $this->loopName;
    }

    /**
     * @param null|String $loopName
     */
    public function setLoopName($loopName)
    {
        $this->loopName = $loopName;
        return $this;
    }
}
