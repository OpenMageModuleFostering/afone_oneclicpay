<?php
/**
 * Main payment model
 *
 * @category    Model
 * @package     Afone_Oneclicpay
 * @author      Afone
 * @license     GPL http://opensource.org/licenses/gpl-license.php
 */
class Afone_Oneclicpay_Model_Standard extends Mage_Payment_Model_Method_Abstract {
	protected $_code = 'oneclicpay';
	
	protected $_isInitializeNeeded      = true;
	protected $_canUseInternal          = true;
	protected $_canUseForMultishipping  = false;
	
	public function getOrderPlaceRedirectUrl() {
		return Mage::getUrl('oneclicpay/payment/redirect', array('_secure' => true));
	}
}
?>