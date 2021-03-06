<?php
class Edc_allotment_model extends CI_Model
{
    function __construct() {
        // Call the Model constructor
        parent::__construct();
    }

    function initialize_building($building = '', $id = '') {
        $this->db->query('INSERT INTO edc_room_details (id, building, floor, room_no, room_type, remark) VALUES ("'.$id.'", "'.$building.'", "ground", "0", "dummy", "dummy_room")');
    }

    function get_room_details($room_id) {
        $this->db->where('id',$room_id);
        $query = $this->db->get('edc_room_details');
        return $query->row_array();
    }

    function get_allocated_room_detail_limited($app_num, $limit) {
        $query = $this->db->query("SELECT * FROM edc_booking_details WHERE app_num = '".$app_num."' LIMIT ".$limit);
        //$this->db->where('app_num',$app_num);
        //$query = $this->db->get('edc_booking_details');
        return $query->result_array();
    }

    function get_allocated_room_details($app_num) { //used in booking_request/details
        //$query = $this->db->query("SELECT * FROM edc_booking_details WHERE app_num = '".$app_num."' ORDER by id DESC LIMIT $limit;");
        $this->db->where('app_num',$app_num);
        $query = $this->db->get('edc_booking_details');
        return $query->result_array();
    }

    function get_allocated_rooms($app_num) {
        $this->db->where('app_num',$app_num);
        $query = $this->db->get('edc_booking_details');
        return $query->num_rows();
    }

    function get_floors($building) {
        $this->db->where('building',$building);
        $this->db->group_by('floor');
        $query = $this->db->order_by('id')->get('edc_room_details');
        return $query->result_array();
    }

    function get_rooms($building, $floor) {
        $this->db->where('building',$building);
        $this->db->where('floor',$floor);
        $query = $this->db->order_by('room_no','asc')->get('edc_room_details');
        return $query->result_array();
    }

    function get_room_types() {
        $result = array(
            0 => array('room_type' => 'AC Suite'),
            1 => array('room_type' => 'Double Bedded AC')
        );
        return $result;
    }

    function no_of_rooms($building) {
        return intval($this->db->query('
        SELECT COUNT(*) AS count
        FROM edc_room_details
        WHERE building = "'.$building.'"
        ')->row_array()['count']);
    }

    function add_rooms($data) {
        $this->db->insert('edc_room_details', $data);
    }

    function remove_rooms($data) {
        $query = 'DELETE FROM edc_room_details WHERE id IN (';
        foreach($data as $room)
        $query .= $room.',';
        $query = substr($query, 0, -1).')';
        $this->db->query($query);
    }

    function block_rooms($rooms, $remark) {
        $query = 'UPDATE edc_room_details SET blocked = "1", remark = "'.$remark.'" WHERE id IN (';
        foreach($rooms as $room)
        $query .= $room.',';
        $query = substr($query, 0, -1).')';
        $this->db->query($query);
    }

    function unblock_rooms($rooms) {
        $query = 'UPDATE edc_room_details SET blocked = "", remark="" WHERE id IN (';
        foreach($rooms as $room)
        $query .= $room.',';
        $query = substr($query, 0, -1).')';
        $this->db->query($query);
    }

    function check_unavail($check_in, $check_out) {
        $query = $this->db->query("SELECT edc_booking_details.room_id as room_id
            FROM edc_registration_details
            INNER JOIN edc_booking_details
            ON edc_registration_details.app_num = edc_booking_details.app_num
            WHERE ( edc_registration_details.check_out >=  '".$check_in.
            "' AND edc_registration_details.check_in <=  '".$check_in.
            "') OR ( edc_registration_details.check_in >=  '".$check_in.
            "' AND edc_registration_details.check_in <=  '".$check_out.
            "')
        ");
        return $query->result_array();
    }

    function get_checked_app($room_id) {
        $query = $this->db->query('
            SELECT DISTINCT egd.app_num
            FROM edc_guest_details as egd
            INNER JOIN
            edc_registration_details as erd
            ON egd.app_num = erd.app_num
            WHERE egd.check_out IS NULL
            AND erd.check_out > CURRENT_TIMESTAMP
            AND room_alloted = "'.$room_id.'"
        ');
        if(count($query->row_array()))
            return $query->row_array()['app_num'];
        else return '';
    }
//Select only those applications which require the room in the future and have not checked in yet . 
//Along with the constraint that application does not get cancelled .
 function get_future_room_bookings($room_id) {
        $query = $this->db->query('
        SELECT DISTINCT app_num
        FROM edc_booking_details as ebd
        WHERE ebd.app_num NOT IN (
            SELECT DISTINCT app_num
            FROM edc_guest_details)
	     AND room_id = "'.$room_id.'" AND ebd.app_num IN
	
	(SELECT DISTINCT erd.app_num FROM edc_registration_details as erd WHERE (pce_status!="Cancelled" OR pce_status IS NULL) AND (dsw_status!="Cancel" OR dsw_status IS NULL)  AND (hod_status!="Cancel"  OR hod_status IS NULL) AND (pce_status !="Cancel" OR pce_status IS NULL) AND (pce_status !="Rejected" OR pce_status IS NULL) AND check_out > CURRENT_TIMESTAMP)
		
       
        ');

        return $query->result_array();
    }
    function get_room_bookings($room_id) {
        $query = $this->db->query('
        SELECT DISTINCT app_num
        FROM edc_booking_details
        WHERE app_num NOT IN (
            SELECT DISTINCT app_num
            FROM edc_guest_details)
        AND room_id = "'.$room_id.'"
        ');

        return $query->result_array();
    }

    function get_booked_rooms($date) {
        $query = $this->db->query("
            SELECT DISTINCT ebd.room_id
            FROM edc_booking_details as ebd
            INNER JOIN
            edc_registration_details as erd
            on ebd.app_num = erd.app_num
            WHERE erd.check_out > '".$date."'
        ");
        return $query->result_array();
    }

    function get_checked_rooms() {
        $query = $this->db->query('
            SELECT DISTINCT room_alloted as room_id
            FROM edc_guest_details as egd
            INNER JOIN
            edc_registration_details as erd
            ON egd.app_num = erd.app_num
            WHERE egd.check_out IS NULL
            
        ');

        return $query->result_array();
    }

    function set_ctk_status($status,$app_num) {
        $this->db->query ("UPDATE edc_registration_details SET ctk_allotment_status = '".$status."', ctk_action_timestamp = now(), pce_status = 'Pending' WHERE app_num = '".$app_num."';");
    }

    function insert_booking_details($data) {
        $this->db->insert('edc_booking_details',$data);
    }

    function delete_room_booking($app_num = '') {
        if($app_num == '') {
            $this->db->query('
                DELETE FROM edc_booking_details
                WHERE app_num IN
                (
                    SELECT app_num
                    FROM edc_registration_details
                    WHERE check_out < TIMESTAMP(now())
                    OR pce_status = "Rejected"
                )
            ');
        }
        else
            $this->db->query('
                DELETE FROM edc_booking_details
                WHERE app_num = "'.$app_num.'"
            ');
        }
    }
?>
