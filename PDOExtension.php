<?php
/**
 * Extended PDO class with additional helper methods.
 */

class Aptivate_PDOExtension extends PDO
{
	/**
	 * If database open fails, report the DSN! (including the file
	 * name for sqlite databases)
	 */
	public function __construct($dsn)
	{
		$this->dsn = $dsn;
		
		try
		{
			// you can't just pass func_get_args() to call_user_func_array?
			$args = func_get_args();
			call_user_func_array(array('parent', '__construct'), $args);
		}
		catch (Exception $e)
		{
			throw new Exception("Failed to open database: $dsn: $e");
		}
	}

	/**
	 * Prepare and execute a query and return a statement to the caller.
	 *
	 * @param $query the SQL query to execute
	 * @param $attribs an optional associative array of parameter values
	 * to bind to the query before execution.
	 */
	public function prepareBindExec($query, $attribs = array())
	{
		$startTime = microtime(true);
		$stmt = $this->prepare($query);
		
		if ($stmt == null)
		{
			$errorInfo = $this->errorInfo();
			throw new Exception("Database query failed: $query: ".
				$errorInfo[2]);
		}

		preg_match_all("/:(\w+)/", $query, $matches);
		$bindings = array();
		
		foreach ($matches[1] as $name)
		{
			if (is_array($attribs))
			{
				if (! array_key_exists($name, $attribs)) 
				{
					throw new Exception("You haven't supplied a value for :$name in query: $query");
				}
				$stmt->bindValue(":$name", $attribs[$name]);
				$bindings[":$name"] = $attribs[$name];
			}
			else if (is_object($attribs))
			{
				# print("binding :$name => ".$attribs->$name."\n");
				$stmt->bindValue(":$name", $attribs->$name);
				$bindings[":$name"] = $attribs->$name;
			}
			else
			{
				throw new Exception("Binding values must be ".
					"supplied in an array or object, not ".
					get_class($attribs)." (".$attribs.")");
			}
		}

		$query_msg = "Database query failed: $query with ".
			print_r($bindings, TRUE);

		try
		{
			if (!$stmt->execute())
			{
				$errorInfo = $stmt->errorInfo();
				throw new Exception("$query_msg: ".$errorInfo[2]);
			}
		}
		catch (PDOException $e)
		{
			throw new Exception("$query_msg: ".$e->getMessage());
		}
		
		$elapsedTime = microtime(true) - $startTime;
		#error_log(sprintf("Query took %0.2fs to prepare and execute: $query",
		#	$elapsedTime));
		
		return $stmt;
	}

	/**
	 * Override prepare() for logging and debugging.
	 *
	 * @param $query the SQL query to execute
	 * @param $attribs an optional associative array of parameter values
	 * to bind to the query before execution.
	 */
	public function prepare($query)
	{
		$startTime = microtime(true);
		
		try
		{
			$old_args = func_get_args();
			$stmt = call_user_func_array(array($this, "PDO::prepare"),
				$old_args);
		}
		catch (PDOException $e)
		{
			throw new PDOException($e->getMessage()."; ".
				"dsn=".$this->dsn."; query=$query");
		}
		catch (Exception $e)
		{
			throw new PDOException("$e (for query: $query)");
		}
		
		$elapsedTime = microtime(true) - $startTime;
		# error_log(sprintf("Query took %0.2fs to prepare and execute: $query",
		#	$elapsedTime));
		
		return $stmt;
	}

	/**
	 * Prepare and execute a query that returns a single value from
	 * the database, and return that value to the caller after
	 * cleaning up.
	 *
	 * @param $query the SQL query to execute
	 * @param $attribs an optional associative array of parameter values
	 * to bind to the query before execution.
	 */
	public function queryValue($query, $attribs = array())
	{
		$stmt = $this->prepareBindExec($query, $attribs);
		$row = $stmt->fetch(PDO::FETCH_NUM);
		$stmt->closeCursor();
		return $row[0];
	}

	/**
	 * Prepare and execute a query that returns a single row from
	 * the database, fetch the row using the specified fetch mode,
	 * and return it to the caller after cleaning up.
	 *
	 * @param $query the SQL query to execute
	 * @param $fetchMode the fetch mode to use for the result,
	 * defaulting to PDO::FETCH_OBJ which returns an anonymous object.
	 * @param $attribs an optional associative array of parameter values
	 * to bind to the query before execution.
	 */
	public function queryRow($query, $attribs = array(),
		$fetchMode = PDO::FETCH_OBJ)
	{
		$stmt = $this->prepareBindExec($query, $attribs);
		$row = $stmt->fetch($fetchMode);
		$stmt->closeCursor();
		return $row;
	}

	/**
	 * Prepare and execute a query that returns multiple rows from
	 * the database, fetch the rows using the specified fetch mode,
	 * and return them to the caller after cleaning up.
	 *
	 * @param $query the SQL query to execute
	 * @param $fetchMode the fetch mode to use for the result,
	 * defaulting to PDO::FETCH_OBJ which returns an anonymous object.
	 * @param $attribs an optional associative array of parameter values
	 * to bind to the query before execution.
	 */
	public function queryRows($query, $attribs = array(),
		$fetchMode = PDO::FETCH_OBJ)
	{
		$stmt = $this->prepareBindExec($query, $attribs);
		$rows = $stmt->fetchAll($fetchMode);
		$stmt->closeCursor();
		return $rows;
	}

	/**
	 * Same as queryRow, but throw an exception if no row is returned.
	 *
	 * @param $query the SQL query to execute
	 * @param $fetchMode the fetch mode to use for the result,
	 * defaulting to PDO::FETCH_OBJ which returns an anonymous object.
	 * @param $attribs an optional associative array of parameter values
	 * to bind to the query before execution.
	 */
	public function assertRow($query, $attribs = array(),
		$fetchMode = PDO::FETCH_OBJ)
	{
		$result = $this->queryRow($query, $attribs, $fetchMode);
		if (!$result)
		{
			throw new Exception("No rows found: ".$query);
		}
		return $result;
	}
	
	/**
	 * Add support for bound parameters to exec().
	 *
	 * @param $query the SQL query to execute
	 * @param $attribs an optional associative array of parameter values
	 * to bind to the query before execution.
	 */
	public function exec($query, $attribs = array())
	{
		$stmt = $this->prepareBindExec($query, $attribs);
		return $stmt->rowCount();
	}
}
?>
