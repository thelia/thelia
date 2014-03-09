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
use Thelia\Model\MessageVersion as ChildMessageVersion;
use Thelia\Model\MessageVersionQuery as ChildMessageVersionQuery;
use Thelia\Model\Map\MessageVersionTableMap;

/**
 * Base class that represents a query for the 'message_version' table.
 *
 *
 *
 * @method     ChildMessageVersionQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildMessageVersionQuery orderByName($order = Criteria::ASC) Order by the name column
 * @method     ChildMessageVersionQuery orderBySecured($order = Criteria::ASC) Order by the secured column
 * @method     ChildMessageVersionQuery orderByTextLayoutFileName($order = Criteria::ASC) Order by the text_layout_file_name column
 * @method     ChildMessageVersionQuery orderByTextTemplateFileName($order = Criteria::ASC) Order by the text_template_file_name column
 * @method     ChildMessageVersionQuery orderByHtmlLayoutFileName($order = Criteria::ASC) Order by the html_layout_file_name column
 * @method     ChildMessageVersionQuery orderByHtmlTemplateFileName($order = Criteria::ASC) Order by the html_template_file_name column
 * @method     ChildMessageVersionQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method     ChildMessageVersionQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 * @method     ChildMessageVersionQuery orderByVersion($order = Criteria::ASC) Order by the version column
 * @method     ChildMessageVersionQuery orderByVersionCreatedAt($order = Criteria::ASC) Order by the version_created_at column
 * @method     ChildMessageVersionQuery orderByVersionCreatedBy($order = Criteria::ASC) Order by the version_created_by column
 *
 * @method     ChildMessageVersionQuery groupById() Group by the id column
 * @method     ChildMessageVersionQuery groupByName() Group by the name column
 * @method     ChildMessageVersionQuery groupBySecured() Group by the secured column
 * @method     ChildMessageVersionQuery groupByTextLayoutFileName() Group by the text_layout_file_name column
 * @method     ChildMessageVersionQuery groupByTextTemplateFileName() Group by the text_template_file_name column
 * @method     ChildMessageVersionQuery groupByHtmlLayoutFileName() Group by the html_layout_file_name column
 * @method     ChildMessageVersionQuery groupByHtmlTemplateFileName() Group by the html_template_file_name column
 * @method     ChildMessageVersionQuery groupByCreatedAt() Group by the created_at column
 * @method     ChildMessageVersionQuery groupByUpdatedAt() Group by the updated_at column
 * @method     ChildMessageVersionQuery groupByVersion() Group by the version column
 * @method     ChildMessageVersionQuery groupByVersionCreatedAt() Group by the version_created_at column
 * @method     ChildMessageVersionQuery groupByVersionCreatedBy() Group by the version_created_by column
 *
 * @method     ChildMessageVersionQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildMessageVersionQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildMessageVersionQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildMessageVersionQuery leftJoinMessage($relationAlias = null) Adds a LEFT JOIN clause to the query using the Message relation
 * @method     ChildMessageVersionQuery rightJoinMessage($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Message relation
 * @method     ChildMessageVersionQuery innerJoinMessage($relationAlias = null) Adds a INNER JOIN clause to the query using the Message relation
 *
 * @method     ChildMessageVersion findOne(ConnectionInterface $con = null) Return the first ChildMessageVersion matching the query
 * @method     ChildMessageVersion findOneOrCreate(ConnectionInterface $con = null) Return the first ChildMessageVersion matching the query, or a new ChildMessageVersion object populated from the query conditions when no match is found
 *
 * @method     ChildMessageVersion findOneById(int $id) Return the first ChildMessageVersion filtered by the id column
 * @method     ChildMessageVersion findOneByName(string $name) Return the first ChildMessageVersion filtered by the name column
 * @method     ChildMessageVersion findOneBySecured(int $secured) Return the first ChildMessageVersion filtered by the secured column
 * @method     ChildMessageVersion findOneByTextLayoutFileName(string $text_layout_file_name) Return the first ChildMessageVersion filtered by the text_layout_file_name column
 * @method     ChildMessageVersion findOneByTextTemplateFileName(string $text_template_file_name) Return the first ChildMessageVersion filtered by the text_template_file_name column
 * @method     ChildMessageVersion findOneByHtmlLayoutFileName(string $html_layout_file_name) Return the first ChildMessageVersion filtered by the html_layout_file_name column
 * @method     ChildMessageVersion findOneByHtmlTemplateFileName(string $html_template_file_name) Return the first ChildMessageVersion filtered by the html_template_file_name column
 * @method     ChildMessageVersion findOneByCreatedAt(string $created_at) Return the first ChildMessageVersion filtered by the created_at column
 * @method     ChildMessageVersion findOneByUpdatedAt(string $updated_at) Return the first ChildMessageVersion filtered by the updated_at column
 * @method     ChildMessageVersion findOneByVersion(int $version) Return the first ChildMessageVersion filtered by the version column
 * @method     ChildMessageVersion findOneByVersionCreatedAt(string $version_created_at) Return the first ChildMessageVersion filtered by the version_created_at column
 * @method     ChildMessageVersion findOneByVersionCreatedBy(string $version_created_by) Return the first ChildMessageVersion filtered by the version_created_by column
 *
 * @method     array findById(int $id) Return ChildMessageVersion objects filtered by the id column
 * @method     array findByName(string $name) Return ChildMessageVersion objects filtered by the name column
 * @method     array findBySecured(int $secured) Return ChildMessageVersion objects filtered by the secured column
 * @method     array findByTextLayoutFileName(string $text_layout_file_name) Return ChildMessageVersion objects filtered by the text_layout_file_name column
 * @method     array findByTextTemplateFileName(string $text_template_file_name) Return ChildMessageVersion objects filtered by the text_template_file_name column
 * @method     array findByHtmlLayoutFileName(string $html_layout_file_name) Return ChildMessageVersion objects filtered by the html_layout_file_name column
 * @method     array findByHtmlTemplateFileName(string $html_template_file_name) Return ChildMessageVersion objects filtered by the html_template_file_name column
 * @method     array findByCreatedAt(string $created_at) Return ChildMessageVersion objects filtered by the created_at column
 * @method     array findByUpdatedAt(string $updated_at) Return ChildMessageVersion objects filtered by the updated_at column
 * @method     array findByVersion(int $version) Return ChildMessageVersion objects filtered by the version column
 * @method     array findByVersionCreatedAt(string $version_created_at) Return ChildMessageVersion objects filtered by the version_created_at column
 * @method     array findByVersionCreatedBy(string $version_created_by) Return ChildMessageVersion objects filtered by the version_created_by column
 *
 */
abstract class MessageVersionQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Thelia\Model\Base\MessageVersionQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\Thelia\\Model\\MessageVersion', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildMessageVersionQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildMessageVersionQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \Thelia\Model\MessageVersionQuery) {
            return $criteria;
        }
        $query = new \Thelia\Model\MessageVersionQuery();
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
     * $obj = $c->findPk(array(12, 34), $con);
     * </code>
     *
     * @param array[$id, $version] $key Primary key to use for the query
     * @param ConnectionInterface $con an optional connection object
     *
     * @return ChildMessageVersion|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = MessageVersionTableMap::getInstanceFromPool(serialize(array((string) $key[0], (string) $key[1]))))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(MessageVersionTableMap::DATABASE_NAME);
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
     * @return   ChildMessageVersion A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `NAME`, `SECURED`, `TEXT_LAYOUT_FILE_NAME`, `TEXT_TEMPLATE_FILE_NAME`, `HTML_LAYOUT_FILE_NAME`, `HTML_TEMPLATE_FILE_NAME`, `CREATED_AT`, `UPDATED_AT`, `VERSION`, `VERSION_CREATED_AT`, `VERSION_CREATED_BY` FROM `message_version` WHERE `ID` = :p0 AND `VERSION` = :p1';
        try {
            $stmt = $con->prepare($sql);
            $stmt->bindValue(':p0', $key[0], PDO::PARAM_INT);
            $stmt->bindValue(':p1', $key[1], PDO::PARAM_INT);
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute SELECT statement [%s]', $sql), 0, $e);
        }
        $obj = null;
        if ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
            $obj = new ChildMessageVersion();
            $obj->hydrate($row);
            MessageVersionTableMap::addInstanceToPool($obj, serialize(array((string) $key[0], (string) $key[1])));
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
     * @return ChildMessageVersion|array|mixed the result, formatted by the current formatter
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
     * $objs = $c->findPks(array(array(12, 56), array(832, 123), array(123, 456)), $con);
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
     * @return ChildMessageVersionQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {
        $this->addUsingAlias(MessageVersionTableMap::ID, $key[0], Criteria::EQUAL);
        $this->addUsingAlias(MessageVersionTableMap::VERSION, $key[1], Criteria::EQUAL);

        return $this;
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildMessageVersionQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {
        if (empty($keys)) {
            return $this->add(null, '1<>1', Criteria::CUSTOM);
        }
        foreach ($keys as $key) {
            $cton0 = $this->getNewCriterion(MessageVersionTableMap::ID, $key[0], Criteria::EQUAL);
            $cton1 = $this->getNewCriterion(MessageVersionTableMap::VERSION, $key[1], Criteria::EQUAL);
            $cton0->addAnd($cton1);
            $this->addOr($cton0);
        }

        return $this;
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
     * @see       filterByMessage()
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildMessageVersionQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(MessageVersionTableMap::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(MessageVersionTableMap::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(MessageVersionTableMap::ID, $id, $comparison);
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
     * @return ChildMessageVersionQuery The current query, for fluid interface
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

        return $this->addUsingAlias(MessageVersionTableMap::NAME, $name, $comparison);
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
     * @return ChildMessageVersionQuery The current query, for fluid interface
     */
    public function filterBySecured($secured = null, $comparison = null)
    {
        if (is_array($secured)) {
            $useMinMax = false;
            if (isset($secured['min'])) {
                $this->addUsingAlias(MessageVersionTableMap::SECURED, $secured['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($secured['max'])) {
                $this->addUsingAlias(MessageVersionTableMap::SECURED, $secured['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(MessageVersionTableMap::SECURED, $secured, $comparison);
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
     * @return ChildMessageVersionQuery The current query, for fluid interface
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

        return $this->addUsingAlias(MessageVersionTableMap::TEXT_LAYOUT_FILE_NAME, $textLayoutFileName, $comparison);
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
     * @return ChildMessageVersionQuery The current query, for fluid interface
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

        return $this->addUsingAlias(MessageVersionTableMap::TEXT_TEMPLATE_FILE_NAME, $textTemplateFileName, $comparison);
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
     * @return ChildMessageVersionQuery The current query, for fluid interface
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

        return $this->addUsingAlias(MessageVersionTableMap::HTML_LAYOUT_FILE_NAME, $htmlLayoutFileName, $comparison);
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
     * @return ChildMessageVersionQuery The current query, for fluid interface
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

        return $this->addUsingAlias(MessageVersionTableMap::HTML_TEMPLATE_FILE_NAME, $htmlTemplateFileName, $comparison);
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
     * @return ChildMessageVersionQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(MessageVersionTableMap::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(MessageVersionTableMap::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(MessageVersionTableMap::CREATED_AT, $createdAt, $comparison);
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
     * @return ChildMessageVersionQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(MessageVersionTableMap::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(MessageVersionTableMap::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(MessageVersionTableMap::UPDATED_AT, $updatedAt, $comparison);
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
     * @return ChildMessageVersionQuery The current query, for fluid interface
     */
    public function filterByVersion($version = null, $comparison = null)
    {
        if (is_array($version)) {
            $useMinMax = false;
            if (isset($version['min'])) {
                $this->addUsingAlias(MessageVersionTableMap::VERSION, $version['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($version['max'])) {
                $this->addUsingAlias(MessageVersionTableMap::VERSION, $version['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(MessageVersionTableMap::VERSION, $version, $comparison);
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
     * @return ChildMessageVersionQuery The current query, for fluid interface
     */
    public function filterByVersionCreatedAt($versionCreatedAt = null, $comparison = null)
    {
        if (is_array($versionCreatedAt)) {
            $useMinMax = false;
            if (isset($versionCreatedAt['min'])) {
                $this->addUsingAlias(MessageVersionTableMap::VERSION_CREATED_AT, $versionCreatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($versionCreatedAt['max'])) {
                $this->addUsingAlias(MessageVersionTableMap::VERSION_CREATED_AT, $versionCreatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(MessageVersionTableMap::VERSION_CREATED_AT, $versionCreatedAt, $comparison);
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
     * @return ChildMessageVersionQuery The current query, for fluid interface
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

        return $this->addUsingAlias(MessageVersionTableMap::VERSION_CREATED_BY, $versionCreatedBy, $comparison);
    }

    /**
     * Filter the query by a related \Thelia\Model\Message object
     *
     * @param \Thelia\Model\Message|ObjectCollection $message The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildMessageVersionQuery The current query, for fluid interface
     */
    public function filterByMessage($message, $comparison = null)
    {
        if ($message instanceof \Thelia\Model\Message) {
            return $this
                ->addUsingAlias(MessageVersionTableMap::ID, $message->getId(), $comparison);
        } elseif ($message instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(MessageVersionTableMap::ID, $message->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByMessage() only accepts arguments of type \Thelia\Model\Message or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Message relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildMessageVersionQuery The current query, for fluid interface
     */
    public function joinMessage($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Message');

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
            $this->addJoinObject($join, 'Message');
        }

        return $this;
    }

    /**
     * Use the Message relation Message object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\MessageQuery A secondary query class using the current class as primary query
     */
    public function useMessageQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinMessage($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Message', '\Thelia\Model\MessageQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ChildMessageVersion $messageVersion Object to remove from the list of results
     *
     * @return ChildMessageVersionQuery The current query, for fluid interface
     */
    public function prune($messageVersion = null)
    {
        if ($messageVersion) {
            $this->addCond('pruneCond0', $this->getAliasedColName(MessageVersionTableMap::ID), $messageVersion->getId(), Criteria::NOT_EQUAL);
            $this->addCond('pruneCond1', $this->getAliasedColName(MessageVersionTableMap::VERSION), $messageVersion->getVersion(), Criteria::NOT_EQUAL);
            $this->combine(array('pruneCond0', 'pruneCond1'), Criteria::LOGICAL_OR);
        }

        return $this;
    }

    /**
     * Deletes all rows from the message_version table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(MessageVersionTableMap::DATABASE_NAME);
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
            MessageVersionTableMap::clearInstancePool();
            MessageVersionTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildMessageVersion or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildMessageVersion object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(MessageVersionTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(MessageVersionTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        MessageVersionTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            MessageVersionTableMap::clearRelatedInstancePool();
            $con->commit();

            return $affectedRows;
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }
    }

} // MessageVersionQuery
