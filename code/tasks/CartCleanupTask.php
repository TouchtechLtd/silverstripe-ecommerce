<?php


/**
 * @description: cleans up old (abandonned) carts...
 *
 * @authors: Silverstripe, Jeremy, Nicolaas
 *
 * @package: ecommerce
 * @sub-package: cms
 *
 **/


class CartCleanupTask extends HourlyTask {


	static $allowed_actions = array(
		'*' => 'ADMIN',
		'*' => 'SHOP_ADMIN'
	);


	protected $title = 'Clear old carts';

	protected $description = "Deletes abandonned carts";

	public static function run_on_demand() {
		$obj = new CartCleanupTask();
		$obj->run($verbose = true);
		$obj->cleanupUnlinkedOrderObjects($verbose = true);
	}


/*******************************************************
	 * CLEARING OLD ORDERS
*******************************************************/

	protected static $clear_days = 30;
		function set_clear_days(integer $i){self::$clear_days = $i;}
		function get_clear_days(){return(integer)self::$clear_days;}

	/**
	 * We need to protect the system from falling over by limiting the number of objects that can be deleted at any one time
	 *@var Integer
	 **/
	protected static $maximum_number_of_objects_deleted = 100;
		function set_maximum_number_of_objects_deleted(integer $i){self::$maximum_number_of_objects_deleted = $i;}
		function get_maximum_number_of_objects_deleted(){return(integer)self::$maximum_number_of_objects_deleted;}

	protected static $never_delete_if_linked_to_member = true;
		function set_never_delete_if_linked_to_member(boolean $b){self::$never_delete_if_linked_to_member = $b;}
		function get_never_delete_if_linked_to_member(){return(boolean)self::$never_delete_if_linked_to_member;}


	protected static $linked_objects_array = array(
		"ShippingAddress",
		"BillingAddress",
		"OrderAttribute",
	);
		static function set_linked_objects_array($a) {self::$linked_objects_array = $a;}
		static function get_linked_objects_array() {return self::$linked_objects_array;}
		static function add_linked_object($s) {self::$linked_objects_array[] = $s;}
/*******************************************************
	 * DELETE OLD SHOPPING CARTS
*******************************************************/

	/**
	 *@return Integer - number of carts destroyed
	 **/
	public function run($verbose = false){
		$count = 0;
		$time = date('Y-m-d H:i:s', strtotime("-".self::$clear_days." days"));
		$where = "\"StatusID\" = ".OrderStep::get_status_id_from_code("CREATED")." AND \"Order\".\"LastEdited\" < '$time'";
		$sort = "\"Order\".\"Created\" ASC";
		$join = "";
		$limit = "0, ".self::get_maximum_number_of_objects_deleted();
		if(self::$never_delete_if_linked_to_member) {
			$where .= " AND \"Member\".\"ID\" IS NULL";
			$join .= "LEFT JOIN \"Member\" ON \"Member\".\"ID\" = \"Order\".\"MemberID\" ";
		}
		$oldCarts = DataObject::get('Order',$where, $sort, $join, $limit);
		if($oldCarts){
			if($verbose) {
				$totalToDeleteSQLObject = DB::query("SELECT COUNT(*) FROM \"Order\" $join WHERE $where");
				$totalToDelete = $totalToDeleteSQLObject->value();
				DB::alteration_message("<h2>Total number of abandonned carts: ".$totalToDelete." .... now deleting: ".self::get_maximum_number_of_objects_deleted()." from ".self::get_clear_days()." days ago or more.</h2>", "created");
				if(self::get_never_delete_if_linked_to_member()) {
					DB::alteration_message("<h3>Carts linked to a member will NEVER be deleted.</h3>", "edited");
				}
				else {
					DB::alteration_message("<h3>We will also delete carts in this category that are linked to a member.</h3>", "edited");
				}
			}
			foreach($oldCarts as $carts){
				$count++;
				if($verbose) {
					DB::alteration_message("$count ... deleting abandonned order #".$carts->ID, "deleted");
				}
				$carts->delete();
				$carts->destroy();
			}
		}
		else {
			if($verbose) {
				DB::alteration_message("There are no abandonned orders.", "created");
			}
		}
		return $count;
	}

	function cleanupUnlinkedOrderObjects($verbose = false) {
		$classNames = self::get_linked_objects_array();
		if(is_array($classNames) && count($classNames)) {
			foreach($classNames as $className) {
				$where = "\"Order\".\"ID\" IS NULL";
				$sort = '';
				$join = "LEFT JOIN \"Order\" ON \"Order\".\"ID\" = \"OrderID\"";
				$limit = "0, ".self::get_maximum_number_of_objects_deleted();
				$unlinkedObjects = DataObject::get($className, $where, $sort, $join, $limit);
				if($unlinkedObjects){
					foreach($unlinkedObjects as $object){
						if($verbose) {
							DB::alteration_message("Deleting ".$object->ClassName." with ID #".$object->ID." because it does not appear to link to an order.", "deleted");
						}
						$object->delete();
						$object->destroy();
					}
				}
			}
		}
	}

}
