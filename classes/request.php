<?



class Request {
	public $ip, $query_string;
	public $content_type, $url, $method;

	function __construct() {
		$this->parse_request();
	}


	function parse_request() {
		$this->query_string = $_SERVER['QUERY_STRING'];
		$this->user_agent = $_SERVER['HTTP_USER_AGENT'];
		$this->url = $_SERVER['SCRIPT_URL'];
		$this->method = $_SERVER['REQUEST_METHOD'];

		if (CURRENT_LOG_LEVEL >= Logger::MSGTYPE_DEBUG)
			$this->raw_data = VD::chomp(file_get_contents("php://input"));

		$this->get_headers();
		$this->set_ip();
	}


	private function get_headers() {
		if (isset($_SERVER['HTTP_CONTENT_TYPE']))
			$this->content_type = $_SERVER['HTTP_CONTENT_TYPE'];

		if (isset($_SERVER['HTTP_CONTENT_LENGTH']))
			$this->content_length = $_SERVER['HTTP_CONTENT_LENGTH'];
	}

	private function set_ip() {
		$this->proxy = false;

		if (isset($_SERVER['X_HTTP_FORWARDED_FOR'])) {
			$this->ip = $_SERVER['X_HTTP_FORWARDED_FOR'];
			$this->proxy = true;
		} else if (isset($_SERVER['REMOTE_ADDR'])) {
			$this->ip = $_SERVER['REMOTE_ADDR'];
		}
        }

	function __toString() {
		$string = $this->ip . " " . $this->method . " " . $this->url . " " . $this->query_string;
		$string .= "[ " . $this->user_agent . " ]";

		if (!is_null($this->raw_data))
			$string .= " Rawdata: " . $this->raw_data;
		
		return $string;
	}

}























?>
