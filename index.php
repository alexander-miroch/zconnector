<?

$root = dirname(__FILE__);
$host = $_SERVER['SERVER_NAME'];

require_once $root . "/config.php";
require_once $root . "/core.php";

require_once "$root/classes/zconnector.php";

Logger::debug("Script executed");


$r = new Request();
Logger::debug("New request: " . $r);
if (!Validate::request($r)) {
	Logger::error("Request validate failed: " . Validate::$error);
	exit(1);
}

if (!Validate::permitted_ip($r->ip)) {
	Logger::error("IP access denied for " . $r->ip);
	exit(1);
}


Zconnector::set_request($r);

$soap = new ZSoap(WSDL_FILENAME, array('cache_wsdl' => false));
$soap->setClass('Zconnector');

//print_r($soap->getFunctions());

$soap->handle();



Logger::debug("Script finished");
?>
