<?

ini_set('display_errors', 'on');
error_reporting(E_ALL);

ini_set('include_path', $root);
ini_set('always_populate_raw_post_data', 1);
ini_set("soap.wsdl_cache_enabled", 0);

require_once $root . "/" . CLASS_DIR . "/logger.php";


if (CURRENT_LOG_LEVEL >= Logger::MSGTYPE_DEBUG)  
	ini_set('always_populate_raw_post_data', 1);


if (php_sapi_name() == "cli") {
	Logger::error("executing us as cli...");
	print "We cant run as cli. Sorry";
	exit(1);
}


function __autoload($class_name) {
	global $root;

	$class_name = strtolower($class_name);
	$class_filename = "$root/" . CLASS_DIR . "/$class_name.php";
	if (!file_exists($class_filename)) {
		Logger::error("Can't find class $class_filename");
		return;
	}

	include_once $class_filename;
}


?>
