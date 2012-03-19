<?php

/**
 * which returns an array of IDs
 * SEQUENCE - USE FOR ALL MODIFIERS!!!
 * *** 1. model defining static variables (e.g. $db, $has_one)
 * *** 2. cms variables + functions (e.g. getCMSFields, $searchableFields)
 * *** 3. other (non) static variables (e.g. protected static $special_name_for_something, protected $order)
 * *** 4. CRUD functions (e.g. canEdit)
 * *** 5. init and update functions
 * *** 6. form functions (e. g. showform and getform)
 * *** 7. template functions (e.g. ShowInTable, TableTitle, etc...) ... USES DB VALUES
 * *** 8. inner calculations.... USES CALCULATED VALUES
 * *** 9. calculate database fields: protected function Live[field name]  ... USES CALCULATED VALUES
 * *** 10. standard database related functions (e.g. onBeforeWrite, onAfterWrite, etc...)
 * *** 11. AJAX related functions
 * *** 12. debug functions
 *
 * FAQs
 *
 * *** What is the difference between cart and table ***
 * The Cart is a smaller version of the Table. Table is used for Checkout Page + Confirmation page.
 * Cart is used for other pages (pre-checkout for example). At times, the values and names may differ
 *
 *
 * @authors: Silverstripe, Jeremy, Nicolaas
 *
 * @package: ecommerce
 * @sub-package: modifiers
 *
 **/
class OrderModifier extends OrderAttribute {

	/**
	 * what variables are accessible through  http://mysite.com/api/v1/OrderModifier/
	 * @var array
	 */
	public static $api_access = array(
		'view' => array(
				'CalculatedTotal',
				'Sort',
				'GroupSort',
				'TableTitle',
				'TableSubTitle',
				'CartTitle',
				'Name',
				'TableValue',
				'HasBeenRemoved',
				"Order"
			)
	 );

// ########################################  *** 1. model defining static variables (e.g. $db, $has_one)

	public static $db = array(
		'Name' => 'Varchar(255)', // we use this to create the TableTitle, CartTitle and TableSubTitle
		'TableValue' => 'Currency', //the $$ shown in the checkout table
		'HasBeenRemoved' => 'Boolean' // we add this so that we can see what modifiers have been removed
	);

	// make sure to choose the right Type and Name for this.
	public static $defaults = array(
		'Name' => 'Modifier' //making sure that you choose a different name for any class extensions.
	);

// ########################################  *** 2. cms variables  + functions (e.g. getCMSFields, $searchableFields)

	public static $searchable_fields = array(
		'OrderID' => array(
			'field' => 'NumericField',
			'title' => 'Order Number'
		),
		"TableTitle" => "PartialMatchFilter",
		"TableValue",
		"HasBeenRemoved"
	);

	public static $summary_fields = array(
		"Order.ID" => "Order ID",
		"TableTitle" => "Table Title",
		"TableValue" => "Value Shown"
	);

	public static $casting = array(
		'CartValue' => 'Currency', // the $$ shown in the cart (smaller version of the checkout table)
	);

	public static $singular_name = "Order Extra";
		function i18n_singular_name() { return _t("OrderModifier.ORDERMODIFIER", "Order Extra");}

	public static $plural_name = "Order Extras";
		function i18n_plural_name() { return _t("OrderModifier.ORDERMODIFIERS", "Order Extras");}

	function getCMSFields(){
		$fields = parent::getCMSFields();
		$fields->removeByName("Sort");
		$fields->removeByName("GroupSort");
		$fields->replaceField("Name", new ReadonlyField("Name"));
		$fields->removeByName("TableValue");
		$fields->removeByName("CalculatedTotal");
		$fields->removeByName("HasBeenRemoved");
		$fields->addFieldToTab(
			"Root",
			new Tab(
				"Debug",
				new ReadonlyField("ClassName", "Type", $this->ClassName),
				new ReadonlyField("CreatedShown", "Created", $this->Created),
				new ReadonlyField("LastEditedShown", "Last Edited", $this->LastEdited),
				new ReadonlyField("TableValueShown", "Table Value", $this->TableValue),
				new ReadonlyField("CalculatedTotal", "Raw Value", $this->CalculatedTotal)
			)
		);
		$fields->addFieldToTab("Root.Status", new CheckboxField("HasBeenRemoved", "Has been removed"));
		$fields->removeByName("OrderAttribute_GroupID");
		return $fields;
	}

	/**
	 *
	 * @return FieldSet
	 **/
	function scaffoldSearchFields(){
		$fields = parent::scaffoldSearchFields();
		$fields->replaceField("OrderID", new NumericField("OrderID", "Order Number"));
		return $fields;
	}

// ########################################  *** 3. other static variables (e.g. special_name_for_something)

	/**
	 * $do_not_add_automatically Identifies whether a modifier is NOT automatically added
	 * Most modifiers, such as delivery and GST would be added automatically.
	 * However, there are also ones that are not added automatically.
	 * @var Boolean
	 **/
	protected static $do_not_add_automatically = false;
		static function set_do_not_add_automatically($b) {self::$do_not_add_automatically = $b;}
		static function get_do_not_add_automatically() {
			if(function_exists("get_called_class")) {
				$class = get_called_class();
				self::$do_not_add_automatically;
				//return $class::$do_not_add_automatically;
			}
			else {
				self::$do_not_add_automatically;
			}
		}

	/**
	 * $can_be_removed Identifies whether a modifier can be removed by the user.
	 * @var Boolean
	 **/
	protected static $can_be_removed = false;
		static function set_can_be_removed($b) {self::$can_be_removed = $b;}
		static function get_can_be_removed() {
			if(function_exists("get_called_class")) {
				$class = get_called_class();
				self::$can_be_removed;
				//return $class::$can_be_removed;
			}
			else {
				self::$can_be_removed;
			}
		}

	/**
	 * we use this variable to make sure that the parent::runUpdate() is called in all child classes
	 * this is similar to the checks run for parent::init in the controller class.
	 * @var Boolean
	 **/
	protected $baseInitCalled = false;

	/**
	* This is a flag for running an update.
	* Running an update means that all fields are (re)set, using the Live{FieldName} methods.
	* @var Boolean
	**/
	protected $mustUpdate = false;


// ######################################## *** 4. CRUD functions (e.g. canEdit)



// ########################################  *** 5. init and update functions


	public static function init_for_order($className) {
		user_error("the init_for_order method has been depreciated, instead, use \$myModifier->init()", E_USER_ERROR);
		return false;
	}

	/**
	* This method runs when the OrderModifier is first added to the order.
	**/
	public function init() {
		parent::init();
		$this->write();
		$this->mustUpdate = true;
		$this->runUpdate();
		return true;
	}

	/**
	* all modifier child-classes must have this method if it has more fields
	 * @param Bool $force - run it, even if it has run already
	**/
	public function runUpdate($force = false) {
		if(!$this->IsRemoved()) {
			$this->checkField("Name");
			$this->checkField("CalculatedTotal");
			$this->checkField("TableValue");
			$this->checkField("CartValue");
			if($this->mustUpdate && $this->canBeUpdated()) {
				$this->write();
			}
		}
		$this->baseInitCalled = true;
	}

	/**
	* You can overload this method as canEdit might not be the right indicator.
	* @return Boolean
	**/
	protected function canBeUpdated() {
		return $this->canEdit();
	}

	/**
	* This method simply checks if a fields has changed and if it has changed it updates the field.
	**/
	protected function checkField($fieldName) {
		if($this->canBeUpdated()) {
			$functionName = "Live".$fieldName;
			$this->$fieldName = $this->$functionName();
			$this->mustUpdate = true;
		}
		//debug::show($this->ClassName.".".$fieldName.": ".floatval(microtime() - $start));
	}

	/**
	 * Provides a modifier total that is positive or negative, depending on whether the modifier is chargable or not.
	 * This number is used to work out the order Grand Total.....
	 * It is important to note that this can be positive or negative, while the amount is always positive.
	 * @return float / double
	 */
	public function CalculationTotal() {
		if($this->HasBeenRemoved) {
			return 0;
		}
		return $this->CalculatedTotal;
	}

// ########################################  *** 6. form functions (showform and getform)

	/**
	 * This determines whether the OrderModifierForm is shown or not. {@link OrderModifier::get_form()}.
	 * OrderModifierForms are forms that are added to check out to facilitate the use of the modifier.
	 * An example would be a form allowing the user to select the delivery option.
	 * @return boolean
	 */
	public function ShowForm() {
		return false;
	}

	/**
	 * This function returns a form that allows a user
	 * to change the modifier to the order.
	 *
	 * We have mainly added this function as an example!
	 *
	 * @param Controller $optionalController  - optional custom controller class
	 * @param Validator $optionalValidator  - optional custom validator class
	 * @return OrderModifierForm or subclass
	 */
	public function getModifierForm($optionalController = null, $optionalValidator = null) {
		if($this->showForm()) {
			$fields = new FieldSet();
			$fields->push($this->headingField());
			$fields->push($this->descriptionField());
			return new OrderModifierForm($optionalController, "ModifierForm", $fields, $actions = new FieldSet(), $optionalValidator);
		}
	}

	/**
	 *
	 * @return Object (HeadingField)
	 */
	protected function headingField(){
		$name = $this->ClassName.'Heading';
		if($this->Heading()) {
			return new HeaderField($name, $this->Heading(), 4);
		}
		return new LiteralField($name, "<!-- EmptyHeading -->", "<!-- EmptyHeading -->");
	}

	/**
	 *
	 * @return Object (LiteralField)
	 */
	protected function descriptionField(){
		$name = $this->ClassName.'Description';
		if($this->Description()) {

			return new LiteralField($name, "<div id=\"{$name}DescriptionHolder\" class=\"descriptionHolder\">".$this->Description()."</div>");
		}
		return new LiteralField($name, "<!-- EmptyDescription -->","<!-- EmptyDescription -->");
	}


// ######################################## *** 7. template functions (e.g. ShowInTable, TableTitle, etc...)

	/**
	 * returns a heading if there is one.
	 * @return String
	 **/
	public function Heading(){
		if($obj = DataObject::get_one("OrderModifier_Descriptor", "\"ModifierClassName\" = '".$this->ClassName."'")) {
			return $obj->Heading;
		}
		return "";
	}

	/**
	 * returns a description if there is one.
	 * @return String (html)
	 **/
	public function Description(){
		if($obj = DataObject::get_one("OrderModifier_Descriptor", "\"ModifierClassName\" = '".$this->ClassName."'")) {
			return $obj->Description;
		}
		return "";
	}

	/**
	 * returns a page for a more info link... (if there is one)
	 * @return Object (SiteTree)
	 **/
	public function Link(){
		if($obj = DataObject::get_one("OrderModifier_Descriptor", "\"ModifierClassName\" = '".$this->ClassName."'")) {
			return $obj->Link();
		}
		return null;
	}


	/**
	 * tells you whether the modifier shows up on the checkout  / cart form.
	 * this is also the place where we check if the modifier has been updated.
	 * @return Boolean
	 */
	public function ShowInTable() {
		if(!$this->baseInitCalled && $this->canBeUpdated()) {
			user_error("While the order can be edited, you must call the runUpdate method everytime you get the details for this modifier", E_USER_ERROR);
		}
		return false;
	}

	/**
	* some modifiers can be hidden after an ajax update (e.g. if someone enters a discount coupon and it does not exist).
	* There might be instances where ShowInTable (the starting point) is TRUE and HideInAjaxUpdate return false.
	*@return Boolean
	**/
	public function HideInAjaxUpdate() {
		if($this->IsRemoved()) {
			return true;
		}
		if($this->ShowInTable()) {
			return false;
		}
		return true;
	}

	/**
	 * Checks if the modifier can be removed.
	 *
	 * @return boolean
	 **/
	public function CanBeRemoved() {
		return self::get_can_be_removed();
	}

	/**
	 * Checks if the modifier can be added manually
	 *
	 * @return boolean
	 **/
	public function CanAdd() {
		return $this->HasBeenRemoved || $this->DoNotAddOnInit();
	}

	/**
	 *Identifier whether a modifier will be added automatically for all new orders.
	 * @return boolean
	 */
	public function DoNotAddAutomatically() {
		return self::get_do_not_add_automatically();
	}



	/**
	 * Sometimes we need a difference between Cart and Checkout Value - the cart value can be differentiated here.
	 *
	 * @return Currency Object
	 **/
	public function CartValue() {return $this->getCartValue();}
	public function getCartValue(){
		return $this->TableValue;
	}
	/**
	 * Actual calculation used
	 *
	 * @return Float / Double
	 **/
	public function CalculatedTotal() {
		return $this->CalculatedTotal;
	}

	/**
	 * This describes what the name of the  modifier should be, in relation to
	 * the order table on the check out page - which the templates uses directly.
	 * For example, this could be something  like "Shipping to NZ", where NZ is a
	 * dynamic variable on where the user currently is, using {@link Geoip}.
	 *
	 * @return string
	 */
	public function TableTitle() {return $this->getTableTitle();}
	public function getTableTitle() {
		return $this->Name;
	}

	/**
	 * Sometimes we need a difference between Cart and Checkout Title - the cart Title can be differentiated here.
	 *
	 * @return String
	 **/

	public function CartTitle() {return $this->getCartTitle();}
	public function getCartTitle() {
		return $this->TableTitle();
	}

	/**
	 * This link is for modifiers that have been removed and are being put "back".
	 * @return String
	  **/
	public function AddLink() {
		return ShoppingCart_Controller::add_modifier_link($this->ID,$this->ClassName);
	}
	/**
	 *
	 * @return String
	  **/
	public function RemoveLink() {
		return ShoppingCart_Controller::remove_modifier_link($this->ID,$this->ClassName);
	}


// ######################################## ***  8. inner calculations....


// ######################################## ***  9. calculate database fields ( = protected function Live[field name]() { ....}

	protected function LiveName() {
		user_error("The \"LiveName\" method has be defined in ...".$this->ClassName, E_USER_NOTICE);
		return self::$defaults["Name"];
	}

	protected function LiveTableValue() {
		return $this->LiveCalculatedTotal();
	}

	protected function LiveCartValue() {
		return $this->LiveCalculatedTotal();
	}
	/**
	 * This function is always called to determine the
	 * amount this modifier needs to charge or deduct - if any.
	 *
	 *
	 * @return Currency
	 */
	protected function LiveCalculatedTotal() {
		return $this->CalculatedTotal;
	}


// ######################################## ***  10. Type Functions (IsChargeable, IsDeductable, IsNoChange, IsRemoved)

	/**
	 * should be extended if it is true in child class
	 * @return boolean
	 */
	public function IsChargeable() {
		return $this->CalculatedTotal > 0;
	}
	/**
	 * should be extended if it is true in child class
	 * @return boolean
	 */
	public function IsDeductable() {
		return $this->CalculatedTotal < 0;
	}

	/**
	 * should be extended if it is true in child class
	 * @return boolean
	 */
	public function IsNoChange() {
		return $this->CalculatedTotal == 0 ;
	}

	/**
	 * should be extended if it is true in child class
	 * Needs to be a public class
	 * @return boolean
	 */
	public function IsRemoved() {
		return $this->HasBeenRemoved;
	}



// ######################################## ***  11. standard database related functions (e.g. onBeforeWrite, onAfterWrite, etc...)

	/**
	 */
	function onBeforeWrite() {
		parent::onBeforeWrite();
	}

	function onBeforeRemove(){
		//you can add more stuff here in sub classes
	}

	function onAfterRemove(){
		//you can add more stuff here in sub classes
	}


	function requireDefaultRecords(){
		parent::requireDefaultRecords();
		$arrayOfModifiers = Order::get_modifiers();
		if(is_array($arrayOfModifiers) && count($arrayOfModifiers)) {
			if(in_array($this->ClassName, $arrayOfModifiers)) {
				$obj = DataObject::get_one("OrderModifier_Descriptor", "\"ModifierClassName\" = '".$this->ClassName."'");
				if(!$obj) {
					$obj = new OrderModifier_Descriptor();
					$obj->ModifierClassName = $this->ClassName;
					$obj->Heading = $this->i18n_singular_name();
					$obj->write();
					DB::alteration_message("Creating description for ".$this->ClassName, "created");
				}
			}
			elseif($obj) {
				$obj->delete();
				$obj->destroy();
				DB::alteration_message("Deleting description for ".$this->ClassName, "deleted");
			}
		}
	}


// ######################################## ***  11. AJAX related functions

	/**
	 *
	 * @param Array $js javascript array
	 * @return Array for AJAX JSON
	 **/
	function updateForAjax(array &$js) {
		$tableTitle = $this->TableTitle();
		$cartTitle = $this->CartTitle();
		//TableValue is a database value, but CartValue is not... Important difference.
		$tableValue = DBField::create('Currency',$this->TableValue)->Nice();
		$cartValue = DBField::create('Currency',$this->CartValue())->Nice();
		if($this->HideInAjaxUpdate()) {
			$js[] = array('id' => $this->TableID(), 'parameter' => 'hide', 'value' => 1);
		}
		else {
			$js[] = array('id' => $this->TableTitleID(), 'parameter' => 'innerHTML', 'value' => $tableTitle);
			$js[] = array('id' => $this->CartTitleID(), 'parameter' => 'innerHTML', 'value' => $cartTitle);
			$js[] = array('id' => $this->TableTotalID(), 'parameter' => 'innerHTML', 'value' => $tableValue);
			$js[] = array('id' => $this->CartTotalID(), 'parameter' => 'innerHTML', 'value' => $cartValue);
			$js[] = array('id' => $this->TableID(), 'parameter' => 'hide', 'value' => 0);
		}
	}


// ######################################## ***  12. debug functions

	/**
	 * Debug helper method.
	 */
	public function debug() {
		return "
			<h2>".$this->ClassName."</h2>
			<h3>OrderModifier class details</h3>
			<p>
				<b>ID : </b>".$this->ID."<br/>
				<b>Order ID : </b>".$this->OrderID."<br/>
				<b>Calculation Value : </b>".$this->CalculatedTotal."<br/>
				<b>Table Title: </b>".$this->TableTitle()."<br/>
				<b>Table Value: </b>".$this->TableValue."<br/>
				<b>Cart Value: </b>".$this->CartTitle()."<br/>
				<b>Cart Title: </b>".$this->CartValue()."<br/>
			</p>";
	}

}


class OrderModifier_Descriptor extends DataObject {

	static $db = array(
		"ModifierClassName" => "Varchar(100)",
		"Heading" => "Varchar",
		"Description" => "Text"
	);

	static $has_one = array(
		"Link" => "SiteTree"
	);

	//database related settings
	static $indexes = array(
		"ModifierClassName" => true
	);

	public static $searchable_fields = array(
		"Heading" => "PartialMatchFilter",
		"Description" => "PartialMatchFilter"
	);

	public static $field_labels = array(
		"ModifierClassName" => "Code"
	);

	public static $summary_fields = array(
		"RealName" => "Code",
		"Heading" => "Heading",
		"Description" => "Description"
	);

	public static $casting = array(
		"RealName" => "Varchar"
	);

	public static $singular_name = "Order Extra Description";
		function i18n_singular_name() { return _t("OrderModifier.ORDEREXTRADESCRIPTION", "Order Extra Description");}

	public static $plural_name = "Order Extra Descriptions";
		function i18n_plural_name() { return _t("OrderModifier.ORDEREXTRADESCRIPTIONS", "Order Extra Descriptions");}

	static $can_create = false;

	public function canCreate($member = null) {return false;}

	public function canView($member = null) {return true;}

	public function canEdit($member = null) {return true;}

	public function canDelete($member = null) {return false;}

	function getCMSFields(){
		$fields = parent::getCMSFields();
		$fields->replaceField("ModifierClassName", new ReadonlyField("RealName", "Name"));
		$fields->replaceField("LinkID", new TreeDropdownField("LinkID", "More info link (optional)", "SiteTree"));
		$fields->replaceField("Description", new TextareaField("Description", "Description", 3));
		return $fields;
	}

	function RealName(){
		return $this->getRealName();
	}

	function getRealName(){
		if(class_exists($this->ModifierClassName)) {
			$obj = DataObject::get_one($this->ModifierClassName);
			return $obj->i18n_singular_name(). " (".$this->ModifierClassName.")";
		}
		return $this->ModifierClassName;
	}

}
