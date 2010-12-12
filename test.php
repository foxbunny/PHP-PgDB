<?php

require dirname(__FILE__).'/PgDB/PgDatabase.php';

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
    // Time the test
    $mtime = microtime();
    $mtime = explode(" ",$mtime);
    $mtime = $mtime[1] + $mtime[0];
    $starttime = $mtime;
    // Some basic options
    $opts = array(
        'dbname' => 'test',
        // Uncomment and modify the following line if you need to
        // 'password' => 'yourpass',
        'user' => 'postgres'
    );

    // Initialize the database object
    $db = new PgDB\PgDatabase($opts);

    // Connect to the database
    $db->connect();

    // Confirm that we're connected:
    assert($db->getConnection());

    // Connection status should be PGSQL_CONNECTION_OK:
    assert(pg_connection_status($db->getConnection()) == PGSQL_CONNECTION_OK);

    // Confrim that we're connected to a database 'test':
    assert(pg_dbname($db->getConnection()) == 'test');

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
    $firstBob = $results->getObject('MyBob', 0);
    echo "First again: ".$firstBob->name."\n";
    $nextBob = $results->getObject('MyBob', 1);
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
    $firstBob = pg_fetch_array($results, 0);
    echo "First Bob from raw: ".$firstBob['name']."\n";
    // Let's get something more exotic out of the DB
    $age = 15;
    $query = 'SELECT COUNT(name) as older_than FROM test WHERE age > $age;';
    $results = $db->query($query, array('age' => $age));
    $count = $results->get();
    // There should be just one record
    assert($results->getLength() == 1);
    echo "There are ".$count['older_than']." Bobs older than $age\n";
    // How about exception handling?
    try {
        $db->query('BAD SQL');
    }
    catch (PgDB\SyntaxError $e){
        echo "We had a syntax error, alright!\n";
    }

    // Another test with classes. This time with extra params.
    $results = $db->query('SELECT * FROM test LIMIT 1;');
    class MyOtherBob {
        public $name;
        public $age;
        public $somethingElse;
        public function __construct($name, $age, $somethingElse) {
            $this->name = $name;
            $this->age = $age;
            $this->somethingElse = $somethingElse;
        }
    }
    $bob = $results->getObject('MyOtherBob', 0, array('somethingElse' => 'this'));
    assert($bob->somethingElse == 'this');
    echo var_dump($bob); 

    // Stop timer
    $mtime = microtime();
    $mtime = explode(" ",$mtime);
    $mtime = $mtime[1] + $mtime[0];
    $endtime = $mtime;
    $totaltime = ($endtime - $starttime);
    echo "Test run time is ".$totaltime." seconds";
}

?>
