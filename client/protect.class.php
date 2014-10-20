<?php
/***********************************************************************
 * Protect - ������� �������������� ��� PHP �������� �� ��������� ������
 ***********************************************************************
 * @author       Oleg Budrin <support@regger.pw>
 * @copyright    Copyright (c) 2013-2015, Oleg Budrin (Mofsy)
 **********************************************************************/

class protect
{
	/*
	 * ������, ��������� ��� ���������
	 * @bool
	 */
	public $errors = false;

	/*
	 * ������ � ����������� � ��������
	 * @array
	 */
	public $license_info = array();

	/*
	 * ������������ ���� ���������
	 * @string
	 */
	public $license_key = '';

	/*
	 * ������ ����� �������, ��� �������� �������� � ������� �����.
	 * @string
	 */
	public $api_server = '';

	/*
	 * ��������� ���� ������� ��������
	 * @integer
	 */
	public $remote_port = 80;

	/*
	 * ������ �������� ������ �� ������� ��������
	 * @integer
	 */
	public $remote_timeout = 10;

	/*
	 * ������ ������ �������� �����
	 * database - ������� � ����
	 * filesystem - ������� � �����
	 */
	public $local_key_storage = 'filesystem';

	/*
	 * ----------
	 */
	public $read_query = false;

	/*
	 * ----------
	 */
	public $update_query = false;

	/*
	 * ������ ���� �� ���������� ����� � ��������� ���������
	 * @string
	 */
	public $local_key_path = './';

	/*
	 * �������� ����� � ��������� ���������
	 * @string
	 */
	public $local_key_name = 'license.lic';

	/*
	 * ���������� ������� ������� � ������� ��������.
	 *
	 * ��������:
	 * s - �� �������
	 * c - �� cURL
	 * f - �� file_get_contents
	 *
	 * @string
	 */
	public $local_key_transport_order = 'scf';

	/*
	 * ������ ����� ��������� ������ �������� ���������� �����, ����� �������� �������� �������.
	 * ����� ��� �� ���������� �������, ���� ������ �������� �������� �� ��������.
	 *
	 * @integer
	 */
	public $local_key_grace_period = 4;

	/*
	 *
	 *
	 * @integer
	 */
	public $local_key_last = 0;

	/*
	 * ������, � ������� �������� �������� ����������.
	 */
	public $validate_download_access = false;

	/*
	 * ���� ������ ��������.
	 */
	public $release_date = false;

	/*
	 * ��������� ���� ��� ���������
	 *
	 * @array
	 */
	public $key_data;

	/*
	 * ����������� �������� �������� � ������ ���������
	 *
	 * @array
	 */
	public $status_messages;

	/*
	 *
	 */
	public $valid_for_product_tiers = false;

	/*
	 *
	 */
	private  $trigger_grace_period;


	/*
	 * ����������� ������
	 */
	public function __construct()
	{
		$this->valid_local_key_types = array('protect');
		$this->local_key_type = 'protect';

		$this->key_data = array(
						'custom_fields' => array(),
						'download_access_expires' => 0,
						'license_expires' => 0,
						'local_key_expires' => 0,
						'status' => 'Invalid',
						);

		$this->status_messages = array(
						'active' => 'This license is active.',
						'suspended' => 'Error: This license has been suspended.',
						'expired' => 'Error: This license has expired.',
						'pending' => 'Error: This license is pending review.',
						'download_access_expired' => 'Error: This version of the software was released '.
													 'after your download access expired. Please '.
													 'downgrade or contact support for more information.',
						'missing_license_key' => 'Error: The license key variable is empty.',
						'unknown_local_key_type' => 'Error: An unknown type of local key validation was requested.',
						'could_not_obtain_local_key' => 'Error: I could not obtain a new local license key.',
						'maximum_grace_period_expired' => 'Error: The maximum local license key grace period has expired.',
						'local_key_tampering' => 'Error: The local license key has been tampered with or is invalid.',
						'local_key_invalid_for_location' => 'Error: The local license key is invalid for this location.',
						'missing_license_file' => 'Error: Please create the following file (and directories if they dont exist already): ',
						'license_file_not_writable' => 'Error: Please make the following path writable: ',
						'invalid_local_key_storage' => 'Error: I could not determine the local key storage on clear.',
						'could_not_save_local_key' => 'Error: I could not save the local license key.',
						'license_key_string_mismatch' => 'Error: The local key is invalid for this license.',
						'localhost' => 'localhost',
		);

	}

	/*
	* ���������
	*
	* @return string
	*/
	public function validate()
	{
		/*
		 * ���� ���� ��������� ������, �� ������ ������
		 */
		if (!$this->license_key)
		{
			return $this->errors = $this->status_messages['missing_license_key'];
		}

		/*
		 * ���� ��� ������������ ���� ���������� �����
		 */
		if (!in_array(strtolower($this->local_key_type), $this->valid_local_key_types))
		{
			return $this->errors = $this->status_messages['unknown_local_key_type'];
		}

		/*
		 * ���� ��������� ��������� � Windows
		 */
		if($this->get_local_ip() && $this->is_windows())
		{
			return $this->errors = $this->status_messages['localhost'];
		}

		/*
		 * �������� ��������� ���� �� ���������� ���������
		 */
		switch($this->local_key_storage)
		{
			/*
			 * �������� �� ���� ������
			 */
			case 'database':
				$local_key = $this->db_read_local_key();
				break;
			/*
			 * �������� �� �����
			 */
			case 'filesystem':
				$local_key = $this->read_local_key();
				break;
			/*
			 * ��� ��������� ������ ������
			 */
			default:
				return $this->errors = $this->status_messages['missing_license_key'];
		}

		/*
		 * ����������� ��������� �� ������, ���� �� ������� �������� ����� ��������� ���� � ������� ��� ���������.
		 */
		$this->trigger_grace_period = $this->status_messages['could_not_obtain_local_key'];

		/*
		 * ���� �������� ���������� ����� ����� � �� �������� �������� ����� ��������� ����,
		 * �� ���� ������ ����� �� ������������� ���������.
		 */
		if ( $this->errors == $this->trigger_grace_period && $this->local_key_grace_period )
		{
			/*
			 * �������� �������� ������
			 */
			$grace = $this->process_grace_period($this->local_key_last);
			if ($grace['write'])
			{
				/*
				 * ���� ���� �������� ������, �� ���������� ����� ����
				 */
				if ($this->local_key_storage == 'database')
				{
					$this->db_write_local_key($grace['local_key']);
				}
				elseif ($this->local_key_storage == 'filesystem')
				{
					$this->write_local_key($grace['local_key'], "{$this->local_key_path}{$this->local_key_name}");
				}
			}

			/*
			 * ���� ��� �������� ������� ������������
			 */
			if ($grace['errors'])
			{
				return $this->errors = $grace['errors'];
			}

			/*
			 * ���� �������� ������� �� ������������
			 */
			$this->errors = false;

			return $this;
		}

		/*
		 * ���������, ��� �� ������, ���� ���� �� ����������.
		 */
		if ($this->errors)
		{
			return $this->errors;
		}

		/*
		 * ��������� ��������� ����
		 */
		return $this->validate_local_key($local_key);
	}

	/*
	* ����������� ������������ ����� �������� ��������� �������
	*
	* @param integer $local_key_expires
	* @param integer $grace
	* @return integer
	*/
	private function calc_max_grace($local_key_expires, $grace)
	{
		return ( (integer)$local_key_expires + ( (integer)$grace * 86400 ) );
	}

	/*
	* ��������� ��������� ������� ��� ���������� �����
	*
	* @param string $local_key
	* @return string
	*/
	private function process_grace_period($local_key)
	{
		/*
		 * �������� ���� ��������� ���������� �����
		 */
		$local_key_src = $this->decode_key($local_key);
		$parts = $this->split_key($local_key_src);
		$key_data = unserialize($parts[0]);
		$local_key_expires = (integer)$key_data['local_key_expires'];
		unset($parts, $key_data);

		/*
		 * ������� ��������� �������
		 */
		$write_new_key = false;
		$parts = explode("\n\n", $local_key); $local_key = $parts[0];
		foreach ( $local_key_grace_period=explode(',', $this->local_key_grace_period) as $key => $grace )
		{
			// ��������� �����������
			if (!$key) { $local_key.="\n"; }

			// ������� �������� ������
			if ($this->calc_max_grace($local_key_expires, $grace) > time() ) { continue; }

			// log the new attempt, we'll try again next time
			$local_key.="\n{$grace}";

			$write_new_key = true;
		}

		/*
		 * ��������� ������������ ����� ��������� �������
		 */
		if ( time() > $this->calc_max_grace( $local_key_expires, array_pop($local_key_grace_period) ) )
		{
			return array('write' => false, 'local_key' => '', 'errors' => $this->status_messages['maximum_grace_period_expired']);
		}

		return array('write' => $write_new_key, 'local_key' => $local_key, 'errors' => false);
	}

	/*
	* �������� �� �������������� � ��������� �������
	*
	* @param string $local_key
	* @param integer $local_key_expires
	* @return integer
	*/
	private function in_grace_period($local_key, $local_key_expires)
	{
		$grace = $this->split_key($local_key, "\n\n");
		if (!isset($grace[1])) { return -1; }

		return (integer)( $this->calc_max_grace( $local_key_expires, array_pop( explode( "\n", $grace[1] ) ) )-time() );
	}

	/*
	* ���������� ��������� ����.
	*
	* @param string $local_key
	* @return string
	*/
	private function decode_key($local_key)
	{
		return base64_decode(str_replace("\n", '', urldecode($local_key)));
	}

	/*
	* ��������� ��������� ���� �� �����
	*
	* @param string $local_key
	* @param string $token		{protect} or \n\n
	* @return string
	*/
	private function split_key($local_key, $token = '{protect}')
	{
		return explode($token, $local_key);
	}

	/*
	* ��������� ������� ����� �� ���������� �������
	*
	* @param string $key
	* @param array $valid_accesses
	* @return array
	*/
	private function validate_access($key, $valid_accesses)
	{
		return in_array($key, (array)$valid_accesses);
	}

	/*
	* �������� ������ ��������� IP �������
	*
	* @param string $key
	* @param array $valid_accesses
	* @return array
	*/
	private function wildcard_ip($key)
	{
		$octets = explode('.', $key);

		array_pop($octets);
		$ip_range[] = implode('.', $octets).'.*';

		array_pop($octets);
		$ip_range[] = implode('.', $octets).'.*';

		array_pop($octets);
		$ip_range[] = implode('.', $octets).'.*';

		return $ip_range;
	}

	/*
	* �������� �������� ��� � ������ wildcard
	*
	* @param string $key
	* @param array $valid_accesses
	* @return array
	*/
	private function wildcard_domain($key)
	{
		return '*.' . str_replace('www.', '', $key);
	}

	/*
	* �������� server hostname � ������ wildcard
	*
	* @param string $key
	* @param array $valid_accesses
	* @return array
	*/
	private function wildcard_server_hostname($key)
	{
		$hostname = explode('.', $key);
		unset($hostname[0]);

		$hostname = (!isset($hostname[1])) ? array($key) : $hostname;

		return '*.' . implode('.', $hostname);
	}

	/*
	* �������� ������������ ����� ������� ������� �� ����������
	*
	* @param array $instances
	* @param string $enforce
	* @return array
	*/
	private function extract_access_set($instances, $enforce)
	{
		foreach ($instances as $key => $instance)
		{
			if ($key != $enforce)
			{
				continue;
			}
			return $instance;
		}

		return array();
	}

	/*
	* ��������� ���������� ������������� �����
	*
	* @param string $local_key
	* @return string
	*/
	private function validate_local_key($local_key)
	{
		/*
		 * ��������������� �������� � ������� �����
		 */
		$local_key_src = $this->decode_key($local_key);

		/*
		 * ��������� �� ������
		 */
		$parts = $this->split_key($local_key_src);

		/*
		 * ��������� �� ������� ���� ������, ���� ���, �� �� �� ����� ��������� ������.
		 */
		if (!isset($parts[1]))
		{
			return $this->errors = $this->status_messages['local_key_tampering'];
		}

		/*
		 * ��������� ��������� ���� �� ��������. ���� �� ���������, �� ��������� ������.
		 */
		if ( md5((string)$this->secret_key . (string)$parts[0]) != $parts[1] )
		{
			return $this->errors = $this->status_messages['local_key_tampering'];
		}
		unset($this->secret_key);

		/*
		 * ��������������� ������ ���������� ����� � ������� �����
		 */
		$key_data = unserialize($parts[0]);
		$instance = $key_data['instance']; unset($key_data['instance']);
		$enforce = $key_data['enforce']; unset($key_data['enforce']);
		$this->key_data = $key_data;

		/*
		 * ��������� ������������ ���� �� �������������� � ����������� ������������� �����.
		 */
		if ( (string)$key_data['license_key_string'] != (string)$this->license_key )
		{
			return $this->errors=$this->status_messages['license_key_string_mismatch'];
		}

		/*
		 * ��������� ������ ��������, ���� ��� �� �������, �� ���������� ������
		 */
		if ( (string)$key_data['status'] != 'active' )
		{
			return $this->errors = $this->status_messages[$key_data['status']];
		}

		/*
		 * ��������� ���� ��������� ��������, ���� ���� �����, �� ���������� ��������� �� ������
		 */
		if ((string)$key_data['license_expires'] != 'never' && (integer)$key_data['license_expires'] < time())
		{
			return $this->errors = $this->status_messages['expired'];
		}

		/*
		 * ��������� ���� ��������� ���������� �����, ���� �� �����, �� ������� ���� � �������� ������� �����
		 */
		if ( (string)$key_data['local_key_expires'] != 'never' && (integer)$key_data['local_key_expires'] < time() )
		{
			if ($this->in_grace_period($local_key, $key_data['local_key_expires']) < 0)
			{
				/*
				 * ���� ���� �����, ������� �� �������������� ��������� ����
				 */
				$this->clear_cache_local_key(true);

				/*
				 * ��������� ��������� ������ �����.
				 */
				return $this->validate();
			}
		}

		/*
		 *  ��������� ���� ��������� ���������� (�� �������), ���� �� �����������
		 */
		if ($this->validate_download_access && strtolower($key_data['download_access_expires']) != 'never' && (integer)$key_data['download_access_expires'] < strtotime($this->release_date))
		{
			return $this->errors = $this->status_messages['download_access_expired'];
		}

		/*
		 * ��������� ����� �� ������:
		 *
		 * - ������ ������� ��� �������� ������������.
		 * - ��������� �����. ����� ����������� ����� �� ���������, ���� ����� ������ � www.
		 * - ��������� IP ����� �������.
		 * - ��������� ��� �������.
		 *
		 */
		$conflicts = array();
		$access_details = $this->access_details();

		foreach ((array)$enforce as $key)
		{
			$valid_accesses = $this->extract_access_set($instance, $key);
			if (!$this->validate_access($access_details[$key], $valid_accesses))
			{
				$conflicts[$key] = true;

				if (in_array($key, array('ip', 'server_ip')))
				{
					foreach ($this->wildcard_ip($access_details[$key]) as $ip)
					{
						if ($this->validate_access($ip, $valid_accesses))
						{
							unset($conflicts[$key]);
							break;
						}
					}
				}
				elseif (in_array($key, array('domain')))
				{
					if ($this->validate_access($this->wildcard_domain($access_details[$key]), $valid_accesses))
					{
						unset($conflicts[$key]);
					}
				}
				elseif (in_array($key, array('server_hostname')))
					{
					if ($this->validate_access($this->wildcard_server_hostname($access_details[$key]), $valid_accesses))
						{
						unset($conflicts[$key]);
						}
					}
				}
		}

		/*
		 * ���� ��������� ��� ���������� ����� ��������, �� ������ ������.
		 * ����� �� ����� ����� ����������� � ������ ������������ �� ��������� ��������.
		 */
		if (!empty($conflicts))
		{
			return $this->errors = $this->status_messages['local_key_invalid_for_location'];
		}
	}

	/*
	* ������ ��������� ���� �� ���� ������
	*
	* @return string
	*/

	public function db_read_local_key()
	{
		$query = @mysql_query($this->read_query);
		if ($mysql_error=mysql_error()) { return $this -> errors="Error: {$mysql_error}"; }

		$result = @mysql_fetch_assoc($query);
		if ($mysql_error=mysql_error()) { return $this -> errors="Error: {$mysql_error}"; }

		// ���� ��������� ���� ������
		if (!$result['local_key'])
		{
			// �������� ����� ��������� ����
			$result['local_key'] = $this->fetch_new_local_key();

			// ��� �� � �������? ��������� ������ ��������� �����, ���� ���� �� ����������.
			if ($this->errors) { return $this->errors; }

			// ���������� ����� ��������� ���� � ����.
			$this->db_write_local_key($result['local_key']);
		}

		// ���������� ��������� ����
		return $this->local_key_last=$result['local_key'];
	}

	/*
	* ���������� ��������� ���� � ���� ������
	*
	* @return string|boolean string on error; boolean true on success
	*/
	public function db_write_local_key($local_key)
	{
		@mysql_query(str_replace('{local_key}', $local_key, $this->update_query));
		if ($mysql_error = mysql_error()) { return $this -> errors = "Error: {$mysql_error}"; }

		return true;
	}

	/*
	* ������ ���������� ���������� ������������� ����� �� �����.
	*
	* @return string
	*/
	public function read_local_key()
	{
		// ��������� �� ������������� ����� � ���������
		if ( !file_exists( $path = "{$this->local_key_path}{$this->local_key_name}" ) )
		{
			return $this->errors = $this->status_messages['missing_license_file'] . $path;
		}
		// ��������� �� ����������� ������ ����� ��������
		if (!is_writable($path))
		{
			return $this->errors = $this->status_messages['license_file_not_writable'] . $path;
		}

		// ��������� �� ������� ���������� ���������� �����
		if ( !$local_key = @file_get_contents($path) )
		{
			// �������� ����� ��������� ����
			$local_key = $this->fetch_new_local_key();

			// ��������� �� ������� ������
			if ($this->errors) { return $this->errors; }

			// ���������� ����� ��������� ����
			$this->write_local_key(urldecode($local_key), $path);
		}

		// ���������� ��������� ����
		return $this->local_key_last = $local_key;
	}

	/*
	* ������� ��������� ��������� ����
	*
	* @param boolean $clear
	* @return string on error
	*/
	public function clear_cache_local_key($clear=false)
	{
		switch(strtolower($this->local_key_storage))
		{
			case 'database':
				$this->db_write_local_key('');
				break;

			case 'filesystem':
				$this->write_local_key('', "{$this->local_key_path}{$this->local_key_name}");
				break;

			default:
				return $this->errors = $this->status_messages['invalid_local_key_storage'];
		}
	}

	/*
	* ���������� ��������� ���� � ����
	*
	* @param string $local_key
	* @param string $path
	* @return string|boolean (string ��� ������; boolean true ��� ������).
	*/
	public function write_local_key($local_key, $path)
	{
		$fp = @fopen($path, 'w');
		if (!$fp) { return $this->errors = $this->status_messages['could_not_save_local_key']; }
		@fwrite($fp, $local_key);
		@fclose($fp);

		return true;
	}

	/*
	* ������ � API ������� �������� ��� ��������� ������ ���������� �����
	*
	* @return string|false string local key ��� ������; boolean false ��� ������.
	*/
	private function fetch_new_local_key()
	{
		/*
		 * C������� ������ �������
		 */
		$querystring = "mod=license&task=protect_validate_license&license_key={$this->license_key}&";
		$querystring .= $this->build_querystring($this->access_details());

		/*
		 * ��������� ������� ������ ��� ��������� ������� ������� ($this->access_details)
		 */
		if ($this->errors)
		{
			return false;
		}

		/*
		 *  �������� ��������� ������� �������.
		 */
		$priority = $this->local_key_transport_order;

		/*
		 * ������� �������� ��������� ���� �������� ��������� ������� ������� �� ������
		 */
		while (strlen($priority))
		{
			$use = substr($priority, 0, 1);

			// ���� ������������ fsockopen()
			if ($use=='s')
			{
				if ($result = $this->use_fsockopen($this->api_server, $querystring))
				{
					break;
				}
			}

			// ���� ������������ curl()
			if ($use=='c')
			{
				if ($result = $this->use_curl($this->api_server, $querystring))
				{
					break;
				}
			}

			// ���� ������������ fopen()
			if ($use=='f')
			{
				if ($result = $this->use_fopen($this->api_server, $querystring))
				{
					break;
				}
			}

			$priority = substr($priority, 1);
		}

		/*
		 * ���� �� ������� ��������� ������ ����� ��������,
		 * ������ ������ ��������� ���������� �����
		 */
		if (!$result)
		{
			$this->errors = $this->status_messages['could_not_obtain_local_key'];
			return false;
		}

		/*
		 * ���� ��������� ������� ������ ������ �����
		 * �� ������ ������ + ����� �������� Error �� ������ � �������.
		 */
		if (substr($result, 0, 7) == 'Invalid')
		{
			$this->errors = str_replace('Invalid', 'Error', $result);
			return false;
		}

		/*
		 * ���� ��������� ������� ������ ������ (�������� ������ ����������)
		 */
		if (substr($result, 0, 5) == 'Error')
		{
			$this->errors = $result;
			return false;
		}

		return $result;
	}

	/*
	* ����������� ������� � ������ ������� � ���� key / value ���
	*
	* @param array $array
	* @return string
	*/
	private function build_querystring($array)
	{
		$buffer='';
		foreach ((array)$array as $key => $value)
		{
			if ($buffer) { $buffer.='&'; }
			$buffer.="{$key}={$value}";
		}

		return $buffer;
	}

	/*
	* �������� ������ � �������� �������
	*
	* @return array
	*/
	private function access_details()
	{
		$access_details=array();

		// ���� ������� phpinfo() ����������
		if (function_exists('phpinfo'))
		{
			ob_start();
			phpinfo();
			$phpinfo = ob_get_contents();
			ob_end_clean();

			$list = strip_tags($phpinfo);
			$access_details['domain'] = $this->scrape_phpinfo($list, 'HTTP_HOST');
			$access_details['ip'] = $this->scrape_phpinfo($list, 'SERVER_ADDR');
			$access_details['directory'] = $this->scrape_phpinfo($list, 'SCRIPT_FILENAME');
			$access_details['server_hostname'] = $this->scrape_phpinfo($list, 'System');
			$access_details['server_ip'] = @gethostbyname($access_details['server_hostname']);
		}

		// �� ������ ������ �������� ��� ������
		$access_details['domain'] = ($access_details['domain']) ? $access_details['domain'] : $_SERVER['HTTP_HOST'];
		$access_details['ip'] = ($access_details['ip']) ? $access_details['ip'] : $this->server_addr();
		$access_details['directory'] = ($access_details['directory']) ? $access_details['directory'] : $this->path_translated();
		$access_details['server_hostname'] = ($access_details['server_hostname']) ? $access_details['server_hostname'] : @gethostbyaddr($access_details['ip']);
		$access_details['server_hostname'] = ($access_details['server_hostname']) ? $access_details['server_hostname'] : 'Unknown';
		$access_details['server_ip'] = ($access_details['server_ip']) ? $access_details['server_ip'] : @gethostbyaddr($access_details['ip']);
		$access_details['server_ip'] = ($access_details['server_ip']) ? $access_details['server_ip'] : 'Unknown';

		// Last resort, send something in...
		foreach ($access_details as $key => $value)
		{
			$access_details[$key]=($access_details[$key])?$access_details[$key]:'Unknown';
		}

		// enforce product IDs
		if ($this->valid_for_product_tiers)
		{
			$access_details['valid_for_product_tiers'] = $this->valid_for_product_tiers;
		}

		return $access_details;
	}

	/*
	* �������� ���� �� ���������� �������
	*
	* @return string|boolean string ��� ������; boolean ��� ������
	*/
	private function path_translated()
	{
		$option = array('PATH_TRANSLATED',
					'ORIG_PATH_TRANSLATED',
					'SCRIPT_FILENAME',
					'DOCUMENT_ROOT',
					'APPL_PHYSICAL_PATH');

		foreach ($option as $key)
		{
			if (!isset($_SERVER[$key])||strlen(trim($_SERVER[$key]))<=0) { continue; }

			if ($this->is_windows() && strpos($_SERVER[$key], '\\'))
			{
				return  @substr($_SERVER[$key], 0, @strrpos($_SERVER[$key], '\\'));
			}

			return  @substr($_SERVER[$key], 0, @strrpos($_SERVER[$key], '/'));
		}

		return false;
	}

	/*
	* �������� ���� ����� �������
	*
	* @return string|boolean string ��� ������; boolean ��� ������
	*/
	private function server_addr()
	{
		// todo: ������� ������� �������� ������ � ������� ��������
		$options = array('SERVER_ADDR', 'LOCAL_ADDR');
		foreach ($options as $key)
		{
			if (isset($_SERVER[$key])) { return $_SERVER[$key]; }
		}

		return false;
	}

	/*
	* �������� ������ ������� ��������� phpinfo()
	*
	* @param array $all
	* @param string $target
	* @return string|boolean string ��� ������; boolean ��� ������
	*/
	private function scrape_phpinfo($all, $target)
	{
		$all = explode($target, $all);
		if (count($all) < 2) { return false; }
		$all = explode("\n", $all[1]);
		$all = trim($all[0]);

		if ($target == 'System')
		{
			$all = explode(" ", $all);
			$all = trim($all[(strtolower($all[0]) == 'windows' && strtolower($all[1]) == 'nt')?2:1]);
		}

		if ($target=='SCRIPT_FILENAME')
		{
			$slash = ($this->is_windows()?'\\':'/');

			$all = explode($slash, $all);
			array_pop($all);
			$all = implode($slash, $all);
		}

		if (substr($all, 1, 1) == ']') { return false; }

		return $all;
	}

	/*
	* �������� �������� �� API ������� �������� � ��������� fsockopen
	*
	* @param string $url
	* @param string $querystring
	* @return string|boolean string ��� ������; boolean ��� ������
	*/
	private function use_fsockopen($url, $querystring)
	{
		if (!function_exists('fsockopen')) { return false; }

		$url = parse_url($url);

		$fp = @fsockopen($url['host'], $this->remote_port, $errno, $errstr, $this->remote_timeout);
		if (!$fp) { return false; }

		$header="POST {$url['path']} HTTP/1.0\r\n";
		$header.="Host: {$url['host']}\r\n";
		$header.="Content-type: application/x-www-form-urlencoded\r\n";
		$header.="User-Agent: protect (http://www.regger.pw)\r\n";
		$header.="Content-length: " . @strlen($querystring)."\r\n";
		$header.="Connection: close\r\n\r\n";
		$header.=$querystring;

		$result=false;
		fputs($fp, $header);
		while (!feof($fp)) { $result.=fgets($fp, 1024); }
		fclose ($fp);

		if (strpos($result, '200')===false) { return false; }

		$result=explode("\r\n\r\n", $result, 2);

		if (!$result[1]) { return false; }

		return $result[1];
	}

	/*
	* �������� �������� �� API ������� �������� � ��������� cURL
	*
	* @param string $url
	* @param string $querystring
	* @return string|boolean string ��� ������; boolean ��� ������
	*/
	private function use_curl($url, $querystring)
	{
		if (!function_exists('curl_init')) { return false; }

		$curl = curl_init();

		$header[0] = "Accept: text/xml,application/xml,application/xhtml+xml,";
		$header[0] .= "text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5";
		$header[] = "Cache-Control: max-age=0";
		$header[] = "Connection: keep-alive";
		$header[] = "Keep-Alive: 300";
		$header[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
		$header[] = "Accept-Language: en-us,en;q=0.5";
		$header[] = "Pragma: ";

		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_USERAGENT, 'protect (http://regger.pw)');
		curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
		curl_setopt($curl, CURLOPT_ENCODING, 'gzip,deflate');
		curl_setopt($curl, CURLOPT_AUTOREFERER, true);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $querystring);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->remote_timeout);
		curl_setopt($curl, CURLOPT_TIMEOUT, $this->remote_timeout); // 60

		$result = curl_exec($curl);
		$info = curl_getinfo($curl);
		curl_close($curl);

		if ((integer)$info['http_code']!=200) { return false; }

		return $result;
	}

	/*
	* �������� �������� �� API ������� �������� � ��������� fopen �������� file_get_contents()
	*
	* @param string $url
	* @param string $querystring
	* @return string|boolean string ��� ������; boolean ��� ������
	*/
	private function use_fopen($url, $querystring)
	{
		if (!function_exists('file_get_contents')) { return false; }

		return @file_get_contents("{$url}?{$querystring}");
	}

	/*
	* ���������� windows �������
	*
	* @return boolean
	*/
	private function is_windows()
	{
		return (strtolower(substr(php_uname(), 0, 7)) == 'windows');
	}

	/*
	* ������ ���������������� �������
	*
	* @param array $stack ������ ��� ������
	* @param boolean $stop_execution
	* @return string
	*/
	private function debug($stack, $stop_execution=true)
	{
		$formatted = '<pre>'.var_export((array)$stack, 1).'</pre>';

		if ($stop_execution) { die($formatted); }

		return $formatted;
	}

	/*
	* ��������� �� ����������� �������
	*
	* @return bool
	*/
	private function get_local_ip()
	{
		// ���� ������� phpinfo() ����������
		if (function_exists('phpinfo'))
		{
			ob_start();
			phpinfo();
			$phpinfo = ob_get_contents();
			ob_end_clean();

			$list = strip_tags($phpinfo);
			$local_ip = $this->scrape_phpinfo($list, 'SERVER_ADDR');
		}

		// �� ������ ������ �������� ��� ������
		$local_ip = ($local_ip) ? $local_ip : $this->server_addr();

		if($local_ip == '127.0.0.1')
			return true;

		return false;
	}
}

/*
 * ���� ����������� ������� ��������
 */
include_once ('Russian.lng');


/*
 * ������� ��������� ������
 */
$protect = new protect();

/*
 * ��������� ���������� � ������� �� ������.
 * � ��� ���������� ����� ����������� ���� �������� � �������.
 */
$protect->local_key_path = ENGINE_DIR . '/data/';

/*
 * ������������� ����������� ��������
 *
 * @array
 */
$protect->status_messages = $protect_status;

/*
 * ��������� ���� ��������, �������� �� ������������ ������.
 */
$protect->license_key = $this->regger_config['license_key'];

/*
 * ��������� ������ ���� �� ������� ��������.
 */
$protect->api_server = 'http://regger.pw/api/v2/license_check.php';

/*
 * ��������� ��������� ����
 * ������������� ��������� ��� ������� ������ ����.
 */
$protect->secret_key = 'fdfblhlLgnJDKJklblngkkkrtkghm565678kl78klkUUHtvdfdoghphj';

/*
 * ��������� ���������
 */
$protect->validate();


// ���� ��� ������, �� �������� � ������ ���������
if(!$protect->errors)
{
	$license = true;
}

// ���� ���� ������ � �������� �� �� �������, � ���� �� ���������� �������� ����� ���� ��� �������� �������
if(($protect->errors != '' && $protect->errors != $protect_status['localhost']) || ($protect->errors != '' && $protect->errors != $protect_status['localhost'] && $protect->errors != $protect_status['could_not_obtain_local_key']))
{
	$license = false;
}

?>