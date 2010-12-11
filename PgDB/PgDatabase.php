<?php
/**
 *  PgDB package for working with PostgreSQL 8.0+ databases
 *
 *  Before you begin, you should be aware that this package only works with PHP 
 *  5.3+, and Postgres 8.0+. While it doesn't make use of many advanced 
 *  Postgres features directly, it does use namespaces, which means it will not 
 *  work with PHP <5.3. Namespaces are a good thing, though, so you should be 
 *  happy about this. :)
 *
 *  This package provides two classes for simplifying your PostgreSQL 
 *  experience. The main class is PgDatabase. It handles the connection, and 
 *  basic query template system with _very_ basic sanitizing. It is your holy 
 *  duty as a diligent developer to sanitize the input values before you pass 
 *  them as query variables.
 *
 *  Here's a basic introduction to get you started.
 *
 *  First the import.
 *
 *      require_once 'your_includes/PgDB/PgDatabase.php';
 *
 *  Next you will build the options array.
 *
 *      $opts = array({'hostname'=>'localhost', 'username'=>'postgres', 
 *      'dbname'=>'mydatabase'});
 *
 *  The reason options are shoved in an array is to make it possible to just 
 *  read them from an .ini file and use parse_ini_file() to read them, and then 
 *  directly pass the results to PgDatabase class. Let's initialize our 
 *  dtabase.
 *
 *      $db = \PgDB\PgDatabase($opts);
 *
 *  That's it. You're all set up... Oh. No, wait. You have to connect.
 *
 *      $db->connect();
 *
 *  Ok, now you're all set up. Time to do some queries:
 *
 *      try {
 *          $results = $db->query('SELECT * FROM sometable;');
 *      } catch (\PgDB\DatabaseError $e) {
 *          echo 'Something bad happened: '.$e->getMessage();
 *      }
 *
 *  When dealing with database queries, it's always a good idea to handle the 
 *  exceptions. All the cool guys do it, and so should you. There are many 
 *  exeptions in here for your handling fun. I suggest you read the section on 
 *  {@link Exceptions}.
 *
 *  Finally, if you care, you can close the connection:
 *
 *      $db->disconnect();
 *
 *  This is not really necessary, since the connection will be automatically 
 *  closed once the $db object is destroyed.
 *
 *  The query template language is simple string interpolation type. In any 
 *  query, you can insert PHP-like variables in the form of $varname, and you 
 *  can supply an array of name-value pairs to use for replacing those 
 *  placeholder variables. A variable can appear multiple times in a query 
 *  template. To avoid collision with real defined variables in your code, 
 *  surround all queries with single quotes. To escape single quotes that are 
 *  part of the query, you can use the backslash escape character. Here's an 
 *  example:
 *
 *     'SELECT * FROM $table WHERE name = \'$name\';'
 *
 *  The data array that matches the above query may look like this:
 *
 *      array('table'=>'sometable', 'name'=>'sammy')
 *
 *  Basic sanitizing is performed on the query variables, but quoting is NOT 
 *  done. You will have to quote your variables yourself. The reason for this 
 *  is that variables are not necessarily SQL values. They may be table names, 
 *  and raw SQL query. Therefore, a more complex abstraction layer is needed in 
 *  order to properly sanitize different parts of queries. And I'll not be the 
 *  one to do it. ;)
 *
 *  More about the usage of actual class can be found in documentation on those 
 *  classes.
 *
 *  @package PgDB
 *  @author Branko Vukelic <studio@brankovukelic.com>
 *  @version 0.1
 *  @license GPLv3
 */
namespace PgDB;

// Postgres error codes as per:
// http://www.postgresql.org/docs/8.4/static/errcodes-appendix.html
// Only commonly used ones are listed.
// Codes are valid for Postgres 8.4.x
// General
define('SUCCESS', '00000');
define('NODATA', '02000');
// Integrity
define('INTEGRITY_CONSTRAINT_VIOLATION', '23000');
define('RESTRICT_VIOLATION', '23001');
define('NOT_NULL_VIOLATION', '23502');
define('FOREIGN_KEY_VIOLATION', '23503');
define('UNIQUE_VIOLATION', '23505');
define('CHECK_VIOLATION', '23514');
// Common errors
define('SYNTAX_ERROR', '42601');
define('UNDEFINED_COLUMN', '42703');
define('UNDEFINED_TABLE', '42P01');

/**
 *  Connection error exception
 *
 *  This exception is raised when the query fails to connect to the database.
 *
 *  @package PgDB
 *  @subpackage Exceptions
 */
class ConnectionError extends \Exception {}

/**
 * Generic exception class
 *
 * This exception will be raised when PgDatabase class is unaware of the real 
 * problem. The SQLSTATUS code will be returned embedded in the message (it's 
 * usually not useful to handle the SQLSTATUS code programmatically, except 
 * when replacing it with a PHP exception), and the result resource returned by 
 * pg_get_result() will be available through the getResource() method.
 *
 * All PgDatabase exceptions inherit the properties of the DatabaseError 
 * exception, so you can expect to find the getResource() method on all of 
 * them.
 *
 * @package PgDB
 * @subpackage Exceptions
 */
class DatabaseError extends \Exception {
    
    protected $result;

    public function __construct($message, $result) {
        parent::__construct($message, 1);
        $this->result = $result;
    }

    public function getResource() {
        return $this->result;
    }
}

/**
 *  Integrity error exception
 *
 *  This exception is triggered when Postgres returns the INTEGRIRTY VIOLATION 
 *  exception. This usually means that your query broke some (or all) of the 
 *  constraints on your table.
 *
 *  @package PgDB
 *  @subpackage Exceptions
 */
class IntegrityError extends DatabaseError {}

/**
 *  Restrict error exception
 *
 *  This exception is triggered when Postgres returns the RESTRICT VIOLATION 
 *  exception. This usually means that a cascading removal of records was 
 *  performed when not allowed by RESTRICT constraint.
 *
 *  @package PgDB
 *  @subpackage Exceptions
 */
class RestrictError extends DatabaseError {}

/**
 *  Not NULL error
 *
 *  This exception is triggered when Postgres returns the NOT NULL VIOLATION 
 *  exception. When you have a NOT NULL constraint on a column, and try to 
 *  assign a NULL value, you will see this exception raised.
 *
 *  @package PgDB
 *  @subpackage Exceptions
 */
class NotNullError extends DatabaseError {}

/**
 *  Foreign key error
 *
 *  This exception is triggered when a foreign key constraint is violated.
 *
 *  @package PgDB
 *  @subpackage Exceptions
 */
class ForeignKeyError extends DatabaseError {}

/**
 *  Unique error exception
 *
 *  This exception is triggered when Postgres returns a UNIQUE VIOLATION 
 *  exception. This means thata UNIQUE constraints was placed on a column, and 
 *  you tried to insert a value that already exists. You can either test 
 *  uniqueness beforehand (which means you will be queriying the database at 
 *  least twice on all inserts), or you can handle this exception, and test 
 *  uniqueness after it is raised. The latter is more efficient, as it requires 
 *  multiple queries only when the uniqueness constraint is actually violated.
 *
 *  If you at any moment query the database for fields that may break 
 *  uniqueness later, you may consider caching the values, and testing those to 
 *  prevent UNIQUE VIOLATION exception. However, this is not a fool-proof 
 *  method, as other threads may have already written data that outdates the 
 *  cached values.
 *
 *  @package PgDB
 *  @subpackage Exceptions
 */
class UniqueError extends DatabaseError {}

/**
 *  Check error exception
 *
 *  This exception is triggered when Postgres returns a CHECK VIOLATION 
 *  exception. This happens when a column has a CHECK constraint that your 
 *  query is violating.
 *
 *  @package PgDB
 *  @subpackage Exceptions
 */
class CheckError extends DatabaseError {}

/** 
 *  Syntax error exception
 *
 *  This exception is most useful in development. You should probably NOT trap 
 *  this exception in any situation. The only possible solution to this error 
 *  is to actually fix your query.
 *
 *  @package PgDB
 *  @subpackage Exceptions
 */
class SyntaxError extends DatabaseError {}

/**
 *  Undefined column error exception
 *
 *  This exception is triggered when Postgres returns a UNDEFINED COLUMN 
 *  exception. If your query contains references to columns that are not 
 *  defined, this exception will be triggered. It is also useful to check for 
 *  any unquoted strings in your queries. Postgres expects you to always 
 *  enclose string values in single quotes (double quotes are not allowed). 
 *  Otherwise, strings will be treated as column or table references.
 *
 *  @package PgDB
 *  @subpackage Exceptions
 */
class UndefinedColumnError extends DatabaseError {}

/**
 *  PgDBNotImplemented error
 *
 *  This error is triggered by either the PgDatabase or the PgResultSet class as 
 *  a way to notify you of unimplemented features. If you try to call a method 
 *  that is not yet implemented, this exception will be raised.
 *
 *  The only scenario that I imagine handling this exception can be of any use 
 *  is when you know beforehand that this feature will be implemented soon 
 *  (perhaps you submitted a patch, and I said "Cool, let's do this"?) and you 
 *  hadnle it to provide crutches until it's finished. Highly unlikely 
 *  scneario, though. :)
 *
 *  @package PgDB
 *  @subpackage Exceptions
 */
class PgDBNotImplemented extends \Exception {}

/**
 *  Result set error
 *
 *  This is a generic exception raised by the PgResultSet class. For now it 
 *  means something bad happened. :P
 *
 *  @package PgDB
 *  @subpackage Exception
 *  @todo Implement a more meaningful set of exceptions
 */
class PgDBResultSetError extends \Exception {}


/**
 *  Class that encapsulates the result resource
 *
 *  The pg_* functions that come with PHP are all nice, but they aren't meant 
 *  for human consumption (correct me if I'm wrong). Therefore, the PgResultSet 
 *  class encapsulates some of their (otherwise great) utility and provides a 
 *  more human-friendly interface. However, you should be aware that this does 
 *  not provide the full power of pg_* functions, so you should read the 
 *  documentation and see what's missing.
 *
 *  This class is not used directly, but returned as a result of calling a 
 *  query on the PgDatabase objects. For more details on how to use the result 
 *  sets, look at the methods it exposes.
 *
 *  @package PgDB
 *  @subpackage Result set
 */
class PgResultSet {

    /**
     *  The raw result resource
     *
     *  This property contains the raw resource object returned by 
     *  pg_get_result() call made within the PgDatabase objects.
     *
     *  @access protected
     *  @var object
     */
    protected $rawResult;

    /**
     *  Result set size
     *
     *  Contains the number of records in the result set.
     *
     *  @access protected
     *  @var integer
     */
    protected $length;

    /**
     *  Cached objects
     *
     *  When retrieving the query set as objects using the {@link allObjects()} 
     *  method, the created objects are caches in this property, so subsequent 
     *  calls to allObjects() will be significantly cheaper.
     *
     *  @access protected
     *  @var array
     */
    protected $objSet = NULL;

    /**
     *  Object counter
     *
     *  The internal counter used to track the last returned object.
     *
     *  @access protected
     *  @var integer
     */
    protected $objectCounter = NULL;


    /**
     *  All result rows as an array
     *
     *  As soon as the PgResultSet object is created, it loads all results as 
     *  an array. This is done so that multiple calls to methods like {@link 
     *  all()} or {@link last()} can be made at any time.
     *
     *  @access protected
     *  @var array
     */
    protected $resultArray = array();

    /**
     *  Array counter
     *
     *  The internal counter that keeps track of the last returned records, 
     *  used by the {@link next()} method.
     *
     *  @access protected
     *  @var integer
     */
    protected $arrayCounter = NULL;

    public function __construct($result) {
        $this->rawResult = $result;
        $this->length = \pg_num_rows($result);
        // This gets all results as arrays, and we use this instead of the raw 
        // result resource. However, this also means the result resource will 
        // be empty.
        $this->resultArray = \pg_fetch_all($result);
    }


    /**
     *  Create an instance of a class using row data
     *
     *  The $row argument is used to specify the row that will be used to 
     *  instantiate the calss. The $classname is a string containing the class 
     *  name.
     *
     *  @param string $classname Name of the class
     *  @param integer $row The row index
     *  @return object
     *  @access protected
     */
    protected function makeInstance($classname, $row) {
        echo "Row: $row\n";
        $constructorArgs = $this->resultArray[$row];
        $reflector = new \ReflectionClass($classname);
        return $reflector->newInstanceArgs($constructorArgs);
    }

    /**
     *  Return a row as an object
     *
     *  You can call getObject() to retrieve a single row from the result set 
     *  as an object. Provided you have a class whose constructor takes 
     *  arguments named like table columns, this method can instantiate objects 
     *  of the class and pass the values to the constructor.
     *
     *  If the $row parameter is omitted, the first object is returned. 
     *  Therefore, you can use this as a shortcut to get the first object in 
     *  the record set.
     *
     *  @param string $classname Name of the class to use for creating new 
     *  objects
     *  @param integer $row The row number of the record to return
     *  @return object
     *  @access public
     */
    public function getObject($classname, $row=0) {
        return $this->makeInstance($classname, $row);
    }

    /**
     *  Returns the next object from the set
     *
     *  When called multiple times, this method returns the recors from the 
     *  result set one by one. It will always return the object that comes 
     *  after the last object retrieved. This is true even if you retrieved 
     *  objects using other methods. It returns no objects if you've called 
     *  allObjects(). See the description of the {@link getObject()} method on 
     *  how objects are created.
     *
     *  @param string $classname Name of the class to use for creating the 
     *  objects
     *  @return object
     *  @access public
     */
    public function nextObject($classname) {
        if (is_null($this->objectCounter)) {
            $this->objectCounter = 0;
        }
        else {
            $this->objectCounter += 1;
        }
        if ($this->objectCounter < $this->length) {
            return $this->makeInstance($classname, $this->objectCounter);
        }
        return NULL;
    }

    /**
     *  Returns the last object from the set
     *
     *  This method works the same way as {@link getObject()}, except it 
     *  returns the last object from the record set.
     *
     *  @param string $classname Name of the class to use for creating the 
     *  object
     *  @return object
     *  @access public
     */
    public function lastObject($classname) {
        return $this->makeInstance($classname, $this->length - 1);
    }

    /**
     *  Return all records as objects
     *
     *  This method works the same way as {@link getObject()} except that it 
     *  returns an array containing all objects in the result set.
     *
     *  The returned array has numeric index.
     *
     *  @param string $classname Name of the class to use for creating the 
     *  objects
     *  @return array
     *  @access public
     */
    public function allObjects($className) {
        if ($this->objSet) {
            return $this->objSet;
        }
        $this->objSet = array();
        for ($i=0; $i < $this->length; $i++) { 
            $this->objSet[] = $this->makeInstance($clasname, $i);
        }
        return $this->objSet;
    }

    /**
     *  Return all records as an array of associative arrays
     *
     *  Every record in the result set is converted into an array whose keys 
     *  match the column names. For example, to access a record, you may do 
     *  something like this:
     *
     *      $arr = $results.all();
     *      echo $arr[0]['name'];
     *      echo $arr[10]['address'];
     *      ....
     *  
     *  This is probably a more efficient way to retrieve records as it is a 
     *  simple thin wrapper around the native pg_fetch_all() function.
     *
     *  @return array
     *  @access public
     */
    public function all() {
        return $this->resultArray;       
    }

    /**
     *  Return a single record as an associative array
     *
     *  This method returns a single row from the result set as an associative 
     *  array whose keys match the column names. If the optional $row parameter 
     *  is omitted, the first record is returned.
     *
     *  @param integer $row The number of the row to return.
     *  @access public
     */
    public function get($row=0) {
        return $this->resultArray[$row];
    }

    /**
     *  Return the next record as an associative array
     *
     *  This method can be called sequentially to return records in the result 
     *  set one by one. You should note that the next row is always the next 
     *  row regardless of the method you've called, so a call to {@link all()} 
     *  may cause this method to return no records. See the documentation on 
     *  {@link get()} for more information on the return format.
     *
     *  @return array
     *  @access public
     */
    public function next() {
        if (is_null($this->arrayCounter)) {
            $this->arrayCounter = -1;
        }
        else {
            $this->arrayCounter += 1;
        }
        if ($this->arrayCounter < $this.length) {
            return $this->resultArray[$this->arrayCounter];
        }
        return NULL;
    }

    /**
     *  Return the last record as an associative array
     *
     *  This method returns the last record in the result set as an 
     *  associative array. See the documentation on {@link get()} for more 
     *  information on the return value.
     *
     *  @return array
     *  @access public
     */
    public function last() {
        return $this->resultArray[$this->length - 1];
    }

    /**
     *  Returns the size of the result set
     *
     *  @return integer
     *  @access public
     */
    public function getLength() {
        return $this->length;
    }

    /**
     *  Returns true if result set is empty
     *  
     *  @return boolean
     *  @access public
     */
    public function isEmpty() {
        return $this->length == 0;
    }

    /**
     *  Return the raw result for use with pg_* functions
     *
     *  @return object
     *  @access public
     */
    public function getRawResult() {
        return $this->rawResult;
    }

}

/**
 *  Connection management and query processing
 *
 *  The PgDatabase is the class that you will include (require) in your project 
 *  to server as the primary interface to the database. It is in charge of 
 *  connections, queries, and connection- and query-level error handling.
 *
 *  You can read more about this class in the documentation of its parameters 
 *  and methods.
 *
 *  Not that it is possible to connect to multiple databases by creating 
 *  multiple instances of the PgDatabase class. It is, however, not possible to 
 *  connect to multiple databases using the single object. This will likely 
 *  change in the future.
 *
 *  @package PgDB
 *  @subpackage PgDatabase
 */
class PgDatabase {

    /**
     *  Name of the database host (defaults to 'localhost')
     *
     *  @access protected
     *  @var string
     */
    protected $hostname;

    /**
     *  Port on which the database is listenning (defaults to '5432')
     *
     *  @access protected
     *  @var string
     */
    protected $port;

    /**
     *  Database name (defaults to the default username)
     *
     *  You should at least specify this parameter in the options array.
     *  
     *  @access protected
     *  @var string
     */
    protected $database;

    /** 
     *  Username (defaults to the name of HTTP server's username)
     *
     *  It is considered the best option to use the HTTP server's user to 
     *  connect to the database, but this is not required if appropriate 
     *  permissions are set up in the Postgres configuration. See Postgres' 
     *  manual for more information on access control.
     *
     *  Also, make sure that the user has CONNECT permissions for the database 
     *  you will be connecting to.
     *
     *  @access protected
     *  @var string
     */
    protected $user;

    /**
     *  Database users's password (defaults to empty)
     *
     *  @access protected
     *  @var string
     */
    protected $password;

    /**
     *  Other options passed to Postgres (as described in pg_connect() docs)
     *
     *  @access protected
     *  @var string
     */
    protected $options;

    /**
     *  Stored value of the connection string
     *
     *  This string is used if the initialized object needs to connect multiple 
     *  times during the same response cycle.
     *
     *  @access protected
     *  @var string
     */
    protected $connectionString;

    /**
     *  Connection object
     *
     *  Once the connection is established, the connection object is stored in 
     *  this property.
     *
     *  @access protected
     *  @var object
     */
    protected $connection;

    public function __construct($options) {
        if (isset($options['hostname'])) {
            $hostname = $options['hostname'];
            $cstring[] = "hostname=$hostname";
            $this->hostname = $hostname;
        }
        if (isset($options['username'])) {
            $user = $options['username'];
            $cstring[] = "user=$user";
            $this->user = $user;
        }
        if (isset($options['port'])) {
            $port = $options['port'];
            $cstring[] = "port=$port";
            $this->port = $port;
        }
        if (isset($options['dbname'])) {
            $database = $options['dbname'];
            $cstring[] = "dbname=$database";
            $this->database = $database;
        }
        if (isset($options['user'])) {
            $user = $options['user'];
            $cstring[] = "user=$user";
            $this->user = $user;
        }
        if (isset($options['password'])) {
            $password = $options['password'];
            $cstring[] = "password=$password";
            $this->password = $password;
        }
        if (isset($options['options'])) {
            $misc = $options['options'];
            $cstring[] = "options='$misc'";
            $this->options = $misc;
        }
        $this->connectionString = \implode(' ', $cstring);
    }

    public function __destruct() {
        if ($this->connection) {
            $this->disconnect();
        }
    }

    /**
     *  Connect to the database using the options specified during creation
     *
     *  Note that the PgDatabase object does not automatically connect when 
     *  initialized. You can (and need to) connect by calling this method 
     *  before issuing any queries.
     *  
     *  @return object The connection object created during connection
     *  @access public
     */
    public function connect() {
        $this->connection = \pg_pconnect($this->connectionString)
            or die ('Error connecting to database: ' . \pg_last_error());
        \pg_set_error_verbosity($this->connection, \PGSQL_ERRORS_VERBOSE);
        return $this->connection;
    }

    /**
     *  Disconnect from the database
     *
     *  While the PgDatabase object doesn't connect automatically when created, 
     *  it does automatically disconnect when destroyed. So you don't really 
     *  need to explicitly disconnect it, unless you know you need to.
     *
     *  @return NULL
     *  @access public
     */
    public function disconnect() {
        \pg_close($this->connection);
        $this->connection = \NULL;
    }

    /**
     *  Execute a query
     *
     *  This method is used to actually execute a query.
     *
     *  If the connection is currently being used, this method will try 5 times 
     *  before throwing an exception. Each time it will wait for one second 
     *  (not configurable at this moment) between each try. 
     *
     *  The $query parameter should contain a single-quoted string. The sting 
     *  can either be a raw SQL query, or a query template. Template uses 
     *  simple string interpolation method and supports PHP-style variables in 
     *  the $varname format. If the template is used, you also need to supply 
     *  the $values array, which contains name-value pairs that correspond to 
     *  template variables used in the $query. An example query may look like 
     *  this:
     *
     *      $query:
     *          'SELECT * FROM $table WHERE $column = \'$value\';'
     *
     *      $values:
     *          {'table'=>'foo', 'column'=>'bar', 'value'=>'baz'}
     *
     *  Never quote string in the $vales array. This method employs a simple 
     *  sanitizing for string values, so any strings that you pass in will 
     *  have single quote characters escaped. On the other hand, you must quote 
     *  any string values in the $query yourself as in the example above. Since 
     *  $query must be a single-quoted string, use backslashes to escape the 
     *  single quotes used for quoting strings.
     *
     *  Also note that you may use the same variable in multiple places in your 
     *  query template.
     *
     *  If the query fails for any reason, an appropriate exception will be 
     *  raised as detailed in {@link Exceptions} section.
     *
     *  On success, the query() method returns a {@link PgResultSet} object.
     *
     *  @param string $query Raw SQL query or query template
     *  @param array $value Values for template variables
     *  @return object
     *  @access public
     */
    public function query($query, $values=NULL, $returnRaw=FALSE) {
        $query_sent = FALSE;
        if ($this->connection) {
            if (\is_array($values)) {
                $query = $this->interpolate($query, $values);
            }
            $sent = FALSE;
            for ($i=0; $i < 5; $i++) { 
                if (!\pg_connection_busy($this->connection)) {
                    \pg_send_query($this->connection, $query);
                    $sent = TRUE;
                    break;
                }
                \sleep(1); // Pause 1 second in hopes of connection freeing up
            }
            if (!$sent) {
                throw new ConnectionError('Connection timed out');
            }
            $result = \pg_get_result($this->connection);
            $status = \pg_result_error_field($result, PGSQL_DIAG_SQLSTATE);
            switch ($status) {
            case '': // Weird Postgres doesn't report SUCESS, right?
                if ($returnRaw) {
                    return $result;
                }
                $resultSet = new PgResultSet($result);
                return $resultSet;
                break;
            case NODATA:
                return NULL;
                break;
            case INTEGRITY_CONSTRAINT_VIOLATION:
                throw new IntegrityError("Integrity violation on: `$query`", $result);
                break;
            case RESTRICT_VIOLATION:
                throw new RestrictError("Restrict violation on: `$query`", $result);
                break;
            case NOT_NULL_VIOLATION:
                throw new NotNullError("Not NULL violation on: `$query`", $result);
                break;
            case FOREIGN_KEY_VIOLATION:
                throw new ForeignKeyError("Foreign key violation on: `$query`", $result);
                break;
            case UNIQUE_VIOLATION:
                throw new UniqueError("Unique violation on: `$query`", $result);
                break;
            case CHECK_VIOLATION:
                throw new CheckError("Check violation on: `$query`", $result);
                break;
            case SYNTAX_ERROR:
                throw new SyntaxError("Syntax error on: `$query`", $result);
            case UNDEFINED_COLUMN:
                throw new UndefinedColumnError("Undefined column in: `$query`", $result);
            default:
                throw new DatabaseError("Error code `$status` on: `$query`", $result);
            }
        } else {
            die ('Must be connected to a database before querying.');
        }
    }

    /**
     *  Interpolate template variables
     *
     *  Used by {@link query()} to perform the template-value interpolation.
     *
     *  @param string $query Query template
     *  @param array $values Variable-name-value pairs
     *  @return string
     *  @access protected
     */
    protected function interpolate($query, $values) {
        $interpolated = $query;
        foreach ($values as $key => $value) {
            $value = $this->sanitizeValue($value);
            $interpolated = \str_replace('$'.$key, $value, $interpolated);
        }
        return \str_replace('$$', '$', $interpolated);
    }

    /**
     *  Simple-dirty sanitizing
     *
     *  Quotes and converts strings to UTF-8 strings, leaving all other data 
     *  types intact. It is used internally by {@link interpolate()}.
     *
     *  @param mixed $value
     *  @return mixed
     *  @access protected
     */
    protected function sanitizeValue($value) {
        // Just basic sanitizing. We assume all data has been cleared already 
        // someplace else. If it hasn't SHAME ON YOU.
        if (\is_string($value)) {
            return \pg_escape_string(\utf8_encode($value));
        }
        else {
            return $value;
        }
    }

    /**
     *  Return the connection object
     *
     *  This method can be used to gain access to the connection object the 
     *  PgDatabase object is using internally. This can be useful if you want 
     *  to take advantage of the pg_* functions for which the equivalent 
     *  functionality doesn't exist in PgDatabase class.
     *
     *  @return object
     *  @access public
     */
    public function getConnection() {
        return $this->connection;
    }
}

/**************************************************************
 *  Example usage (and test)
 *
 *  Create a database 'test', and create a table called 'test' using the 
 *  following query:
 *
 *      CREATE TABLE test (
 *          name varchar primary key,
 *          age integer
 *      );
 *
 *  The example assumes you'll be connected using 'postgres' user (the default 
 *  user). You can change that by modifying the $opts keys below.
 *
 *  Note that these tests WILL run if you test your other scripts that use this 
 *  file in a CLI environment. It's best to just comment out the if block below 
 *  in such cases.
 */

if (defined('STDIN')) {
    // Some basic options
    $opts = array(
        'dbname' => 'test',
        // Uncomment and modify the following line if you need to
        // 'password' => 'yourpass',
        'user' => 'postgres'
    );

    // Initialize the database object
    $db = new PgDatabase($opts);

    // Connect to the database
    $db->connect();

    // Confirm that we're connected:
    assert($db->getConnection());

    // Connection status should be PGSQL_CONNECTION_OK:
    assert(\pg_connection_status($db->getConnection()) == PGSQL_CONNECTION_OK);

    // Confrim that we're connected to a database 'test':
    assert(\pg_dbname($db->getConnection()) == 'test');

    // Let's get anything that is already in the database
    // Look, ma, no template!
    $results = $db->query('SELECT * FROM test;');

    // Let's find out how many results were returned
    $currentNumberOfRows = $results->getLength();
    echo "We have $currentNumberOfRows rows in the database.\n";

    // Here's our insert query with two variables, $name and $age:
    $query = 'INSERT INTO test (name, age) VALUES (\'$name\', $age);';

    // Let's loop and insert multiple records
    for ($i=$currentNumberOfRows + 1; $i < $currentNumberOfRows + 21; $i++) {
        // We assign the results to a variable to we can do fun stuff later
        $results = $db->query($query, array('name'=>'Bob '.$i, 'age'=>$i));
        // We can test the numbers of rows affected:
    }

    // Let's do a new select now:
    $query = 'SELECT * FROM test LIMIT 5;';
    $results = $db->query($query);

    // Make sure only 5 items were returned:
    assert($results->getLength() == 5);

    echo 'Got '.$results->getLength()." rows, expected 5\n";
    $resultArray = $results->all();

    echo "First bob: ".$resultArray[0]['name']."\n";
    $lastBob = $results->last();
    echo "Last bob: ".$lastBob['name']."\n";
    // Let's call this a 'model' class.
    // The idea is to populate the new instances with the data from the database 
    // automagically, by just passing the class name to the *Object() methods.
    class MyBob {
        // The properties need not be public, but we use public properties
        // so we can avoid writing accessors, just for demonstration purposes, 
        // obviously.
        public $name; // This matches the table's column
        public $age; // As does this one.
        // You can have more properties, but currently there is no way of 
        // specifying extra properties. That'll be implemented in 0.3 release.
        public function __construct($name, $age) {
            $this->name = $name;
            $this->age = $age;
        }
    }
    // Watch out: You must provide the full namespace when calling getObject()!
    $firstBob = $results->getObject('PgDB\MyBob', 0);
    echo "First again: ".$firstBob->name."\n";
    $nextBob = $results->getObject('PgDB\MyBob', 1);
    echo "This is the 2nd Bob: ".$nextBob->name."\n";
    $theSameFirstBob = $results->get();
    // Use whichever form you prefer, each has its advantages, and they are 
    // both equally fast (or slow):
    assert($theSameFirstBob['name'] == $firstBob->name);
    // Just to make sure we have 20 new rows:
    $results = $db->query('SELECT * FROM test;');
    assert($results->getLength() == $currentNumberOfRows + 20);
    // Now, let's use the $returnRaw flag. Note the NULL instead of $values.
    $results = $db->query('SELECT * FROM test LIMIT 1;', NULL, $returnRaw=TRUE);
    echo var_dump($results);
    echo "\n";
    $firstBob = \pg_fetch_array($results, 0);
    echo "First Bob from raw: ".$firstBob['name']."\n";

}

?>
