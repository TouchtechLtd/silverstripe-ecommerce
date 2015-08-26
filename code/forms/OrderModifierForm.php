<?php

/**
 * @description: this class is the base class for modifier forms in the checkout form... we could do with more stuff here....
 *
 *
 * @authors: Nicolaas [at] Sunny Side Up .co.nz
 * @package: ecommerce
 * @sub-package: forms
 * @inspiration: Silverstripe Ltd, Jeremy
 **/

class OrderModifierForm extends Form {

	/**
	 * @var Order
	 */
	protected $order;

	/**
	 *NOTE: we semi-enforce using the OrderModifier_Controller here to deal with the submission of the OrderModifierForm
	 * You can use your own modifiers or an extension of OrderModifier_Controller by setting the first parameter (optionalController)
	 * to your own controller.
	 *
	 *@param $optionalController Controller
	 *@param $name String
	 *@param $fields FieldList
	 *@param $actions FieldList
	 *@param $validator SS_Validator
	 **/

	function __construct(
		Controller $optionalController = null,
		$name,
		FieldList $fields,
		FieldList $actions,
		Validator $optionalValidator = null
	){
		if(!$optionalController) {
			$controllerClassName = EcommerceConfig::get("OrderModifierForm", "controller_class");
			$optionalController = new $controllerClassName();
		}
		if(!$optionalValidator) {
			$validatorClassName = EcommerceConfig::get("OrderModifierForm", "validator_class");
			$optionalValidator = new $validatorClassName();
		}
		parent::__construct($optionalController, $name, $fields, $actions, $optionalValidator);

		//extension point
		$this->extend('updateFields', $fields); $this->setFields($fields);
		$this->extend('updateActions', $actions); $this->setActions($actions);
		$this->extend('updateValidator', $optionalValidator); $this->setValidator($optionalValidator);

		$this->setAttribute("autocomplete", "off");
		Requirements::themedCSS($this->ClassName, 'ecommerce');
		$this->addExtraClass($this->myLcFirst(ucwords($name)));
		Requirements::javascript(THIRDPARTY_DIR."/jquery-form/jquery.form.js");
		//add JS for the modifier - added in modifier
		$oldData = Session::get("FormInfo.{$this->FormName()}.data");
		if($oldData && (is_array($oldData) || is_object($oldData))) {
			$this->loadDataFrom($oldData);
		}
		$this->extend('updateOrderModifierForm', $this);
	}

	protected function myLcFirst($str){
		if(function_exists('lcfirst') === false) {
			function lcfirst($str) {
				$str[0] = strtolower($str[0]);
				return $str;
			}
		}
		else {
			return lcfirst($str);
		}
	}

	/**
	 *
	 * @param String $status
	 * @param String $message
	 */
	function redirect($status = "success", $message = ""){
		//return ShoppingCart::singleton()->addmessage($status, $message);
	}

	/**
	 * @param Array $data
	 * @param Form $form
	 * @param String $status
	 * @param String $message
	 * @return ShoppingCart Response (JSON / Redirect Back)
	 */
	function submit(Array $data, Form $form, $message = "order updated", $status = "good") {
		//to do - add other checks here...
		 return ShoppingCart::singleton()->setMessageAndReturn($message, $status);
	}

	/**
	 * saves the form into session
	 * @param Array $data - data from form.
	 */
	public function saveDataToSession(){
		$data = $this->getData();
		Session::set("FormInfo.{$this->FormName()}.data", $data);
	}


}



/**
 * This controller allows you to submit modifier forms from anywhere on the site,
 * Most likely this will be from the the cart / checkout page.
 */
class OrderModifierForm_Controller extends Controller{

	/**
	 *
	 * @var Order
	 */
	protected $currentOrder = null;

	/**
	 *
	 * @var Array
	 */
	private static $allowed_actions = array(
		'removemodifier'
	);


	/**
	 * sets virtual methods and order
	 */
	public function init() {
		parent::init();
		$this->currentOrder = ShoppingCart::current_order();
		$this->initVirtualMethods();
	}

	/**
	 * Inits the virtual methods from the name of the modifier forms to
	 * redirect the action method to the form class
	 */
	protected function initVirtualMethods() {
		if($this->currentOrder) {
			if($forms = $this->currentOrder->getModifierForms($this)) {
				foreach($forms as $form) {
					$this->addWrapperMethod($form->getName(), 'getOrderModifierForm');
					self::$allowed_actions[] = $form->getName(); // add all these forms to the list of allowed actions also
				}
			}
		}
	}

	/**
	 * Return a specific {@link OrderModifierForm} by it's name.
	 *
	 * @param string $name The name of the form to return
	 * @return Form
	 */
	protected function getOrderModifierForm($name) {
		if($this->currentOrder) {
			if($forms = $this->currentOrder->getModifierForms($this)) {
				foreach($forms as $form) {
					if($form->getName() == $name) return $form;
				}
			}
		}
	}

	/**
	 * @ToDO: check this method
	 * It looks like this: /$ClassName/$action/
	 * @return String
	 */
	function Link($action = null){
		$URLSegment = Config::inst()->get($this->class, "url_segment");
		if(!$URLSegment) {
			$URLSegment = $this->class;
		}
		return Controller::join_links(
			Director::BaseURL(),
			$URLSegment,
			$action
		);
	}

	function removemodifier(){
		//@TODO: See issue 149
	}

}



class OrderModifierForm_Validator extends RequiredFields{

	function php($data){
		$this->form->saveDataToSession();
		return parent::php($data);
	}

}
