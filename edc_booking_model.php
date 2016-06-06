<?php

class Edc_booking_model extends CI_Model
{
	function __construct()
	{
		// Call the Model constructor
		parent::__construct();
	}

	function get_user_auth ($app_num)
	{
		$query = $this->db->query('SELECT auth_id
									FROM users
									WHERE id IN
										(SELECT user_id
											FROM edc_registration_details
											WHERE app_num = "'.$app_num.'")');
		return $query->row_array();
	}

	function get_current_tariff () {
		$query = $this->db->query ('
			SELECT id
			FROM edc_tariff
			ORDER BY wef DESC
			LIMIT 1
		');

		return intval($query->row_array()['id']);
	}

	function get_tariff ($app_num) {
		$query = $this->db->query('
			SELECT *
			FROM edc_tariff AS et
			INNER JOIN
			edc_registration_details as erd
			ON et.id = erd.tariff
			WHERE app_num = "'.$app_num.'"
		');

		return $query->row_array();
	}

	function insert_guest_details ($data)
	{
		$this->db->insert('edc_guest_details',$data);
		$this->db->query ("UPDATE edc_guest_details SET check_in = now() WHERE app_num = '".$data['app_num']."';");
	}

	function insert_edc_registration_details ($data)
	{
		$this->db->insert('edc_registration_details',$data);
	}

	function set_paid_data($app_num, $name, $check_in, $total_sum) {
		$this->db->query('
			UPDATE edc_guest_details
			SET paid = "'.$total_sum.'"
			WHERE app_num = "'.$app_num.'"
			AND name = "'.$name.'"
			AND check_in = "'.$check_in.'"
		');
	}

	function get_requests($status, $auth, $dept_id)
	{
		$_pending = '';

		//for each auth, if it is fetching pending, then it can't show those who are set to cancel or cancelled for previous authority
		if($status === 'Pending') {
			switch($auth) {
				case 'dsw':
				case 'hod':
				case 'hos': $_pending = ' AND (pce_status IS NULL)';
							break;
				case 'pce_da5':
							$_pending = ' AND (hod_status = "Approved" OR hod_status IS NULL) AND (dsw_status = "Approved" OR dsw_status IS NULL) AND (pce_status IS NULL)';
							break;
				case 'pce': $_pending = ' AND (hod_status = "Approved" OR hod_status IS NULL) AND (dsw_status = "Approved" OR dsw_status IS NULL) AND (ctk_allotment_status = "Approved")';
							break;
			}
		}

		switch($auth) {
			case 'dsw': $query = $this->db->query('SELECT * FROM edc_registration_details WHERE dsw_status = "'.$status.'"'.$_pending.' ORDER BY app_date DESC');
						break;
			case 'hod':
			case 'hos': $query = $this->db->query('SELECT * FROM edc_registration_details WHERE purpose = "Official" AND hod_status = "'.$status.'"'.$_pending.' AND user_id IN (SELECT id FROM user_details WHERE dept_id = "'.$dept_id.'") ORDER BY app_date DESC');
						break;
			case 'pce_da5':
						$query = $this->db->query('SELECT * FROM edc_registration_details WHERE ctk_allotment_status = "'.$status.'"'.$_pending.' ORDER BY app_date DESC');
						break;
			case 'pce': $query = $this->db->query('SELECT * FROM edc_registration_details WHERE pce_status = "'.$status.'"'.$_pending.' ORDER BY app_date DESC');
						break;
		}

		return $query->result_array();
	}

	function get_new_applications($auth, $dept_id)
	{
		if($auth == 'hos' || $auth == 'hod')
			$query = $this->db->query('SELECT erd.*
										FROM edc_registration_details as erd
											INNER JOIN
										    user_details AS ud
										ON erd.user_id = ud.id
										WHERE purpose != "Official"
										AND dept_id = "'.$dept_id.'"
										AND (
											pce_status IS NULL
											OR pce_status = "Pending"
										)');
		else if($auth == 'dsw')
			$query = $this->db->query('SELECT erd.* FROM edc_registration_details AS erd INNER JOIN users ON erd.user_id = users.id WHERE auth_id = "stu" AND (pce_status IS NULL OR pce_status = "Pending")');
		else if($auth == 'pce_da5')
			$query = $this->db->query('SELECT * FROM edc_registration_details WHERE app_num NOT IN (SELECT app_num FROM edc_registration_details WHERE ctk_allotment_status != "")');
		else if($auth == 'pce')
			$query = $this->db->query('SELECT * FROM edc_registration_details WHERE app_num NOT IN (SELECT app_num FROM edc_registration_details WHERE pce_status != "")');

		return $query->result_array();
	}

	function get_booking_details ($app_num)
	{
		$this->db->where('app_num',$app_num);
		$query = $this->db->get('edc_registration_details');
			return $query->result_array();
	}

	function get_building ()
	{
		$this->db->where('app_num',NULL);
		$query = $this->db->get('edc_room_details');
		return $query->result_array();
	}

	function update_action($app_num, $auth, $status, $reason)
	{
		$_null = '';
		if($auth == 'dsw'){
			$col = 'dsw_status';
			$next_user = 'ctk_allotment_status';
			$ts = 'dsw_action_timestamp';
			$_null = ', hod_status = NULL';
		}
		else if($auth == 'hod' || $auth == 'hos'){
			$col = 'hod_status';
			$next_user = 'ctk_allotment_status';
			$ts = 'hod_action_timestamp';
			$_null = ', dsw_status = NULL';
		}
		else if($auth == 'pce_da5'){
			$col = 'ctk_allotment_status';
			$next_user = 'pce_status';
			$ts = 'ctk_action_timestamp';
		}
		else if($auth == 'pce'){
			$col = 'pce_status';
			$ts = 'pce_action_timestamp';
		}
		if($auth != 'pce')
			$this->db->query('UPDATE edc_registration_details SET '.$col.' = "'.$status.'"'.$_null.', '.$next_user.' = "Pending", '.$ts.' = now(), deny_reason = "'.$reason.'" WHERE app_num = "'.$app_num.'"');
		else $this->db->query('UPDATE edc_registration_details SET '.$col.' = "'.$status.'", '.$ts.' = now(), deny_reason = "'.$reason.'" WHERE app_num = "'.$app_num.'"');
	}

	function cancel($app_num, $col)
	{
		$this->db->query('UPDATE edc_registration_details SET '.$col.' = "Cancel" WHERE app_num = "'.$app_num.'"');
	}

	function cancel_request($app_num)
	{
		$this->db->query('UPDATE edc_registration_details SET hod_status = "Cancelled", dsw_status = "Cancelled", ctk_allotment_status = "Cancelled", pce_status = "Cancelled", cancellation_date = now() WHERE app_num = "'.$app_num.'"');
		$this->db->query('DELETE FROM edc_booking_details WHERE app_num = "'.$app_num.'"');
	}

	function set_cancel_reason($app_num, $reason)
	{
		$this->db->query('UPDATE edc_registration_details SET deny_reason = "'.$reason.'" WHERE app_num = "'.$app_num.'"');
	}

	function get_request_user_id ($app_num)
	{
		$this->db->where('app_num',$app_num);
		$query = $this->db->get('edc_registration_details');
		$user_id = '';
		foreach ($query->result_array() as $row)
			$user_id = $row['user_id'];
		return $user_id;
	}

	function get_pending_booking_details ($user_id)
	{
		$query = $this->db->query('SELECT * FROM edc_registration_details WHERE user_id="'.$user_id.'" AND (pce_status = "Pending" OR pce_status IS NULL) ORDER BY app_date DESC');

		return $query->result_array();
	}

	function get_booking_history ($user_id, $status)
	{
		if ($status == 'Approved') {
			$this->db->where('user_id',$user_id);
			$this->db->where('pce_status = "'.$status.'" OR pce_status = "Cancel"');
			$query = $this->db->order_by('app_num','desc')->get('edc_registration_details');
			return $query->result_array();
		}
		else if($status == 'Rejected'){
			$this->db->where('user_id = "'.$user_id.'" AND (hod_status = "Rejected" OR dsw_status = "Rejected" OR pce_status = "Rejected")');
			$query = $this->db->get('edc_registration_details');
			return $query->result_array();
		}
		else if($status == 'Cancelled'){
			$this->db->where('user_id = "'.$user_id.'" AND pce_status = "Cancelled"');
			$query = $this->db->order_by('app_date', 'DESC')->get('edc_registration_details');
			return $query->result_array();
		}
	}

	function get_allotted_applications()
	{
		$query = $this->db->query('SELECT * FROM edc_registration_details WHERE pce_status = "Approved" ORDER BY check_out DESC');
		return $query->result_array();
	}

	function get_rooms_for_application($app_num)
	{
		$query = $this->db->query("SELECT edc_booking_details.room_id as id,edc_room_details.building as building,edc_room_details.floor as floor,edc_room_details.room_no as room_no,edc_room_details.room_type as room_type FROM edc_booking_details inner join edc_room_details on edc_booking_details.room_id=edc_room_details.id WHERE edc_booking_details.app_num = '".$app_num."'");
		return $query->result();
	}

	function checkout($app_num,$room_allocated, $guest_name)
	{
		$this->db->query ("UPDATE edc_guest_details SET check_out = now() WHERE app_num = '".$app_num."' AND room_alloted = '".$room_allocated."' AND name = '".urldecode($guest_name)."'");
	}

	function set_check_out($app_num) {
		$query = $this->db->query('
			SELECT r.no_of_guests, COUNT(g.check_out) as count, MAX(g.check_out) as check_out
			FROM edc_registration_details AS r
			INNER JOIN
			edc_guest_details AS g
			ON r.app_num = g.app_num
			WHERE r.app_num = "'.$app_num.'"
			GROUP By r.app_num
		');

		if($query->row_array()['no_of_guests'] === $query->row_array()['count'])
			$this->db->query('
				UPDATE edc_registration_details
				SET check_out = "'.$query->row_array()['check_out'].'"
				WHERE app_num = "'.$app_num.'"
			');
	}

	function get_guest_details($app_num)
	{
		$this->db->where('app_num',$app_num);
		$query = $this->db->get('edc_guest_details');
		return $query->result_array();
	}

	function get_group_details($app_num, $name, $check_in)
	{
		$query = $this->db->query('SELECT * FROM edc_guest_details WHERE app_num = "'.$app_num.'" AND name = "'.urldecode($name).'" AND check_in = "'.urldecode($check_in).'"');
		return $query->row_array();
	}

	function get_no_of_guests($app_num)
	{
		$query = $this->db->query('SELECT no_of_guests FROM edc_registration_details WHERE app_num = "'.$app_num.'"');
		return $query->row_array()['no_of_guests'];
	}

	function check_single_room($app_num, $name, $check_in, $room)
	{
		$query = $this->db->query('SELECT COUNT(*) as count FROM edc_guest_details WHERE app_num = "'.$app_num.'" AND room_alloted = "'.$room.'"');
		if($query->row_array()['count'] == 1)
			return 0;
		else
		{
			$query = $this->db->query('SELECT COUNT(*) as count FROM edc_guest_details WHERE app_num = "'.$app_num.'" AND name = "'.urldecode($name).'" AND check_in = "'.urldecode($check_in).'" AND room_alloted = "'.$room.'"');
			if($query->row_array()['count'] == 1)
				return 1;
			else return 2;
		}
	}

	function is_academic($dept_id)
	{
		$query = $this->db->query('SELECT * FROM departments
									WHERE type="academic"
									AND id="'.$dept_id.'"');
		return count($query->result_array());
	}

	function get_guest_entries($app_num, $room_id)
	{
		$query = $this->db->query('SELECT rd.room_type, COUNT(*) AS count FROM edc_room_details AS rd INNER JOIN edc_guest_details AS gd ON rd.id = gd.room_alloted WHERE gd.app_num = "'.$app_num.'" AND rd.id = "'.$room_id.'" GROUP BY rd.id');
		if(count($query->result_array()))
			return $query->row_array()['count'];
		else return 0;
	}

	function get_guest_groups($app_num)
	{
		$query = $this->db->query('SELECT *, count(*) as no_of_guests from edc_guest_details where app_num = "'.$app_num.'" group by app_num, name, check_in');
		return $query->result_array();
	}

	function get_guest_rooms($app_num, $name, $check_in)
	{
		$query = $this->db->query('SELECT room_alloted from edc_guest_details where app_num = "'.$app_num.'" and name = "'.urldecode($name).'" and check_in = "'.urldecode($check_in).'" group by room_alloted');
		return $query->result_array();
	}

	function get_room_type($room_id)
	{
		$query = $this->db->query('SELECT room_type FROM edc_room_details WHERE id = "'.$room_id.'"');
		return $query->row_array()['room_type'];
	}

	function get_room_occupance_history($data)
	{
		if(urldecode($data['name'])) {
			if(gettype($data['rooms']) == 'array') {
				$room_array = '';
				foreach($data['rooms'] as $room)
					$room_array .= $room.',';
				$room_array = substr($room_array, 0, -1);

				if($data['date'] != 19700101) {
					$query = $this->db->query('SELECT DISTINCT g.* FROM edc_guest_details as g, edc_registration_details as e Where g.app_num = e.app_num and g.name LIKE "%'.urldecode($data['name']).'%" and g.room_alloted IN ('.$room_array.') and CAST(g.check_in as DATE)="'.$data['date'].'"');
					return $query->result_array();
				}
				else if($data['check_in'] !=19700101 && $data['check_out']!=19700101 ) {
					$query = $this->db->query('SELECT DISTINCT g.* FROM edc_guest_details as g, edc_registration_details as e Where g.app_num = e.app_num and g.name LIKE "%'.urldecode($data['name']).'%" and g.room_alloted  IN('.$room_array.') and CAST(g.check_in as DATE) BETWEEN "'.$data['check_in'].'" AND "'.$data['check_out'].'"');
					return $query->result_array();
				}
				else {
				$query = $this->db->query('SELECT DISTINCT g.* FROM edc_guest_details as g, edc_registration_details as e Where g.app_num = e.app_num and g.name LIKE "%'.urldecode($data['name']).'%" and g.room_alloted IN('.$room_array.')');
				return $query->result_array();
				}
			}
			else if($data['check_in'] !=19700101 && $data['check_out'] !=19700101 ) {
				$query = $this->db->query('SELECT DISTINCT g.* FROM edc_guest_details as g, edc_registration_details as e WHERE e.app_num = g.app_num and g.name LIKE "%'.urldecode($data['name']).'%" and CAST(g.check_in as DATE) BETWEEN "'.$data['check_in'].'" AND "'.$data['check_out'].'"');
				return $query->result_array();
				}
			else if($data['date'] !=19700101 ) {
				$query = $this->db->query('SELECT DISTINCT g.* FROM edc_guest_details as g, edc_registration_details as e WHERE e.app_num = g.app_num and g.name LIKE "%'.urldecode($data['name']).'%"and CAST(g.check_in as DATE)="'.$data['date'].'"');
				return $query->result_array();
				}
			else {
				$query = $this->db->query('SELECT DISTINCT g.* FROM edc_guest_details as g, edc_registration_details as e Where g.app_num = e.app_num and g.name LIKE "%'.urldecode($data['name']).'%"');
				return $query->result_array();
				}
			}

		else 	if(gettype($data['rooms']) == 'array') {
				$room_array = '';
				foreach($data['rooms'] as $room)
					$room_array .= $room.',';
				$room_array = substr($room_array, 0, -1);

				if($data['date'] !=19700101 ) {
					$query = $this->db->query('SELECT DISTINCT g.* FROM edc_guest_details as g, edc_registration_details as e WHERE e.app_num = g.app_num and g.room_alloted IN ('.$room_array.')and CAST(g.check_in as DATE)="'.$data['date'].'"');
					return $query->result_array();
				}
				else if($data['check_in'] !=19700101  && $data['check_out'] !=19700101 ) {
					$query = $this->db->query('SELECT DISTINCT g.* FROM edc_guest_details as g, edc_registration_details as e WHERE e.app_num = g.app_num  and g.room_alloted IN ('.$room_array.') and CAST(g.check_in as DATE) BETWEEN "'.$data['check_in'].'" AND "'.$data['check_out'].'"');
					return $query->result_array();
				}
				else {
					$query = $this->db->query('SELECT DISTINCT g.* FROM  edc_guest_details as g, edc_registration_details as e where e.app_num = g.app_num and g.room_alloted IN('.$room_array.')');
					return $query->result_array();
				}
		}

		else if($data['date'] !=19700101 ) {
				$query = $this->db->query('SELECT DISTINCT g.* FROM edc_guest_details as g, edc_registration_details as e WHERE e.app_num = g.app_num and CAST(g.check_in as DATE)= "'.$data['date'].'" ');
				return $query->result_array();
		}

		else if($data['check_in'] !=19700101  && $data['check_out'] !=19700101 ) {
				$query = $this->db->query('SELECT DISTINCT g.* FROM edc_guest_details as g, edc_registration_details as e WHERE e.app_num = g.app_num  and CAST(g.check_in as DATE) BETWEEN "'.$data['check_in'].'" AND "'.$data['check_out'].'"');
				return $query->result_array();
		}
		else {
				$query = $this->db->query('SELECT DISTINCT g.* FROM edc_guest_details as g');
				return $query->result_array();
		}

	}

	function get_guest_info($app_num, $name, $check_in)
	{
		$query = $this->db->query('SELECT * FROM edc_guest_details WHERE app_num = "'.$app_num.'" AND name = "'.urldecode($name).'" AND check_in = "'.urldecode($check_in).'"');
		return $query->row();
	}

	function set_check_in($app_num)
	{
		$query = $this->db->query('SELECT erd.check_in as reg_check_in, min(egd.check_in) AS check_in FROM edc_guest_details AS egd INNER JOIN edc_registration_details AS erd ON egd.app_num = erd.app_num WHERE egd.app_num = "'.$app_num.'" GROUP BY egd.app_num');
		if($query->row_array()['check_in'] < $query->row_array()['reg_check_in'])
			$this->db->query('UPDATE edc_registration_details SET check_in = "'.$query->row_array()['check_in'].'" WHERE app_num = "'.$app_num.'"');
	}
}
