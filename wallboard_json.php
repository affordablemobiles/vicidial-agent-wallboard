<?php

$start_time = time();
$data = array();

$sql = "SELECT
			extension,
			vicidial_live_agents.user,
			conf_exten,
			vicidial_live_agents.status,
			vicidial_live_agents.server_ip,
			UNIX_TIMESTAMP(last_call_time) as last_call_time,
			UNIX_TIMESTAMP(last_call_finish) as last_call_finish,
			call_server_ip,
			vicidial_live_agents.campaign_id,
			vicidial_users.user_group,
			vicidial_users.full_name,
			vicidial_live_agents.comments,
			vicidial_live_agents.calls_today,
			vicidial_live_agents.callerid,
			lead_id,
			UNIX_TIMESTAMP(last_state_change) as last_state_change,
			on_hook_agent,
			ring_callerid,
			agent_log_id
		FROM
			vicidial_live_agents,
			vicidial_users
		WHERE
			vicidial_live_agents.user = vicidial_users.user";

$db = new mysqli("localhost", "cron", "1234", "asterisk");

$result = $db->query($sql);

$sql2 = "select callerid,lead_id,phone_number from vicidial_auto_calls";
$cidresult = $db->query($sql2);
$callerids = '';
while ($row = $cidresult->fetch_assoc()){
	$callerids .= $row['callerid'] . "|";
}

while ($row = $result->fetch_assoc()){
	$status = $row['status'];

	if ($row['on_hook_agent'] == 'Y')
		$status = 'RING';

	// 3-way Check
	if ($row['lead_id'] != 0){
		$sql = "SELECT UNIX_TIMESTAMP(last_call_time) FROM vicidial_live_agents WHERE lead_id = '" . $db->escape_string($row['lead_id']) . "' AND status = 'INCALL' ORDER BY UNIX_TIMESTAMP(last_call_time) DESC";
		$r2 = $db->query($sql);
		if (!$r2){
			printf("Error: %s\n", $db->error);
		} else {
			if ($r2->num_rows > 1){
				$status = "3-WAY";
			}
		}
	}

	$epoch_sec = 0;
	if (preg_match("/READY|PAUSED/i", $row['status'])){
		$epoch_sec = $row['last_state_change'];

		if ($row['lead_id'] > 0){
			$status = 'DISPO';
		}
	} else {
		$epoch_sec = $row['last_call_time'];
	}

	if (preg_match("/INCALL/i", $status)){
		$sql4 = "SELECT UNIX_TIMESTAMP(parked_time) AS pt FROM parked_channels WHERE channel_group = '" . $db->escape_string($row['callerid']) . "'";
		$q4 = $db->query($sql4);

		if ($q4->num_rows > 0){
			$status = 'PARK';
			$rowP = $q4->fetch_assoc();
			$epoch_sec = $rowP['pt'];
		} else{
			if (!preg_match("/" . $row['callerid'] . "\|/",$callerids)){
				$epoch_sec = $row['last_state_change'];

				$status = 'DEAD';
			}
		}
	}

	switch($status){
		case 'DISPO':
		case 'QUEUE':
			$colour = '8A2BE2';
			break;
		case 'INCALL':
			$colour = '00BFFF';
			break;
		case 'PARK':
			$colour = 'FF8C00';
			break;
		case 'DEAD':
			$colour = '00008B';
			break;
		case '3-WAY':
			$colour = 'FF1493';
			break;
		case 'RING':
			$colour = 'FFFF00';
			break;
		case 'PAUSED':
			$colour = 'FF0000';
			break;
		case 'READY':
			$colour = '32CD32';
			break;
		default:
			$colour = 'D2BEAA';
			break;
	}

	$data[$row['extension']] = array(
		'user' => $row['user'],
		'status' => $status,
		'conf_exten' => $row['conf_exten'],
		'seconds' => ( time() - $epoch_sec ),
		'campaign_id' => $row['campaign_id'],
		'user_group' => $row['user_group'],
		'full_name' => $row['full_name'],
		'calls_today' => $row['calls_today'],
		'lead_id' => $row['lead_id'],
		'colour' => $colour
	);

}

$qsql = "SELECT
			status,
			campaign_id,
			phone_number,
			server_ip,
			UNIX_TIMESTAMP(call_time) as call_time,
			call_type,
			queue_priority,
			agent_only
		FROM
			vicidial_auto_calls
		WHERE
				status IN ('LIVE')
			AND
				call_type='IN'
		ORDER BY
			call_time ASC";

$qresult = $db->query($qsql);

$data['queue'] = array();
$data['queue']['global'] = array();

$qrow = $qresult->fetch_assoc();

$data['queue']['global']['number'] = $qresult->num_rows;
$data['queue']['global']['wait_time'] = ( $start_time - $qrow['call_time'] );

echo json_encode($data) . "\n";
