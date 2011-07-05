<?php
/**
 * ShoppingCart - provides a global way to interface with the cart (current order).
 *
 * This can be used in other code by calling $cart = ShoppingCart::singleton();
 *
 *
 * This version of shopping cart has been rewritten to:
 * - Seperate controller from the cart functions, abstracts out and encapsulates specific functionality.
 * - Reduce the excessive use of static variables.
 * - Clearly define an API for editing the cart, trying to keep the number of functions to a minimum.
 * - Allow easier testing of cart functionality.
 * - Message handling done in one place.
 * This is not taking a step backward, be cause the old ShoppingCart / Controller seperation had all static variables/functions on ShoppingCart
 *
 *@author: Jeremy Shipman, Nicolaas Francken
 *@package: ecommerce
 *
 *@todo handle rendering?
 *@todo copying order - repeat orders
 *
 */
class ShoppingCart extends Object{

	/**
	 * used for setting/getting cart things from the session
	 *@var string
	 **/
	protected static $session_variable = "EcommerceShoppingCart";
		public static function get_session_variable(){return self::$session_variable;}
		public static function set_session_variable($s){self::$session_variable = $s;}

	/**
	 * indicates where carts are cleaned up all the time (the alternative is to setup a cron job).
	 *@var Boolean
	 **/
	protected static $cleanup_every_time = true;
		static function set_cleanup_every_time($bool = false){self::$cleanup_every_time = $bool;}


	/**
	 * Jeremy todo: explain how this works
	 *@var Array
	 **/
	protected static $default_param_filters = array();
		static function set_default_param_filters(array $paramarray){self::$default_param_filters = $paramarray;}

	/**
	 * Feedback message to user (e.g. cart updated, could not delete item, someone in standing behind you).
	 *@var Array
	 **/
	protected $messages = array();

	/**
	 * Class used to provide the Shopping Cart Response (e.g. JSON data)
	 *@var string
	 **/
	protected static $response_class = "CartResponse";
		static function set_response_class(string $s) {self::$response_class = $s;}
		static function get_response_class() {return self::$response_class;}

	/**
	 * stores a reference to the current order object
	 *@var Object
	 **/
	protected $order = null;


	protected static $singletoncart = null;

	/**
	 * Allows access to the cart from anywhere in code.
	 *@return ShoppingCart Object
	 */
	public static function singleton(){
		if(!self::$singletoncart){
			self::$singletoncart = new ShoppingCart();
		}
		return self::$singletoncart;
	}

	/**
	 * Allows access to the current order from anywhere in the code..
	 *@return ShoppingCart Object
	 */
	public static function current_order() {
		return self::singleton()->currentOrder();
	}

	/**
	 * Adds any number of items to the cart.
	 *@param DataObject $buyable - the buyable (generally a product) being added to the cart
	 *@param Integer $quantity - number of items add.
	 *@param $parameters - array of parameters to target a specific order item. eg: group=1, length=5
	 *@return false | DataObject (OrderItem) 
	 */
	public function addBuyable($buyable, $quantity = 1, $parameters = array()){
		$item = $this->prepareQuantityChange($mustBeExistingItem = false, $buyable, $quantity, $parameters);
		if($item){ //find existing order item or make one
			$item->Quantity += $quantity;
			$item->write();
			$this->currentOrder()->Attributes()->add($item); //save to current order
			//TODO: distinquish between incremented and set
			//TODO: use sprintf to allow product name etc to be included in message
			if($quantity > 1) {
				$msg = _t("ShoppingCart.ITEMSADDED", "Items added.");
			}
			else {
				$msg = _t("ShoppingCart.ITEMADDED", "Item added.");
			}
			$this->addMessage($msg,'good');
			return $item;
			
		}
		$this->addMessage(_t("ShoppingCart.ITEMCOULDNOTBEADDED", "Item could not be added."),'bad');
	}
	
	/**
	 * Sets quantity for an item in the cart.
	 *@param DataObject $buyable - the buyable (generally a product) being added to the cart
	 *@param Integer $quantity - number of items add.
	 *@param Array $parameters - array of parameters to target a specific order item. eg: group=1, length=5
	 *@return false | DataObject (OrderItem) 
	 */
	function setQuantity($buyable, $quantity, $parameters = array()) {
		$item = $this->prepareQuantityChange($mustBeExistingItem = true, $buyable, $quantity, $parameters);
		if($item) {
			$item->Quantity = $quantity; //remove quantity
			$item->write();
			$this->addMessage(_t("ShoppingCart.CANTREMOVENONE", "Item updated."),'good');
			return $item;
		}
		return false;
	}

	/**
	 * Removes any number of items from the cart.
	 *@param DataObject $buyable - the buyable (generally a product) being added to the cart
	 *@param Integer $quantity - number of items add.
	 *@param Array $parameters - array of parameters to target a specific order item. eg: group=1, length=5
	 *@return false | DataObject (OrderItem) 
	 */
	public function decrementBuyable($buyable,$quantity = 1, $parameters = array()){
		$item = $this->prepareQuantityChange($mustBeExistingItem = false, $buyable, $quantity, $parameters);
		if($item) {
			$item->Quantity -= $quantity; //remove quantity
			if($item->Quantity < 0 ) {
				$item->Quantity = 0;
			}
			$item->write();
			if($quantity > 1) {
				$msg = _t("ShoppingCart.ITEMSREMOVED", "Items removed.");
			}
			else {
				$msg = _t("ShoppingCart.ITEMREMOVED", "Item removed.");
			}
			$this->addMessage($msg ,'good');
			return $item;
		}
		return false;
	}

	/**
	 * Delete item from the cart.
	 *@param OrderItem $buyable - the buyable (generally a product) being added to the cart
	 *@param Array $parameters - array of parameters to target a specific order item. eg: group=1, length=5
	 *@return boolean - successfully removed
	 */
	function deleteBuyable($buyable, $parameters = array()) {
		$item = $this->prepareQuantityChange($mustBeExistingItem = true, $buyable, $quantity = 1, $parameters);
		if($item) {
			$this->currentOrder()->Attributes()->remove($item);
			$item->delete();
			$item->destroy();
			$this->addMessage(_t("ShoppingCart.ITEMCOMPLETELYREMOVED", "Item removed from cart."),'good');
			return $item;
		}
		return false;
	}

	/**
	 * Checks and prepares variables for a quantity change (add, edit, remove) for an Order Item.
	 *@param Boolean $mustBeExistingItems - if false, the Order Item get created if it does not exist - if TRUE the order item is searched for and an error shows if there is no Order item.
	 *@param DataObject $buyable - the buyable (generally a product) being added to the cart
	 *@param Integer $quantity - number of items add.
	 *@param Array $parameters - array of parameters to target a specific order item. eg: group=1, length=5* 
	 *@return boolean | DataObject ($orderItem) 
	 */
	protected function prepareQuantityChange($mustBeExistingItem = true, $buyable, $quantity = 1, $parameters = array()) {
		if(!$buyable) {
			user_error("No buyable was provided", E_USER_WARNING);
		}
		if($buyable->canPurchase()) {
			if($mustBeExistingItem) {
				$item = $this->getExistingItem($buyable,$parameters);
			}
			else {
				$item = $this->findOrMakeItem($buyable,$parameters); //find existing order item or make one
			}
		}
		if(!$item){//check for existence of item
			$this->addMessage(_t("ShoppingCart.ITEMCOULDNOTBEFOUNDINCART", "Item could not found in cart."),'warning');
			return false;
		}
		$quantity = intval($quantity);		
		if($quantity < 0 || (!$quantity && $quantity !== 0)) {
			$this->addMessage(_t("ShoppingCart.INVALIDQUANTITY", "Invalid quantity."),'warning');
			return false;
		}
		return $item;
	} 

	/**
	 * Helper function for making / retrieving order items.
	 * we do not need things like "canPurchase" here, because that is with the "addBuyable" method.
	 * NOTE: does not write!
	 *@param DataObject $buyable
	 *@param Array $parameters
	 *@return OrderItem
	 */
	public function findOrMakeItem($buyable,$parameters = array()){
		if($item = $this->getExistingItem($buyable,$parameters)){
			return $item;
		}
		//otherwise create a new item
		$className = $buyable->classNameForOrderItem();
		$item = new $className();
		$item->BuyableID = $buyable->ID;
		return $item;
	}

	/**
	 * Clears the cart contents completely by removing the orderID from session, and thus creating a new cart on next request.
	 */
	public function clear(){
		Session::clear(self::$session_variable); //clear the orderid from session
		$this->order = null; //clear local variable
	}

	/**
	 * Removes a modifier from the cart
	 */
	public function removeModifier($modifier){
		$modifier = (is_numeric($modifier)) ? DataObject::get_by_id('OrderModifier',$modifier) : $modifier;
		if(!$modifier || !$modifier->CanBeRemoved()){
			$this->addMessage(_t("ShoppingCart.MODIFIERNOTREMOVED", "Could not be removed."),'bad');
			return;
		}
		$modifier->HasBeenRemoved = 1;
		$modifier->write();
		$this->addMessage(_t("ShoppingCart.MODIFIERREMOVED", "Removed."), 'good');
	}
	/**
	 * Removes a modifier from the cart
	 */
	public function addModifier($modifier){
		$modifier = (is_numeric($modifier)) ? DataObject::get_by_id('OrderModifier',$modifier) : $modifier;
		$modifier->HasBeenRemoved = 0;
		$modifier->write();
		$this->addMessage(_t("ShoppingCart.MODIFIERREMOVED", "Added."), 'good');
	}

	/**
	 * Sets an order as the current order.
	 *
	 */
	public function loadOrder($order){
		//TODO: how to handle existing order
		//TODO: permission check - does this belong to another member? ...or should permission be assumed already?
		if($this->order = (is_numeric($order)) ? DataObject::get_by_id('Order',$order) : $order){
			Session::set(self::$session_variable.".ID",$this->order->ID);
			$this->addMessage(_t("ShoppingCart.LOADEDEXISTING", "Order loaded."),'good');
		}
		else {
			$this->addMessage(_t("ShoppingCart.NOORDER", "No such order."),'bad');
		}
	}


	/**
	 * NOTE: tried to copy part to the Order Class - but that was not much of a go-er.
	 *@return DataObject(Order)
	 **/
	public function copyOrder($oldOrderID) {
		$oldOrder = Order::get_by_id_if_can_view($oldOrderID);
		if(!$oldOrder) {
			$this->addMessage(_t("ShoppingCart.NOORDER", "No such order."),'bad');
		}
		else {
			$newOrder = new Order();
			//for later use...
			$newOrder->write();
			$fieldList = array_keys(DB::fieldList("Order"));
			$this->loadOrder($newOrder);
			$items = DataObject::get("OrderItem", "\"OrderID\" = ".$oldOrder->ID);
			if($items) {
				foreach($items as $item) {
					$buyable = $item->Buyable($current = true);
					if($buyable->canPurchase()) {
						$this->addBuyable($buyable, $item->Quantity);
					}
				}
			}
			$newOrder->write();
			$this->addMessage(_t("ShoppingCart.ORDERCOPIED", "Order has been copied."),'good');
		}
	}

	/**
	 * sets country in order so that modifiers can be recalculated, etc...
	 *@param String - $countryCode
	 **/
	public function setCountry($countryCode) {
		if(EcommerceCountry::code_allowed($countryCode)) {
			$this->currentOrder()->SetCountry($countryCode);
		}
	}
	/**
	 * sets region in order so that modifiers can be recalculated, etc...
	 *@param Integer - $regionID - EcommerceRegion.ID
	 **/
	public function setRegion($regionID) {
		$this->currentOrder()->SetRegion($regionID);
	}

	/**
	 * Produces a debug of the shopping cart.
	 */
	public function debug(){
		Debug::show($this->currentOrder());
	}

	/**
	 * Stores a message that can later be returned via ajax or to $form->sessionMessage();
	 *@param $message - the message, which could be a notification of successful action, or reason for failure
	 *@param $type - please use good, bad, warning
	 */
	public function addMessage($message, $status = 'good'){
		//clean status for the lazy programmer
		$status = strtolower($status);
		str_replace(array("success", "failure"), array("good", "bad"), $status);
		$statusOptions = array("good", "bad", "warning");
		if(!in_array($status, $statusOptions)) {
			user_error("Message status should be one of the following: ".implode(",", $statusOptions), E_USER_NOTICE);
		}
		$this->messages[] = array(
			'Message' => $message,
			'Type' => $status
		);
	}

	/*******************************************************
	* HELPER FUNCTIONS
	*******************************************************/

	/**
	 * Gets or creates the current order.
	 */
	protected function currentOrder(){
		if (!$this->order) {
			//TODO: try to retrieve incomplete member order
			$this->order = DataObject::get_by_id('Order',intval(Session::get(self::$session_variable.".ID"))); //find order by id saved to session (allows logging out and retaining cart contents)
			if(!$this->order){
				$this->order = new Order();
				$this->order->MemberID = Member::currentUserID();
				$this->order->write();
				Session::set(self::$session_variable.".ID",$this->order->ID);
			}
			$this->order->calculateModifiers();
		}
		return $this->order;
	}


	/**
	 * Gets an existing order item based on buyable and passed parameters
	 *@param DataObject $buyable
	 *@param Array $parameters
	 *@return OrderItem or null
	 */
	protected function getExistingItem($buyable,$parameters = array()){
		$filterString = $this->parametersToSQL($parameters);
		$orderItemClassName = $buyable->classNameForOrderItem();
		$orderID = $this->currentOrder()->ID;
		// NOTE: MUST HAVE THE EXACT CLASSNAME !!!!! THEREFORE INCLUDED IN WHERE PHRASE
		return DataObject::get_one($orderItemClassName, "\"ClassName\" = '".$orderItemClassName."' AND \"OrderID\" = ".$orderID." AND \"BuyableID\" = ".$buyable->ID." ". $filterString);
	}

	/**
	 * Removes parameters that aren't in the default array, merges with default parameters, and converts raw2SQL.
	 *@param Array $parameters -  unclean array
	 *@return cleaned array
	 */
	protected function cleanParameters($params = array()){
		$newarray = array_merge(array(),self::$default_param_filters); //clone array
		if(!count($newarray)) {
			return array(); //no use for this if there are not parameters defined
		}
		foreach($newarray as $field => $value){
			if(isset($params[$field])){
				$newarray[$field] = Convert::raw2sql($params[$field]);
			}
		}
		return $newarray;
	}

	/**
	 * Converts parameter array to SQL query filter
	 */
	protected function parametersToSQL($parameters = array()){
		$defaultParamFilters = self::$default_param_filters;
		if(!count($defaultParamFilters)) {
			return ""; //no use for this if there are not parameters defined
		}
		$cleanedparams = $this->cleanParameters($parameters);
		$outputArray = array();
		foreach($cleanedparams as $field => $value){
			$outputarray[$field] = "\"".$field."\" = ".$value;
		}
		if(count($outputArray)) {
			return implode(" AND ",$outputArray);
		}
		return "";
	}

	/*******************************************************
	* UI MESSAGE HANDLING
	*******************************************************/


	/**
	 * Retrieves all good, bad, and ugly messages that have been produced during the current request.
	 *@return array of messages
	 */
	function getMessages(){
		$sessionVariableName = (ShoppingCart::get_session_variable()."Messages");
		//get old messages
		$messages = unserialize(Session::get($sessionVariableName));
		//clear old messages
		Session::clear($sessionVariableName, "");
		//set to form????
		if($messages && count($messages)) {
			$this->messages = array_merge($messages, $this->messages);
		}
		return $this->messages;
	}

	/**
	 *Saves current messages in session for retrieving them later.
	 *@return array of messages
	 */
	protected function StoreMessagesInSession(){
		Session::set(ShoppingCart::get_session_variable()."Messages", serialize($this->messages));
	}

	public function setMessageAndReturn($message = "", $status = ""){
		if($message && $status) {
			$this->addMessage($message,$status);
		}
		//TODO: handle passing back multiple messages
		if(Director::is_ajax()){
			$responseClass = self::get_response_class();
			$obj = new $responseClass();
			return $obj->ReturnCartData($this->getMessages());
		}
		else {
			//TODO: handle passing a message back to a form->sessionMessage
			$this->StoreMessagesInSession();
			Director::redirectBack();
			return;
		}
	}

}

/**
 * ShoppingCart_Controller
 *
 * Handles the modification of a shopping cart via http requests.
 * Provides links for making these modifications.
 *
 *@author: Jeremy Shipman, Nicolaas Francken
 *@package: ecommerce
 *
 *@todo supply links for adding, removing, and clearing cart items
 *@todo link for removing modifier(s)
 */
class ShoppingCart_Controller extends Controller{


	/**
	 * URLSegment used for the Shopping Cart controller
	 *@var string
	 **/
	protected static $url_segment = 'shoppingcart';
		static function set_url_segment($s) {self::$url_segment = $s;}
		static function get_url_segment() {return self::$url_segment;}


	protected $cart = null;

	function init() {
		parent::init();
		$this->cart = ShoppingCart::singleton();
	}

	public static $allowed_actions = array (
		'additem',
		'removeitem',
		'removeallitem',
		'removemodifier',
		'addmodifier',
		'setcountry',
		'setregion',
		'setquantityitem',
		'clear',
		'clearandlogout',
		'numberofitemsincart',
		'showcart',
		'loadorder',
		'copyorder',
		'debug' => 'ADMIN'
	);

	/*******************************************************
	* CONTROLLER LINKS
	*******************************************************/

	public function Link($action = null) {
		return Controller::join_links(Director::baseURL(), $this->RelativeLink($action));
	}

	static function add_item_link($buyableID, $classNameForBuyable = "Product", $parameters = array()) {
		return self::$url_segment.'/additem/'.$buyableID."/".$classNameForBuyable."/".self::params_to_get_string($parameters);
	}

	static function remove_item_link($buyableID, $classNameForBuyable = "Product", $parameters = array()) {
		return self::$url_segment.'/removeitem/'.$buyableID."/".$classNameForBuyable."/".self::params_to_get_string($parameters);
	}

	static function remove_all_item_link($buyableID, $classNameForBuyable = "Product", $parameters = array()) {
		return self::$url_segment.'/removeallitem/'.$buyableID."/".$classNameForBuyable."/".self::params_to_get_string($parameters);
	}

	static function set_quantity_item_link($buyableID, $classNameForBuyable = "Product", $parameters = array()) {
		return self::$url_segment.'/setquantityitem/'.$buyableID."/".$classNameForBuyable."/".self::params_to_get_string($parameters);
	}

	static function remove_modifier_link($modifierID) {
		return self::$url_segment.'/removemodifier/'.$modifierID."/";
	}

	static function add_modifier_link($modifierID) {
		return self::$url_segment.'/addmodifier/'.$modifierID."/";
	}

	static function clear_cart_and_logout_link() {
		return self::$url_segment.'/clearandlogout/';
	}

	/**
	 * Helper function used by link functions
	 * Creates the appropriate url-encoded string parameters for links from array
	 *
	 * Produces string such as: MyParam%3D11%26OtherParam%3D1
	 *     ...which decodes to: MyParam=11&OtherParam=1
	 *
	 * you will need to decode the url with javascript before using it.
	 *
	 *@todo: check that comment description actually matches what it does
	 *@return String (URLSegment)
	 */
	protected static function params_to_get_string($array){
		if($array & count($array > 0)){
			array_walk($array , create_function('&$v,$k', '$v = $k."=".$v ;'));
			return "?".implode("&",$array);
		}
		return "";
	}

	/**
	 * Adds item to cart via controller action; one by default.
	 *@return Mixed - if the request is AJAX, it returns JSON - CartResponse::ReturnCartData(); If it is not AJAX it redirects back to requesting page.
	 */
	public function additem(){
		$this->cart->addBuyable($this->buyable(),$this->quantity(),$this->parameters());
		return $this->cart->setMessageAndReturn();
	}

	/**
	 * Sets the exact passed quantity.
	 * Note: If no ?quantity=x is specified in URL, then quantity will be set to 1.
	 *@return Mixed - if the request is AJAX, it returns JSON - CartResponse::ReturnCartData(); If it is not AJAX it redirects back to requesting page.
	 */
	public function setquantityitem(){
		$this->cart->setQuantity($this->buyable(),$this->quantity(),$this->parameters());
		return $this->cart->setMessageAndReturn();
	}

	/**
	 * Removes item from cart via controller action; one by default.
	 *@return Mixed - if the request is AJAX, it returns JSON - CartResponse::ReturnCartData(); If it is not AJAX it redirects back to requesting page.
	 */
	public function removeitem(){
		$this->cart->decrementBuyable($this->buyable(),$this->quantity(),$this->parameters());
		return $this->cart->setMessageAndReturn();
	}

	/**
	 * Removes all of a specific item
	 *@return Mixed - if the request is AJAX, it returns JSON - CartResponse::ReturnCartData(); If it is not AJAX it redirects back to requesting page.
	 */
	public function removeallitem(){
		$this->cart->deleteBuyable($this->buyable(),$this->parameters());
		return $this->cart->setMessageAndReturn();
	}

	/**
	 * Removes a specified modifier from the cart;
	 *@return Mixed - if the request is AJAX, it returns JSON - CartResponse::ReturnCartData(); If it is not AJAX it redirects back to requesting page.
	 */
	public function removemodifier($request){
		$this->cart->removeModifier($request->param('ID'));
		return $this->cart->setMessageAndReturn();
	}

	/**
	 * Adds a specified modifier to the cart;
	 *@return Mixed - if the request is AJAX, it returns JSON - CartResponse::ReturnCartData(); If it is not AJAX it redirects back to requesting page.
	 */
	public function addmodifier($request){
		$this->cart->addModifier($request->param('ID'));
		return $this->cart->setMessageAndReturn();
	}


	/**
	 *sets the country
	 *@return Mixed - if the request is AJAX, it returns JSON - CartResponse::ReturnCartData(); If it is not AJAX it redirects back to requesting page.
	 **/
	function setcountry($request) {
		$request = $this->getRequest();
		$countryCode = $request->param('ID');
		if($countryCode) {
			//set_country will check if the country code is actually allowed....
			$this->cart->setCountry($countryCode);
		}
		return $this->cart->setMessageAndReturn();
	}

	/**
	 *@return String (message)
	 *@return Mixed - if the request is AJAX, it returns JSON - CartResponse::ReturnCartData(); If it is not AJAX it redirects back to requesting page.
	 **/
	function setregion($request) {
		$request = $this->getRequest();
		$regionID = intval($request->param('ID'));
		if($regionID) {
			//set_country will check if the country code is actually allowed....
			$this->cart->setRegion($regionID);
		}
		return $this->cart->setMessageAndReturn();
	}

	function clear() {
		$this->cart->clear();
		Director::redirectBack();
		exit();
	}

	function clearandlogout() {
		$this->cart->clear();
		if($m = Member::currentUser()) {
			$m->logout();
		}
		Director::redirectBack();
		exit();
	}

	/**
	 * return number of items in cart
	 *@return integer
	 **/
	function numberofitemsincart() {
		$order = $this->cart->CurrentOrder();
		return $order->TotalItems();
	}

	/**
	 * return cart for ajax call
	 *@return HTML
	 */
	function showcart($request) {
		return $this->renderWith("AjaxSimpleCart");
	}

	/**
	 * Gets a buyable object based on URL actions
	 *@return DataObject | Null - returns buyable
	 */
	protected function buyable(){
		$request = $this->getRequest();
		$className = $request->param('OtherID');
		$buyableID = $request->param('ID');
		if($className && $buyableID){
			if(Buyable::is_buyable($className)) {
				$obj = DataObject::get_by_id($className,intval($buyableID)); //TODO: possible unsafe class name being passed...do proper subclass check
				if($obj->ClassName == $className) {
					return $obj;
				}
			}
			else {
				if(strpos($className, "OrderItem")) {
					user_error("ClassName in URL should be buyable and not an orderitem", E_USER_NOTICE);
				}
			}
		}
		return null;
	}

	/**
	 * Gets the requested quantity
	 */
	protected function quantity(){
		$qty = $this->getRequest()->getVar('quantity');
		if(is_numeric($qty)){
			return $qty;
		}
		return 1;
	}

	/**
	 * Gets the request parameters
	 *@param $getpost - choose between obtaining the chosen parameters from GET or POST
	 */
	protected function parameters($getpost = 'GET'){
		return ($getpost == 'GET') ? $this->request->getVars() : $_POST;
	}


	/**
	 * Handy debugging action visit.
	 * Log in as an administrator and visit mysite/shoppingcart/debug
	 */
	function debug(){
		$this->cart->debug();
	}





}