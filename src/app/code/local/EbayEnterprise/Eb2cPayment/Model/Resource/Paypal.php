<?php
class EbayEnterprise_Eb2cPayment_Model_Resource_Paypal extends Mage_Core_Model_Resource_Db_Abstract
{
	public function _construct()
	{
		$this->_init('eb2cpayment/paypal', 'paypal_id');
	}
	/**
	 * Load paypal by quote_id
	 * @throws Mage_Core_Exception
	 * @param EbayEnterprise_Eb2cPayment_Model_Paypal $paypal
	 * @param int $quoteId
	 * @return EbayEnterprise_Eb2cPayment_Model_Mysql4_Paypal
	 */
	public function loadByQuoteId(EbayEnterprise_Eb2cPayment_Model_Paypal $paypal, $quoteId)
	{
		$this->load($paypal, $quoteId, 'quote_id');
		return $this;
	}
}