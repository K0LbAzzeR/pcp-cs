<?php
/*
=====================================================
 ����: license_check.php
-----------------------------------------------------
 ����������: �������� ���������� � ��������� ��������
=====================================================
*/

@error_reporting ( E_ALL ^ E_WARNING ^ E_NOTICE );
@ini_set ( 'display_errors', false );
@ini_set ( 'html_errors', false );
@ini_set ( 'error_reporting', E_ALL ^ E_WARNING ^ E_NOTICE );

$date = time();


/*
 * ������� ��������� ���������� �����
 * @param string $license_key ������������ ����
 * @param string $domain �������� ���, �� ������� ������������ ��������
 * @param 
 * @param 
 * @param 
 */

function create_local_key($license_user_id, $license_user_name, $license_key, $domain, $ip, $directory, $server_hostname, $server_ip, $license_method_id, $license_expires, $license_status, $license_wildcard)
{
	global $db;

	/*
	  * ����������� ��� ����������� � ������ �� ���� ������ �� ����������� ID
	  */
	$method_result = $db->query("SELECT * FROM " . PREFIX . "_clients_license_methods WHERE id='{$license_method_id}'");
	$method_row = $db->get_row($method_result);
	/*
	  ��������� ���� ������ */
	$secret_key = $method_row['secret_key'];
	/*
	  ������ ����, ��� ��������� */
	$enforce = explode("|",$method_row['enforce']);
	/*
	  ������ �������� ���������� ����� � ���� */
	$check_period = $method_row['check_period'];

	/*
	  ������ � ����������� ��������
	  ����� ��������� �����, ���� �����, ��� ����� */
	$instance = array();

	$instance['domain'] =  array (0 => "$domain", 1 => "www.$domain");

	/*
	  ������ �� ����� ����������� ������� �������� */
	$key_data = array();
	/*
	  ���������� ������������� ������� */
	$key_data['customer'] = $license_user_id;
	/*
	  ���������� ����� ������� �� ����� */
	$key_data['user'] = $license_user_name;
	/*
	  ������������ ���� */
	$key_data['license_key_string'] = $license_key;
	/*
	  ������ � ���, ��� ������� ��������� */
	$key_data['instance'] = $instance;
	/*
	  ������ ��������, ��������� �� ��, ��� ���� ��������� � ������ */
	$key_data['enforce'] = 'domain';
	/*
	  ��������� ����, �� ����������� */
	$key_data['custom_fields'] = array();
	/*
	  ����� ��������� ����� ���������� ������ */
	$key_data['download_access_expires'] = 0;
	/*
	  ����� ��������� ����� ��������� */
	$key_data['support_access_expires'] = 0;
	/*
	   ���� ��������� �������� � Unix-������� */
	$key_data['license_expires'] = $license_expires;
	/*
	  ����� ��������� ���������� �����
	  ����� ���������� ���� �� ������, �������� ��� �� ���������� ������ � ������ � ���������� � Unix-�������. */
	$key_data['local_key_expires'] = ((integer)$check_period * 86400) + time();
	/*
	  ������ ��������, ���� ������� ������, �� �������� ���������� �������� */
	if ($license_status == 0 || $license_status == 1 || $license_status == 3)
		$status = 'Active';

	$key_data['status'] = strtolower( $status );

	/*
	  ����������� ��� ������ �������� */
	$key_data = serialize( $key_data );

	/*
	  �������� ��������� ���� ������ */
	$license_info = array();
	$license_info[0] = $key_data;
	$license_info[1] = md5($secret_key.$license_info[0]);
	$license_info[2] = md5( microtime() );

	$license_info = base64_encode(implode( "{protect}", $license_info ));

	return urlencode( wordwrap( $license_info, 64, "\n", 1 ) );
}



/*
** ��������� ���� ������ �� ������� ��������
*/
if($_POST['license_key']) {

	/*
	   ������������ ���� ��������� */
	$key = $db->safesql( htmlspecialchars( trim( strip_tags(strval( $_POST['license_key'] ) ) ) ));

	/*
	   ����� �� ������� ���������� ������ */
	$domain = $db->safesql( htmlspecialchars( trim( strip_tags(strval( $_POST['domain'])))));
	$domain = str_replace("www.","",$domain);

	/*
	   ���� ����� ������� */
	$ip = $_POST['ip'];

	/*
	   ���������� �� root ��� ���������� ������ */
	$directory = $db->safesql( htmlspecialchars( trim( strip_tags(strval( $_POST['directory'])))));

	/*
	   ��� ����� ��� ���������� ����� */
	$server_hostname = $db->safesql( htmlspecialchars( trim( strip_tags(strval( $_POST['server_hostname'])))));

	/*
	   ���� ����� ������� ��� ���������� ������ */
	$server_ip = $db->safesql( htmlspecialchars( trim( strip_tags($_POST['server_ip']))));


	/*
	   ����������� ��� ������ � ������������ ����� �� ���� ������ */
	$result = $db->query("SELECT * FROM " . PREFIX . "_clients_license WHERE l_key='$key' LIMIT 0,1");
	$row = $db->get_row($result);

	/*
	   ���� ����������� � ���� ����� ������������ ���� ��������� */
	if (count($row) < 1) {
		// ������� ������� � ���� � ��� ��������� ������
		$db->query( "INSERT INTO " . PREFIX . "_clients_license_logs SET `l_status` = 'Invalid', `date` = '$date', `l_key` = '$key', `l_id` = '', `l_domain` = '$domain', `l_ip` = '$ip', `l_directory` = '$directory', `l_server_hostname` = '$server_hostname', `l_server_ip` = '$server_ip', `l_method_id`  = ''" );
		// ������ ��, ��� ���� �� �������.
		die("Invalid");
	}

	/*
	** ������ ���������� �� ���� ������
	*/
	/*
	   ������������� ������������� ����� */
	$license_id = $row['id'];
	/*
	   ������������ ���� ��������� */
	$license_key = $row['l_key'];
	/*
	   ������������� ������� �� ����� */
	$license_user_id = $row['user_id'];
	/*
	   ����� ������� �� ����� */
	$license_user_name = $row['user_name'];
	/*
	   �������� ��� ��������, ���� ��� ���� ������������ */
	$license_domain = $row['l_domain'];
	$license_wildcard = $row['l_domain_wildcard'];
	/*
	   ���� ����� ������� */
	$license_ip = $row['l_ip'];
	/*
	   ���������� ��� ��������� */
	$license_directory = $row['l_directory'];
	/*
	   �������� ����� ��� ��������� */
	$license_server_hostname = $row['l_server_hostname'];
	/*
	   ���� ����� ����� ��� ��������� */
	$license_server_ip = $row['l_server_ip'];
	/*
	   ������ ��������:
	   0 - �� ������������
	   1 - �������� ������������
	   2 - ���� �����
	   3 - �������� ���������� (������� ���������) */
	$license_status = $row['l_status'];
	/*
	   ����� ��������������������� ����� */
	$license_method_id = $row['l_method_id'];
	/*
	   ���� ��������� ����� �������� ������������� ����� � UNIX ������� */
	$license_expires = $row['l_expires'];

	/*
	   ���������� ������ �������� ��� ���� */
	if ($license_status == 0 || $license_status == 1 || $license_status == 3)
		$status = 'Active';
	else
		$status = 'Invalid';

	/*
	   ������ ��������� � ��� */
	$db->query( "INSERT INTO " . PREFIX . "_clients_license_logs SET `l_status` = '$status', `date` = '$date', `l_key` = '$key', `l_id` = '$license_id', `l_domain` = '$domain', `l_ip` = '$ip', `l_directory` = '$directory', `l_server_hostname` = '$server_hostname', `l_server_ip` = '$server_ip', `l_method_id`  = '$license_method_id'" );


	//���� � �������� ������ 0 ��� 3, �.�. �������������, �� ������� ��� ������ � ����, ������ ������ � ���������� ��������� ����
	if ($license_status == 0 || $license_status == 3)
	{
		//������� � ���� ������ � ������ ������
		$db->query( "UPDATE " . PREFIX . "_clients_license SET l_domain='$domain', l_ip='$ip', l_directory='$directory', l_server_hostname='$server_hostname', l_server_ip = '$server_ip', l_status='1', l_last_check='$date' WHERE id='$license_id'" );

		$localkey = create_local_key($license_user_id, $license_user_name, $license_key, $domain, $ip, $directory, $server_hostname, $server_ip, $license_method_id, $license_expires, $license_status, $license_wildcard);

		echo $localkey;
	}

	// ���� �������� �������� ��� ���� ������������
	if ($license_status == 1)
	{
		/*
		* ��������� ��������� ����� � �������������� � ��������� ����������.
		*/
		// ���� ��������� ����� �� ����� ������ �� ����, � �������� ��������� ��� ��������,
		// �� ��������� �������������� ������ � ���������� � ��������� �������� � ����� ���� ������ ������ � ��������� �������� ��������
		$pos = dle_strrpos($domain, $license_domain, $config['charset'] );
		if($domain != $license_domain && $license_wildcard == 1 && $pos !== false) {
			$license_domain = $domain;
		}

		if ($license_domain != $domain) {die('Invalid');}


		$localkey = create_local_key($license_user_id, $license_user_name, $license_key, $domain, $ip, $directory, $server_hostname, $server_ip, $license_method_id, $license_expires, $license_status, $license_wildcard);

		echo $localkey;

		$db->query( "UPDATE " . PREFIX . "_clients_license  SET l_last_check='$date' WHERE id='$license_id'" );
	}

}

?>