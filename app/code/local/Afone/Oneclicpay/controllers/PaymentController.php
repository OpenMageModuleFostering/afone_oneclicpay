<?php
/**
 * Main payment controller
 * Documentation : http://docs.oneclicpay.com/
 *
 * @category    Controller
 * @package     Afone_Oneclicpay
 * @author      Afone
 * @license     GPL http://opensource.org/licenses/gpl-license.php
 */
class Afone_Oneclicpay_PaymentController extends Mage_Core_Controller_Front_Action {

	/**
	 * Redirects the user to the success page
	 */
	public function successAction() {
		$this->_redirect('checkout/onepage/success');
	}

	/**
	 * Redirects the user to the error page
	 */
	public function errorAction() {
		$this->_redirect('checkout/onepage/failure');	
	}

	/*
	 * The redirect action is triggered when someone places an order
	 */
	public function redirectAction() {
		
		$order = new Mage_Sales_Model_Order();
		$order_id = Mage::getSingleton( 'checkout/session' )->getLastRealOrderId();
		$order->loadByIncrementId( $order_id );
		
		$oneclicpay['montant'] = number_format( $order->base_grand_total, 2 , '.', '');
		$oneclicpay['idTPE'] = Mage::getStoreConfig( 'payment/oneclicpay/tpe_no' );
		$oneclicpay['idTransaction'] = $order_id;
		$oneclicpay['devise'] = Mage::getStoreConfig( 'payment/oneclicpay/devise' );
		$oneclicpay['lang'] = Mage::getStoreConfig( 'payment/oneclicpay/langue' );
		$oneclicpay['nom_produit'] = Mage::app()->getStore()->getName();
		$oneclicpay['source'] = $_SERVER['SERVER_NAME'];
		$oneclicpay['urlRetourOK'] = Mage::getUrl( 'oneclicpay/payment/success' , array('_secure' => true) );
		$oneclicpay['urlRetourNOK'] = Mage::getUrl( 'oneclicpay/payment/error' , array('_secure' => true));
		$oneclicpay['urlIPN'] = Mage::getUrl( 'oneclicpay/payment/ipn' , array('_secure' => true) );
		$oneclicpay['key'] = Mage::getStoreConfig( 'payment/oneclicpay/secret_key' );
		
		// Encoding
		$oneclicpayWithKey = base64_encode(implode("|", $oneclicpay));
		unset($oneclicpay['key']);
		$oneclicpay['sec'] = hash("sha512",$oneclicpayWithKey);

		// Generate Form
		$form = "";
        foreach ($oneclicpay as $key => $value) {
			$form .= "<input type='hidden' name='$key' value='$value'/>";
        }
        $oneclicpay_config['form'] = $form;

        $oneclicpay_config['url-config'] = Mage::getStoreConfig( 'payment/oneclicpay/url_gateway_config' );
        if ($oneclicpay_config['url-config']=="HOMOLOGATION")
        {
        	$oneclicpay_config['form-action'] = Mage::getStoreConfig( 'payment/oneclicpay/url_gateway_homologation' );
        }
        else
        {
        	$oneclicpay_config['form-action'] = Mage::getStoreConfig( 'payment/oneclicpay/url_gateway_production' );
        }
        
        // Save information
        Mage::register( 'oneclicpay', $oneclicpay_config );

        // Load and render the layout
		$this->loadLayout();
        $block = $this->getLayout()->createBlock('Mage_Core_Block_Template','oneclicpay',array('template' => 'oneclicpay/redirect.phtml'));
		$this->getLayout()->getBlock('content')->append($block);
        $this->renderLayout();
	}
	
	/*
	 * The ipn action is triggered when your gateway sends back a response after processing the customer's payment
	 */
	public function ipnAction() {
		Mage::log("Appel de l'ipn ...", Zend_Log::DEBUG);
		Mage::log($_REQUEST, Zend_Log::DEBUG);

	
		if($this->getRequest()->isPost()) 
		{
			if (!$this->validSec($_REQUEST, Mage::getStoreConfig( 'payment/oneclicpay/secret_key' )))
			{
				Mage::log("Oneclicpay : Erreur lors de la validation des informations transmises !", Zend_Log::ERR);
				$this->cancelAction();
				header("Status: 400 Bad Request", false, 400);
				exit();
			}

			$validated="";
			if (isset($_REQUEST['result']))
				$validated = $_REQUEST['result'];


			if($validated == "OK") {
				// Payment was successful, so update the order's state, send order email and move to the success page
				$orderId="";
				if (isset($_REQUEST['idTransaction']))
					$orderId = $_REQUEST['idTransaction'];
				
				$order = Mage::getModel('sales/order');
				$order->loadByIncrementId($orderId);
				$order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true, 'Gateway has authorized the payment.');
				
				$order->sendNewOrderEmail();
				$order->setEmailSent(true);
				
				$order->save();
			
				Mage::getSingleton('checkout/session')->unsQuoteId();
			}
			else 
			{
				// There is a problem in the response we got
				Mage::log("Oneclicpay : Paiement refusé !", Zend_Log::DEBUG);
				$this->cancelAction();
				exit();
			}
		}
		else
		{
			Mage::log("Oneclicpay : Aucun paramètres POST reçu !", Zend_Log::ERR);
			header("Status: 400 Bad Request", false, 400);
			exit();
		}
	}
	
	/*
	 * The cancel action is triggered when an order is to be cancelled
	 */
	public function cancelAction() {
        if (Mage::getSingleton('checkout/session')->getLastRealOrderId()) {
            $order = Mage::getModel('sales/order')->loadByIncrementId(Mage::getSingleton('checkout/session')->getLastRealOrderId());
            if($order->getId()) {
				// Flag the order as 'cancelled' and save it
				$order->cancel()->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, 'Gateway has declined the payment.')->save();
			}
        }
	}

	/*
	 * Secret Validation
	 */
	private function validSec($values, $secret_key){
        if (isset($values['sec']) && $values['sec'] != "")
        {
                $sec = $values['sec'];
                unset($values['sec']);
                return strtoupper(hash("sha512", base64_encode(implode("|",$values)."|".$secret_key))) == strtoupper($sec);
        }
        else
        {
                return false;
        }
}

}