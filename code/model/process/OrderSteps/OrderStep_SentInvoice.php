<?php


/**
 *
 * @authors: Nicolaas [at] Sunny Side Up .co.nz
 * @package: ecommerce
 * @sub-package: model
 * @inspiration: Silverstripe Ltd, Jeremy
 **/

class OrderStep_SentInvoice extends OrderStep implements OrderStepInterface  {

	/**
	 * @var String
	 */
	protected $emailClassName = "Order_InvoiceEmail";

	private static $db = array(
		"SendInvoiceToCustomer" => "Boolean"
	);

	private static $defaults = array(
		"CustomerCanEdit" => 0,
		"CustomerCanCancel" => 0,
		"CustomerCanPay" => 1,
		"Name" => "Send invoice",
		"Code" => "INVOICED",
		"ShowAsInProcessOrder" => 1,
		"SendInvoiceToCustomer" => 0
	);

	public function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->addFieldToTab("Root.Main", new HeaderField("ActuallySendTheInvoice", _t("OrderStep.ACTUALLYSENDTHEINVOICE", "Actually send the invoice? "), 3), "SendInvoiceToCustomer");
		return $fields;
	}

	/**
	 * can run step once order has been submitted.
	 * NOTE: must have a payment (even if it is a fake payment).
	 * The reason for this is if people pay straight away then they want to see the payment shown on their invoice.
	 * @param Order object
	 * @return Boolean - true if the current step is ready to be run...
	 **/
	 public function initStep(Order $order) {
		if( $order->IsSubmitted()) {
			return true;
		}
		return false;

	}

	/**
	 * send invoice to customer
	 * or in case this is not selected, it will send a message to the shop admin only
	 * The latter is useful in case the payment does not go through (and no receipt is received).
	 * @param DataObject $order Order
	 * @return Boolean
	 **/
	public function doStep(Order $order) {
		$subject = $this->EmailSubject;
		$message = "";
		if($this->SendInvoiceToCustomer){
			if(!$this->hasBeenSent($order)) {
				return $order->sendEmail($subject, $message, $resend = false, $adminOnly = false, $this->getEmailClassName());
			}
		}
		else {
			if(!$this->hasBeenSent($order)) {
				//looks like we are sending an error, but we are actually just sending a message to admin
				$message = _t("OrderStep.THISMESSAGENOTSENTTOCUSTOMER", "NOTE: This message was not sent to the customer.")."<br /><br /><br /><br />".$message;
				return $order->sendAdminNotification($subject, $message);
			}
		}
		return true;
	}

	/**
	 * can do next step once the invoice has been sent or in case the invoice does not need to be sent.
	 * @param Order $order
	 * @return OrderStep | Null (next step OrderStep object)
	 **/
	public function nextStep(Order $order) {
		if(!$this->SendInvoiceToCustomer || $this->hasBeenSent($order)) {
			return parent::nextStep($order);
		}
		return null;
	}

	/**
	 * Allows the opportunity for the Order Step to add any fields to Order::getCMSFields
	 *@param FieldList $fields
	 *@param Order $order
	 *@return FieldList
	 **/
	function addOrderStepFields(FieldList $fields, Order $order) {
		$fields = parent::addOrderStepFields($fields, $order);
		$title = _t("OrderStep.CANADDGENERALLOG", " ... if you want to make some notes about this step then do this here...");
		$fields->addFieldToTab("Root.Next", $order->getOrderStatusLogsTableField("OrderStatusLog", $title),"ActionNextStepManually");
		return $fields;
	}

	/**
	 * For some ordersteps this returns true...
	 * @return Boolean
	 **/
	protected function hasCustomerMessage() {
		return $this->SendInvoiceToCustomer;
	}

	/**
	 * Explains the current order step.
	 * @return String
	 */
	protected function myDescription(){
		return _t("OrderStep.SENTINVOICE_DESCRIPTION", "Invoice gets sent to the customer via e-mail. In many cases, it is better to only send a receipt and sent the invoice to the shop admin only so that they know an order is coming, while the customer only sees a receipt which shows payment as well as the order itself.");
	}
}

