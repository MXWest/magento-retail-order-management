<?php
/**
 * Copyright (c) 2013-2014 eBay Enterprise, Inc.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @copyright   Copyright (c) 2013-2014 eBay Enterprise, Inc. (http://www.ebayenterprise.com/)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use \eBayEnterprise\RetailOrderManagement\Payload\Order\IOrderCreateRequest;

/**
 * Adds Tax information to an OrderItem payload and its sub payloads.
 */
class EbayEnterprise_Eb2cTax_Model_Order_Create_Order
{
	/** @var EbayEnterprise_MageLog_Helper_Data */
	protected $_logger;
	/** @var EbayEnterprise_Eb2cTax_Overrides_Model_Calculation */
	protected $_calculator;
	/** @var boolean */
	protected $_hasErrors;

	public function __construct(array $args=array())
	{
		list($this->_logger, $this->_calculator) =
			$this->_checkTypes(
				$this->_nullCoalesce('logger', $args, Mage::helper('ebayenterprise_magelog')),
				$this->_nullCoalesce('calculator', $args, Mage::getModel('tax/calculation'))
			);
	}

	protected function _checkTypes(
		EbayEnterprise_MageLog_Helper_Data $logger,
		EbayEnterprise_Eb2cTax_Overrides_Model_Calculation $calculator
	) {
		return [$logger, $calculator];
	}

	/**
	 * set the tax header error flag on the order payload
	 * @param  IOrderCreateRequest
	 * @param  Mage_Sales_Model_Order
	 * @return self
	 */
	public function setTaxHeaderErrorFlag(
		IOrderCreateRequest            $orderPayload,
		Mage_Sales_Model_Order         $order
	) {
		foreach ($order->getAddressesCollection() as $address) {
			$this->_checkForErrors($address);
			if ($this->_hasErrors ) {
				$orderPayload->setTaxHasErrors(true);
				break;
			}
		}
		return $this;
	}

	/**
	 * check if there are any errors in the taxes.
	 * @param  Mage_Sales_Model_Order_Address
	 */
	protected function _checkForErrors(Mage_Sales_Model_Order_Address $address)
	{
		$responseItems = $this->_calculator->getTaxResponse()->getResponseItems();
		$responseItems = isset($responseItems[$address->getQuoteAddressId()]) ?
			$responseItems[$address->getQuoteAddressId()] : [];
		foreach ($responseItems as $responseItem) {
			$this->_checkItemTaxes($responseItem);
		}
	}

	/**
	 * scan through the an item's taxes, if errors are detected, set a flag.
	 * @param  EbayEnterprise_Eb2cTax_Model_Response_Orderitem
	 */
	protected function _checkItemTaxes(EbayEnterprise_Eb2cTax_Model_Response_Orderitem $responseItem)
	{
		foreach ($responseItem->getTaxQuotes() as $taxQuote) {
			if ($taxQuote->getCode() === 'CalculationError') {
				$this->_hasErrors = true;
				return;
			}
		}
	}

	protected function _nullCoalesce($key, array $ar, $default)
	{
		return isset($ar[$key]) ? $ar[$key] : $default;
	}
}
