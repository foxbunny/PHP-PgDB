<?php
/**
 *  Miscellaneous return value parsing utilities
 *
 *  The PHP Postgres extension returns everything as strings. This is done to 
 *  preserve data where PHP data types may not have sufficient capacity to 
 *  represent the return values (e.g, 32-bit PHP). As automatic casting may 
 *  require database table introspecton or may cause data loss, it is simply not 
 *  done for you. This class provides methods you can use to do semi-automatic 
 *  casting. It is assumed that you know what return value is desired, and you 
 *  can call the appropriate method to do the cast.
 *
 *  Read individual method documentation for more information.
 *
 *  @package PgDB
 *  @author Branko Vukelic <studio@brankovukelic.com>
 *  @version 0.2
 *  @license GPLv3
 */

namespace PgDB;

define('SQLTRUE', 't');
define('SQLFALSE', 'f');


/**
 *  PgParser exception class
 *
 *  This exception is raised on parse errors. It is mostly helpful for 
 *  development and prevents unexpected values from slipping through.
 *
 *  It keeps the original value in the $value property, and you can call the 
 *  {@link getValue()} method to retrieve it.
 *
 *  @package PgDB
 *  @subpackage Exceptions
 */
class PgParserError extends \Exception {
    protected $value;

    public function __construct($msg, $val=NULL) {
        $this->value = $val;
        parent::__construct($msg, 1);
    }

    /**
     *  Returns the original value that triggered this exception
     *
     *  @return mixed
     *  @access public
     */
    public function getValue() {
        return $this->value;
    }
}

/**
 *  Parser utilities class
 *
 *  This class contains the static methods that can be used for parsing the 
 *  return values.
 *
 *  @package PgDB
 *  @subpackage PgParser
 */
class PgParser {

    /**
     *  Boolean value cast
     *
     *  Typically, Postgres will return 'f' or 't' for boolean values. Any 
     *  other value will cause bool() to trigger the {@link PgParserError} 
     *  exception.
     *  
     *  @param string $val The return value from a query
     *  @return boolean
     *  @access public
     */
    public static function bool($val) {
        switch ((string) $val) {
        case SQLTRUE:
            return TRUE;
            break;
        case SQLFALSE:
            return FALSE;
            break;
        default:
            throw new PgParserError('Not a boolean value', $val);
        }
    }

    /**
     *  Integer cast
     *
     *  Apart from castingt the incoming string to an integer value, it also 
     *  checks that the input value is an integer.
     *
     *  @param string $val The return value from a query
     *  @return integer
     *  @access public
     */
    public static function int($val) {
        if (preg_match('/^[+-]?\d*$/', $val)) {
            return intval($val);
        }
        throw new PgParserError('Not an integer value', $val);
    }

}

?>
