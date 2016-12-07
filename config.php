<?


/*
	Paths
*/


define('CLASS_DIR', 'classes');
define('LOG_FILENAME', '/tmp/trace.log');

define('WSDL_FILENAME', 'zconnector.wsdl');

/*
	Mysql parameters
*/


define('MYSQL_HOST', '127.0.0.1');
define('MYSQL_USER', 'zconnector');
define('MYSQL_DATABASE', 'zconnector');
define('MYSQL_PASSWORD', 'testpassword');


/*
	Log

*/

define('CURRENT_LOG_LEVEL', 3);
define('CURRENT_LOG_TYPE', 0);



//define('TRUSTED_ACCESS', false);
define('TRUSTED_ACCESS', true);


$permitted_ips = array(
	"127.0.0.1",
	"10.0.0.1",
	"10.0.0.2",
	"10.0.0.169"
);















?>
