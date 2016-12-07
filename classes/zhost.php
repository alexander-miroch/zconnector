<?

class Result {

}

class zHost {

	var $sessionId;
		
	const I_ERR = "Internal error, please refer to server admin";
	const TIMEOUT = 3;

	const MEDIATYPE_SMS = "SMS";
	const MEDIATYPE_ACTIVE = 0;
	const MEDIATYPE_SEVERITY = 63;
	const ZABBIX_USER = 1;

	function __construct($id, $hostname, $username, $password, $type = 'SYSTEM') {
		$this->id = $id;
		$this->hostname = $hostname;
		$this->username = $username;
		$this->password = $password;
		$this->type = $type;
		$this->json = new Json();
		$this->counter = 1;
		$this->apiVersion = "2.0";

		$this->timeout = self::TIMEOUT;
		$this->error = false;
		$this->be_init();	
	}


	function __destruct() {
		if ($this->beLink)
			curl_close($this->beLink);
	}

	function auth() {
		Logger::debug("Authenticating to " . $this->hostname . " with " . $this->username . " and " . $this->password);

		$data['user'] = $this->username;
		$data['password'] = $this->password;

		$result = $this->call("user.authenticate", $data);
		if (!$result) {
			Logger::error("Auth failed for " . $this->username . " at " . $this->hostname);
			return false;
		}

		if (!Validate::hash($result)) {
			Logger::error("Invalid hash $result");
			return false;
		}

		$this->sessionId = $result;
		Logger::debug("Auth success");

		return true;
	}

	function call($method, $params = null) {
		$obj = array(
			'jsonrpc' => $this->apiVersion,
			'method'  => $method,
		);

		if (!is_null($params)) 
			$obj['params'] = $params;

		if (!is_null($this->sessionId)) 
			$obj['auth'] = $this->sessionId;
		
		$obj['id'] = $this->counter;		

		$jsonString = $this->json->encode($obj);
		Logger::debug("Sending to " . $this->hostname . " $jsonString");

		$result = $this->send($jsonString);
		if (!$this->json->isValid($result)) {
			Logger::error("Invalid result json $jsonString");
			$this->error = self::I_ERR;
			$this->counter++;
			return false;
		}

		$result = $this->json->decode($result);
		Logger::debug("actual result " . VD::dump($result));

		$result = $this->handle_error($result);
		if ($result === false) {
			$this->counter++;
			Logger::error("Error occured in response to request");
			return false;
		}

		$this->counter++;
		return $result;
	}

	private function handle_error($result) {
		if (!is_object($result)) {
			Logger::error("Empty result from " . $this->hostname);
			$this->error = "Empty result";
			return false;
		}

		if (empty($result->id)) {
			Logger::error("Idrequest not present");
			$this->error = self::I_ERR;
			return false;
		}

		if ($result->id != $this->counter) {
			Logger::error("Id mismatch " . $result->id . " vs " . $this->counter);
			$this->error = self::I_ERR;
			return false;
		}

		if (empty($result->result)) {
			if (empty($result->error)) {
				Logger::error("Empty response from " . $this->hostname);
				return $result->result;
			}

			$error = $result->error;
			Logger::debug("Error returned. code: " . $error->code . " msg: " . $error->message . " data: " . $error->data);
			$this->error = $error->message . " " . $error->data;
			return false;
		}

		return $result->result;
	}

	private function be_init() {
		$this->beLink = curl_init();
		if (!$this->beLink) {
			Logger::error("Failed to init curl: " . curl_error());
			return false;
		}	
		
		$url = $this->hostname . "/api_jsonrpc.php";

		curl_setopt($this->beLink, CURLOPT_URL, $url);
		curl_setopt($this->beLink, CURLOPT_VERBOSE, 0);
		curl_setopt($this->beLink, CURLOPT_FOLLOWLOCATION, 0);
		curl_setopt($this->beLink, CURLOPT_POST, 1);
		curl_setopt($this->beLink, CURLOPT_HEADER, 0);
		curl_setopt($this->beLink, CURLOPT_NOBODY, 1);
		curl_setopt($this->beLink, CURLOPT_HTTPGET, 1);
		curl_setopt($this->beLink, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($this->beLink, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($this->beLink, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($this->beLink, CURLOPT_CONNECTTIMEOUT, $this->timeout);
		curl_setopt($this->beLink, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
		
		return true;
	}

	private function send($data) {
		if (!$this->beLink) {
			Logger::error("Curl not inited");
			$this->error = self::I_ERR;
			return false;
		}	
	
		curl_setopt($this->beLink, CURLOPT_POSTFIELDS, $data);
		$result = @curl_exec($this->beLink);
		if (curl_errno($this->beLink)) {
			Logger::error("Request $data to ". $this->hostname ." error: " . curl_error($this->beLink));
			$this->error = self::I_ERR;
			return false;
		}
		
		$httpCode = curl_getinfo($this->beLink, CURLINFO_HTTP_CODE); 
		Logger::debug("Code $httpCode Result " . VD::chomp($result));

		if ($httpCode != 200) {
			Logger::error("Invalid code $httpCode from " . $this->hostname);
			$this->error = self::I_ERR;
			return false;
		}

		return $result;
	}

	private function form_period($number) {
		$start = (isset($number->TimeFrom)) ? $number->TimeFrom : "00:00";
		$end   = (isset($number->TimeTo))   ? $number->TimeTo   : "23:59";

		return "1-7,$start-$end;";
	}

	function GetMediaTypeByName($name) {
		$mts = $this->call("mediatype.get", array("output" => "extend"));
		if ($this->error) {
			Logger::debug(__METHOD__ . " error: " . $this->error);
			$this->error = "Error. Can't specify details";
			return false;
		}

		foreach ($mts as $mt) {
			if ($mt->description == $name)
				return $mt->mediatypeid;
		}

		Logger::error("Cant find mediatype by $name");
		$this->error = self::I_ERR;

		return false;
	}


	function GetHostById($data) {

		Logger::debug("id");

		return 12;
	}


	function GetGroups() {
		$groups = $this->call("usergroup.get", array("output" => "extend"));
		if ($this->error) {
			Logger::debug(__METHOD__ . " error: " . $this->error);
			$this->error = "Error. Can't specify details";
			return false;
		}

		return $groups;
	}

	function CreateGroup($name) {
		$group = $this->call("usergroup.create", array("name" => $name));
		if ($this->error) {
			Logger::debug(__METHOD__ . " error: " . $this->error);
			$this->error = "Error. Can't specify details";
			return false;
		}
		
		return $group;
	}
	

	function GetIpByTriggersIds($ids) {
		if (empty($ids))
			return array();

		$hosts = $this->call("host.get", array("triggerids" => $ids, "extend_pattern" => true, "output" => "extend", "selectInterfaces" => "extend"));
		if ($this->error) {
			Logger::debug(__METHOD__ . " error: " . $this->error);
			$this->error = "Can't get hosts by ids";
			return false;
		}

		$rv = array();
		if (!$hosts)
			return $rv;

		foreach ($hosts as $name => $value) {
			if (isset($value->interfaces)) {
				foreach ($value->interfaces as $interface) {
					$ip = $interface->ip;
				}
			}

			if (isset($value->triggerids)) {
				foreach ($value->triggerids as $triggerid) {
					$rv[$triggerid] = $value->ip;
				}
			} else if (isset($value->triggers)) {
				foreach ($value->triggers as $trigger) {
					$rv[$trigger->triggerid] = $ip;
				}
			}


		}

		Logger::debug("GetIpByTriggerIds retval: " . VD::dump($rv));

		return $rv;
		
	}


	function GetHostsByIp($ip) {
		$hosts = $this->call("host.get",  array (
					"filter" => array(
						"ip" => $ip
					),
					"extend_pattern" => true
				)					
		);
		//$hosts = $this->call("host.get", array("extended_output" => true));
		if ($this->error) {
			Logger::debug(__METHOD__ . " error: " . $this->error);
			$this->error = "Can't get hosts by ip";
			return false;
		}

		Logger::debug("GetHOstsByip: " . VD::dump($hosts));

		$rv = array();
		if (!$hosts)
			return $rv;

		foreach ($hosts as $name => $value) {
			if (is_object($value))
				$value = $value->hostid;

			$rv[] = $value;
		}

		return $rv;
	}

	function GetTriggersIds($filter = false) {
		$exdata['lastChangeSince'] = $filter->TimeFrom;
		$exdata['lastChangeTill'] = $filter->TimeTo;

		return $this->GetTriggers($exdata);


		
	}

	function GetTriggerById($id) {
		$exdata['triggerids'] = array($id);

		return $this->GetTriggers($exdata);
	}


	function GetTriggersHostIds($hostids) {
		$exdata = array();
		$exdata['hostids'] = $hostids;

		return $this->GetTriggers($exdata);
	}

	function GetTriggers($exdata) {
		$exdata['output'] = "extend";

//		$triggers = $this->call("trigger.get", array("extendoutput" => true, "hostids" => $hostids));
		$triggers = $this->call("trigger.get", $exdata);
		if ($this->error) {
			Logger::debug(__METHOD__ . " error: " . $this->error);
			$this->error = "Can't get triggers by ip";
			return false;
		}

		$rv = array();
		if (!$triggers)
			return $rv;

		foreach ($triggers as $name => $value) {
			$trigger['triggerid'] = $value->triggerid;
			$trigger['name'] = $value->description;
			$trigger['value'] = $value->value;
			$trigger['priority'] = $value->priority;
			$trigger['status'] = $value->status;
			$trigger['lastchange'] = $value->lastchange;
			$trigger['comments'] = $value->comments;
			$rv[] = $trigger;
		}
	

		Logger::info($triggers);

		return $rv;
	}

	function GetEventsByTriggerIds($args) {
		$events = $this->call("event.get", array("extendoutput" => true, "triggerids" => $args['ids'], 
					 "time_from" => $args['time_from'], "time_till" => $args['time_to'], "sortfield" => "clock"));
		if ($this->error) {
			Logger::debug(__METHOD__ . " error: " . $this->error);
			$this->error = "Can't get events by ip";
			return false;
		}

		$rv = array();
		if (!$events)
			return $rv;

		foreach ($events as $eventid => $value) {
			$event['triggerid'] = $value->objectid;
			$event['value'] = $value->value;
			$event['clock'] = $value->clock;
			$rv[] = $event;
		}

		return $rv;
	}

	function GetUserByName($name) {
		
		$users = $this->call("user.get", array(
				"filter" => array(
					"alias" => $name
				),
				"output" => "extend", 
				"selectMedias" => "extend"
		));
		if ($this->error) {
			Logger::debug(__METHOD__ . " error: " . $this->error);
			$this->error = "Update error. Can't post error details";
			return false;
		}

		Logger::debug("u=".VD::dump($users));

		$zUser = null;

		$name = strtoupper($name);
		foreach ($users as $user) {
			Logger::debug("a=$name " . $user->alias);
			if (strtoupper($user->alias) == $name) {
				if ($zUser) {
					Logger::debug("duplicate alias $name  from " . $this->hostname);
					$this->error = "Duplicate login error";
					return false;
				}
				$zUser = $user;
			}
		}

		if (!$zUser) {
			Logger::debug("User $name not found at " . $this->hostname);
			$this->error = "User not found";
		}

		return $zUser;
	}

	function CreateUser($user) {
		Logger::debug("Creating " . $user->Login);

		$data = array(
			"name" => $user->Login	,
                        'surname' => 'USER',
                        'alias' => $user->Login,
                        'passwd' => 'za2$bbwrq23512ix',		/* Doesn't matter */
			'usrgrps' => 8,				/* Guests , must be updated as soon as possible */
                        'url' => '',
                               'autologin' => 0,
                                'autologout' => 900,
                                'lang' => 'en_gb',
                                'theme' => 'default.css',
                                'refresh' => 30,
                                'rows_per_page' => 50,
                                'type' => self::ZABBIX_USER,
                                'user_medias' => array(),
		);
		$rv = $this->call("user.create", $data);
		if ($this->error) {
			Logger::debug("Failed to create user " . $this->error);
			return false;
		}

		if (!is_array($rv->userids)) {
			Logger::debug("Invalid response from zhost");
			$this->error = self::I_ERR;
			return false;
		}

		$userid = $rv->userids[0];
		Logger::debug("Created user " . $userid);

		return $userid;

	}

	function UpdateUser($user) {

		Logger::debug("processing ". __METHOD__ . " userid " . $user->UserId);

		Logger::debug("ssssss   " . VD::dump($user));

		$zGroupIds = array();
		if (isset($user->UserGroups)) {
			$groups = $this->GetGroups();
			if (!$groups) 
				return false;
		
			Logger::debug("Get groups success");

			$zGroups = array();
			foreach ($groups as $group) 
				$zGroups[$group->name] = $group->usrgrpid;

			foreach ($user->UserGroups as $groupName) {
				Logger::debug("ggg=$groupName");
				if (!array_key_exists($groupName, $zGroups)) {
					Logger::debug("No such group: $groupName");
					$newgroup = $this->CreateGroup($groupName);
					//Logger::debug("AAAAAAAA   " . VD::dump($newgroup));
					if(!isset($newgroup->usrgrpids)) {
						Logger::debug("Failed to create group: $groupName");						
						$this->error = "Failed to create group: $groupName";
						return false;
					}
					$zGroups[$groupName] = $newgroup->usrgrpids[0];
					Logger::debug("Create group: $groupName : ".$newgroup->usrgrpids[0]);
				}		

				$zGroupIds[] = $zGroups[$groupName];
			}
		}

		if (isset($user->PhoneNumbers)) {
			$sms_mt_id = $this->GetMediaTypeByName(self::MEDIATYPE_SMS);
			if (!$sms_mt_id)
				return false;

			$medias = array();
			foreach ($user->Media as $media) {
				if ($media->mediatypeid != $sms_mt_id) {
					$medias[] = array (
						"mediaid" => $media->mediaid,
						"mediatypeid" => $media->mediatypeid,
						"sendto" => $media->sendto,
						"period" => $media->period,
						"active" => $media->active,
						"severity" => $media->severity
					);
				}
			}

			foreach ($user->PhoneNumbers as $number) {
				$period = $this->form_period($number);
				$medias[] = array(
					"mediatypeid" => $sms_mt_id,
					"sendto" => $number->PhoneNumber,
					"period" => $period,
					"active" => self::MEDIATYPE_ACTIVE,
					"severity" => self::MEDIATYPE_SEVERITY
				);
			}
		}
	


		$data = array();
		$data[0] = array(
			"userid" => $user->UserId,
	//		"usrgrps" => $zGroupIds
		);

		if (isset($user->UserGroups)) {
			Logger::debug("will set groupids: " . VD::dump($zGroupIds));
			$data[0]['usrgrps'] = $zGroupIds;
		}

		$this->call("user.update", $data);
		if ($this->error) {
			Logger::debug("Zhost error " . $this->error);
			return false;
		}

		if (!isset($medias))
			return true;

		Logger::debug("Will update medias " . VD::dump($medias));

		$data = array(
			"users" => array("userid" => $user->UserId),
			"medias" => $medias
		);

		$this->call("user.updateMedia", $data);
		if ($this->error) {
			Logger::debug("Zhost error " . $this->error);
			return false;
		}

		return true;
	}



}


