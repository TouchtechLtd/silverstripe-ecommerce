<span id="{$Name}_Holder" class="creditCardField">
	<input name="{$Name}[0]" id="{$Form.Name}_CreditCardField1" class="ecommercecreditcard" type="text" value="{$ValueOne}" required="required" autocomplete="off" size="4" pattern="[0-9]{4}"  $TabIndexHTML(0) maxlength="4" onkeydown="return EcomCreditCardValidation.NumbersOnly(event)" onkeyup="EcomCreditCardValidation.AutoTab(this, document.getElementById('{$Form.Name}_CreditCardField2'));" />
	<span class="enDash">-</span>
	<input name="{$Name}[1]" id="{$Form.Name}_CreditCardField2" class="ecommercecreditcard" type="text" value="{$ValueTwo}" required="required" autocomplete="off" size="4" pattern="[0-9]{4}"  $TabIndexHTML(1) maxlength="4" onkeydown="return EcomCreditCardValidation.NumbersOnly(event)" onkeyup="EcomCreditCardValidation.AutoTab(this, document.getElementById('{$Form.Name}_CreditCardField3'));"/>
	<span class="enDash">-</span>
	<input name="{$Name}[2]" id="{$Form.Name}_CreditCardField3" class="ecommercecreditcard" type="text" value="{$ValueThree}" required="required" autocomplete="off" size="4" pattern="[0-9]{4}"  $TabIndexHTML(2) maxlength="4" onkeydown="return EcomCreditCardValidation.NumbersOnly(event)" onkeyup="EcomCreditCardValidation.AutoTab(this, document.getElementById('{$Form.Name}_CreditCardField4'));" />
	<span class="enDash">-</span>
	<input name="{$Name}[3]" id="{$Form.Name}_CreditCardField4" class="ecommercecreditcard" type="text" value="{$ValueFour}" required="required" autocomplete="off" size="4" pattern="[0-9]{1,4}"  $TabIndexHTML(3) maxlength="4"onkeydown="return EcomCreditCardValidation.NumbersOnly(event)" />
</span>
