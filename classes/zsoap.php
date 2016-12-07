<?


class ZSoap extends SoapServer {

	function handle() {

		$result = parent::handle();

		if (CURRENT_LOG_LEVEL >= Logger::MSGTYPE_DEBUG) {
			$data = VD::chomp(ob_get_contents());
			ob_flush();

			Logger::debug("value $data");
		}

		return $result;

	}
}


?>
