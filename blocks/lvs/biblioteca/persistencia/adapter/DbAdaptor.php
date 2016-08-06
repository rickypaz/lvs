<?php
namespace uab\ifce\lvs\persistencia\adapter;

/**
*  	Enter description here...
*
*	@package
* 	@author Ricky Persivo (rickypaz@gmail.com)
* 	@version
*/
class DbAdaptor {
	
	public function getRecords($table, $conditions = array(), $fields = '', $order = '') {
		global $DB;
		return $DB->get_records($table, $conditions, $order, $fields);
	}
	
}
?>