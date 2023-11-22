<?php
/**
 * Things to check:
 * 1. Whether any bug or logical inconsistency is being introduced by the use of $IS_ERROR as a return value & for setting it to false at the start of associated functions
 * 2. whether checking the field names & table names for spaces is at all necessary
 * 3. Whether there is any value of any passed parameter confusing the related function
 * 4. Whether there is any logical errors
 * 5. Whether the code can be optimized more
 * 6. Whether too much (unimportant)checking making the actual processing slower, need to identify redundant checkings & remove them
 * 7. Whether any error report is being bypassed as a result of direct true/false value returning instead of returning $this->IS_ERROR
 *
 */

/**
 * Things to Follow:
 * 1. when a value is returned immediately after calling setErrorMessage, it is better to return fasle directly instead of $this->IS_ERROR for better performance
 * 2. $this->IS_ERROR = false needed to set only at the beginning of those function where the value of $this->IS_ERROR is chacked to determine any occurance of errors(e.g. selectDatabase function)
 * 3. if a function needs to return $this->IS_ERROR then it should always be set to false at the beginning of the function because the value may have set to true as a result of the previous operation
 * 4. if any query is run as $this->query(...) from any function other than query() itself then there is no need to call the alternative function(using or) if the query fails, since this is done inside query
 * 5. If a function calls a function from this class which may return a boolean value specifically due to some operation failure then, that value must be checked in the calling function before returning any value
 * 6. Trim a value passed before using it inside an sql query
 * 7. blacklist input characters:-	; " space --  ..... have to check for their existance in user supplied variables
 * 8. Thers a conglomer of null & "".
 */

/** This Class requires:
 * 1. needs some optin for perform joins(natural, left, right or external)
 */


//This class specifically handles MySQL Databases...for supporitng other types of databases & other options
//etc this class needs to be modified
//For security reasons this class does not support table & field names to contain space..., in those cases the functions return false
class Database
{
	private $ERROR_MESSAGE;
	private $IS_ERROR = false;


	private $db_host = "";
	private $db_username = "";
	private $db_password = "";

	private $database = "";
	private $table = "";

	//This variables shall contain strings to be used as the select, where etc clauses in the query to be run
	//if $whereClause = "" then the where clause is omitted altogether
	private $whereClause = "";
	//if $selectClause = "" then * assumed
	private $selectClause = "";
	//this clause needs to be set to a valid clause at the time of update, otherwise it returns false
	private $updateClause = "";
	//this is an array which holds the two values of the limit clause.
	private $limitClause;

	//This variable holds resourse, so it doesnt persist between pages, so it needs
	//to be rebuilt before using them for any operation .... they are set to 0 not NULL
	private $link;

	private $resultSet;

	//Holds the total number of results that would have been returned if the limit clause were not there.
	private $totalResults;
	//Holds the total number of rows that are affected by any query
	private $affectedRows;
	//If the limit clause is not present then the values of the two variables would be the same



	function Database($db_host, $db_username, $db_password, $db_name = null)
	{
		define('INDEX_NORMAL', 1);
		define('INDEX_PRIMARY_KEY', 2);
		//		echo "insede Constructor<br />";
		$this->connect($db_host, $db_username, $db_password);
		if (is_resource($this->link) && $db_name != null && $db_name != "")
		{
			$this->selectDatabase($db_name);
		}
	}



	function connect($db_host, $db_username, $db_password)
	{
		/*echo "inside connect<br />";
		echo "host = $db_host<br />";
		echo "uname = $db_username<br />";
		echo "pswd = $db_password<br />";*/

		$this->IS_ERROR = false;

		$this->link = mysql_connect($db_host, $db_username, $db_password)
			or $this->setErrorMessage("could not connect". mysql_error());

//		$this->link = mysql_connect($db_host, $db_username, $db_password) or die("could not connect". mysql_error());

		if(is_resource($this->link))
		{
			$this->db_host = $db_host;
			$this->db_username = $db_username;
			$this->db_password = $db_password;
		}
		return $this->link;
	}




	//use of $this->IS_ERROR, to be checked
	//the parameters should either be all present or none
	//returns true if a link is already presentor the selection is succesful returns false otherwise
	//normally this function need not be called manually from outside of this class, that's why no constraint has been has been imposed on the database name, host, username etc.

	//Change needed: this function needs to set all the clauses to empty string on each run
	function selectDatabase($db_name, $db_host = null, $db_username = null, $db_password = null)
	{
		/*echo "inside selectDatabase<br />";
		echo "dbname = $db_name<br />";
		echo "host = $db_host<br />";
		echo "uname = $db_username<br />";
		echo "pswd = $db_password<br />";*/

		$this->IS_ERROR = false;
//		$argumentNumber = func_num_args();

		if (($db_host != null && $db_username != null && $db_password != null)/* || ($this->db_host != "" && $this->db_username != "" && $this->db_password)*/)
		{
			$this->connect($db_host, $db_username, $db_password);
		}

		if (is_resource($this->link))
		{
			mysql_select_db($db_name, $this->link)
				or $this->setErrorMessage("Could not Select Database");
		}
		else
		{
			$this->setErrorMessage("Could not connect to database");
		}

		//to be checked
		//If connection was successfully made then set the name of the database to the passed name
		if (!$this->IS_ERROR)
		{
			$this->database = $db_name;
		}
		return !$this->IS_ERROR;
	}

	//ok -- redundancy checking needed
	//Have to check if setting the $this->limitClause to null rather than to an empty string works
	function selectTable($tableName)
	{
		$this->ERROR_MESSAGE = "";
		if ($tableName == null || !is_string($tableName) || trim($tableName) == "")
		{
			$this->setErrorMessage("Invalid Table name specified!!");
			//echo "111<br>";
			return false;
		}
		//to determine whether this checking is redundant
		if (strpos(trim($tableName), " ") !== false)
		{
			$this->setErrorMessage("Space in table name!!");
			//echo "222<br>";
			return false;
		}

		//if table name ok program control goes to the next section
		//flush off the select, update & where clauses to avoid any coding errors
		$this->selectClause = "";
		$this->updateClause = "";
		$this->whereClause = "";
		$this->limitClause = "";

		$this->totalResults = "";
		$this->affectedRows = "";

		$this->freeResultSet();

		$this->table = $tableName;
		return true;
	}

	/**
	 * Flushes all clauses but does not reset the table or the database names
	 *
	 */
	function freeAllClauses()
	{
		$this->selectClause = "";
		$this->updateClause = "";
		$this->whereClause = "";
		$this->limitClause = "";
	}


	//use of $this->IS_ERROR, to be checked
	function disconnect()
	{
		$this->IS_ERROR = false;
		if($this->link)
		{
			mysql_close($this->link)
				or $this->setErrorMessage("Could not close database connection");
		}
		return !$this->IS_ERROR;
	}


	//to check whether returning the reference of the resultset would be more appropriate
	function getResultSet()
	{
		return $this->resultSet;
	}



	function getLink()
	{
		return is_resource($this->link) ? $this->link : false;
	}


	//use of $this->IS_ERROR, to be checked
	function freeResultSet()
	{
		$this->IS_ERROR = false;

		if(is_resource($this->resultSet))
		{
			mysql_free_result($this->resultSet)
				or $this->setErrorMessage("Problem trying to free the resultset!!");
		}
		/*else
		{
			$this->setErrorMessage("The specified variable does not contain a valid resultset!!");
		}*/

		return !$this->IS_ERROR;
	}


	//to check whether returning the reference of the link would be more appropriate
	function getDatbaseLink()
	{
		/*if(!$this->link)
		{
			$this->selectDatabase();
		}*/
		return is_resource($this->link) ? $this->link : false;
	}

	//ok
	function getErrorValue()
	{
		return $this->IS_ERROR;
	}

	//ok
	function getErrorMessage()
	{
		return $this->ERROR_MESSAGE;
	}





	//to determine whether a checking about the existance of the table in the database is required
	//returns the total number of fields in the specified table
	//returns false if the table doesn't exists in the selected database as a result of query failure
	function getFieldNumber($table = null)
	{
		if (($table == null || !is_string($table) || trim($table) == "") && $this->table == "")
		{
			$this->setErrorMessage("No table specified explicitly or implicitly!!");
			return false;
		}
		//to determine whether this checking is redundant
		elseif (strpos(trim($table), " ") !== false)
		{
			$this->setErrorMessage("Space in table name!!");
			return false;
		}

		$tableName = ($table != null && is_string($table) && trim($table) != "") ? trim($table) : $this->table;

		//the query would fail if any table having the passed name does not exist in the database
		$resultSet = $this->query("select * from `".$tableName."` limit 0,1");

		if(!is_resource($resultSet))
		{
			return false;
		}

		//Program control comes to this portion only if the returned resultset is a valid resource
		//so no chance of errors
		return mysql_num_fields($resultSet);
	}


	//to determine whether a checking about the existance of the table in the database is required
	//returns the names of fields in the specified table
	//returns false if the table doesn't exists in the selected database as a result of query failure
	function getFieldNames($table = null)
	{
		if (($table == null || !is_string($table) || trim($table) == "") && $this->table == "")
		{
			$this->setErrorMessage("No table specified explicitly or implicitly!!");
			return false;
		}
		//to determine whether this checking is redundant
		elseif (strpos(trim($table), " ") !== false)
		{
			$this->setErrorMessage("Space in table name!!");
			return false;
		}

		$tableName = ($table != null && is_string($table) && trim($table) != "") ? trim($table) : $this->table;

		//the query would fail if any table having the passed name does not exist in the database
		//No need to wory if the table from which fetching is done is empty, then also the resultset would contain the fieldnames aas its keys
//		$resultSet = $this->query("select * from `".$tableName."` limit 0,1");
		$resultSet = $this->query("SHOW COLUMNS FROM ".$tableName);
		if(!is_resource($resultSet))
		{
			return false;
		}

		//Program control comes to this portion onyl if the returned resultset is a valid resource
		//so no chance of errors
		/*$fieldNumber =  mysql_num_fields($resultSet);

		$fieldNames = array();
		for ($i = 0; $i < $fieldNumber; $i++)
		{
			$fieldNames[] = mysql_field_name($resultSet, $i);
		}*/
		$fieldNames = array();
		if (mysql_num_rows($resultSet) > 0)
		{
			while ($row = mysql_fetch_assoc($resultSet))
			{
				$fieldNames[] = $row['Field'];
			}
		}

		return $fieldNames;
	}




	//to determine whether a checking about the existance of the table in the database is required
	//returns an array containg the name of the primary keys in the selected table
	//returns false if the table doesn't exists in the selected database as a result of query failure
	function getPrimaryKeys($table = null)
	{
		if (($table == null || !is_string($table) || trim($table) == "") && $this->table == "")
		{
			$this->setErrorMessage("No table specified explicitly or implicitly!!");
			return false;
		}
		//to determine whether this checking is redundant
		elseif (strpos(trim($table), " ") !== false)
		{
			$this->setErrorMessage("Space in table name!!");
			return false;
		}

		$tableName = ($table != null && is_string($table) && trim($table) != "") ? trim($table) : $this->table;

		//echo $tableName."<br>";

		//the query would fail if any table having the passed name does not exist in the database
		$resultSet = $this->query("select * from `".$tableName."` limit 0,1");
		if(!is_resource($resultSet))
		{
			return false;
		}

		//Program control comes to this portion onyl if the returned resultset is a valid resource
		//so no chance of errors
		$fieldNumber =  mysql_num_fields($resultSet);
		//echo $fieldNumber."<br>";


		$keys = array();
		for ($i = 0; $i < $fieldNumber; $i++)
		{
			if (strpos(mysql_field_flags($resultSet, $i), "primary_key") !== false)
			{
				$keys[] = mysql_field_name($resultSet, $i);
			}
		}
		return $keys;
	}


	/**
	 * Checks whether the field exists in the specified table must be checked to be valid befor caling this function
	 *
	 * @param string/array $field
	 * @param string $table
	 * @return unknown
	 */
	function doesFieldExist_1($field, $table)
	{
		if (!is_array($field) && !is_string($field))
		{
			return false;
		}

		//table name must be valid
		$this->selectTable($tableName);
		$fields = $this->getFieldNames();

		if (!is_array($fields) || count($fields) == 0)
		{
			return false;
		}

		return in_array($field, $fields);
	}
	
	
	
	function doesFieldExist($fieldName)
	{
		if (($table == null || !is_string($table) || trim($table) == "") && $this->table == "")
		{
			$this->setErrorMessage("No table specified explicitly or implicitly!!");
			return false;
		}
		
		//to determine whether this checking is redundant
		elseif (strpos(trim($table), " ") !== false)
		{
			$this->setErrorMessage("Space in table name!!");
			return false;
		}
		
		$tableName = ($table != null && is_string($table) && trim($table) != "") ? trim($table) : $this->table;
		$resultSet = $this->query("select * from `".$tableName."` limit 0,1");
		
		if(!is_resource($resultSet))
		{
			return false;
		}
		
		$line = mysql_fetch_assoc($resultSet);
//		print_r($line);
		return array_key_exists($fieldName, $line);
	}
	

	//This function returns the number of rows for the table specified
	//If the table whose name was passed does not exist this function returns "false"
	function noOfRows($tableName = LOGIN_TABLE)
	{
		$resultSet = $this->query("select count(*) from `".trim($tableName));

		if(is_resource($resultSet))
		{
			$row = mysql_fetch_row($resultSet);
			return $row[0];
		}
		//else
		return false;
	}


	/*function getFieldInformation($table = null) {
		if (($table == null || $table == "") && $this->table == "") {
			$this->setErrorMessage("NO table specified explicitly or implicitly");
			return $this->IS_ERROR;
		}
		elseif ($table == null || $table == "") {
			$table = $this->table;
		}

		$resultSet = $this->query("select null from `".$this->table."`");

	}*/



	//ok
	//This function secures the value & returns it after attaching single quotes before & after it.... so if the value after securing it becomes "acv" excluding the double quotes this function would return "'acv'" excluding the double quotes
	//This function converts a null string("") to "null" excluding the null string & returns it...  but a string "null" would returned as "'null'" excluding the double quotes
	//this would remove the need to attach or not attach single quotes depending on whether the value is treated as null
	//$addSingleQuote tells the function whether to surround the value with the single quote
	function secureValue(&$value/*, $addSingleQuote = true*/)
	{
		if (!is_resource($this->link) && $this->selectDatabase($this->database, $this->db_host, $this->db_username, $this->db_password) === false)
		{
//			echo "error";
//			exit(1);
			return false;
		}
		//this variable contains the single quote is the value is to be surrounded by the single quote
//		$singleQuote = "";
		//print_r($this);
//		echo "<br>received value = ".$value;
//		echo "<br>in secureValue";

		/*if (!$this->selectDatabase())
		{
			$this->setErrorMessage("Could not select database!!");
			return false;
		}*/

		/*if (is_string($value))
		{

			if (get_magic_quotes_gpc())
			{
				$value = stripcslashes($value);
			}

			$temp_value = mysql_real_escape_string($value, $this->link);
			$value = "";
			echo "<br>temp value = ".$temp_value;
			if($addSingleQuote)
			{
				$value = "'";
				echo "<br>value1 = ".$value;
			}
			$value = $value.$temp_value;
//			echo "<br>value2 = ".$value;
			if($addSingleQuote)
			{
				$value = $value."'";
				echo "<br>value3 = ".$value;
			}
		}*/

//		echo "<br>intermediate value = ".$value;


		if ($value == null || $value == "null" /*|| $value == "" */|| is_array($value) || is_resource($value))
		{
			$value = "null";
		}
		/*elseif ($value == "null")
		{
			$value = "'null'";
		}*/
		elseif ($value == "")
		{
			$value = "''";
		}
		elseif (is_string($value))
		{

			if (get_magic_quotes_gpc())
			{
				$value = stripcslashes($value);
			}

			$value = "'".mysql_real_escape_string($value, $this->link)."'";
		}


//		echo "<br>returned value = ".$value;

		return $value;
	}




	//need to check whether returning from an iteration of a loop causes any problem
	//running this function without any parameter flushes off the select clause
	//arrays can be used to specify the field names
	//this function does not support spaces in field names even though mysql suppports that, any such field name is thought of as possible sql injection attack
	function selectAdd($fields = null)
	{

		$comma = "";
		if($this->selectClause != "")
		{
			$comma = ", ";
		}


		//If no fields specified then flush off the select clause
		if($fields == null)
		{
			$this->selectClause = "";
			return true;
		}
		//If an array has been passed
		elseif (is_array($fields) && count($fields) > 0)
		{
//			echo '<br>7';
			$count = 0;
			$backupSelectSelectClause = $this->selectClause;

			foreach ($fields as $field)
			{
				//if a particular field name contains only spaces or is a null string then that is simply ignored
				//but no action(like breaking from the loop) is taken against it
				if(trim($field) != "")
				{
					//if space found in the field name even after trimming it, then that is thought of as as sql injection attack
					if (strpos(trim($field), " ") !== false)
					{
						//roll back the select clause
						$this->selectClause = $backupSelectSelectClause;
						$this->setErrorMessage("Field name contains space!!");
						return false;
					}

					$count++;
					$this->selectClause .= $comma.trim($field);

					$comma = ", ";
				}
			}

			//When at least a single field has been added to the existing select clause report that as success
			return $count != 0 ? true : false;
		}
		//If a string has been passed
		elseif (is_string($fields) && trim($fields) != "")
		{
			//if space found in the field name even after trimming it, then that is thought of as as sql injection attack
			//except when it is "count (fieldname/*)" in that case the number of space is only one
			if (strpos(trim($fields), " ") === false || (substr_count($fields, " ") == 1 && substr_count($fields, "count") == 1 && substr_count($fields, "(") == 1 && substr_count($fields, ")") == 1))
			{
				$this->selectClause .= $comma.trim($fields);
				return true;
			}
			/*if (strpos(trim($fields), " ") === false)// || (substr_count($fields, " ") == 1 && substr_count($fields, "count") == 1 && substr_count($fields, "(") == 1 && substr_count($fields, ")") == 1))
			{
				$this->selectClause .= $comma.trim($fields);
				return true;
			}*/
			else
			{
				$this->setErrorMessage("Field name contains space!!");
				return false;
			}
		}
		else
		{
			$this->setErrorMessage("Invalid value passed as as argument");
			return false;
		}
	}


	//ok
	//if no whereclauses are passed then the existing whereclause is flushed off
	//not a very secure way of adding where clause instead use secureWhereAdd
	//use this function to flush off the where clause instead of secureWhereAdd
	function whereAdd($where = null, $logic = "AND")
	{
		//If the $logic passed is wrong(& even if it contains space) then set it to the default value
		$logicalConnector = $this->whereClause != "" ? (strtoupper(trim($logic)) == "OR" || trim($logic) == "||" ? " OR " : " AND ") : "";

		//if no fields specified then flush off the where clause
		if($where == null)
		{
			$this->whereClause = "";
			return true;
		}
		elseif (is_string($where) && trim($where) != "")
		{
			$this->whereClause .= $logicalConnector.trim($where);
			return true;
		}
		else
		{
			$this->setErrorMessage("Invalid where clause specified!!");
			return false;
		}
	}




	/**
	 * This function adds a where clause only for the primary key
	 * the table name needs to be set, either explicitly through this function or must be set previously
	 * does not work for composite primary key
	 *
	 * @param mixed $PKeyValue
	 * @param string $tableName
	 */
	/*function whereAddPrimary($PKeyValue, $tableName = null)
	{

	}*/



	//Have to check if setting the $this->limitClause to null rather than to an empty string works
	//This function inserts two integers into the array named $limitClause which are used as limit values while selecting rows from a table
	//It must be made sure that the both of them are integers & the offset is non negative & the count(if present) is positive.
	//Calling this function without any argument resets the $limitClause array
	function limitAdd($offset = null, $count = null)
	{
		//echo "within limitadd<br>";
		if (is_integer($offset) && $offset >= 0)
		{

			//echo "within block 1<br>";
			$this->limitClause = array();
			$this->limitClause[0] = $offset;
			if(is_integer($count) && $count > 0)
			{
				$this->limitClause[1] = $count;
			}
			return true;
		}

		if ($offset == null && $count == null)
		{
			//echo "within block 2<br>";
			$this->limitClause = "";
			return true;
		}

		//print_r($this->limitClause);

		return false;
	}




	//does not check for spaces in $relation since it can be of many form like, "is not", "not like"....  need to check its risk value
	//also need to determine whether the table name has to be set before calling this function & whether the field name specified has to be present in the table
	//This function just adds to the existing where clause but it escapes the value with mysql_real_escape_string to vaoid attacks
	//to fluch off the where clause call whereAdd() instead of secureWhereAdd()
	//this function is not needed for escaping a numeric value
	function secureWhereAdd ($field, $value, $relation = "=", $logic = "AND")
	{
//		echo "<br> in secureWhereAdd";
		//If the $logic passed is wrong(& even if it contains space) then set it to the default value
		$logicalConnector = $this->whereClause != "" ? (strtoupper(trim($logic)) == "OR" || trim($logic) == "||" ? " OR " : " AND ") : "";

		if (is_string($field) && trim($field) != "")
		{
			if(strpos(trim($field), " ") !== false)
			{
				$this->setErrorMessage("Field Name contains space!!");
				return false;
			}

			//secure the value
			$this->secureValue($value);
			$relation = $value == "null" ? (trim($relation) == "!=" || trim($relation) == "><" || trim($relation) == '<>'? "is not" : "is") : (trim($relation) == "" ? "=" : $relation);

			$this->whereClause .= $logicalConnector."`".trim($field)."` ".$relation." ".$value;
			return true;
		}
		else
		{
			$this->setErrorMessage("Invalid where field specified!!");
			return false;
		}
	}


	//need to determine whether the table name has to be set before calling this function & whether the field name specified has to be present in the table
	//this function just sets the update clause but does not execute any query
	//running this function without any arguments flushes off the updateClause
	function updateAdd($field = null, $value = null)
	{
//		echo "<br> in updateAdd";
//		echo "<br>field = $field, value = $value";
		$argNumber = func_num_args();
		/*if ($argNumber != 0 && $argNumber != 1 && $argNumber != 2)
		{
//			echo "1";
			$this->setErrorMessage("Wrong Number of arguments passed passed!!");
			return false;
		}*/

		//If no arguments passed then flush off the updateclause
		if ($argNumber == 0)
		{
//			echo "2";
			$this->updateClause = "";
			return true;
		}


		if (!is_string($field) || trim($field) == "" || strpos(trim($field), " ") !== false)
		{
//			echo "3";
			$this->setErrorMessage("Invalid field name value specified!!");
			return false;
		}

		//If a single argument is passed then that is taken to be the fieldname & then the value that is to be given to the field is supposed to be 'null'
		//on the other hand if a null is explicitly passed as the 2nd argument then that is converted to the string null
		/*if($argNumber == 1 || ($argNumber == 2 && $value == null))
		{
			echo '<br>changing null to string null';
//			exit(1);
			$value = "null";
		}*/

//		echo "<br>4";
		$comma = $this->updateClause == "" ? "" : ", ";
		$this->updateClause .= $comma."`".trim($field)."` = ".$this->secureValue($value/*, false*/);
		/*echo "updateclause = $this->updateClause";
		exit(1);*/
		return false;
	}


	//seems ok
	//selects & returns all(*) for the "specified value of the specified field" or the "specified primary key"
	//if only a single parameter($field) is used then that is taken to be the value of the primary key instead of as a field name
	//should not be used for a compisite primary key, if used then it returns false
	//If the selection is to be done depending on multiple "field"-"value" pair then use whereAdd functions followed by fetchAll() instead of this function
	//this function sets the select clause to ""(since selectclause = "" means select *) & where clause to the formed clause
	//this function requires the $this->table to be set to a valid tablename
	function get($fieldOrPKValue, $value = null)
	{
		//This function executes only if the table name is set, otherwise returns false
		if ($this->table == "" || $this->table == null)
		{
			$this->setErrorMessage("Table name not specified!!");
			return false;
		}

		//when two parameters are passed disregard any previously set whereClause
		if($value != null)
		{
			//$this->selectClause = "";
			$this->whereClause = "";
			return $this->secureWhereAdd($fieldOrPKValue, $value) ? $this->fetchAll() : false;
		}


		//when the single passed parameter has to be used as the value of the primary key
		else
		{
			$keys = $this->getPrimaryKeys();

			if (!is_array($keys))
			{
				//echo "<br>1";
				return false;
			}
			elseif (count($keys) != 1)
			{
				$this->setErrorMessage("Composite primary key exists or no primary keys exists at all!!");
				//print_r($keys);
				//echo "<br>2";
				return false;
			}
			//Program control comes to  this portion only if there is exactly one primary key in the returned array
			else
			{
				//This block should be uncommented if all the primary keys are known to be are of integer
				/*if(!is_integer($fieldOrPKValue + 0))
				{
					$this->setErrorMessage("Non Integer Primary Key Value Specified!!");
					return false;
				}*/

				//This function does not dieregard the select clause if this is set
				//$this->selectClause = "";
				$this->whereClause = "";
				//echo "<br>3";
				return $this->secureWhereAdd($keys[0], $fieldOrPKValue) ? $this->fetchAll() : false;
			}
		}
	}


	//runs a query based on previously set select & where clauses & select the
	/*function find() {

	}*/


	//redundance check needed.... otherwise seems ok
	//uses previously set whereclause or the passed whereclause to select the row to be updated
	//if no such clause present or table name not set or updation fails then returns false
	//only the keys in the source array which have the same name as those of the fields in the table are inserted in the update clause
	//this function completely ignores previously set updateclause & set it afreash from the passed arguments
	//The array needs to be non-nested array
	function setFrom($sourceArray, $table = null, $whereClause = null)
	{
		/*echo "<pre>";
			print_r($sourceArray);
		echo "</pre>";*/
		//check if a valid non-empty array is available as the source array
		if (!is_array($sourceArray) || count($sourceArray) == 0 || count($sourceArray) != count($sourceArray, COUNT_RECURSIVE))
		{
			$this->setErrorMessage("invalid argument passed as the source array");
			//echo "<br>1";
			return !$this->IS_ERROR;
		}


		//check whether a valid string is available as the tablename
		if (($table == null || !is_string($table) || trim($table) == "") && $this->table == "")
		{
			$this->setErrorMessage("No table specified explicitly or implicitly!!");
			//echo "<br>2";
			return false;
		}
		//to determine whether this checking is redundant
		elseif (strpos(trim($table), " ") !== false)
		{
			$this->setErrorMessage("Space in table name!!");
			//echo "<br>3";
			return false;
		}


		//check whether a valid string is available as the the where clause
		if (($whereClause == null || !is_string($whereClause) || trim($whereClause) == "") && $this->whereClause == "")
		{
			$this->setErrorMessage("No where clause specified!!");
			//echo "<br>4";
			return false;
		}


		//Program control reaches this portion If & only IF a valid non-empty array has been passed,
		//a valid non-empty string is specified as the table name & a valid non-empty string is specified as the where clause
		$tableName = ($table != null && is_string($table) && trim($table) != "") ? trim($table) : $this->table;
		$whereClause = ($whereClause != null && is_string($whereClause) && trim($whereClause) != "") ? trim($whereClause) : $this->whereClause;

		$updateClause = "";
		$comma = "";
		$this->selectTable($tableName);
		$fields = $this->getFieldNames();

		/*echo "<pre>";
			print_r($fields);
		echo "</pre>";*/


		if (!is_array($fields))
		{
			//echo "<br>5";
			return false;
		}

		//Resetting any previously set update  clause
		$this->updateAdd();
		//Program control reaches to this portion if & only if the returned object is an array
		foreach ($fields as $field=>$value)
		{
//			echo "<br>h";
			if (array_key_exists($value, $sourceArray))
			{
//				echo "<br>1";
				//two conditions have been inserted in the if statement so that from the third
				//time onwards only one condition is tested & the if body is not executed...
				//removing the first condition will lead to the execution of $comma = ", "; stmt on each run
				/*if ($comma == "" && $updateClause != "")
				{
					$comma = ", ";
				}

				//no trimming of the variable $field is necessary since it is obtained from the table itself & not from the user passed array
				$updateClause .= $comma."`".$field."` = ".$this->secureValue($sourceArray[$field]);*/

				$this->updateAdd($value, $sourceArray[$value]);
			}
		}

		if ($this->updateClause == "")
		{
			$this->setErrorMessage("No keys in the passed array match with any fields of the selected table");
			//echo "<br>6";
			return false;
		}

		//echo "<br>7";
		//If everything is fine & the control wasnt returned in the previous if block
		$this->whereClause = $whereClause;
//		$this->updateClause = $updateClause;
		return $this->query("update `".$tableName."` set ".$this->updateClause." where ".$whereClause);
	}



	//redundancy checking needed...otherwise looka ok
	//also need to determine whether the table name has to be set before calling this function & whether the field name specified has to be present in the table
	//This function tires to insert new rows specifying values only to those fields which have the
	//same field names to those of the keys in the passed array, ignoring the other.......
	//returns true or false depending on whether the update succeeded or failed
	//This function requires a single dimentional or a symmetric(having same no of elements & within each subarray the set of keys are identical) multidimentional array, otherwise it returns FALSE
	function insert($sourceArray, $table = null)
	{
//		echo "<br />inside insert";
		if (!is_array($sourceArray))
		{
//			print_array($sourceArray);
			$this->setErrorMessage("The passed variable is not a valid array");
			return !$this->IS_ERROR;
		}

		//check whether a valid string is available as the tablename
		if (($table == null || !is_string($table) || trim($table) == "") && $this->table == "")
		{
			$this->setErrorMessage("No table name specified!!");
			return !$this->IS_ERROR;
		}
		//to determine whether this checking is redundant
		elseif (strpos(trim($table), " ") !== false)
		{
			$this->setErrorMessage("Space in table name!!");
			return false;
		}

		//Program control reaches this portion If & only IF the passed variable is a valid array & a string is specified as the table name
		$tableName = ($table != null && is_string($table) && trim($table) != "") ? trim($table) : $this->table;
		$insertFields = "";
		$insertValues = "";


		$this->selectTable($tableName);
		$fieldNames = $this->getFieldNames();
		if (!is_array($fieldNames))
		{
			$this->setErrorMessage("Invalid tablename supplied!!");
			return false;
		}

		//If a single dimensional array passed
		if (count($sourceArray) == count($sourceArray, 1))
		{
//			echo "1<br />";
			//quit if the passed array is empty
			if (count($sourceArray) == 0)
			{
//				echo "2<br />";
				$this->setErrorMessage("Empty array passed!!");
				return $this->IS_ERROR;
			}

			//Program control reaches the this portion if & only the array is non-empty
			foreach ($sourceArray as $key=>$value)
			{
//				echo "3<br />";
				$comma = $insertFields != "" ? "," : "";

				//a cheching of whether the field exists in the table specified should be give below
				if (in_array($key, $fieldNames))
				{
//					echo "4<br />";
					/*if($key == null || !is_string($key) || trim($key) == "" || strpos(trim($key), " ") !== false)
					{
						$this->setErrorMessage("Invalid field name passed!!");
						return false;
					}*/
					$this->secureValue($value);

					$insertFields .= $comma."`".trim($key)."`";
					$insertValues .= $comma.$value;
				}
			}
			if($insertFields != "")
			{
				$this->table = $tableName;
				return $this->query("insert into `".$tableName."` (".$insertFields.") values(".$insertValues.")");
			}
			else
			{
				$this->setErrorMessage("Invalid field names passed!!");
				return false;
			}

		}
		//multi-dimensional array passed
		//this portion needs to be fromed in such a way so that different set of field & values from the first subarray can also be entered in the insertclause
		//better to form a function for that purpose & better to avoid a multidimensiional array until this part is reformed
		else
		{
			//if the first sub-array is empty then quit
			if(!is_array(current($sourceArray)) || count(current($sourceArray)) == 0)
			{
				$this->setErrorMessage("Invalid array passed");
				return false;
			}

			//Program control comes to this portion if the first subarry is a non-empty valid array
			$condition = true;

			//set the first array as the reference array & its length as the reference length
			$refArray = current($sourceArray);
			$refArrayLength = count(current($sourceArray));

			//set the $insertFields string from the first subarray
			foreach (current($sourceArray) as $key=>$value)
			{
				//a cheching of whether the field exists in the table specified can be give below
				if($key == null || !is_string($key) || trim($key) == "" || strpos(trim($key), " ") !== false)
				{
					$this->setErrorMessage("Invalid field name passed!!");
					return false;
				}

				$comma = $insertFields != "" ? "," : "";
				$insertFields .= $comma."`".trim($key)."`";
			}

			//Tries to determine whther the multi-dimensional array is a symmetric one & whether the set of keys in all
			//the subarrays are identical & sets $condition = false; if otherwise found
			//This hard way of checking is necessary to allow the keys of the outer array to be something other than an increasing list of integers starting from 0.
			foreach ($sourceArray as $currentIndex)
			{
				//if the keys of any particular sub-array differ from those of the first sub-array, then quit
				if (!is_array($sourceArray[$currentIndex]) || count(array_intersect_key($refArray, $sourceArray[$currentIndex])) != $refArrayLength)
				{
					$condition = false;
					//$insertFields = "";
					//$insertValues = "";
					break;
				}

				//Program control comes to this portion if & only if everything is ok till now
				$comma = $insertValues != "" ? "," : "";
				$insertValues .= $comma."(";
				foreach ($sourceArray[$currentIndex] as $value)
				{
					$this->secureValue($value);
					$comma = strripos($insertValues, "(") === strlen($insertValues) - 1 ? "" : ",";

					$insertValues .= $comma.$value;
				}
				$insertValues .= ")";

			}

			//symetric multidimensional has been passed
			if ($condition)
			{
				$this->table = $tableName;
				return $this->query("insert into `".$tableName."` (".$insertFields.") values".$insertValues);
			}
			//ragged multi-dimensional array passed
			else
			{
				$this->setErrorMessage("Invalid array passed");
				return !$this->IS_ERROR;
			}
		}
	}




	//seems ok
	//this function updates the specified(explicitly or implicitly) table by using the updateClause & whereclause
	//if no updateclause and/or whereclause present it returns flase, otherwise returns the success/failure result of the bilt update query
	//this function uses the previously existing update clause but ignoress completely the previous value of the where clause if a whereclause is passed as argument
	function update($field = null, $value = null, $whereClause = null, $table = null)
	{
//		echo "<br> in update";
		//check whether a valid string is available as the tablename
		if (($table == null || !is_string($table) || trim($table) == "" || strpos(trim($table), " ") !== false) && $this->table != "")
		{
			$table = $this->table;
		}
		else
		{
			$this->setErrorMessage("No table specified or invalid table name specified!!");
			//echo "tablename = ".$table."<br>";
			//echo "1<br>";
			return false;
		}

//		$updateClause = $this->updateClause;

		if ($field != null)
		{// && $value != null) {
			if (!is_string($field) || trim($field) == "" || strpos(trim($field), " ") !== false)
			{
				$this->setErrorMessage("Invalid field name used for update clause");
				//echo "2<br>";
				return false;
			}
//			$comma = $updateClause == "" ? "" : ", ";
//			$updateClause .= $comma."`".trim($field)."` = ".$this->secureValue($value);
			$this->updateAdd($field, $value);
//			echo "<br>".$this->updateClause;
//			exit(1);
		}
		elseif ($this->updateClause == "")
		{
			$this->setErrorMessage("Invalid update clause used for update operation!!");
			//echo "3<br>";
			return false;
		}

		//check whether a valid string is available as the whereclause
		if ($whereClause == null || !is_string($whereClause) || trim($whereClause) == "")
		{
			if ($this->whereClause == "")
			{
				$this->setErrorMessage("Invalid where clause used for update operation!!");
				//echo "4<br>";
				return false;
			}
			$whereClause = $this->whereClause;
		}


//		$this->updateClause = $updateClause;
		$this->whereClause = trim($whereClause);
		$this->table = trim($table);
		//return $this->query("update `".$this->table."` set ".$updateClause." where ".$this->whereClause);
		return $this->query("update `".$this->table."` set ".$this->updateClause." where ".$this->whereClause);
	}




	//delets all the rows for the "specified value of the specified field" or the "specified primary key value"
	//if only a single parameter($field) is used then that is taken to be the value of the primary key instead of as a field name
	//should not be used for a compisite primary key, if used then it returns false
	//this function requires the $this->table to be set to a valid tablename using the selectTable() function
	//If the deletion is to be done depending on multiple "field"-"value" pair then use whereAdd functions followed by this function with the last pair as the arguments or not arguments at all(In such a situation never use this function using the single argument.. that will disregard the previously set where clause).
	//If the function is called without any argument and the where Clause is not set then this function returns false
	function delete($fieldOrPKValue = null, $value = null)
	{
		//This function executes only if the table name is set, otherwise returns false

//		echo "in delete;";

		if ($this->table == "" || $this->table == null)
		{
			$this->setErrorMessage("Table name not specified!!");
			return false;
		}



		//when the single parameter passed that has to be used as the value of the primary key
		if($fieldOrPKValue != null && $value == null)
		{
			$keys = $this->getPrimaryKeys();

			if (!is_array($keys))
			{
				//echo "<br>1";
				return false;
			}
			elseif (count($keys) != 1)
			{
				$this->setErrorMessage("Composite primary key exists or no primary keys exists at all!!");
				//print_r($keys);
				//echo "<br>2";
				return false;
			}
			//Program control comes to  this portion only if there is exactly one primary key in the returned array
			else
			{
				//This block should be uncommented if all the primary keys are known to be are of integer
				/*if(!is_integer($fieldOrPKValue + 0))
				{
					$this->setErrorMessage("Non Integer Primary Key Value Specified!!");
					return false;
				}*/
				$this->selectClause = "";
				$this->whereClause = "";
				//echo "<br>3";
				return $this->secureWhereAdd($keys[0], $fieldOrPKValue) ? $this->query("delete from `".$this->table."` where ".$this->whereClause) : false;
			}
		}

		elseif($fieldOrPKValue == null && $value == null)
		{
			if ($this->whereClause == null && trim($this->whereClause) == "")
			{
				$this->setErrorMessage("Inappropriate field name specified!!");
				return false;
			}

			$this->selectClause = "";
			return $this->query("delete from `".$this->table."` where ".$this->whereClause);
		}

		//when two parameters are passed or no parameters passed at all(with the where Clause being set)
		elseif ($fieldOrPKValue != null && $value != null)
		{
			//If the field name is inappropriate then return false
			if(trim($fieldOrPKValue) == "" || strpos($fieldOrPKValue, " "))
			{
				$this->setErrorMessage("Inappropriate field name specified!!");
				return false;
			}

			$this->selectClause = "";
			//Since this function does not disregard the previously set Where Clause so this the where clause is not set to "" or null
			return $this->secureWhereAdd($fieldOrPKValue, $value) ? $this->query("delete from `".$this->table."` where ".$this->whereClause) : false;
		}




		//If the parameters do not conform to the rules then return false
		return false;
	}



	//This function deletes rows depending on the table selected & where clause specified(either before calling this function ir through function)
	//This
	//function delete($table = null, $whereClause = null)
	/*function delete($table = null, $whereClause = null)
	{
		if ($this->table == "" || $this->table == null)
		{
			$this->setErrorMessage("Table name not specified!!");
			return false;
		}

		if (($table == null || !is_string($table) || trim($table) == "" || strpos(trim($table), " ") !== false) && $this->table != "")
		{
			$table = $this->table;
		}
		else
		{
			$this->setErrorMessage("No table specified or invalid table name specified!!");
			return false;
		}


		//check whether a valid string is available as the whereclause
		if ($whereClause == null || !is_string($whereClause) || trim($whereClause) == "")
		{
			if ($this->whereClause == "")
			{
				$this->setErrorMessage("Invalid where clause used for update operation!!");
				return false;
			}
			//else if $this->whereClause is a non-empty string
			$whereClause = $this->whereClause;
		}


		//Program control reaches this portion If & only IF a valid non-empty string is specified as the table name
		//& a valid non-empty string is specified as the where clause
		$this->whereClause = trim($whereClause);
		$this->table = trim($table);
		return $this->query("delete from `".$this->table."` where ".$this->whereClause);
	}*/



	/**
	 * Enter description here...
	 *
	 * @param unknown_type $message
	 * @param unknown_type $echo
	 * @param unknown_type $die
	 * @return unknown
	 */
	private function setErrorMessage($message, $echo = false, $die = false)
	{
		$this->ERROR_MESSAGE .= $message;
		$this->IS_ERROR = true;

		if($echo)
		{
			echo $message;
		}

		if($die)
		{
			die();
		}
		//if dosnt die would always return false
		return false;
	}




	/*Need to check if the block
		if($this->resultSet = mysql_query($query, $this->link))
		{
			$this->totalResults = mysql_num_rows($this->resultSet);
		}
		else
		{
			$this->setErrorMessage("Query Failed:".mysql_error());
		}
	works properly otherwise OK
	*/
	//Have to check if setting the $this->limitClause to null rather than to an empty string works
	//have to see whether the addition of the limit clause works properly
	//This function runs a passed query & returns the resultset
	//if no query is passed this function tries to build a query from the where & select clauses
	//This function needs to be extended to be able to run to update when only the updateclause is set
	//Also there should eb priority placed on the clauses(select, update etc)
	function query($query = null)
	{

		//if the database could not be selected then quit
		/*if (!$this->selectDatabase())
		{
			return false;
		}*/
		if (!is_resource($this->link) && $this->selectDatabase($this->database, $this->db_host, $this->db_username, $this->db_password) === false)
		{
//			echo "error";
//			exit(1);
			return false;
		}

//		echo '<br>4';

		//Program control reaches this portion if & only if the database was selected successfully
		if ($query == null || trim($query) == "")
		{
//			echo '<br>5';
			//If the table name is set then try to build the query
			if ($this->table != "")
			{
				$queryStart = "select ";

				$selectClause = $this->selectClause == "" ? "*" : $this->selectClause;
//				echo "<br>selectClause = $selectClause";
				$query .= $selectClause;

				$query .= " from `".$this->table."`";

				$query .= $this->whereClause == "" ? "" : " where ".$this->whereClause;

				//The following block assumes that the limit values are integer & conforms to the rules, since the variable is not public & the function used to set the two values does necessary checking
				if ($this->limitClause != null && is_array($this->limitClause) && count($this->limitClause) > 0)
				{
					//Adding the offset limit value
					$query .= " limit " . $this->limitClause[0];
					if(count($this->limitClause) == 2)
					{
						//Adding the count limit value
						$query .= "," . $this->limitClause[1];
					}
					$queryStart .= "SQL_CALC_FOUND_ROWS ";
				}

				$query = $queryStart.$query.";";
			}
			else
			{
				$this->setErrorMessage("Table name not set!!");
				return false;
			}
		}

//		echo "<br>query = ".$query."<br>";
//		die();


		//Calculation of the total number of rows affected by this query
		//If the program control reaches this point then $query has a nonempty string stored in it, either passed as function argument or built from different clauses
		if($this->resultSet = mysql_query($query, $this->link))
		{
//			echo 'hi1<br />';
			//Getting the number of affected rows depending on the type of the query
			$this->affectedRows = (stripos($query, "select") !== false) ? (is_resource($this->resultSet) ? mysql_num_rows($this->resultSet) : 0) : mysql_affected_rows($this->link);

//			echo "<br>hi  affectedRows = $this->affectedRows";
//			echo "<br>insert into = ".substr_count($query, "insert into");
			//If the query involves insertion operation & the number of inserted rows is 1 then set the to be returned value to the id of the newly inserted row
			if(substr_count($query, "insert into") == 1 && $this->affectedRows == 1)
			{
//				echo "<b>**hi2**</b><br />";
				$this->resultSet = mysql_insert_id();
			}

			//try to find the number of affected rows if the limit clause was not set only if the query contains the substring "SQL_CALC_FOUND_ROWS"
			if(strpos($query, "SQL_CALC_FOUND_ROWS") !== false && $resultSet1 = mysql_query("SELECT FOUND_ROWS();"))
			{
//				echo 'hi3<br />';
				$totalResults = mysql_fetch_row($resultSet1);
				$this->totalResults = (int) $totalResults[0];
			}
			else
			{
//				echo 'hi4<br />';
				$this->totalResults = null;
			}
		}
		else
		{
//			echo 'hi5<br />';
			$this->setErrorMessage("Query Failed:".mysql_error());
			$this->affectedRows = null;
			$this->totalResults = null;
			//return false;
		}

		//If the query fails then the resultset is set to false so it itself can be returned
		return $this->resultSet;
	}


	//ok
	//This function returns all the rows(if any rows returned at all) using an array by optionally running
	//an sql query if one such passed or tries to build a default query from the select & whereclause if a valid table name is stored
	//This function retuns arrays only for SELECT, SHOW, DESCRIBE or EXPLAIN statements,
	//returns false for any other statements(e.g. insert, update etc.) even though successful & unsuccessful select statements
	//currently onely one fetch mode is supported i.e., associative array.. it will be extended later
	function fetchAll($query = null, $index_field = null)
	{
//		echo "index_field = $index_field";
//		echo '<br>2';
		$resultSet = $this->query($query);

		if(!is_resource($resultSet))
		{
//			echo '<br>3';
//			$this->setErrorMessage("Incompatible type of statements using fetchAll() function or unsessful query!!");
			$this->setErrorMessage(mysql_error($this->link));
			return false;
		}

		//Program control comes to this portion if & only if everything is fine & we have a valid resultset in our hand,
		//so no checking is required, now fetch the individual rows from the resultset
		$values = array();
		
		/*if ($index_type == INDEX_PRIMARY_KEY)
		{
			$pKeys = $this->getPrimaryKeys();
			if (count($pKeys) != 1)
			{
				$this->setErrorMessage("No primary keys or more than one primary keys");
				return false;
			}
//			print_array($pKeys);
			while($line = mysql_fetch_assoc($resultSet))
			{
				$values[$line[$pKeys[0]]] = $line;
			}
		}*/
		if ($index_field != null && $index_field != "" && is_string($index_field))
		{
			if (!$this->doesFieldExist($index_field))
			{
				$this->setErrorMessage("Field does not exist in the table");
				return false;
			}
			while($line = mysql_fetch_assoc($resultSet))
			{
				$values[$line[$index_field]] = $line;
			}
		}
		else
		{
			while($line = mysql_fetch_assoc($resultSet))
			{
				$values[] = $line;
			}
		}


//		echo '<br>4';
		//mysql_free_result($this->resultSet);

		return $values;
	}



	function fetchAll1($query = null)
	{
		//		echo '<br>2';
		$resultSet = $this->query($query);

		if(!is_resource($resultSet))
		{
//			echo '<br>3';
//			$this->setErrorMessage("Incompatible type of statements using fetchAll() function or unsessful query!!");
			$this->setErrorMessage(mysql_error($this->link));
			return false;
		}

		//Program control comes to this portion if & only if everything is fine & we have a valid resultset in our hand,
		//so no checking is required, now fetch the individual rows from the resultset
		$values = array();
		while($line = mysql_fetch_array($resultSet))
		{
			/*echo "<pre>";
				print_r($line);
			echo "</pre>";*/

			$values[] = $line;
		}

//		echo '<br>4';
		//mysql_free_result($this->resultSet);

		return $values;
	}


	//This function is juct an exctension of the above function e.g. it supprot an extra parameter so this function just calls the above function rather than doing the common operation itself again
	//The parameter $whetherReplaceSingletonArray indicates whether to cut the associativeness of a singleton array by placing the elements of the first element outside
	//I guess that this function would rearely be required so this can be removed in the future
	function fetchAllIntoCustomizedArray($query = null, $whetherReplaceSingletonArray = true)
	{
		$values = $this->fetchAll($query);


		//If the result array contains only one outer element(e.g. unnder [0] but does not contain [1] , [2] etc)
		//& the specified parameter for removing the single outer element is true then put remove the outer key
		if($values !== false && is_array($values) && count($values) == 1 && $whetherReplaceSingletonArray === true)
		{
			$tempValues = $values;
			$values = null;
			$values = $tempValues[0];
		}
		/*echo "<pre>";
		print_r($values);
		echo "</pre>";
		exit();*/

		return $values;
	}


	function getTotalResults()
	{
		return $this->totalResults;
	}

	function getAffectedRows()
	{
		return$this->affectedRows;
	}

	/**
	 * Checks whether a valid table set as the targetted table upon which the intended operation has to be performed
	 *
	 * @param unknown_type $tableName
	 * @return unknown
	 */
	function isValidTableSet($tableName = null)
	{
		return false;
	}


	/*function dumpTable($table)
	{
		if (!is_string($table) || strpos(trim($table), " ") !== false)
		{
			$this->setErrorMessage("Invald table name specified!!");
			return false;
		}

		$resultSet = $this->fetchAll("SHOW COLUMNS FROM ".$table);
		print_array($resultSet);

	}*/
}
?>