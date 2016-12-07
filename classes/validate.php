<?

class CommonValidator {
	public static function inarray($value, &$array) {
		return in_array($value, $array);
	}

	public static function hash($value) {
		return preg_match("/^[a-f0-9]/i", $value);
	}

	public static function ip($value) {
		return preg_match("/^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/", $value);
	}

	public static function datetime($value) {
		return preg_match("/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/", $value);
	}
	
}



class Validate extends CommonValidator {
	public static $error = false;

	public static function request ($r) {
		if ($r->query_string) {
			self::$error = "No query string allowed";
			return false;
		}

		if ($r->method != "POST") {
			self::$error = "Invalid method " . $r->method;
			return false;
		}
	
		if ($r->url != "/") {
			self::$error = "Invalid url " . $r->url . "\n";
			return false;
		}

/*
		if (!preg_match("/^text\/xml/", $r->content_type)) {
			self::$error = "Invalid content-type: " . $r->content_type;
			return false;
		}
*/
		return true;
	}

	public static function login($name) {
		if (strlen($name) > 64)
			return false;

		if (!preg_match("/[A-Za-z0-9_ @%^-]+/", $name)) {
			self::$error = "Invalid characters";
			return false;
		}

		return true;
	}

	public static function tel_number($number) {
		if (!preg_match("/^7\d+$/", $number)) 
			return false;

		return true;
	}

	public static function time($time) {
		if (!preg_match("/^[012][0-9]:[0-9]{2}$/", $time))
			return false;

		$data = preg_split("/:/", $time);
		if ($data[0] < 0 || $data[0] > 23)
			return false;

		if ($data[1] < 0 || $data[1] > 59)
			return false;

		return true;
	}

	public static function time_period($start, $end) {
		if ($start == "23:59" || $end == "00:00")
			return false;

		if (!$start ||  !$end)
			return true;

		$t0 = preg_split("/:/", $start);
		$t1 = preg_split("/:/", $end);

		$t0 = $t0[0] * 60 + $t0[1];
		$t1 = $t1[0] * 60 + $t1[1];

		if ($t0 >= $t1)
			return false;

		return true;
		

	}



	public static function permitted_ip($ip) {
		global $permitted_ips;

		if (TRUSTED_ACCESS)
			return true;

		return self::inarray($ip, $permitted_ips);
	}

}




?>
