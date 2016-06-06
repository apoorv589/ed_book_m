<?php

class Edc_booking_menu_model extends CI_Model
{
	function __construct()
	{
		// Call the Model constructor
		parent::__construct();
	}

	function getMenu()
	{
		$menu=array();
		//auth ==> Employee
		$menu['emp']['EDC Booking']=array();
		$menu['emp']['EDC Booking']['Room Booking Form'] = site_url('edc_booking/booking/form');
		$menu['emp']['EDC Booking']['Track Booking Status'] = site_url('edc_booking/booking/track_status');
		$menu['emp']['EDC Booking']['Booked History'] = site_url('edc_booking/booking/history');

		$menu['stu']['EDC Booking']=array();
		$menu['stu']['EDC Booking']['Room Booking Form'] = site_url('edc_booking/booking/form');
		$menu['stu']['EDC Booking']['Track Booking Status'] = site_url('edc_booking/booking/track_status');
		$menu['stu']['EDC Booking']['Booked History'] = site_url('edc_booking/booking/history');

		$menu['hod']['EDC Booking'] = site_url('edc_booking/booking_request/app_list/hod');

		$menu['hos']['EDC Booking'] = site_url('edc_booking/booking_request/app_list/hos');

		$menu['dsw']['EDC Booking'] = site_url('edc_booking/booking_request/app_list/dsw');

		$menu['pce']['EDC Booking']=array();
		$menu['pce']['EDC Booking']['Booking Requests'] = site_url('edc_booking/booking_request/app_list/pce');
 		$menu['pce']['EDC Booking']['Room Availability'] = site_url('edc_booking/management/building_status/pce');
		$menu['pce']['EDC Booking']['Search Booking History'] = site_url('edc_booking/guest_details/search/pce');

		$menu['pce_da5']['EDC Booking'] = array();
		$menu['pce_da5']['EDC Booking']['Room Booking Form (Others)'] = site_url('edc_booking/booking/other_bookings_form');
		$menu['pce_da5']['EDC Booking']['Guest Details'] = site_url('edc_booking/guest_details');
		$menu['pce_da5']['EDC Booking']['Room Allotment'] = site_url('edc_booking/booking_request/pce_da5_app_list');
 		$menu['pce_da5']['EDC Booking']['Room Availability'] = site_url('edc_booking/management/building_status/pce_da5');
		$menu['pce_da5']['EDC Booking']['Room Planning'] = site_url('edc_booking/management/room_management');
		$menu['pce_da5']['EDC Booking']['Search Booking History'] = site_url('edc_booking/guest_details/search/pce_da5');
		return $menu;
	}
}
