<?


class zSingleton {
	protected static $instance;
	public $hosts_desc;
	public $hosts;
	
	const SYSTEM_ZABBIX_ID = 1;
	const CLIENT_ZABBIX_ID = 2;

	private function __construct() {
		$this->error = false;

		$this->db = DBFactory::get_instance(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD);
		$this->db->set_database(MYSQL_DATABASE);
		if (!$this->db->connect()) {
			Logger::error("Failed to create singleton due to db: " . $this->db->error);
			return null;
		}

		$this->fetch_hosts();
		$this->init_hosts();
	}

	function __destruct() {
		self::$instance->db->disconnect();
	}

	public static function get_instance() {
		if (is_null(self::$instance)) 		
			self::$instance = new zSingleton();

		return self::$instance;
	}

	public function fetch_hosts() {
		if (!$this->db->connected())
			return;

		$query = "SELECT * from zhosts";
		if (!$this->db->query($query)) {
			Logger::error("query $query failed: " . $this->db->error);
			return;
		}

		$this->hosts = array();
		while (($row = $this->db->fetch())) {
			$id = $row['id'];

			unset($row['id']);
			$this->hosts_desc[$id] = $row;
		}
	}

	public function init_hosts() {
		if (!is_array($this->hosts_desc))
			return;

		$this->hosts = array();
		foreach ($this->hosts_desc as $id => $data) {
			if ($data['status'] == 'DISABLED') {
				Logger::debug("Skipping disabled " . $data['hostname']);
				continue;
			}

			$this->hosts[$id] = new ZHost($id, 
						$data['hostname'], 
						$data['username'], 
						$data['password'],
						$data['type']
						);

			if (!$this->hosts[$id]->auth()) {
				Logger::error("auth for hostid $id failed");
				$this->error = Zhost::I_ERR;
				return false;
			}
		}
	}

	public function traverse($name, $args) {
		/*
			TODO: async call!!!
		*/

		$data = array();
		foreach ($this->hosts as $id => $host) {
			Logger::debug("x=$id");
			$retval = $this->hostcall($id, $name, $args);
			if ($retval === false) {
				$this->error = $this->hosts[$id]->error;
				return false;
			}

			if (!empty($retval))
				$data[$id] = $retval;
		}

		$this->wait();
		return $data;
	}	

	public function __call($name, $args) {

		Logger::debug("zsingleton $name() call, args: " . VD::dump($args));	

		$method = "__" . $name;
		if (method_exists($this, $method)) {
			Logger::debug("zsingleton calling $method()");
			return $this->$method($args);
		}

		Logger::debug("proxy to hostcall");

		$data = $this->traverse($name, $args);
		if ($data === false)
			return false;

		$data = $this->merge($data);

		Logger::debug("$name() call finished: " . VD::dump($data));
		
		return $data;
	}

	/*
		Async call wait
	*/
	private function wait() {
		return;
	}

	/* temporray only 1st host */
	private function merge($data) {
		$data = array_merge($data);
		return $data[0];
	}

	public function hostcall($id, $name, $args) {
		Logger::debug("calling $name() for hostid $id");

		if (!array_key_exists($id, $this->hosts)) {
			Logger::error("Invalid hostid $id calling $name()");
			$this->error = "Invalid hostid";
			return false;
		}

		$host =& $this->hosts[$id];
		if (!method_exists("ZHost", $name)) {
			Logger::error("Invalid method $name() for $id");
			$this->error = "No such method";
			return false;
		}

		$result = $host->$name($args);
		if (!$result)
			$this->error = $host->error;

		return $result;
	}	

	public function form_tag($comments) {
		if (!$comments)
			return array();

		$v = array();
		if (preg_match_all("/^Device#\d+?\r?$/m", $comments, $m)) {
		        $v = array_merge($v, $m[0]);
		}	

		if (preg_match_all("/^Port#.+?\r?$/m", $comments, $m)) {
		        $v = array_merge($v, $m[0]);
		}
		if (preg_match_all("/^DevPort#.+?\r?$/m", $comments, $m)) {
		        $v = array_merge($v, $m[0]);
		}

		$rv = array();
		foreach ($v as $item) {
			$rv[] = trim($item);
		}

		return $rv;
	}

	private function get_kv($array) {
		$rv = array();

		$keys = array_keys($array);
		$rv[] = $keys[0];

		$values = array_values($array);
		$rv[] = $values[0];

		return $rv;
	}
/*
	private function  __GetHostById($args) {
		Logger::debug("xxxxxxxxxxxxxxx");
	}
*/

	private function drop_errors() {
		$this->error = false;

		$host_ids = array_keys($this->hosts);
		foreach ($host_ids as $id) {
			$this->hosts[$id]->error = false;
		}
	}

	private function zhost_id($id) {
		$zbxid = $id >> 24;
		$itemid = $id & 0xffffff;

		return array($zbxid, $itemid);
	}

	private function id($zbxid, $id) {
		return ($zbxid << 24) | $id;
	}

	private function __GetTriggerById($args) {
		$id = $args[0];

		list ($zbxid, $triggerid) = $this->zhost_id($id);

		Logger::debug("Getting trigger for $zbxid for triggerid=$triggerid");
		$trigger = $this->hostcall($zbxid, "GetTriggerById", $triggerid);
		if ($trigger === false) {
			Logger::error("failed to get trigger $id for $zbxid");
			$this->error = Zhost::I_ERR;
			return false;
		}


		$trigger = $trigger[0];
		Logger::debug("trigger " . VD::dump($trigger));

		Logger::debug("query $zbxid for " . $trigger['triggerid']);
		$ips = $this->hostcall($zbxid, "GetIpByTriggersIds", array($trigger['triggerid']));
		if ($ips === false) {
			Logger::error("failed to query ips for $zbxid");
			$this->error = Zhost::I_ERR;
			return false;
		}

		$trigger['ip'] = $ips[$trigger['triggerid']];
		$trigger['id'] = $id;
		unset($trigger['triggerid']);

		return $trigger;
	}


	private function __GetTriggers($args) {
		$data = $args[0];

		Logger::debug("xxx=" . VD::dump($data));

		if (!isset($data->Filter))
			$result = $this->traverse("GetTriggersIds");
		else
			$result = $this->traverse("GetTriggersIds", $data->Filter);

		$return = array();
		foreach ($result as $zbxid => $triggers) {
			$ids = array();

	//		Logger::debug("TRIGGERSSSSSSSSSSSSSSsss " .VD::dump($triggers));

			foreach ($triggers as $trigger) {
				$id = $trigger['triggerid'];
				$ids[] = $id;
				
			//	$return[$this->id($zbxid,$id)] = $this->id($zbxid,$id);
			}	
			
			if (empty($ids)) {
				Logger::error("empty ids for $zbxid");
				continue;
			}

			Logger::debug("quering $zbxid for ips");
			$ips = $this->hostcall($zbxid, "GetIpByTriggersIds", $ids);
			if ($ips === false) {
				Logger::error("failed to query ips for $zbxid");
				$this->error = Zhost::I_ERR;
				return false;
			}

			
			foreach ($triggers as $trigger) {
				$id = $trigger['triggerid'];
				Logger::debug("FOR ID=$id");
				if (array_key_exists($id, $ips)) {
					$trigger['ip'] = $ips[$id];
				} else {
					$trigger['ip'] = "0.0.0.0";
				}

				unset($trigger['triggerid']);
				$trigger['id'] = $this->id($zbxid,$id);
				$return[$trigger['id']] = $trigger;
			
			}
		}
		

		Logger::debug("qwwwwwwww=" . VD::dump($return));

		return $return;
	}

	private function __GetTriggersByIp($args) {
		$data = $args[0];

		$result = $this->traverse("GetHostsByIp", $data->Ip);
		if ($result === false) {
			Logger::error("Failed to gethosts");
			$this->error = "Failed to get triggers";
			return false;
		}

		$return = array();
		foreach ($result as $zbxid => $hostids) {
			$triggers = $this->hostcall($zbxid, "GetTriggersHostIds", $hostids);

			$ids = array();
			$triggers_by_id = array();
			foreach ($triggers as $k => $trigger) {
				$id = $trigger['triggerid'];
				$ids[] = $id;
//				$triggers_by_id[$trigger['triggerid']] = $trigger;
				unset($trigger['triggerid']);
				$trigger['id'] = $this->id($zbxid,$id);
				$trigger['ip'] = $data->Ip;
				$return[$this->id($zbxid, $id)] = $trigger;
			}

			if (empty($ids)) 
				continue;
	
			/*		
			$evargs = array(
				"ids" => $ids,
				"time_from" => $data->TimeFrom,
				"time_to" => $data->TimeTo
			);

			$events = $this->hostcall($zbxid, "GetEventsByTriggerIds", $evargs);
			
			if (empty($events))
				continue;
			
			foreach ($events as $k => $event) {
				$id = $event['triggerid'];
				if (array_key_exists($id, $triggers_by_id)) {
					unset($triggers_by_id[$id]['triggerid']);
					$return[$this->id($zbxid,$id)] = $triggers_by_id[$id];
				}
			}
			*/
//			Logger::info("rrrrx " . VD::dump($return));
		}

		Logger::debug("GetTriggersByIp retval: " . VD::dump($return));
	
		return $return;
	}

	private function __UpdateUserByName($args) {
		$user = $args[0];

		$data = $this->hostcall(self::CLIENT_ZABBIX_ID, "GetUserByName", $user->Login);
		if (!$data) {
			if ($this->error == "User not found") {
				$this->drop_errors();
				Logger::debug("Will create e=" . $this->hosts[self::CLIENT_ZABBIX_ID]->error . "l=" . $user->Login);

				Logger::debug("hostid ".self::CLIENT_ZABBIX_ID.", newdatax " . VD::dump($user));
				// TODO: hostid
				$userid = $this->hostcall(self::CLIENT_ZABBIX_ID, "CreateUser", $user);
				if (!$userid) {
					Logger::error("Failed to create user " . $user->Login);
					$this->error = $this->hosts[self::CLIENT_ZABBIX_ID]->error;
					return false;
				}

	//			$data = $this->traverse("GetUserByName", $user->Login);
				$data = $this->hostcall(self::CLIENT_ZABBIX_ID, "GetUserByName", $user->Login);
				if (!$data)
					return false;				

			} else
				return false;
		}

		$data = array(self::CLIENT_ZABBIX_ID => $data);

		Logger::debug("UDDD=" . VD::dump($data));

		if (!is_array($data)) {
			$this->error = "Invalid data returned";
			Logger::error("Invalid data returned: $data");
			return false;
		}
		
		$count = count($data);
		if (!$count) {
			$this->error = "User not found";
			Logger::error("User " . $user->Login . " not found");
			return false;
		}

		if ($count != 1) {
			$this->error = "More than one user found. Can't update";
			Logger::error("Many ($count) users with name " . $user->Login . " found");
			return false;
		}

		list ($hostid, $zUser) = $this->get_kv($data);

		Logger::debug("hostid $hostid, data: " . VD::dump($zUser));

		unset($user->Login);
		$user->UserId = $zUser->userid;
		$user->Media = $zUser->medias;

		Logger::debug("hostid $hostid, newdata " . VD::dump($user));
		$result = $this->hostcall($hostid, "UpdateUser", $user);
		if (!$result) {
			if (!$this->error) 
				$this->error = $this->hosts[$id]->error;
		
			Logger::error("Failed UpdateUserByName: " . $this->error . " args: " . VD::dump($user));
			return false;
		}
	
		Logger::debug(__METHOD__ . ": $hostid ->" . VD::dump($result));
		return true;
	}

}



?>
