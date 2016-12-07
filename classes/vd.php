<?


class VD {

	public static function dump($var) {
		ob_start();
		print_r($var);
		$value = ob_get_contents();
		ob_end_clean();

		return self::chomp($value);
	}

	public static function chomp($var) {
		return trim($var, "\n");	
	}

}


?>
