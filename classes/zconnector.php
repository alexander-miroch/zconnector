<?


class ZFault extends SoapFault {
	const ERROR = "code";

	function __construct ($string) {
		return parent::__construct(self::ERROR, $string);
	}

}


class Zconnector {

	private static $r;
	public static $sort;

	const TRIGGER_TYPE = 16;
	const TRIGGER_NAME = "trigger";	

	function __construct() {

	}


	public static function set_request($r) {
		self::$r = $r;
	}

	public function __call($name, $args) {

		Logger::info("Request from ip " . self::$r->ip . " call " . $name . "()");
		Logger::debug("Params: " . VD::dump($args));

		$method = "__" . $name;
		if (!method_exists($this, $method)) {
			Logger::error("Unknown method $method");
			throw new ZFault("Unknown method");

			return false;
		}

		if (method_exists("ZValidate", $method)) {
			Logger::debug("Validating $method");

			if (!ZValidate::$method($args)) {
				Logger::error("Validate failed for $method: " . ZValidate::$error);
				$method = preg_replace("/^__/", "", $method);
				throw new ZFault("Error in $method: " . ZValidate::$error);

				return false;
			}
		}

		Logger::debug("Calling $method()");	

		$this->zs = ZSingleton::get_instance();
		if (!$this->zs || $this->zs->error) {
			Logger::error("Error creating zs: " . $this->zs->error);
			throw new ZFault("Error: " . $this->zs->error);
			$this->zs = null;
			return false;
		}

		Logger::debug("VD-" . VD::dump($args));
		$this->resp = new ZObject();

		$data = $this->$method(
			$this->getObject($args)
		);

		Logger::debug("Returned data: " . VD::dump($data));

		if ($data === false) {
			Logger::error("Error from backend: " . $this->error);
			throw new ZFault("Error: " . $this->error);
			return false;
		}



		return $data;
	}

	private function getObject($objects) {
		if (!is_array($objects))
			return $objects;

		Logger::debug("oooo==" . count($objects));
		if (count($objects) == 1)
			return $objects[0];

		return $objects;
	}

	private function __GetHosts($val) {

		$host1 = new ZObject();
		$host2 = new ZObject();

		$host1->hostId = 12;
		$host2->hostId = 13;

		$host1->hostName = "pop1";
		$host2->hostName = "pop2";

		return array($host1, $host2);
	}

	private function __GetHostById($o) {

	//	Logger::debug("x=" . VD::dump($o) . "v=" . $o->hostId);

		$host = new ZObject();
		$host->hostId = $o->hostId + 14;
		$host->hostName = "dasda";


		$disk0 = new ZObject();
		$disk0->size = "20";
		$disk0->name = "C:";
		$disk1 = new ZObject();
		$disk1->size = "40";
		$disk1->name = "D:";

		$host->disks = array($disk0, $disk1);

		$host->type = "Linux";

		$rv = $this->zs->GetHostById($o->hostId);
		if (!$rv) {
			$this->error = $this->zs->error;
			return false;
		}

	//	$host->hostId = $o->hostId + $rv;
		Logger::debug("h=" . VD::dump($rv));
		$this->resp->Host = $host;

		return $this->resp;

	}

	private function __GetZabbixHosts() {

		$retval = array();
		if (!is_array($this->zs->hosts_desc))
			return $retval;

		foreach ($this->zs->hosts_desc as $id => $host) {
			$zHost = new ZObject;
			$zHost->Id = $id;
			$zHost->Url = $host['hostname'];
			$zHost->Status = $host['status'];
			$zHost->Type = $host['type'];

			$retval[] = $zHost;	
		}

		return $retval;
	}

	private function __GetClients() {


	}

	private function __GetTriggerById($data) {

		$value = $this->zs->GetTriggerById($data->Id);
		if ($value === false) {
			$this->error = $this->zs->error;
			return false;
		}

		$trigger = new ZObject();
		$trigger->Ip = $value['ip'];
		$trigger->Id = $value['id'];
		$trigger->Name = $value['name'];
		$trigger->Status = $value['status'];
		$trigger->Value = $value['value'];
		$trigger->Priority = $value['priority'];
		$trigger->LastChange = date("c", $value['lastchange']);
		$trigger->Tags = $this->zs->form_tag($value['comments']);


		$this->resp->Trigger = $trigger;

		return $this->resp;	
	}

	private function __GetTriggersByIp($data) {
		Logger::info("ip=".VD::dump($data) );

		$data = $this->zs->GetTriggersByIp($data);
		if ($data === false) {
			$this->error = $this->zs->error;
			return false;
		}

		$retval = array();
		foreach ($data as $k => $value) {
			$trigger = new ZObject;

			$trigger->Ip = $value['ip'];
			$trigger->Id = $value['id'];
			$trigger->Name = $value['name'];
			$trigger->Status = $value['status'];
			$trigger->Value = $value['value'];
			$trigger->Priority = $value['priority'];
			$trigger->LastChange = date("c", $value['lastchange']);
			$trigger->Tags = $this->zs->form_tag($value['comments']);#array("TAG0", "TAG1");
			$retval[] = $trigger;
		}		

		return $retval;
	}

	private function __GetUserGroups($data) {
		$id = ZSingleton::SYSTEM_ZABBIX_ID;

		$data = $this->zs->hostcall($id, "GetGroups");

		$retval = array();
		foreach ($data as $k => $value) {
			$group = new ZObject;

			$group->GroupId = $value->usrgrpid;
			$group->GroupName = $value->name;
			$group->GuiAccess = $value->gui_access;
			$group->UsersStatus = $value->users_status;
			$group->Debug = $value->debug_mode;

			$retval[] = $group;
		}


		return $retval;
		#$data = $this->zs->GetGroups
	}

	private function __UpdateUserByName($user) {

		if (!$this->zs->UpdateUserByName($user)) {
			$this->error = $this->zs->error;
			return false;
		}

		$this->resp->ResultValue = 0;
		return $this->resp;
	}

	private static function sortFunc($a, $b) {
		$sort = self::$sort;

		$field = strtolower($sort->Field);
		if (!array_key_exists($field, $a) || !array_key_exists($field, $b)) {
			cLogger::error("Invalid sort field $field");
			return 0;
		}		

		if ($field == "name" || $field == "ip") {
			$rv = strcasecmp($b[$field], $a[$field]);
		} else {
			$rv = ($a[$field] < $b[$field]) ? 1 : -1;
		}

		if ($sort->Order == "asc")
			$rv *= -1;

		return $rv ;

	}

	private function __GetTriggersIds($idata) {

		Logger::debug("fff=" . VD::dump($idata));

		$retval = array();
		$data = $this->zs->GetTriggers($idata, false);
		if ($data === false) {
			$this->error = $this->zs->error;
			return false;
		}
	
		if (isset($idata->Sort)) {
			self::$sort = $idata->Sort;
			uasort($data, "zConnector::sortFunc");
		}
	
		Logger::debug("savexxx " . VD::dump($idata->Filter));
		
		$tags = false; 
		if (isset($idata->Filter->Tags)) {
			$tags = $idata->Filter->Tags;
			if (!is_array($tags))
				$tags = array($tags);
		}

		$ip = (isset($idata->Filter->Ip)) ? $idata->Filter->Ip : false;
		foreach ($data as $k => $value) {
			if ($ip && $value['ip'] != $ip) 
				continue;
			
			if ($tags) {
				$matched = true;
				$fetched_tags = $this->zs->form_tag($value['comments']);
	#			Logger::debug("eeeeeee" . VD::dump($fetched_args));
				if (empty($fetched_tags))
					continue;

				foreach ($tags as $tag) {
					if (!in_array($tag, $fetched_tags)) {
						$matched = false;
						break;	
					}
				}

				if (!$matched)
					continue;
			}

			$retval[] = $value['id'];
		}		

		return $retval;
	}


	/* NAUMEN */
	private function __listObjectTypes() {
		return self::TRIGGER_TYPE;

	}
	
	private function __listObjectTypeNames($id) {
		return self::TRIGGER_NAME;
	}


	private function __listObjectTypeParentChildPairs() {

		$rv = array( );

	/*	for ($i = 0; $i < 3; $i++) {
			$obj = new ZObject();
			$obj->ParentType = 1;
			$obj->ChildType = $i * 10;
			$rv[] = $obj;
		}*/

		return $rv;
	}

	private function __listAttrs($id) {
		
		$rv = array();
		$rv[] = new ZNaumenAttrObject("object_id","Идентификатор триггера");
		$rv[] = new ZNaumenAttrObject("type_id","Тип триггера");
		$rv[] = new ZNaumenAttrObject("name","Название триггера");
		$rv[] = new ZNaumenAttrObject("ip","IP-адрес устройства, для которого создан триггер");
		$rv[] = new ZNaumenAttrObject("importance","Важность триггера");
		$rv[] = new ZNaumenAttrObject("stage","Состояние триггера");
		$rv[] = new ZNaumenAttrObject("tag","Массив тегов", true);
		$rv[] = new ZNaumenAttrObject("datetime","Дата и время");

		return $rv;
	}


	private function __listObjectIdsPage($data) {

	}		

	/* END NAUMEN */
}

class ZObject {

}

class Response extends ZObject { 

}

class ZNaumenAttrObject extends ZObject {
	function __construct($code, $description, $isMult = null) {
		$this->Code = $code;
		$this->Description = $description;
		$this->IsMultiple = $isMult ? "multiple" : "null";
	}
}

class ZValidate extends Validate {

	public static function __GetHosts($args) {

		return true;
	}


	public static function __listAttrs(&$args) {
		return self::__listObjectTypeNames($args);
	}

	public static function __listObjectTypeNames(&$args) {
		$id = $args[0];

		if ($id != Zconnector::TRIGGER_TYPE) {
			self::$error = "No such objectId";
			return false;
		}

		return true;
	}

	public static function __GetGroups(&$args) {
		$data =& $args[0];

		if (!isset($data->GroupType) || $data->GroupType != "system") {
			self::$error = "Invalid group type";
			return false;
		}

		return true;
	}

	public static function __GetTriggerById(&$args) {
		$data =& $args[0];

		if (!isset($data->Id) || empty($data->Id)) {
			Logger::error("empty triggerid");
			self::$error = "Empty triggerid";
			return false;
		}
			
		$data->Id = intval($data->Id);

		return true;
	}

	public static function __GetTriggersIds(&$args) {
		$data =& $args[0];

		if (isset($data->Filter)) {
			if (!self::ValidateTime($data->Filter))
				return false;

			if ($data->Filter->TimeTo - $data->Filter->TimeFrom > 3600 * 2) {
				self::$error = "Too large interval: >2hours";
				return false;
			}

			if (isset($data->Filter->Ip)) {
				if (!self::ip($data->Filter->Ip)) {
					Logger::error("Ip validate failed " . $data->Filter->Ip);
					self::$error = "Invalid ipaddress";
					return false;
				}
			}

		} else {
			self::$error = "Filter MUST be defined";
			return false;
		}

		if (isset($data->Sort)) {
			
			if (!isset($data->Sort->Field) || !isset($data->Sort->Order)) {
				self::$error = "Sort parameters not specified";
				return false;
			}

			if ($data->Sort->Order != "asc" && $data->Sort->Order != "desc") {
				self::$error = "Invalid sort order";
				return false;
			}

			return true;
		}

		return true;
	}

	public static function __GetTriggersByIp(&$args) {
		$data =& $args[0];

		if (!self::ip($data->Ip)) {
			Logger::error("Ip validate failed " . $data->Ip);
			self::$error = "Invalid ipaddress";
			return false;
		}

		return self::ValidateTime($data);
	}

	private static function ValidateTime($data) {

		if (isset($data->TimeFrom)) {
			if (!self::datetime($data->TimeFrom)) {
				Logger::error("Error validate timefrom " . $data->TimeFrom);
				self::$error = "Invalid timefrom";
				return false;
			}

			$data->TimeFrom = strtotime($data->TimeFrom);
		} else 
			$data->TimeFrom = 0;	

		if (isset($data->TimeTo)) {
			if (!self::datetime($data->TimeTo)) {
				Logger::error("Error validate timeto " . $data->TimeTo);
				self::$error = "Invalid timeto";
				return false;
			}

			$data->TimeTo = strtotime($data->TimeTo);
		} else 
			$data->TimeTo = time();

		if ($data->TimeTo <= $data->TimeFrom) {
			Logger::error("Invalid time " . $data->TimeFrom . " to " . $data->TimeTo);
			self::$error = "Invalid time range";
			return false;
		}		


		return true;
	}

	public static function __UpdateUserByName(&$args) {
		$user =& $args[0];


//		if (!$user->PhoneNumbers)
//			$user->PhoneNumbers = array();

		if (isset($user->UserGroups) && $user->UserGroups && !is_array($user->UserGroups)) {
			$user->UserGroups = array($user->UserGroups);
		}

	
//		if (!is_array($user->PhoneNumbers))
//			$user->PhoneNumbers = array($user->PhoneNumbers);

		if (isset($user->PhoneNumbers)) {
			if ($user->PhoneNumbers && !is_array($user->PhoneNumbers)) {
//				Logger::debug("v= " .is_object($user->PhoneNumbers) . " c=" . method_exists($user->PhoneNumbers, "PhoneNumber"));
				if (is_object($user->PhoneNumbers) && isset($user->PhoneNumbers->PhoneNumber))
					$user->PhoneNumbers = array($user->PhoneNumbers);
			}

			foreach ($user->PhoneNumbers as $number) {
				if (!self::tel_number($number->PhoneNumber)) {
					Logger::error("Failed validate: " .$number->PhoneNumber);
					self::$error = "Invalid phone number " . $number->PhoneNumber;
					return false;		
				}

				$start = $end = false;
				if (isset($number->TimeFrom)) {
					$start = $number->TimeFrom;
					if (!self::time($number->TimeFrom)) {
						Logger::error("Invalid timefrom: " . $number->TimeFrom);
						self::$error = "Invalid timefrom: " . $number->TimeFrom;
						return false;
					}
				}

				if (isset($number->TimeTo)) {
					$end = $number->TimeTo;
					if (!self::time($number->TimeTo)) {
						Logger::error("Invalid timeto: " . $number->TimeTo);
						self::$error = "Invalid timeto: " . $number->TimeTo;
						return false;
					}
				}
		
				if (!self::time_period($start, $end)) {
					Logger::error("Invalid period $start-$end");
					self::$error = "Invalid period $start-$end";
					return false;
				}
			}
		}

		return self::login($user->Login);
	}

}











?>
