<?php

namespace Thelia\Model\Base;

use \DateTime;
use \Exception;
use \PDO;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Collection\Collection;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\BadMethodCallException;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Map\TableMap;
use Propel\Runtime\Parser\AbstractParser;
use Propel\Runtime\Util\PropelDateTime;
use Thelia\Model\Admin as ChildAdmin;
use Thelia\Model\AdminProfile as ChildAdminProfile;
use Thelia\Model\AdminProfileQuery as ChildAdminProfileQuery;
use Thelia\Model\AdminQuery as ChildAdminQuery;
use Thelia\Model\Profile as ChildProfile;
use Thelia\Model\ProfileQuery as ChildProfileQuery;
use Thelia\Model\Map\AdminTableMap;

abstract class Admin implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\Thelia\\Model\\Map\\AdminTableMap';


    /**
     * attribute to determine if this object has previously been saved.
     * @var boolean
     */
    protected $new = true;

    /**
     * attribute to determine whether this object has been deleted.
     * @var boolean
     */
    protected $deleted = false;

    /**
     * The columns that have been modified in current object.
     * Tracking modified columns allows us to only update modified columns.
     * @var array
     */
    protected $modifiedColumns = array();

    /**
     * The (virtual) columns that are added at runtime
     * The formatters can add supplementary columns based on a resultset
     * @var array
     */
    protected $virtualColumns = array();

    /**
     * The value for the id field.
     * @var        int
     */
    protected $id;

    /**
     * The value for the firstname field.
     * @var        string
     */
    protected $firstname;

    /**
     * The value for the lastname field.
     * @var        string
     */
    protected $lastname;

    /**
     * The value for the login field.
     * @var        string
     */
    protected $login;

    /**
     * The value for the password field.
     * @var        string
     */
    protected $password;

    /**
     * The value for the algo field.
     * @var        string
     */
    protected $algo;

    /**
     * The value for the salt field.
     * @var        string
     */
    protected $salt;

    /**
     * The value for the remember_me_token field.
     * @var        string
     */
    protected $remember_me_token;

    /**
     * The value for the remember_me_serial field.
     * @var        string
     */
    protected $remember_me_serial;

    /**
     * The value for the created_at field.
     * @var        string
     */
    protected $created_at;

    /**
     * The value for the updated_at field.
     * @var        string
     */
    protected $updated_at;

    /**
     * @var        ObjectCollection|ChildAdminProfile[] Collection to store aggregation of ChildAdminProfile objects.
     */
    protected $collAdminProfiles;
    protected $collAdminProfilesPartial;

    /**
     * @var        ChildProfile[] Collection to store aggregation of ChildProfile objects.
     */
    protected $collProfiles;

    /**
     * Flag to prevent endless save loop, if this object is referenced
     * by another object which falls in this transaction.
     *
     * @var boolean
     */
    protected $alreadyInSave = false;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $profilesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $adminProfilesScheduledForDeletion = null;

    /**
     * Initializes internal state of Thelia\Model\Base\Admin object.
     */
    public function __construct()
    {
    }

    /**
     * Returns whether the object has been modified.
     *
     * @return boolean True if the object has been modified.
     */
    public function isModified()
    {
        return !empty($this->modifiedColumns);
    }

    /**
     * Has specified column been modified?
     *
     * @param  string  $col column fully qualified name (TableMap::TYPE_COLNAME), e.g. Book::AUTHOR_ID
     * @return boolean True if $col has been modified.
     */
    public function isColumnModified($col)
    {
        return in_array($col, $this->modifiedColumns);
    }

    /**
     * Get the columns that have been modified in this object.
     * @return array A unique list of the modified column names for this object.
     */
    public function getModifiedColumns()
    {
        return array_unique($this->modifiedColumns);
    }

    /**
     * Returns whether the object has ever been saved.  This will
     * be false, if the object was retrieved from storage or was created
     * and then saved.
     *
     * @return boolean true, if the object has never been persisted.
     */
    public function isNew()
    {
        return $this->new;
    }

    /**
     * Setter for the isNew attribute.  This method will be called
     * by Propel-generated children and objects.
     *
     * @param boolean $b the state of the object.
     */
    public function setNew($b)
    {
        $this->new = (Boolean) $b;
    }

    /**
     * Whether this object has been deleted.
     * @return boolean The deleted state of this object.
     */
    public function isDeleted()
    {
        return $this->deleted;
    }

    /**
     * Specify whether this object has been deleted.
     * @param  boolean $b The deleted state of this object.
     * @return void
     */
    public function setDeleted($b)
    {
        $this->deleted = (Boolean) $b;
    }

    /**
     * Sets the modified state for the object to be false.
     * @param  string $col If supplied, only the specified column is reset.
     * @return void
     */
    public function resetModified($col = null)
    {
        if (null !== $col) {
            while (false !== ($offset = array_search($col, $this->modifiedColumns))) {
                array_splice($this->modifiedColumns, $offset, 1);
            }
        } else {
            $this->modifiedColumns = array();
        }
    }

    /**
     * Compares this with another <code>Admin</code> instance.  If
     * <code>obj</code> is an instance of <code>Admin</code>, delegates to
     * <code>equals(Admin)</code>.  Otherwise, returns <code>false</code>.
     *
     * @param  mixed   $obj The object to compare to.
     * @return boolean Whether equal to the object specified.
     */
    public function equals($obj)
    {
        $thisclazz = get_class($this);
        if (!is_object($obj) || !($obj instanceof $thisclazz)) {
            return false;
        }

        if ($this === $obj) {
            return true;
        }

        if (null === $this->getPrimaryKey()
            || null === $obj->getPrimaryKey())  {
            return false;
        }

        return $this->getPrimaryKey() === $obj->getPrimaryKey();
    }

    /**
     * If the primary key is not null, return the hashcode of the
     * primary key. Otherwise, return the hash code of the object.
     *
     * @return int Hashcode
     */
    public function hashCode()
    {
        if (null !== $this->getPrimaryKey()) {
            return crc32(serialize($this->getPrimaryKey()));
        }

        return crc32(serialize(clone $this));
    }

    /**
     * Get the associative array of the virtual columns in this object
     *
     * @return array
     */
    public function getVirtualColumns()
    {
        return $this->virtualColumns;
    }

    /**
     * Checks the existence of a virtual column in this object
     *
     * @param  string  $name The virtual column name
     * @return boolean
     */
    public function hasVirtualColumn($name)
    {
        return array_key_exists($name, $this->virtualColumns);
    }

    /**
     * Get the value of a virtual column in this object
     *
     * @param  string $name The virtual column name
     * @return mixed
     *
     * @throws PropelException
     */
    public function getVirtualColumn($name)
    {
        if (!$this->hasVirtualColumn($name)) {
            throw new PropelException(sprintf('Cannot get value of inexistent virtual column %s.', $name));
        }

        return $this->virtualColumns[$name];
    }

    /**
     * Set the value of a virtual column in this object
     *
     * @param string $name  The virtual column name
     * @param mixed  $value The value to give to the virtual column
     *
     * @return Admin The current object, for fluid interface
     */
    public function setVirtualColumn($name, $value)
    {
        $this->virtualColumns[$name] = $value;

        return $this;
    }

    /**
     * Logs a message using Propel::log().
     *
     * @param  string  $msg
     * @param  int     $priority One of the Propel::LOG_* logging levels
     * @return boolean
     */
    protected function log($msg, $priority = Propel::LOG_INFO)
    {
        return Propel::log(get_class($this) . ': ' . $msg, $priority);
    }

    /**
     * Populate the current object from a string, using a given parser format
     * <code>
     * $book = new Book();
     * $book->importFrom('JSON', '{"Id":9012,"Title":"Don Juan","ISBN":"0140422161","Price":12.99,"PublisherId":1234,"AuthorId":5678}');
     * </code>
     *
     * @param mixed $parser A AbstractParser instance,
     *                       or a format name ('XML', 'YAML', 'JSON', 'CSV')
     * @param string $data The source data to import from
     *
     * @return Admin The current object, for fluid interface
     */
    public function importFrom($parser, $data)
    {
        if (!$parser instanceof AbstractParser) {
            $parser = AbstractParser::getParser($parser);
        }

        $this->fromArray($parser->toArray($data), TableMap::TYPE_PHPNAME);

        return $this;
    }

    /**
     * Export the current object properties to a string, using a given parser format
     * <code>
     * $book = BookQuery::create()->findPk(9012);
     * echo $book->exportTo('JSON');
     *  => {"Id":9012,"Title":"Don Juan","ISBN":"0140422161","Price":12.99,"PublisherId":1234,"AuthorId":5678}');
     * </code>
     *
     * @param  mixed   $parser                 A AbstractParser instance, or a format name ('XML', 'YAML', 'JSON', 'CSV')
     * @param  boolean $includeLazyLoadColumns (optional) Whether to include lazy load(ed) columns. Defaults to TRUE.
     * @return string  The exported data
     */
    public function exportTo($parser, $includeLazyLoadColumns = true)
    {
        if (!$parser instanceof AbstractParser) {
            $parser = AbstractParser::getParser($parser);
        }

        return $parser->fromArray($this->toArray(TableMap::TYPE_PHPNAME, $includeLazyLoadColumns, array(), true));
    }

    /**
     * Clean up internal collections prior to serializing
     * Avoids recursive loops that turn into segmentation faults when serializing
     */
    public function __sleep()
    {
        $this->clearAllReferences();

        return array_keys(get_object_vars($this));
    }

    /**
     * Get the [id] column value.
     *
     * @return   int
     */
    public function getId()
    {

        return $this->id;
    }

    /**
     * Get the [firstname] column value.
     *
     * @return   string
     */
    public function getFirstname()
    {

        return $this->firstname;
    }

    /**
     * Get the [lastname] column value.
     *
     * @return   string
     */
    public function getLastname()
    {

        return $this->lastname;
    }

    /**
     * Get the [login] column value.
     *
     * @return   string
     */
    public function getLogin()
    {

        return $this->login;
    }

    /**
     * Get the [password] column value.
     *
     * @return   string
     */
    public function getPassword()
    {

        return $this->password;
    }

    /**
     * Get the [algo] column value.
     *
     * @return   string
     */
    public function getAlgo()
    {

        return $this->algo;
    }

    /**
     * Get the [salt] column value.
     *
     * @return   string
     */
    public function getSalt()
    {

        return $this->salt;
    }

    /**
     * Get the [remember_me_token] column value.
     *
     * @return   string
     */
    public function getRememberMeToken()
    {

        return $this->remember_me_token;
    }

    /**
     * Get the [remember_me_serial] column value.
     *
     * @return   string
     */
    public function getRememberMeSerial()
    {

        return $this->remember_me_serial;
    }

    /**
     * Get the [optionally formatted] temporal [created_at] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw \DateTime object will be returned.
     *
     * @return mixed Formatted date/time value as string or \DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00 00:00:00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getCreatedAt($format = NULL)
    {
        if ($format === null) {
            return $this->created_at;
        } else {
            return $this->created_at instanceof \DateTime ? $this->created_at->format($format) : null;
        }
    }

    /**
     * Get the [optionally formatted] temporal [updated_at] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw \DateTime object will be returned.
     *
     * @return mixed Formatted date/time value as string or \DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00 00:00:00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getUpdatedAt($format = NULL)
    {
        if ($format === null) {
            return $this->updated_at;
        } else {
            return $this->updated_at instanceof \DateTime ? $this->updated_at->format($format) : null;
        }
    }

    /**
     * Set the value of [id] column.
     *
     * @param      int $v new value
     * @return   \Thelia\Model\Admin The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[] = AdminTableMap::ID;
        }


        return $this;
    } // setId()

    /**
     * Set the value of [firstname] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Admin The current object (for fluent API support)
     */
    public function setFirstname($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->firstname !== $v) {
            $this->firstname = $v;
            $this->modifiedColumns[] = AdminTableMap::FIRSTNAME;
        }


        return $this;
    } // setFirstname()

    /**
     * Set the value of [lastname] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Admin The current object (for fluent API support)
     */
    public function setLastname($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->lastname !== $v) {
            $this->lastname = $v;
            $this->modifiedColumns[] = AdminTableMap::LASTNAME;
        }


        return $this;
    } // setLastname()

    /**
     * Set the value of [login] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Admin The current object (for fluent API support)
     */
    public function setLogin($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->login !== $v) {
            $this->login = $v;
            $this->modifiedColumns[] = AdminTableMap::LOGIN;
        }


        return $this;
    } // setLogin()

    /**
     * Set the value of [password] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Admin The current object (for fluent API support)
     */
    public function setPassword($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->password !== $v) {
            $this->password = $v;
            $this->modifiedColumns[] = AdminTableMap::PASSWORD;
        }


        return $this;
    } // setPassword()

    /**
     * Set the value of [algo] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Admin The current object (for fluent API support)
     */
    public function setAlgo($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->algo !== $v) {
            $this->algo = $v;
            $this->modifiedColumns[] = AdminTableMap::ALGO;
        }


        return $this;
    } // setAlgo()

    /**
     * Set the value of [salt] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Admin The current object (for fluent API support)
     */
    public function setSalt($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->salt !== $v) {
            $this->salt = $v;
            $this->modifiedColumns[] = AdminTableMap::SALT;
        }


        return $this;
    } // setSalt()

    /**
     * Set the value of [remember_me_token] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Admin The current object (for fluent API support)
     */
    public function setRememberMeToken($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->remember_me_token !== $v) {
            $this->remember_me_token = $v;
            $this->modifiedColumns[] = AdminTableMap::REMEMBER_ME_TOKEN;
        }


        return $this;
    } // setRememberMeToken()

    /**
     * Set the value of [remember_me_serial] column.
     *
     * @param      string $v new value
     * @return   \Thelia\Model\Admin The current object (for fluent API support)
     */
    public function setRememberMeSerial($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->remember_me_serial !== $v) {
            $this->remember_me_serial = $v;
            $this->modifiedColumns[] = AdminTableMap::REMEMBER_ME_SERIAL;
        }


        return $this;
    } // setRememberMeSerial()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Admin The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->created_at !== null || $dt !== null) {
            if ($dt !== $this->created_at) {
                $this->created_at = $dt;
                $this->modifiedColumns[] = AdminTableMap::CREATED_AT;
            }
        } // if either are not null


        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Thelia\Model\Admin The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            if ($dt !== $this->updated_at) {
                $this->updated_at = $dt;
                $this->modifiedColumns[] = AdminTableMap::UPDATED_AT;
            }
        } // if either are not null


        return $this;
    } // setUpdatedAt()

    /**
     * Indicates whether the columns in this object are only set to default values.
     *
     * This method can be used in conjunction with isModified() to indicate whether an object is both
     * modified _and_ has some values set which are non-default.
     *
     * @return boolean Whether the columns in this object are only been set with default values.
     */
    public function hasOnlyDefaultValues()
    {
        // otherwise, everything was equal, so return TRUE
        return true;
    } // hasOnlyDefaultValues()

    /**
     * Hydrates (populates) the object variables with values from the database resultset.
     *
     * An offset (0-based "start column") is specified so that objects can be hydrated
     * with a subset of the columns in the resultset rows.  This is needed, for example,
     * for results of JOIN queries where the resultset row includes columns from two or
     * more tables.
     *
     * @param array   $row       The row returned by DataFetcher->fetch().
     * @param int     $startcol  0-based offset column which indicates which restultset column to start with.
     * @param boolean $rehydrate Whether this object is being re-hydrated from the database.
     * @param string  $indexType The index type of $row. Mostly DataFetcher->getIndexType().
                                  One of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_STUDLYPHPNAME
     *                            TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     *
     * @return int             next starting column
     * @throws PropelException - Any caught Exception will be rewrapped as a PropelException.
     */
    public function hydrate($row, $startcol = 0, $rehydrate = false, $indexType = TableMap::TYPE_NUM)
    {
        try {


            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : AdminTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : AdminTableMap::translateFieldName('Firstname', TableMap::TYPE_PHPNAME, $indexType)];
            $this->firstname = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : AdminTableMap::translateFieldName('Lastname', TableMap::TYPE_PHPNAME, $indexType)];
            $this->lastname = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : AdminTableMap::translateFieldName('Login', TableMap::TYPE_PHPNAME, $indexType)];
            $this->login = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : AdminTableMap::translateFieldName('Password', TableMap::TYPE_PHPNAME, $indexType)];
            $this->password = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 5 + $startcol : AdminTableMap::translateFieldName('Algo', TableMap::TYPE_PHPNAME, $indexType)];
            $this->algo = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 6 + $startcol : AdminTableMap::translateFieldName('Salt', TableMap::TYPE_PHPNAME, $indexType)];
            $this->salt = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 7 + $startcol : AdminTableMap::translateFieldName('RememberMeToken', TableMap::TYPE_PHPNAME, $indexType)];
            $this->remember_me_token = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 8 + $startcol : AdminTableMap::translateFieldName('RememberMeSerial', TableMap::TYPE_PHPNAME, $indexType)];
            $this->remember_me_serial = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 9 + $startcol : AdminTableMap::translateFieldName('CreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 10 + $startcol : AdminTableMap::translateFieldName('UpdatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->updated_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 11; // 11 = AdminTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException("Error populating \Thelia\Model\Admin object", 0, $e);
        }
    }

    /**
     * Checks and repairs the internal consistency of the object.
     *
     * This method is executed after an already-instantiated object is re-hydrated
     * from the database.  It exists to check any foreign keys to make sure that
     * the objects related to the current object are correct based on foreign key.
     *
     * You can override this method in the stub class, but you should always invoke
     * the base method from the overridden method (i.e. parent::ensureConsistency()),
     * in case your model changes.
     *
     * @throws PropelException
     */
    public function ensureConsistency()
    {
    } // ensureConsistency

    /**
     * Reloads this object from datastore based on primary key and (optionally) resets all associated objects.
     *
     * This will only work if the object has been saved and has a valid primary key set.
     *
     * @param      boolean $deep (optional) Whether to also de-associated any related objects.
     * @param      ConnectionInterface $con (optional) The ConnectionInterface connection to use.
     * @return void
     * @throws PropelException - if this object is deleted, unsaved or doesn't have pk match in db
     */
    public function reload($deep = false, ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("Cannot reload a deleted object.");
        }

        if ($this->isNew()) {
            throw new PropelException("Cannot reload an unsaved object.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(AdminTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildAdminQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
        $row = $dataFetcher->fetch();
        $dataFetcher->close();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true, $dataFetcher->getIndexType()); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->collAdminProfiles = null;

            $this->collProfiles = null;
        } // if (deep)
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param      ConnectionInterface $con
     * @return void
     * @throws PropelException
     * @see Admin::setDeleted()
     * @see Admin::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(AdminTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        try {
            $deleteQuery = ChildAdminQuery::create()
                ->filterByPrimaryKey($this->getPrimaryKey());
            $ret = $this->preDelete($con);
            if ($ret) {
                $deleteQuery->delete($con);
                $this->postDelete($con);
                $con->commit();
                $this->setDeleted(true);
            } else {
                $con->commit();
            }
        } catch (Exception $e) {
            $con->rollBack();
            throw $e;
        }
    }

    /**
     * Persists this object to the database.
     *
     * If the object is new, it inserts it; otherwise an update is performed.
     * All modified related objects will also be persisted in the doSave()
     * method.  This method wraps all precipitate database operations in a
     * single transaction.
     *
     * @param      ConnectionInterface $con
     * @return int             The number of rows affected by this insert/update and any referring fk objects' save() operations.
     * @throws PropelException
     * @see doSave()
     */
    public function save(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("You cannot save an object that has been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(AdminTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        $isInsert = $this->isNew();
        try {
            $ret = $this->preSave($con);
            if ($isInsert) {
                $ret = $ret && $this->preInsert($con);
                // timestampable behavior
                if (!$this->isColumnModified(AdminTableMap::CREATED_AT)) {
                    $this->setCreatedAt(time());
                }
                if (!$this->isColumnModified(AdminTableMap::UPDATED_AT)) {
                    $this->setUpdatedAt(time());
                }
            } else {
                $ret = $ret && $this->preUpdate($con);
                // timestampable behavior
                if ($this->isModified() && !$this->isColumnModified(AdminTableMap::UPDATED_AT)) {
                    $this->setUpdatedAt(time());
                }
            }
            if ($ret) {
                $affectedRows = $this->doSave($con);
                if ($isInsert) {
                    $this->postInsert($con);
                } else {
                    $this->postUpdate($con);
                }
                $this->postSave($con);
                AdminTableMap::addInstanceToPool($this);
            } else {
                $affectedRows = 0;
            }
            $con->commit();

            return $affectedRows;
        } catch (Exception $e) {
            $con->rollBack();
            throw $e;
        }
    }

    /**
     * Performs the work of inserting or updating the row in the database.
     *
     * If the object is new, it inserts it; otherwise an update is performed.
     * All related objects are also updated in this method.
     *
     * @param      ConnectionInterface $con
     * @return int             The number of rows affected by this insert/update and any referring fk objects' save() operations.
     * @throws PropelException
     * @see save()
     */
    protected function doSave(ConnectionInterface $con)
    {
        $affectedRows = 0; // initialize var to track total num of affected rows
        if (!$this->alreadyInSave) {
            $this->alreadyInSave = true;

            if ($this->isNew() || $this->isModified()) {
                // persist changes
                if ($this->isNew()) {
                    $this->doInsert($con);
                } else {
                    $this->doUpdate($con);
                }
                $affectedRows += 1;
                $this->resetModified();
            }

            if ($this->profilesScheduledForDeletion !== null) {
                if (!$this->profilesScheduledForDeletion->isEmpty()) {
                    $pks = array();
                    $pk  = $this->getPrimaryKey();
                    foreach ($this->profilesScheduledForDeletion->getPrimaryKeys(false) as $remotePk) {
                        $pks[] = array($remotePk, $pk);
                    }

                    AdminProfileQuery::create()
                        ->filterByPrimaryKeys($pks)
                        ->delete($con);
                    $this->profilesScheduledForDeletion = null;
                }

                foreach ($this->getProfiles() as $profile) {
                    if ($profile->isModified()) {
                        $profile->save($con);
                    }
                }
            } elseif ($this->collProfiles) {
                foreach ($this->collProfiles as $profile) {
                    if ($profile->isModified()) {
                        $profile->save($con);
                    }
                }
            }

            if ($this->adminProfilesScheduledForDeletion !== null) {
                if (!$this->adminProfilesScheduledForDeletion->isEmpty()) {
                    \Thelia\Model\AdminProfileQuery::create()
                        ->filterByPrimaryKeys($this->adminProfilesScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->adminProfilesScheduledForDeletion = null;
                }
            }

                if ($this->collAdminProfiles !== null) {
            foreach ($this->collAdminProfiles as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            $this->alreadyInSave = false;

        }

        return $affectedRows;
    } // doSave()

    /**
     * Insert the row in the database.
     *
     * @param      ConnectionInterface $con
     *
     * @throws PropelException
     * @see doSave()
     */
    protected function doInsert(ConnectionInterface $con)
    {
        $modifiedColumns = array();
        $index = 0;

        $this->modifiedColumns[] = AdminTableMap::ID;
        if (null !== $this->id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . AdminTableMap::ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(AdminTableMap::ID)) {
            $modifiedColumns[':p' . $index++]  = 'ID';
        }
        if ($this->isColumnModified(AdminTableMap::FIRSTNAME)) {
            $modifiedColumns[':p' . $index++]  = 'FIRSTNAME';
        }
        if ($this->isColumnModified(AdminTableMap::LASTNAME)) {
            $modifiedColumns[':p' . $index++]  = 'LASTNAME';
        }
        if ($this->isColumnModified(AdminTableMap::LOGIN)) {
            $modifiedColumns[':p' . $index++]  = 'LOGIN';
        }
        if ($this->isColumnModified(AdminTableMap::PASSWORD)) {
            $modifiedColumns[':p' . $index++]  = 'PASSWORD';
        }
        if ($this->isColumnModified(AdminTableMap::ALGO)) {
            $modifiedColumns[':p' . $index++]  = 'ALGO';
        }
        if ($this->isColumnModified(AdminTableMap::SALT)) {
            $modifiedColumns[':p' . $index++]  = 'SALT';
        }
        if ($this->isColumnModified(AdminTableMap::REMEMBER_ME_TOKEN)) {
            $modifiedColumns[':p' . $index++]  = 'REMEMBER_ME_TOKEN';
        }
        if ($this->isColumnModified(AdminTableMap::REMEMBER_ME_SERIAL)) {
            $modifiedColumns[':p' . $index++]  = 'REMEMBER_ME_SERIAL';
        }
        if ($this->isColumnModified(AdminTableMap::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = 'CREATED_AT';
        }
        if ($this->isColumnModified(AdminTableMap::UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = 'UPDATED_AT';
        }

        $sql = sprintf(
            'INSERT INTO admin (%s) VALUES (%s)',
            implode(', ', $modifiedColumns),
            implode(', ', array_keys($modifiedColumns))
        );

        try {
            $stmt = $con->prepare($sql);
            foreach ($modifiedColumns as $identifier => $columnName) {
                switch ($columnName) {
                    case 'ID':
                        $stmt->bindValue($identifier, $this->id, PDO::PARAM_INT);
                        break;
                    case 'FIRSTNAME':
                        $stmt->bindValue($identifier, $this->firstname, PDO::PARAM_STR);
                        break;
                    case 'LASTNAME':
                        $stmt->bindValue($identifier, $this->lastname, PDO::PARAM_STR);
                        break;
                    case 'LOGIN':
                        $stmt->bindValue($identifier, $this->login, PDO::PARAM_STR);
                        break;
                    case 'PASSWORD':
                        $stmt->bindValue($identifier, $this->password, PDO::PARAM_STR);
                        break;
                    case 'ALGO':
                        $stmt->bindValue($identifier, $this->algo, PDO::PARAM_STR);
                        break;
                    case 'SALT':
                        $stmt->bindValue($identifier, $this->salt, PDO::PARAM_STR);
                        break;
                    case 'REMEMBER_ME_TOKEN':
                        $stmt->bindValue($identifier, $this->remember_me_token, PDO::PARAM_STR);
                        break;
                    case 'REMEMBER_ME_SERIAL':
                        $stmt->bindValue($identifier, $this->remember_me_serial, PDO::PARAM_STR);
                        break;
                    case 'CREATED_AT':
                        $stmt->bindValue($identifier, $this->created_at ? $this->created_at->format("Y-m-d H:i:s") : null, PDO::PARAM_STR);
                        break;
                    case 'UPDATED_AT':
                        $stmt->bindValue($identifier, $this->updated_at ? $this->updated_at->format("Y-m-d H:i:s") : null, PDO::PARAM_STR);
                        break;
                }
            }
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute INSERT statement [%s]', $sql), 0, $e);
        }

        try {
            $pk = $con->lastInsertId();
        } catch (Exception $e) {
            throw new PropelException('Unable to get autoincrement id.', 0, $e);
        }
        $this->setId($pk);

        $this->setNew(false);
    }

    /**
     * Update the row in the database.
     *
     * @param      ConnectionInterface $con
     *
     * @return Integer Number of updated rows
     * @see doSave()
     */
    protected function doUpdate(ConnectionInterface $con)
    {
        $selectCriteria = $this->buildPkeyCriteria();
        $valuesCriteria = $this->buildCriteria();

        return $selectCriteria->doUpdate($valuesCriteria, $con);
    }

    /**
     * Retrieves a field from the object by name passed in as a string.
     *
     * @param      string $name name
     * @param      string $type The type of fieldname the $name is of:
     *                     one of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_STUDLYPHPNAME
     *                     TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     *                     Defaults to TableMap::TYPE_PHPNAME.
     * @return mixed Value of field.
     */
    public function getByName($name, $type = TableMap::TYPE_PHPNAME)
    {
        $pos = AdminTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
        $field = $this->getByPosition($pos);

        return $field;
    }

    /**
     * Retrieves a field from the object by Position as specified in the xml schema.
     * Zero-based.
     *
     * @param      int $pos position in xml schema
     * @return mixed Value of field at $pos
     */
    public function getByPosition($pos)
    {
        switch ($pos) {
            case 0:
                return $this->getId();
                break;
            case 1:
                return $this->getFirstname();
                break;
            case 2:
                return $this->getLastname();
                break;
            case 3:
                return $this->getLogin();
                break;
            case 4:
                return $this->getPassword();
                break;
            case 5:
                return $this->getAlgo();
                break;
            case 6:
                return $this->getSalt();
                break;
            case 7:
                return $this->getRememberMeToken();
                break;
            case 8:
                return $this->getRememberMeSerial();
                break;
            case 9:
                return $this->getCreatedAt();
                break;
            case 10:
                return $this->getUpdatedAt();
                break;
            default:
                return null;
                break;
        } // switch()
    }

    /**
     * Exports the object as an array.
     *
     * You can specify the key type of the array by passing one of the class
     * type constants.
     *
     * @param     string  $keyType (optional) One of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_STUDLYPHPNAME,
     *                    TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     *                    Defaults to TableMap::TYPE_PHPNAME.
     * @param     boolean $includeLazyLoadColumns (optional) Whether to include lazy loaded columns. Defaults to TRUE.
     * @param     array $alreadyDumpedObjects List of objects to skip to avoid recursion
     * @param     boolean $includeForeignObjects (optional) Whether to include hydrated related objects. Default to FALSE.
     *
     * @return array an associative array containing the field names (as keys) and field values
     */
    public function toArray($keyType = TableMap::TYPE_PHPNAME, $includeLazyLoadColumns = true, $alreadyDumpedObjects = array(), $includeForeignObjects = false)
    {
        if (isset($alreadyDumpedObjects['Admin'][$this->getPrimaryKey()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['Admin'][$this->getPrimaryKey()] = true;
        $keys = AdminTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getFirstname(),
            $keys[2] => $this->getLastname(),
            $keys[3] => $this->getLogin(),
            $keys[4] => $this->getPassword(),
            $keys[5] => $this->getAlgo(),
            $keys[6] => $this->getSalt(),
            $keys[7] => $this->getRememberMeToken(),
            $keys[8] => $this->getRememberMeSerial(),
            $keys[9] => $this->getCreatedAt(),
            $keys[10] => $this->getUpdatedAt(),
        );
        $virtualColumns = $this->virtualColumns;
        foreach ($virtualColumns as $key => $virtualColumn) {
            $result[$key] = $virtualColumn;
        }

        if ($includeForeignObjects) {
            if (null !== $this->collAdminProfiles) {
                $result['AdminProfiles'] = $this->collAdminProfiles->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
        }

        return $result;
    }

    /**
     * Sets a field from the object by name passed in as a string.
     *
     * @param      string $name
     * @param      mixed  $value field value
     * @param      string $type The type of fieldname the $name is of:
     *                     one of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_STUDLYPHPNAME
     *                     TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     *                     Defaults to TableMap::TYPE_PHPNAME.
     * @return void
     */
    public function setByName($name, $value, $type = TableMap::TYPE_PHPNAME)
    {
        $pos = AdminTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

        return $this->setByPosition($pos, $value);
    }

    /**
     * Sets a field from the object by Position as specified in the xml schema.
     * Zero-based.
     *
     * @param      int $pos position in xml schema
     * @param      mixed $value field value
     * @return void
     */
    public function setByPosition($pos, $value)
    {
        switch ($pos) {
            case 0:
                $this->setId($value);
                break;
            case 1:
                $this->setFirstname($value);
                break;
            case 2:
                $this->setLastname($value);
                break;
            case 3:
                $this->setLogin($value);
                break;
            case 4:
                $this->setPassword($value);
                break;
            case 5:
                $this->setAlgo($value);
                break;
            case 6:
                $this->setSalt($value);
                break;
            case 7:
                $this->setRememberMeToken($value);
                break;
            case 8:
                $this->setRememberMeSerial($value);
                break;
            case 9:
                $this->setCreatedAt($value);
                break;
            case 10:
                $this->setUpdatedAt($value);
                break;
        } // switch()
    }

    /**
     * Populates the object using an array.
     *
     * This is particularly useful when populating an object from one of the
     * request arrays (e.g. $_POST).  This method goes through the column
     * names, checking to see whether a matching key exists in populated
     * array. If so the setByName() method is called for that column.
     *
     * You can specify the key type of the array by additionally passing one
     * of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_STUDLYPHPNAME,
     * TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     * The default key type is the column's TableMap::TYPE_PHPNAME.
     *
     * @param      array  $arr     An array to populate the object from.
     * @param      string $keyType The type of keys the array uses.
     * @return void
     */
    public function fromArray($arr, $keyType = TableMap::TYPE_PHPNAME)
    {
        $keys = AdminTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) $this->setId($arr[$keys[0]]);
        if (array_key_exists($keys[1], $arr)) $this->setFirstname($arr[$keys[1]]);
        if (array_key_exists($keys[2], $arr)) $this->setLastname($arr[$keys[2]]);
        if (array_key_exists($keys[3], $arr)) $this->setLogin($arr[$keys[3]]);
        if (array_key_exists($keys[4], $arr)) $this->setPassword($arr[$keys[4]]);
        if (array_key_exists($keys[5], $arr)) $this->setAlgo($arr[$keys[5]]);
        if (array_key_exists($keys[6], $arr)) $this->setSalt($arr[$keys[6]]);
        if (array_key_exists($keys[7], $arr)) $this->setRememberMeToken($arr[$keys[7]]);
        if (array_key_exists($keys[8], $arr)) $this->setRememberMeSerial($arr[$keys[8]]);
        if (array_key_exists($keys[9], $arr)) $this->setCreatedAt($arr[$keys[9]]);
        if (array_key_exists($keys[10], $arr)) $this->setUpdatedAt($arr[$keys[10]]);
    }

    /**
     * Build a Criteria object containing the values of all modified columns in this object.
     *
     * @return Criteria The Criteria object containing all modified values.
     */
    public function buildCriteria()
    {
        $criteria = new Criteria(AdminTableMap::DATABASE_NAME);

        if ($this->isColumnModified(AdminTableMap::ID)) $criteria->add(AdminTableMap::ID, $this->id);
        if ($this->isColumnModified(AdminTableMap::FIRSTNAME)) $criteria->add(AdminTableMap::FIRSTNAME, $this->firstname);
        if ($this->isColumnModified(AdminTableMap::LASTNAME)) $criteria->add(AdminTableMap::LASTNAME, $this->lastname);
        if ($this->isColumnModified(AdminTableMap::LOGIN)) $criteria->add(AdminTableMap::LOGIN, $this->login);
        if ($this->isColumnModified(AdminTableMap::PASSWORD)) $criteria->add(AdminTableMap::PASSWORD, $this->password);
        if ($this->isColumnModified(AdminTableMap::ALGO)) $criteria->add(AdminTableMap::ALGO, $this->algo);
        if ($this->isColumnModified(AdminTableMap::SALT)) $criteria->add(AdminTableMap::SALT, $this->salt);
        if ($this->isColumnModified(AdminTableMap::REMEMBER_ME_TOKEN)) $criteria->add(AdminTableMap::REMEMBER_ME_TOKEN, $this->remember_me_token);
        if ($this->isColumnModified(AdminTableMap::REMEMBER_ME_SERIAL)) $criteria->add(AdminTableMap::REMEMBER_ME_SERIAL, $this->remember_me_serial);
        if ($this->isColumnModified(AdminTableMap::CREATED_AT)) $criteria->add(AdminTableMap::CREATED_AT, $this->created_at);
        if ($this->isColumnModified(AdminTableMap::UPDATED_AT)) $criteria->add(AdminTableMap::UPDATED_AT, $this->updated_at);

        return $criteria;
    }

    /**
     * Builds a Criteria object containing the primary key for this object.
     *
     * Unlike buildCriteria() this method includes the primary key values regardless
     * of whether or not they have been modified.
     *
     * @return Criteria The Criteria object containing value(s) for primary key(s).
     */
    public function buildPkeyCriteria()
    {
        $criteria = new Criteria(AdminTableMap::DATABASE_NAME);
        $criteria->add(AdminTableMap::ID, $this->id);

        return $criteria;
    }

    /**
     * Returns the primary key for this object (row).
     * @return   int
     */
    public function getPrimaryKey()
    {
        return $this->getId();
    }

    /**
     * Generic method to set the primary key (id column).
     *
     * @param       int $key Primary key.
     * @return void
     */
    public function setPrimaryKey($key)
    {
        $this->setId($key);
    }

    /**
     * Returns true if the primary key for this object is null.
     * @return boolean
     */
    public function isPrimaryKeyNull()
    {

        return null === $this->getId();
    }

    /**
     * Sets contents of passed object to values from current object.
     *
     * If desired, this method can also make copies of all associated (fkey referrers)
     * objects.
     *
     * @param      object $copyObj An object of \Thelia\Model\Admin (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setFirstname($this->getFirstname());
        $copyObj->setLastname($this->getLastname());
        $copyObj->setLogin($this->getLogin());
        $copyObj->setPassword($this->getPassword());
        $copyObj->setAlgo($this->getAlgo());
        $copyObj->setSalt($this->getSalt());
        $copyObj->setRememberMeToken($this->getRememberMeToken());
        $copyObj->setRememberMeSerial($this->getRememberMeSerial());
        $copyObj->setCreatedAt($this->getCreatedAt());
        $copyObj->setUpdatedAt($this->getUpdatedAt());

        if ($deepCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);

            foreach ($this->getAdminProfiles() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addAdminProfile($relObj->copy($deepCopy));
                }
            }

        } // if ($deepCopy)

        if ($makeNew) {
            $copyObj->setNew(true);
            $copyObj->setId(NULL); // this is a auto-increment column, so set to default value
        }
    }

    /**
     * Makes a copy of this object that will be inserted as a new row in table when saved.
     * It creates a new object filling in the simple attributes, but skipping any primary
     * keys that are defined for the table.
     *
     * If desired, this method can also make copies of all associated (fkey referrers)
     * objects.
     *
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @return                 \Thelia\Model\Admin Clone of current object.
     * @throws PropelException
     */
    public function copy($deepCopy = false)
    {
        // we use get_class(), because this might be a subclass
        $clazz = get_class($this);
        $copyObj = new $clazz();
        $this->copyInto($copyObj, $deepCopy);

        return $copyObj;
    }


    /**
     * Initializes a collection based on the name of a relation.
     * Avoids crafting an 'init[$relationName]s' method name
     * that wouldn't work when StandardEnglishPluralizer is used.
     *
     * @param      string $relationName The name of the relation to initialize
     * @return void
     */
    public function initRelation($relationName)
    {
        if ('AdminProfile' == $relationName) {
            return $this->initAdminProfiles();
        }
    }

    /**
     * Clears out the collAdminProfiles collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addAdminProfiles()
     */
    public function clearAdminProfiles()
    {
        $this->collAdminProfiles = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collAdminProfiles collection loaded partially.
     */
    public function resetPartialAdminProfiles($v = true)
    {
        $this->collAdminProfilesPartial = $v;
    }

    /**
     * Initializes the collAdminProfiles collection.
     *
     * By default this just sets the collAdminProfiles collection to an empty array (like clearcollAdminProfiles());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initAdminProfiles($overrideExisting = true)
    {
        if (null !== $this->collAdminProfiles && !$overrideExisting) {
            return;
        }
        $this->collAdminProfiles = new ObjectCollection();
        $this->collAdminProfiles->setModel('\Thelia\Model\AdminProfile');
    }

    /**
     * Gets an array of ChildAdminProfile objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildAdmin is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildAdminProfile[] List of ChildAdminProfile objects
     * @throws PropelException
     */
    public function getAdminProfiles($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collAdminProfilesPartial && !$this->isNew();
        if (null === $this->collAdminProfiles || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collAdminProfiles) {
                // return empty collection
                $this->initAdminProfiles();
            } else {
                $collAdminProfiles = ChildAdminProfileQuery::create(null, $criteria)
                    ->filterByAdmin($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collAdminProfilesPartial && count($collAdminProfiles)) {
                        $this->initAdminProfiles(false);

                        foreach ($collAdminProfiles as $obj) {
                            if (false == $this->collAdminProfiles->contains($obj)) {
                                $this->collAdminProfiles->append($obj);
                            }
                        }

                        $this->collAdminProfilesPartial = true;
                    }

                    $collAdminProfiles->getInternalIterator()->rewind();

                    return $collAdminProfiles;
                }

                if ($partial && $this->collAdminProfiles) {
                    foreach ($this->collAdminProfiles as $obj) {
                        if ($obj->isNew()) {
                            $collAdminProfiles[] = $obj;
                        }
                    }
                }

                $this->collAdminProfiles = $collAdminProfiles;
                $this->collAdminProfilesPartial = false;
            }
        }

        return $this->collAdminProfiles;
    }

    /**
     * Sets a collection of AdminProfile objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $adminProfiles A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildAdmin The current object (for fluent API support)
     */
    public function setAdminProfiles(Collection $adminProfiles, ConnectionInterface $con = null)
    {
        $adminProfilesToDelete = $this->getAdminProfiles(new Criteria(), $con)->diff($adminProfiles);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->adminProfilesScheduledForDeletion = clone $adminProfilesToDelete;

        foreach ($adminProfilesToDelete as $adminProfileRemoved) {
            $adminProfileRemoved->setAdmin(null);
        }

        $this->collAdminProfiles = null;
        foreach ($adminProfiles as $adminProfile) {
            $this->addAdminProfile($adminProfile);
        }

        $this->collAdminProfiles = $adminProfiles;
        $this->collAdminProfilesPartial = false;

        return $this;
    }

    /**
     * Returns the number of related AdminProfile objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related AdminProfile objects.
     * @throws PropelException
     */
    public function countAdminProfiles(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collAdminProfilesPartial && !$this->isNew();
        if (null === $this->collAdminProfiles || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collAdminProfiles) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getAdminProfiles());
            }

            $query = ChildAdminProfileQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByAdmin($this)
                ->count($con);
        }

        return count($this->collAdminProfiles);
    }

    /**
     * Method called to associate a ChildAdminProfile object to this object
     * through the ChildAdminProfile foreign key attribute.
     *
     * @param    ChildAdminProfile $l ChildAdminProfile
     * @return   \Thelia\Model\Admin The current object (for fluent API support)
     */
    public function addAdminProfile(ChildAdminProfile $l)
    {
        if ($this->collAdminProfiles === null) {
            $this->initAdminProfiles();
            $this->collAdminProfilesPartial = true;
        }

        if (!in_array($l, $this->collAdminProfiles->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddAdminProfile($l);
        }

        return $this;
    }

    /**
     * @param AdminProfile $adminProfile The adminProfile object to add.
     */
    protected function doAddAdminProfile($adminProfile)
    {
        $this->collAdminProfiles[]= $adminProfile;
        $adminProfile->setAdmin($this);
    }

    /**
     * @param  AdminProfile $adminProfile The adminProfile object to remove.
     * @return ChildAdmin The current object (for fluent API support)
     */
    public function removeAdminProfile($adminProfile)
    {
        if ($this->getAdminProfiles()->contains($adminProfile)) {
            $this->collAdminProfiles->remove($this->collAdminProfiles->search($adminProfile));
            if (null === $this->adminProfilesScheduledForDeletion) {
                $this->adminProfilesScheduledForDeletion = clone $this->collAdminProfiles;
                $this->adminProfilesScheduledForDeletion->clear();
            }
            $this->adminProfilesScheduledForDeletion[]= clone $adminProfile;
            $adminProfile->setAdmin(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Admin is new, it will return
     * an empty collection; or if this Admin has previously
     * been saved, it will retrieve related AdminProfiles from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Admin.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return Collection|ChildAdminProfile[] List of ChildAdminProfile objects
     */
    public function getAdminProfilesJoinProfile($criteria = null, $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildAdminProfileQuery::create(null, $criteria);
        $query->joinWith('Profile', $joinBehavior);

        return $this->getAdminProfiles($query, $con);
    }

    /**
     * Clears out the collProfiles collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addProfiles()
     */
    public function clearProfiles()
    {
        $this->collProfiles = null; // important to set this to NULL since that means it is uninitialized
        $this->collProfilesPartial = null;
    }

    /**
     * Initializes the collProfiles collection.
     *
     * By default this just sets the collProfiles collection to an empty collection (like clearProfiles());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @return void
     */
    public function initProfiles()
    {
        $this->collProfiles = new ObjectCollection();
        $this->collProfiles->setModel('\Thelia\Model\Profile');
    }

    /**
     * Gets a collection of ChildProfile objects related by a many-to-many relationship
     * to the current object by way of the admin_profile cross-reference table.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildAdmin is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return ObjectCollection|ChildProfile[] List of ChildProfile objects
     */
    public function getProfiles($criteria = null, ConnectionInterface $con = null)
    {
        if (null === $this->collProfiles || null !== $criteria) {
            if ($this->isNew() && null === $this->collProfiles) {
                // return empty collection
                $this->initProfiles();
            } else {
                $collProfiles = ChildProfileQuery::create(null, $criteria)
                    ->filterByAdmin($this)
                    ->find($con);
                if (null !== $criteria) {
                    return $collProfiles;
                }
                $this->collProfiles = $collProfiles;
            }
        }

        return $this->collProfiles;
    }

    /**
     * Sets a collection of Profile objects related by a many-to-many relationship
     * to the current object by way of the admin_profile cross-reference table.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param  Collection $profiles A Propel collection.
     * @param  ConnectionInterface $con Optional connection object
     * @return ChildAdmin The current object (for fluent API support)
     */
    public function setProfiles(Collection $profiles, ConnectionInterface $con = null)
    {
        $this->clearProfiles();
        $currentProfiles = $this->getProfiles();

        $this->profilesScheduledForDeletion = $currentProfiles->diff($profiles);

        foreach ($profiles as $profile) {
            if (!$currentProfiles->contains($profile)) {
                $this->doAddProfile($profile);
            }
        }

        $this->collProfiles = $profiles;

        return $this;
    }

    /**
     * Gets the number of ChildProfile objects related by a many-to-many relationship
     * to the current object by way of the admin_profile cross-reference table.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      boolean $distinct Set to true to force count distinct
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return int the number of related ChildProfile objects
     */
    public function countProfiles($criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        if (null === $this->collProfiles || null !== $criteria) {
            if ($this->isNew() && null === $this->collProfiles) {
                return 0;
            } else {
                $query = ChildProfileQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByAdmin($this)
                    ->count($con);
            }
        } else {
            return count($this->collProfiles);
        }
    }

    /**
     * Associate a ChildProfile object to this object
     * through the admin_profile cross reference table.
     *
     * @param  ChildProfile $profile The ChildAdminProfile object to relate
     * @return ChildAdmin The current object (for fluent API support)
     */
    public function addProfile(ChildProfile $profile)
    {
        if ($this->collProfiles === null) {
            $this->initProfiles();
        }

        if (!$this->collProfiles->contains($profile)) { // only add it if the **same** object is not already associated
            $this->doAddProfile($profile);
            $this->collProfiles[] = $profile;
        }

        return $this;
    }

    /**
     * @param    Profile $profile The profile object to add.
     */
    protected function doAddProfile($profile)
    {
        $adminProfile = new ChildAdminProfile();
        $adminProfile->setProfile($profile);
        $this->addAdminProfile($adminProfile);
        // set the back reference to this object directly as using provided method either results
        // in endless loop or in multiple relations
        if (!$profile->getAdmins()->contains($this)) {
            $foreignCollection   = $profile->getAdmins();
            $foreignCollection[] = $this;
        }
    }

    /**
     * Remove a ChildProfile object to this object
     * through the admin_profile cross reference table.
     *
     * @param ChildProfile $profile The ChildAdminProfile object to relate
     * @return ChildAdmin The current object (for fluent API support)
     */
    public function removeProfile(ChildProfile $profile)
    {
        if ($this->getProfiles()->contains($profile)) {
            $this->collProfiles->remove($this->collProfiles->search($profile));

            if (null === $this->profilesScheduledForDeletion) {
                $this->profilesScheduledForDeletion = clone $this->collProfiles;
                $this->profilesScheduledForDeletion->clear();
            }

            $this->profilesScheduledForDeletion[] = $profile;
        }

        return $this;
    }

    /**
     * Clears the current object and sets all attributes to their default values
     */
    public function clear()
    {
        $this->id = null;
        $this->firstname = null;
        $this->lastname = null;
        $this->login = null;
        $this->password = null;
        $this->algo = null;
        $this->salt = null;
        $this->remember_me_token = null;
        $this->remember_me_serial = null;
        $this->created_at = null;
        $this->updated_at = null;
        $this->alreadyInSave = false;
        $this->clearAllReferences();
        $this->resetModified();
        $this->setNew(true);
        $this->setDeleted(false);
    }

    /**
     * Resets all references to other model objects or collections of model objects.
     *
     * This method is a user-space workaround for PHP's inability to garbage collect
     * objects with circular references (even in PHP 5.3). This is currently necessary
     * when using Propel in certain daemon or large-volume/high-memory operations.
     *
     * @param      boolean $deep Whether to also clear the references on all referrer objects.
     */
    public function clearAllReferences($deep = false)
    {
        if ($deep) {
            if ($this->collAdminProfiles) {
                foreach ($this->collAdminProfiles as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collProfiles) {
                foreach ($this->collProfiles as $o) {
                    $o->clearAllReferences($deep);
                }
            }
        } // if ($deep)

        if ($this->collAdminProfiles instanceof Collection) {
            $this->collAdminProfiles->clearIterator();
        }
        $this->collAdminProfiles = null;
        if ($this->collProfiles instanceof Collection) {
            $this->collProfiles->clearIterator();
        }
        $this->collProfiles = null;
    }

    /**
     * Return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(AdminTableMap::DEFAULT_STRING_FORMAT);
    }

    // timestampable behavior

    /**
     * Mark the current object so that the update date doesn't get updated during next save
     *
     * @return     ChildAdmin The current object (for fluent API support)
     */
    public function keepUpdateDateUnchanged()
    {
        $this->modifiedColumns[] = AdminTableMap::UPDATED_AT;

        return $this;
    }

    /**
     * Code to be run before persisting the object
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preSave(ConnectionInterface $con = null)
    {
        return true;
    }

    /**
     * Code to be run after persisting the object
     * @param ConnectionInterface $con
     */
    public function postSave(ConnectionInterface $con = null)
    {

    }

    /**
     * Code to be run before inserting to database
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        return true;
    }

    /**
     * Code to be run after inserting to database
     * @param ConnectionInterface $con
     */
    public function postInsert(ConnectionInterface $con = null)
    {

    }

    /**
     * Code to be run before updating the object in database
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preUpdate(ConnectionInterface $con = null)
    {
        return true;
    }

    /**
     * Code to be run after updating the object in database
     * @param ConnectionInterface $con
     */
    public function postUpdate(ConnectionInterface $con = null)
    {

    }

    /**
     * Code to be run before deleting the object in database
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preDelete(ConnectionInterface $con = null)
    {
        return true;
    }

    /**
     * Code to be run after deleting the object in database
     * @param ConnectionInterface $con
     */
    public function postDelete(ConnectionInterface $con = null)
    {

    }


    /**
     * Derived method to catches calls to undefined methods.
     *
     * Provides magic import/export method support (fromXML()/toXML(), fromYAML()/toYAML(), etc.).
     * Allows to define default __call() behavior if you overwrite __call()
     *
     * @param string $name
     * @param mixed  $params
     *
     * @return array|string
     */
    public function __call($name, $params)
    {
        if (0 === strpos($name, 'get')) {
            $virtualColumn = substr($name, 3);
            if ($this->hasVirtualColumn($virtualColumn)) {
                return $this->getVirtualColumn($virtualColumn);
            }

            $virtualColumn = lcfirst($virtualColumn);
            if ($this->hasVirtualColumn($virtualColumn)) {
                return $this->getVirtualColumn($virtualColumn);
            }
        }

        if (0 === strpos($name, 'from')) {
            $format = substr($name, 4);

            return $this->importFrom($format, reset($params));
        }

        if (0 === strpos($name, 'to')) {
            $format = substr($name, 2);
            $includeLazyLoadColumns = isset($params[0]) ? $params[0] : true;

            return $this->exportTo($format, $includeLazyLoadColumns);
        }

        throw new BadMethodCallException(sprintf('Call to undefined method: %s.', $name));
    }

}
