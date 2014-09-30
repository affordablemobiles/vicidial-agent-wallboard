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
			$colour = '8e44ad';
			break;
		case 'INCALL':
			$colour = '3498db';
			break;
		case 'PARK':
			$colour = 'e67e22';
			break;
		case 'DEAD':
			$colour = '2980b9';
			break;
		case '3-WAY':
			$colour = '1abc9c';
			break;
		case 'RING':
			$colour = '16a085';
			break;
		case 'PAUSED':
			$colour = 'c0392b';
			break;
		case 'READY':
			$colour = '27ae60';
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
$data['queue']['name'] = "Global Queue Name";

if ($qresult->num_rows > 0){
	$qrow = $qresult->fetch_assoc();

	$data['queue']['global']['number'] = $qresult->num_rows;
	$data['queue']['global']['number_colour'] = number_colour($data['queue']['global']['number']);
	$data['queue']['global']['wait_time'] = ( $start_time - $qrow['call_time'] );
	$data['queue']['global']['wait_time_colour'] = wait_time_colour($data['queue']['global']['wait_time']);
} else {
	$data['queue']['global']['number'] = 0;
	$data['queue']['global']['number_colour'] = number_colour($data['queue']['global']['number']);
	$data['queue']['global']['wait_time'] = 0;
	$data['queue']['global']['wait_time_colour'] = wait_time_colour($data['queue']['global']['wait_time']);
}

$sqllist = "SELECT
				campaign_id,
				campaign_name,
				closer_campaigns
			FROM
				vicidial_campaigns
			WHERE
				active='Y'";

$listresult = $db->query($sqllist);

while ($listrow = $listresult->fetch_assoc()){
	$name = $listrow['campaign_id'];
	$long_name = $listrow['campaign_name'];
	$list = explode(" ", trim(str_replace("- ", "", $listrow['closer_campaigns'])));

	$data['queue'][$name] = array();
	$data['queue'][$name]['name'] = $long_name;

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
				AND
					campaign_id IN ('" . implode("','", $list) . "')
			ORDER BY
				call_time ASC";
	
	$qresult = $db->query($qsql);

	if ($qresult->num_rows > 0){
		$qrow = $qresult->fetch_assoc();

		$data['queue'][$name]['number'] = $qresult->num_rows;
		$data['queue'][$name]['number_colour'] = number_colour($data['queue'][$name]['number']);
		$data['queue'][$name]['wait_time'] = ( $start_time - $qrow['call_time'] );
		$data['queue'][$name]['wait_time_colour'] = wait_time_colour($data['queue'][$name]['wait_time']);
	} else {
		$data['queue'][$name]['number'] = 0;
		$data['queue'][$name]['number_colour'] = number_colour($data['queue'][$name]['number']);
		$data['queue'][$name]['wait_time'] = 0;
		$data['queue'][$name]['wait_time_colour'] = wait_time_colour($data['queue'][$name]['wait_time']);
	}
}


function number_colour($number){
	if ($number < 1){
		return '27ae60';
	} elseif ($number < 5){
		return 'd35400';
	} elseif ($number < 15){
		return 'e74c3c';
	} else {
		return 'c0392b';
	}
}

function wait_time_colour($time){
	if ($time < 20){
		return '27ae60';
	} elseif ($time < 120) {
		return 'd35400';
	} elseif ($time < 300) {
		return 'e74c3c';
	} else {
		return 'c0392b';
	}
}

echo json_encode($data) . "\n";
