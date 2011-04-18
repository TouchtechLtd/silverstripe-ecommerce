<?php


/**
 * @description: this class is the base class for forms in the checkout form... we could do with more stuff here....
 *
 * @see OrderModifier
 * @to do: explain how to set Session Message ($form->SessionMessage("ab"));
 * @package ecommerce
 * @authors: Silverstripe, Jeremy, Nicolaas
 **/
class OrderModifierForm extends Form {

	protected $order;

	function redirect($status = "success", $message = ""){
		return ShoppingCart::return_message($status, $message);
	}




}

