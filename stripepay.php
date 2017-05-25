<?php
/**
 * @component Pay per Download component
 * @author Ratmil Torres
 * @copyright (C) Ratmil Torres
 * @license GNU/GPL http://www.gnu.org/copyleft/gpl.html
**/
defined( '_JEXEC' ) or
die( 'Direct Access to this location is not allowed.' );

// import the JPlugin class
jimport('joomla.event.plugin');

class plgPayperDownloadPlusStripePay extends JPlugin
{
	public function __construct(&$subject, $config = array())
    {
        parent::__construct($subject, $config);
	}
	
	public function onRenderPaymentForm($user, $license, $resource,
		$returnUrl, $thankyouUrl)
	{
		$public_key = $this->params->get('public_key', '');
		$customer_email = $this->params->get('customer_email', '');
		$user_id = 0;
		if($user)
			$user_id = $user->id;
		if($public_key && $customer_email)
		{
			$item_id = 0;
			$name = "";
			$amount = 0;
			$currency = "";
			$description = "";
			$type = "";
			$task = "";
			$download_id = 0;
			if($license || $resource)
			{
				if($resource)
				{
					$amount = $resource->resource_price;
					$currency = $resource->resource_price_currency;
					$item_id = $resource->resource_license_id;
					$name = $resource->resource_name;
					$download_id = $resource->download_id;
					if($resource->alternate_resource_description)
						$description = $resource->alternate_resource_description;
					else
						$description = $resource->resource_description;
					$task = "confirmres";
					$type = "resource";
				}
				else
				{
					$amount = $license->price;
					$currency = $license->currency_code;
					$item_id = $license->license_id;
					$name = $license->license_name;
					$description = $license->description;
					$task = "confirm";
					$type = "license";
				}
				$amount *= 100.00;
				$amount = (int)$amount;
				$returnBase64Coded =  base64_encode($returnUrl);
?>
<form action="index.php?option=com_payperdownload&amp;gateway=stripe&amp;task=<?php echo htmlentities($task);?>" method="POST">
  <script
    src="https://checkout.stripe.com/checkout.js" class="stripe-button"
    data-key="<?php echo htmlentities($public_key);?>"
    data-amount="<?php echo htmlentities($amount);?>"
    data-name="<?php echo htmlentities($name);?>"
    data-description="<?php echo htmlentities($description);?>"
    data-image="https://stripe.com/img/documentation/checkout/marketplace.png"
    data-locale="auto"
	data-currency="<?php echo htmlentities($currency);?>"
    data-zip-code="true">
  </script>
  <input type="hidden" name="item_id" value="<?php echo htmlentities($item_id);?>"/>
  <input type="hidden" name="item_type" value="<?php echo htmlentities($type);?>"/>
  <input type="hidden" name="user_id" value="<?php echo htmlentities($user_id);?>"/>
  <input type="hidden" name="amount" value="<?php echo htmlentities($amount);?>"/>
  <input type="hidden" name="currency" value="<?php echo htmlentities($currency);?>"/>
  <input type="hidden" name="r" value="<?php echo htmlentities($returnBase64Coded);?>"/>
  <input type="hidden" name="redirect" value="<?php echo htmlentities($returnBase64Coded);?>"/>
  <?php
  if($download_id)
  {
  ?>
   <input type="hidden" name="download_id" value="<?php echo (int)$download_id;?>"/>
  <?php
  }
  ?>
</form>
<?php		
			}
		}
	}
	
	public function onPaymentReceived($gateway, &$dealt, &$payed, 
		&$user_id, &$license_id, &$resource_id, &$transactionId,
		&$response, &$validate_response, &$status, &$amount, 
		&$tax, &$fee, &$currency)
	{
		if($gateway == "stripe")
		{
			$dealt = true;
			$payed = false;
			$amount = JRequest::getInt('amount');
			$currency = JRequest::getVar('currency');
			$user_id = JRequest::getInt("user_id");
			$item_type = JRequest::getVar("item_type");
			$item_id = JRequest::getInt("item_id");
			$download_id = JRequest::getInt("download_id");
			$license_id = 0;
			$resource_id = 0;
			$tax = 0;
			if($item_type == "resource")
				$resource_id = $item_id;
			else
				$license_id = $item_id;
			require_once JPATH_SITE . "/plugins/payperdownloadplus/stripepay/stripe/init.php";
			$secret_key = $this->params->get('secret_key', '');
			$customer_email = $this->params->get('customer_email', '');
			if($secret_key && $customer_email)
			{
				try
				{
					\Stripe\Stripe::setApiKey($secret_key);
					$token  = JRequest::getVar('stripeToken');
					$customer = \Stripe\Customer::create(array(
						'email' => $customer_email,
						  'source'  => $token
						));
					$charge = \Stripe\Charge::create(array(
					  'customer' => $customer->id,
					  'amount'   => $amount,
					  'currency' => $currency
					));
					$amount = $amount / 100.0;
					$payed = true;
					$transactionId = $charge->id;
					$fee = $charge->application_fee;
					$status = "COMPLETED";
					$validate_response = "VERIFIED";
					$response = var_export($charge, true);
					$payerEmail = JRequest::getVar("stripeEmail");
					if($download_id)
					{
						$session = JFactory::getSession();
						$transactions = $session->get("trans", array());
						$transactions["$transactionId"] = 
							array("download_id" => $download_id,
								  "payeremail" => $payerEmail
							);
						$session->set("trans", $transactions);
					}
				}
				catch (Exception $e) 
				{
					$status = "FAILED";
					$response = $e->getMessage();
				}	
			}
		}
	}
	
	public function onGetPayerEmail($transactionId, &$payer_email)
	{
		$session = JFactory::getSession();
		$transactions = $session->get("trans", array());
		if(isset($transactions[$transactionId]))
		{
			$payer_email = $transactions[$transactionId]["payeremail"];
			unset($transactions[$transactionId]);
			$session->set("trans", $transactions);
		}
	}
	
	public function onGetDownloadLinkId($transactionId, &$download_id)
	{
		$session = JFactory::getSession();
		$transactions = $session->get("trans", array());
		if(isset($transactions[$transactionId]))
		{
			$download_id = $transactions[$transactionId]["download_id"];
		}
	}

}
?>