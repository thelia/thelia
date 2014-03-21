<?php

namespace Thelia\Model\Base;

use \Exception;
use \PDO;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveQuery\ModelJoin;
use Propel\Runtime\Collection\Collection;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Thelia\Model\Message as ChildMessage;
use Thelia\Model\MessageI18nQuery as ChildMessageI18nQuery;
use Thelia\Model\MessageQuery as ChildMessageQuery;
use Thelia\Model\Map\MessageTableMap;

/**
 * Base class that represents a query for the 'message' table.
 *
 *
 *
 * @method     ChildMessageQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildMessageQuery orderByName($order = Criteria::ASC) Order by the name column
 * @method     ChildMessageQuery orderBySecured($order = Criteria::ASC) Order by the secured column
 * @method     ChildMessageQuery orderByTextLayoutFileName($order = Criteria::ASC) Order by the text_layout_file_name column
 * @method     ChildMessageQuery orderByTextTemplateFileName($order = Criteria::ASC) Order by the text_template_file_name column
 * @method     ChildMessageQuery orderByHtmlLayoutFileName($order = Criteria::ASC) Order by the html_layout_file_name column
 * @method     ChildMessageQuery orderByHtmlTemplateFileName($order = Criteria::ASC) Order by the html_template_file_name column
 * @method     ChildMessageQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method     ChildMessageQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 * @method     ChildMessageQuery orderByVersion($order = Criteria::ASC) Order by the version column
 * @method     ChildMessageQuery orderByVersionCreatedAt($order = Criteria::ASC) Order by the version_created_at column
 * @method     ChildMessageQuery orderByVersionCreatedBy($order = Criteria::ASC) Order by the version_created_by column
 *
 * @method     ChildMessageQuery groupById() Group by the id column
 * @method     ChildMessageQuery groupByName() Group by the name column
 * @method     ChildMessageQuery groupBySecured() Group by the secured column
 * @method     ChildMessageQuery groupByTextLayoutFileName() Group by the text_layout_file_name column
 * @method     ChildMessageQuery groupByTextTemplateFileName() Group by the text_template_file_name column
 * @method     ChildMessageQuery groupByHtmlLayoutFileName() Group by the html_layout_file_name column
 * @method     ChildMessageQuery groupByHtmlTemplateFileName() Group by the html_template_file_name column
 * @method     ChildMessageQuery groupByCreatedAt() Group by the created_at column
 * @method     ChildMessageQuery groupByUpdatedAt() Group by the updated_at column
 * @method     ChildMessageQuery groupByVersion() Group by the version column
 * @method     ChildMessageQuery groupByVersionCreatedAt() Group by the version_created_at column
 * @method     ChildMessageQuery groupByVersionCreatedBy() Group by the version_created_by column
 *
 * @method     ChildMessageQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildMessageQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildMessageQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildMessageQuery leftJoinMessageI18n($relationAlias = null) Adds a LEFT JOIN clause to the query using the MessageI18n relation
 * @method     ChildMessageQuery rightJoinMessageI18n($relationAlias = null) Adds a RIGHT JOIN clause to the query using the MessageI18n relation
 * @method     ChildMessageQuery innerJoinMessageI18n($relationAlias = null) Adds a INNER JOIN clause to the query using the MessageI18n relation
 *
 * @method     ChildMessageQuery leftJoinMessageVersion($relationAlias = null) Adds a LEFT JOIN clause to the query using the MessageVersion relation
 * @method     ChildMessageQuery rightJoinMessageVersion($relationAlias = null) Adds a RIGHT JOIN clause to the query using the MessageVersion relation
 * @method     ChildMessageQuery innerJoinMessageVersion($relationAlias = null) Adds a INNER JOIN clause to the query using the MessageVersion relation
 *
 * @method     ChildMessage findOne(ConnectionInterface $con = null) Return the first ChildMessage matching the query
 * @method     ChildMessage findOneOrCreate(ConnectionInterface $con = null) Return the first ChildMessage matching the query, or a new ChildMessage object populated from the query conditions when no match is found
 *
 * @method     ChildMessage findOneById(int $id) Return the first ChildMessage filtered by the id column
 * @method     ChildMessage findOneByName(string $name) Return the first ChildMessage filtered by the name column
 * @method     ChildMessage findOneBySecured(int $secured) Return the first ChildMessage filtered by the secured column
 * @method     ChildMessage findOneByTextLayoutFileName(string $text_layout_file_name) Return the first ChildMessage filtered by the text_layout_file_name column
 * @method     ChildMessage findOneByTextTemplateFileName(string $text_template_file_name) Return the first ChildMessage filtered by the text_template_file_name column
 * @method     ChildMessage findOneByHtmlLayoutFileName(string $html_layout_file_name) Return the first ChildMessage filtered by the html_layout_file_name column
 * @method     ChildMessage findOneByHtmlTemplateFileName(string $html_template_file_name) Return the first ChildMessage filtered by the html_template_file_name column
 * @method     ChildMessage findOneByCreatedAt(string $created_at) Return the first ChildMessage filtered by the created_at column
 * @method     ChildMessage findOneByUpdatedAt(string $updated_at) Return the first ChildMessage filtered by the updated_at column
 * @method     ChildMessage findOneByVersion(int $version) Return the first ChildMessage filtered by the version column
 * @method     ChildMessage findOneByVersionCreatedAt(string $version_created_at) Return the first ChildMessage filtered by the version_created_at column
 * @method     ChildMessage findOneByVersionCreatedBy(string $version_created_by) Return the first ChildMessage filtered by the version_created_by column
 *
 * @method     array findById(int $id) Return ChildMessage objects filtered by the id column
 * @method     array findByName(string $name) Return ChildMessage objects filtered by the name column
 * @method     array findBySecured(int $secured) Return ChildMessage objects filtered by the secured column
 * @method     array findByTextLayoutFileName(string $text_layout_file_name) Return ChildMessage objects filtered by the text_layout_file_name column
 * @method     array findByTextTemplateFileName(string $text_template_file_name) Return ChildMessage objects filtered by the text_template_file_name column
 * @method     array findByHtmlLayoutFileName(string $html_layout_file_name) Return ChildMessage objects filtered by the html_layout_file_name column
 * @method     array findByHtmlTemplateFileName(string $html_template_file_name) Return ChildMessage objects filtered by the html_template_file_name column
 * @method     array findByCreatedAt(string $created_at) Return ChildMessage objects filtered by the created_at column
 * @method     array findByUpdatedAt(string $updated_at) Return ChildMessage objects filtered by the updated_at column
 * @method     array findByVersion(int $version) Return ChildMessage objects filtered by the version column
 * @method     array findByVersionCreatedAt(string $version_created_at) Return ChildMessage objects filtered by the version_created_at column
 * @method     array findByVersionCreatedBy(string $version_created_by) Return ChildMessage objects filtered by the version_created_by column
 *
 */
abstract class MessageQuery extends ModelCriteria
{

    // versionable behavior

    /**
     * Whether the versioning is enabled
     */
    static $isVersioningEnabled = true;

    /**
     * Initializes internal state of \Thelia\Model\Base\MessageQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\Thelia\\Model\\Message', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildMessageQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildMessageQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \Thelia\Model\MessageQuery) {
            return $criteria;
        }
        $query = new \Thelia\Model\MessageQuery();
        if (null !== $modelAlias) {
            $query->setModelAlias($modelAlias);
        }
        if ($criteria instanceof Criteria) {
            $query->mergeWith($criteria);
        }

        return $query;
    }

    /**
     * Find object by primary key.
     * Propel uses the instance pool to skip the database if the object exists.
     * Go fast if the query is untouched.
     *
     * <code>
     * $obj  = $c->findPk(12, $con);
     * </code>
     *
     * @param mixed $key Primary key to use for the query
     * @param ConnectionInterface $con an optional connection object
     *
     * @return ChildMessage|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = MessageTableMap::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(MessageTableMap::DATABASE_NAME);
        }
        $this->basePreSelect($con);
        if ($this->formatter || $this->modelAlias || $this->with || $this->select
         || $this->selectColumns || $this->asColumns || $this->selectModifiers
         || $this->map || $this->having || $this->joins) {
            return $this->findPkComplex($key, $con);
        } else {
            return $this->findPkSimple($key, $con);
        }
    }

    /**
     * Find object by primary key using raw SQL to go fast.
     * Bypass doSelect() and the object formatter by using generated code.
     *
     * @param     mixed $key Primary key to use for the query
     * @param     ConnectionInterface $con A connection object
     *
     * @return   ChildMessage A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `NAME`, `SECURED`, `TEXT_LAYOUT_FILE_NAME`, `TEXT_TEMPLATE_FILE_NAME`, `HTML_LAYOUT_FILE_NAME`, `HTML_TEMPLATE_FILE_NAME`, `CREATED_AT`, `UPDATED_AT`, `VERSION`, `VERSION_CREATED_AT`, `VERSION_CREATED_BY` FROM `message` WHERE `ID` = :p0';
        try {
            $stmt = $con->prepare($sql);
            $stmt->bindValue(':p0', $key, PDO::PARAM_INT);
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute SELECT statement [%s]', $sql), 0, $e);
        }
        $obj = null;
        if ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
            $obj = new ChildMessage();
            $obj->hydrate($row);
            MessageTableMap::addInstanceToPool($obj, (string) $key);
        }
        $stmt->closeCursor();

        return $obj;
    }

    /**
     * Find object by primary key.
     *
     * @param     mixed $key Primary key to use for the query
     * @param     ConnectionInterface $con A connection object
     *
     * @return ChildMessage|array|mixed the result, formatted by the current formatter
     */
    protected function findPkComplex($key, $con)
    {
        // As the query uses a PK condition, no limit(1) is necessary.
        $criteria = $this->isKeepQuery() ? clone $this : $this;
        $dataFetcher = $criteria
            ->filterByPrimaryKey($key)
            ->doSelect($con);

        return $criteria->getFormatter()->init($criteria)->formatOne($dataFetcher);
    }

    /**
     * Find objects by primary key
     * <code>
     * $objs = $c->findPks(array(12, 56, 832), $con);
     * </code>
     * @param     array $keys Primary keys to use for the query
     * @param     ConnectionInterface $con an optional connection object
     *
     * @return ObjectCollection|array|mixed the list of results, formatted by the current formatter
     */
    public function findPks($keys, $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getReadConnection($this->getDbName());
        }
        $this->basePreSelect($con);
        $criteria = $this->isKeepQuery() ? clone $this : $this;
        $dataFetcher = $criteria
            ->filterByPrimaryKeys($keys)
            ->doSelect($con);

        return $criteria->getFormatter()->init($criteria)->format($dataFetcher);
    }

    /**
     * Filter the query by primary key
     *
     * @param     mixed $key Primary key to use for the query
     *
     * @return ChildMessageQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(MessageTableMap::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildMessageQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(MessageTableMap::ID, $keys, Criteria::IN);
    }

    /**
     * Filter the query on the id column
     *
     * Example usage:
     * <code>
     * $query->filterById(1234); // WHERE id = 1234
     * $query->filterById(array(12, 34)); // WHERE id IN (12, 34)
     * $query->filterById(array('min' => 12)); // WHERE id > 12
     * </code>
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildMessageQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(MessageTableMap::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(MessageTableMap::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(MessageTableMap::ID, $id, $comparison);
    }

    /**
     * Filter the query on the name column
     *
     * Example usage:
     * <code>
     * $query->filterByName('fooValue');   // WHERE name = 'fooValue'
     * $query->filterByName('%fooValue%'); // WHERE name LIKE '%fooValue%'
     * </code>
     *
     * @param     string $name The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildMessageQuery The current query, for fluid interface
     */
    public function filterByName($name = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($name)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $name)) {
                $name = str_replace('*', '%', $name);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(MessageTableMap::NAME, $name, $comparison);
    }

    /**
     * Filter the query on the secured column
     *
     * Example usage:
     * <code>
     * $query->filterBySecured(1234); // WHERE secured = 1234
     * $query->filterBySecured(array(12, 34)); // WHERE secured IN (12, 34)
     * $query->filterBySecured(array('min' => 12)); // WHERE secured > 12
     * </code>
     *
     * @param     mixed $secured The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildMessageQuery The current query, for fluid interface
     */
    public function filterBySecured($secured = null, $comparison = null)
    {
        if (is_array($secured)) {
            $useMinMax = false;
            if (isset($secured['min'])) {
                $this->addUsingAlias(MessageTableMap::SECURED, $secured['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($secured['max'])) {
                $this->addUsingAlias(MessageTableMap::SECURED, $secured['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(MessageTableMap::SECURED, $secured, $comparison);
    }

    /**
     * Filter the query on the text_layout_file_name column
     *
     * Example usage:
     * <code>
     * $query->filterByTextLayoutFileName('fooValue');   // WHERE text_layout_file_name = 'fooValue'
     * $query->filterByTextLayoutFileName('%fooValue%'); // WHERE text_layout_file_name LIKE '%fooValue%'
     * </code>
     *
     * @param     string $textLayoutFileName The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildMessageQuery The current query, for fluid interface
     */
    public function filterByTextLayoutFileName($textLayoutFileName = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($textLayoutFileName)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $textLayoutFileName)) {
                $textLayoutFileName = str_replace('*', '%', $textLayoutFileName);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(MessageTableMap::TEXT_LAYOUT_FILE_NAME, $textLayoutFileName, $comparison);
    }

    /**
     * Filter the query on the text_template_file_name column
     *
     * Example usage:
     * <code>
     * $query->filterByTextTemplateFileName('fooValue');   // WHERE text_template_file_name = 'fooValue'
     * $query->filterByTextTemplateFileName('%fooValue%'); // WHERE text_template_file_name LIKE '%fooValue%'
     * </code>
     *
     * @param     string $textTemplateFileName The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildMessageQuery The current query, for fluid interface
     */
    public function filterByTextTemplateFileName($textTemplateFileName = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($textTemplateFileName)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $textTemplateFileName)) {
                $textTemplateFileName = str_replace('*', '%', $textTemplateFileName);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(MessageTableMap::TEXT_TEMPLATE_FILE_NAME, $textTemplateFileName, $comparison);
    }

    /**
     * Filter the query on the html_layout_file_name column
     *
     * Example usage:
     * <code>
     * $query->filterByHtmlLayoutFileName('fooValue');   // WHERE html_layout_file_name = 'fooValue'
     * $query->filterByHtmlLayoutFileName('%fooValue%'); // WHERE html_layout_file_name LIKE '%fooValue%'
     * </code>
     *
     * @param     string $htmlLayoutFileName The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildMessageQuery The current query, for fluid interface
     */
    public function filterByHtmlLayoutFileName($htmlLayoutFileName = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($htmlLayoutFileName)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $htmlLayoutFileName)) {
                $htmlLayoutFileName = str_replace('*', '%', $htmlLayoutFileName);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(MessageTableMap::HTML_LAYOUT_FILE_NAME, $htmlLayoutFileName, $comparison);
    }

    /**
     * Filter the query on the html_template_file_name column
     *
     * Example usage:
     * <code>
     * $query->filterByHtmlTemplateFileName('fooValue');   // WHERE html_template_file_name = 'fooValue'
     * $query->filterByHtmlTemplateFileName('%fooValue%'); // WHERE html_template_file_name LIKE '%fooValue%'
     * </code>
     *
     * @param     string $htmlTemplateFileName The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildMessageQuery The current query, for fluid interface
     */
    public function filterByHtmlTemplateFileName($htmlTemplateFileName = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($htmlTemplateFileName)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $htmlTemplateFileName)) {
                $htmlTemplateFileName = str_replace('*', '%', $htmlTemplateFileName);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(MessageTableMap::HTML_TEMPLATE_FILE_NAME, $htmlTemplateFileName, $comparison);
    }

    /**
     * Filter the query on the created_at column
     *
     * Example usage:
     * <code>
     * $query->filterByCreatedAt('2011-03-14'); // WHERE created_at = '2011-03-14'
     * $query->filterByCreatedAt('now'); // WHERE created_at = '2011-03-14'
     * $query->filterByCreatedAt(array('max' => 'yesterday')); // WHERE created_at > '2011-03-13'
     * </code>
     *
     * @param     mixed $createdAt The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildMessageQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(MessageTableMap::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(MessageTableMap::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(MessageTableMap::CREATED_AT, $createdAt, $comparison);
    }

    /**
     * Filter the query on the updated_at column
     *
     * Example usage:
     * <code>
     * $query->filterByUpdatedAt('2011-03-14'); // WHERE updated_at = '2011-03-14'
     * $query->filterByUpdatedAt('now'); // WHERE updated_at = '2011-03-14'
     * $query->filterByUpdatedAt(array('max' => 'yesterday')); // WHERE updated_at > '2011-03-13'
     * </code>
     *
     * @param     mixed $updatedAt The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildMessageQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(MessageTableMap::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(MessageTableMap::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(MessageTableMap::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query on the version column
     *
     * Example usage:
     * <code>
     * $query->filterByVersion(1234); // WHERE version = 1234
     * $query->filterByVersion(array(12, 34)); // WHERE version IN (12, 34)
     * $query->filterByVersion(array('min' => 12)); // WHERE version > 12
     * </code>
     *
     * @param     mixed $version The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildMessageQuery The current query, for fluid interface
     */
    public function filterByVersion($version = null, $comparison = null)
    {
        if (is_array($version)) {
            $useMinMax = false;
            if (isset($version['min'])) {
                $this->addUsingAlias(MessageTableMap::VERSION, $version['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($version['max'])) {
                $this->addUsingAlias(MessageTableMap::VERSION, $version['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(MessageTableMap::VERSION, $version, $comparison);
    }

    /**
     * Filter the query on the version_created_at column
     *
     * Example usage:
     * <code>
     * $query->filterByVersionCreatedAt('2011-03-14'); // WHERE version_created_at = '2011-03-14'
     * $query->filterByVersionCreatedAt('now'); // WHERE version_created_at = '2011-03-14'
     * $query->filterByVersionCreatedAt(array('max' => 'yesterday')); // WHERE version_created_at > '2011-03-13'
     * </code>
     *
     * @param     mixed $versionCreatedAt The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildMessageQuery The current query, for fluid interface
     */
    public function filterByVersionCreatedAt($versionCreatedAt = null, $comparison = null)
    {
        if (is_array($versionCreatedAt)) {
            $useMinMax = false;
            if (isset($versionCreatedAt['min'])) {
                $this->addUsingAlias(MessageTableMap::VERSION_CREATED_AT, $versionCreatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($versionCreatedAt['max'])) {
                $this->addUsingAlias(MessageTableMap::VERSION_CREATED_AT, $versionCreatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(MessageTableMap::VERSION_CREATED_AT, $versionCreatedAt, $comparison);
    }

    /**
     * Filter the query on the version_created_by column
     *
     * Example usage:
     * <code>
     * $query->filterByVersionCreatedBy('fooValue');   // WHERE version_created_by = 'fooValue'
     * $query->filterByVersionCreatedBy('%fooValue%'); // WHERE version_created_by LIKE '%fooValue%'
     * </code>
     *
     * @param     string $versionCreatedBy The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildMessageQuery The current query, for fluid interface
     */
    public function filterByVersionCreatedBy($versionCreatedBy = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($versionCreatedBy)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $versionCreatedBy)) {
                $versionCreatedBy = str_replace('*', '%', $versionCreatedBy);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(MessageTableMap::VERSION_CREATED_BY, $versionCreatedBy, $comparison);
    }

    /**
     * Filter the query by a related \Thelia\Model\MessageI18n object
     *
     * @param \Thelia\Model\MessageI18n|ObjectCollection $messageI18n  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildMessageQuery The current query, for fluid interface
     */
    public function filterByMessageI18n($messageI18n, $comparison = null)
    {
        if ($messageI18n instanceof \Thelia\Model\MessageI18n) {
            return $this
                ->addUsingAlias(MessageTableMap::ID, $messageI18n->getId(), $comparison);
        } elseif ($messageI18n instanceof ObjectCollection) {
            return $this
                ->useMessageI18nQuery()
                ->filterByPrimaryKeys($messageI18n->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByMessageI18n() only accepts arguments of type \Thelia\Model\MessageI18n or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the MessageI18n relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildMessageQuery The current query, for fluid interface
     */
    public function joinMessageI18n($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('MessageI18n');

        // create a ModelJoin object for this join
        $join = new ModelJoin();
        $join->setJoinType($joinType);
        $join->setRelationMap($relationMap, $this->useAliasInSQL ? $this->getModelAlias() : null, $relationAlias);
        if ($previousJoin = $this->getPreviousJoin()) {
            $join->setPreviousJoin($previousJoin);
        }

        // add the ModelJoin to the current object
        if ($relationAlias) {
            $this->addAlias($relationAlias, $relationMap->getRightTable()->getName());
            $this->addJoinObject($join, $relationAlias);
        } else {
            $this->addJoinObject($join, 'MessageI18n');
        }

        return $this;
    }

    /**
     * Use the MessageI18n relation MessageI18n object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\MessageI18nQuery A secondary query class using the current class as primary query
     */
    public function useMessageI18nQuery($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        return $this
            ->joinMessageI18n($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'MessageI18n', '\Thelia\Model\MessageI18nQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\MessageVersion object
     *
     * @param \Thelia\Model\MessageVersion|ObjectCollection $messageVersion  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildMessageQuery The current query, for fluid interface
     */
    public function filterByMessageVersion($messageVersion, $comparison = null)
    {
        if ($messageVersion instanceof \Thelia\Model\MessageVersion) {
            return $this
                ->addUsingAlias(MessageTableMap::ID, $messageVersion->getId(), $comparison);
        } elseif ($messageVersion instanceof ObjectCollection) {
            return $this
                ->useMessageVersionQuery()
                ->filterByPrimaryKeys($messageVersion->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByMessageVersion() only accepts arguments of type \Thelia\Model\MessageVersion or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the MessageVersion relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildMessageQuery The current query, for fluid interface
     */
    public function joinMessageVersion($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('MessageVersion');

        // create a ModelJoin object for this join
        $join = new ModelJoin();
        $join->setJoinType($joinType);
        $join->setRelationMap($relationMap, $this->useAliasInSQL ? $this->getModelAlias() : null, $relationAlias);
        if ($previousJoin = $this->getPreviousJoin()) {
            $join->setPreviousJoin($previousJoin);
        }

        // add the ModelJoin to the current object
        if ($relationAlias) {
            $this->addAlias($relationAlias, $relationMap->getRightTable()->getName());
            $this->addJoinObject($join, $relationAlias);
        } else {
            $this->addJoinObject($join, 'MessageVersion');
        }

        return $this;
    }

    /**
     * Use the MessageVersion relation MessageVersion object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\MessageVersionQuery A secondary query class using the current class as primary query
     */
    public function useMessageVersionQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinMessageVersion($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'MessageVersion', '\Thelia\Model\MessageVersionQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ChildMessage $message Object to remove from the list of results
     *
     * @return ChildMessageQuery The current query, for fluid interface
     */
    public function prune($message = null)
    {
        if ($message) {
            $this->addUsingAlias(MessageTableMap::ID, $message->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the message table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(MessageTableMap::DATABASE_NAME);
        }
        $affectedRows = 0; // initialize var to track total num of affected rows
        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            MessageTableMap::clearInstancePool();
            MessageTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildMessage or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildMessage object or primary key or array of primary keys
     *              which is used to create the DELETE statement
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).  This includes CASCADE-related rows
     *                if supported by native driver or if emulated using Propel.
     * @throws PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
     public function delete(ConnectionInterface $con = null)
     {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(MessageTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(MessageTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        MessageTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            MessageTableMap::clearRelatedInstancePool();
            $con->commit();

            return $affectedRows;
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }
    }

    // timestampable behavior

    /**
     * Filter by the latest updated
     *
     * @param      int $nbDays Maximum age of the latest update in days
     *
     * @return     ChildMessageQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(MessageTableMap::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     ChildMessageQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(MessageTableMap::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     ChildMessageQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(MessageTableMap::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     ChildMessageQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(MessageTableMap::UPDATED_AT);
    }

    /**
     * Order by create date desc
     *
     * @return     ChildMessageQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(MessageTableMap::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     ChildMessageQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(MessageTableMap::CREATED_AT);
    }

    // i18n behavior

    /**
     * Adds a JOIN clause to the query using the i18n relation
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    ChildMessageQuery The current query, for fluid interface
     */
    public function joinI18n($locale = 'en_US', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $relationName = $relationAlias ? $relationAlias : 'MessageI18n';

        return $this
            ->joinMessageI18n($relationAlias, $joinType)
            ->addJoinCondition($relationName, $relationName . '.Locale = ?', $locale);
    }

    /**
     * Adds a JOIN clause to the query and hydrates the related I18n object.
     * Shortcut for $c->joinI18n($locale)->with()
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    ChildMessageQuery The current query, for fluid interface
     */
    public function joinWithI18n($locale = 'en_US', $joinType = Criteria::LEFT_JOIN)
    {
        $this
            ->joinI18n($locale, null, $joinType)
            ->with('MessageI18n');
        $this->with['MessageI18n']->setIsWithOneToMany(false);

        return $this;
    }

    /**
     * Use the I18n relation query object
     *
     * @see       useQuery()
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    ChildMessageI18nQuery A secondary query class using the current class as primary query
     */
    public function useI18nQuery($locale = 'en_US', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinI18n($locale, $relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'MessageI18n', '\Thelia\Model\MessageI18nQuery');
    }

    // versionable behavior

    /**
     * Checks whether versioning is enabled
     *
     * @return boolean
     */
    static public function isVersioningEnabled()
    {
        return self::$isVersioningEnabled;
    }

    /**
     * Enables versioning
     */
    static public function enableVersioning()
    {
        self::$isVersioningEnabled = true;
    }

    /**
     * Disables versioning
     */
    static public function disableVersioning()
    {
        self::$isVersioningEnabled = false;
    }

} // MessageQuery
