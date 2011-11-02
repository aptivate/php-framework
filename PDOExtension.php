<?php
/**
 * Extended PDO class with additional helper methods.
 */

class Aptivate_PDOExtension extends PDO
{
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

		foreach ($attribs as $name => $value)
		{
			$stmt->bindValue(":$name", $value);
		}
		
		if (!$stmt->execute())
		{
			$errorInfo = $stmt->errorInfo();
			throw new Exception("Database query failed: $query: ".
				$errorInfo[2]);
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
