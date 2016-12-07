<?




class DBFactory {
	var $link, $dbname, $res, $rows;

	const DBTYPE_MYSQL = 0;

	static function get_instance($host, $user, $password, $type = self::DBTYPE_MYSQL) {
		return new DBMysql($host, $user, $password);
	}

	function __construct($host, $user, $password) {
		$this->host = $host;
		$this->user = $user;
		$this->password = $password;
		$this->link = false;
		$this->prepare();
	}

	function set_database($dbname) {
		$this->dbname = $dbname;
	}

	function connected() {
		return ($this->link) ? true : false;
	}

	function prepare() {
		return;
	}
}



class DBMysql extends DBFactory {

	function __construct($host, $user, $password) {
		return parent::__construct($host, $user, $password);
	}

	function connect() {
		if (!$this->dbname) {
			Logger::error("Database not set");
			$this->error = "Database not set";
			return false;
		}
			
		Logger::debug("Connecting to {$this->host}. Selecting {$this->dbname}");
		$this->link = @mysqli_connect($this->host, $this->user, $this->password, $this->dbname);
		if (!$this->link) {
			$error = mysqli_connect_error();
			Logger::error("Connect failed: $error");
			$this->error = "Cant estabilish database connection";
			return false;
		}

		Logger::debug("Connect to DB:{$this->host}: SUCCESS");
		return true;
	}

	function disconnect() {
		if ($this->link)
			mysqli_close($this->link);

		Logger::debug("Disconnected");
	}

	function query($query) {
		$this->res = @mysqli_query($this->link, $query);
		if (!$this->res) {
			Logger::error("Query $query failed:" . mysqli_error($this->link));
			$this->error = "Query error";
			return false;
		}

		$this->rows = @mysqli_num_rows($this->res);
		return true;
	}


	function fetch() {
		return @mysqli_fetch_assoc($this->res);
	}

	function free_result() {
		@mysqli_free_result($this->res);
		$this->rows = 0;
	}

	function escape($string) {
		return @mysqli_real_escape_string($this->link, $string);
	}

	function last_insert_id() {
		return @mysqli_insert_id($this->link);
	}

	function fields() {
		$res = array();
		while ($field = @mysqli_fetch_field($this->res)) {
			$res[] = $field->name;
		}

		return $res;
	}
}



?>
