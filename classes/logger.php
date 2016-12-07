<?


class Logger {

	private static $instance = null;
	private static $id = null;
	private static $str = array(
		"Error", "Warning", "Info", "Debug"
	);

	const LOGTYPE_FILE = 0;
	const MAX_RECORD_LEN = 65535;

	const MSGTYPE_ERROR = 0;
	const MSGTYPE_WARNING = 1;
	const MSGTYPE_INFO = 2;
	const MSGTYPE_DEBUG = 3;

	const NEED_BARRIER = true;

	public static function init($type) {

		if (self::$instance)
			return;

		self::$id = rand(1024,65535);

		switch ($type) {
			case self::LOGTYPE_FILE:
				self::$instance = new LoggerFile(LOG_FILENAME);
				return;
			default:
				return;
		}
	}

	private static function _log($level, $msg) {
		if ($level > CURRENT_LOG_LEVEL)
			return;

		if (!self::$instance)
			self::init(CURRENT_LOG_TYPE);

		$instance = self::$instance;
		if (!$instance)
			return;

		$id = "ID:" . self::$id;
		$date = date("d/m/Y H:i:s");
		$string = "$date $id [" . self::$str[$level] . "] $msg\n";

		if (strlen($string) > self::MAX_RECORD_LEN) 
			$string = substr($string, 0, self::MAX_RECORD_LEN - 3) . "...\n";
		
		$instance->log($string);

		if (self::NEED_BARRIER && method_exists($instance, "flush"))
			$instance->flush();
	}

	public static function error($msg) {
		return self::_log(self::MSGTYPE_ERROR, $msg);
	}

	public static function warning($msg) {
		return self::_log(self::MSGTYPE_WARNING, $msg);
	}

	public static function info($msg) {
		return self::_log(self::MSGTYPE_INFO, $msg);
	}

	public static function debug($msg) {
		return self::_log(self::MSGTYPE_DEBUG, $msg);
	}

	public static function finish() {
		self::$instance = null;
	}

}


class LoggerFile {
	private $fd = null;	

	function __construct($filename) {
		$this->filename = $filename;
		$this->fd = fopen($this->filename, "a+");
		if (!$this->fd)
			return null;
	}

	function log($string) {
		if (!$this->fd)
			return;

		fwrite($this->fd, $string);
	}

	function flush() {
		if (!$this->fd)
			return;

		fflush($this->fd);
	}

	function __destruct() {
		fclose($this->fd);
		$this->fd = null;
	}

}

?>
