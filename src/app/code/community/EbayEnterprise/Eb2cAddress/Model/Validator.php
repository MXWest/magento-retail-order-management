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

/**
 * Handles validating address via the EB2C address validation service,
 * storing and retrieving address suggestions.
 */
class EbayEnterprise_Eb2cAddress_Model_Validator
{
	const SESSION_KEY                  = 'address_validation_addresses';
	const SUGGESTIONS_ERROR_MESSAGE    = 'EbayEnterprise_Eb2cAddress_Suggestions_Error_Message';
	const NO_SUGGESTIONS_ERROR_MESSAGE = 'EbayEnterprise_Eb2cAddress_No_Suggestions_Error_Message';

	/**
	 * Get the session object to use for storing address information.
	 * Currently will use the customer session but may be swapped out later.
	 * @return Mage_Core_Model_Session_Abstract
	 */
	protected function _getSession()
	{
		return Mage::getSingleton('customer/session');
	}

	/**
	 * If a selection has been made, update the address object with data
	 * from the stashed address. This will include copying over the
	 * has_been_validated flag, which will bypass re-validating the address.
	 * @return Mage_Customer_Model_Address_Abstract
	 */
	protected function _updateAddressWithSelection(Mage_Customer_Model_Address_Abstract $address)
	{
		$key = Mage::app()
			->getRequest()
			->getPost(EbayEnterprise_Eb2cAddress_Block_Suggestions::SUGGESTION_INPUT_NAME);
		$suggestionAddress = $this->getStashedAddressByKey($key);
		if ($suggestionAddress) {
			$address->addData($suggestionAddress->getData());
		}
		return $address;
	}

	/**
	 * Determine if the address has already been validated.
	 * Based upon:
	 * - The address object having a 'has_been_validated' property which is true
	 * - Matches the 'validated_address' object stashed in the session
	 * @param Mage_Customer_Model_Address_Abstract
	 * @return bool
	 */
	protected function _hasAddressBeenValidated(Mage_Customer_Model_Address_Abstract $address)
	{
		// flag set on addresses that are returned from the Address Validation response
		if ($address->getHasBeenValidated()) {
			return true;
		}
		$validatedAddress;
		// when the address is used as a shipping address, must ensure that the validated
		// address was validated as a shipping address
		if ($this->_isAddressUsedForShipping($address)) {
			$validatedAddress = $this->getValidatedAddress(Mage_Customer_Model_Address::TYPE_SHIPPING);
		} else {
			$validatedAddress = $this->getValidatedAddress($address->getAddressType());
		}
		// ensure - a validated address of this type exists
		// it was actually validated/validation wasn't skipped
		// and it matches the current address
		return $validatedAddress && $this->_compareAddressToValidatedAddress($address, $validatedAddress);
	}

	/**
	 * When a checkout quote exists, get the checkout "method" being used for checkout.
	 * Should be one of the Checkout type consts defined by Mage_Checkout_Model_Type_Onepage
	 * @return string
	 */
	protected function _getCheckoutMethod()
	{
		return Mage::getSingleton('checkout/type_onepage')->getCheckoutMethod();
	}

	/**
	 * Determine if the address is for use in checkout, specifically, Onepage Checkout
	 * @param Mage_Customer_Model_Address_Abstract $address
	 * @return bool
	 */
	protected function _isCheckoutAddress(Mage_Customer_Model_Address_Abstract $address)
	{
		return $address->hasData('quote_id');
	}

	/**
	 * When dealing with checkout addresses, check if the current quote is virtual.
	 * @return bool
	 */
	protected function _isVirtualOrder()
	{
		if ($quote = Mage::getSingleton('checkout/session')->getQuote()) {
			return $quote->isVirtual();
		}
		return false;
	}

	/**
	 * Is the address a billing address.
	 * @param Mage_Customer_Model_Address_Abstract $address
	 * @return bool
	 */
	protected function _isBillingAddress(Mage_Customer_Model_Address_Abstract $address)
	{
		return $address->getAddressType() === Mage_Customer_Model_Address::TYPE_BILLING;
	}

	/**
	 * Determine if the address should be used as a shipping address.
	 * For billing address used as a shipping address, this will only
	 * reliably work when the address is submitted during onepage checkout
	 * as the only way to determine this is via the POST data submitted with the address.
	 * @param Mage_Customer_Model_Address_Abstract $address
	 * @return bool
	 */
	protected function _isAddressUsedForShipping(Mage_Customer_Model_Address_Abstract $address)
	{
		// obviously, when the address type is shipping, it's a shipping address
		if ($address->getAddressType() === Mage_Customer_Model_Address::TYPE_SHIPPING) {
			return true;
		}

		// when address type is not a shipping address
		// only other way it could be a shipping address is during onepage checkout
		// billing address, in which case a 'billing[use_for_shipping]' field will be
		// submitted with the address.
		$data = Mage::app()->getRequest()->getPost('billing', array());
		$useForShipping = isset($data['use_for_shipping']) && $data['use_for_shipping'];
		return $useForShipping;
	}

	/**
	 * Determine if the address is to be used as a billing address only and
	 * will not be saved in the address book.
	 * Only applies to Onepage Checkout
	 * @param Mage_Customer_Model_Address_Abstract $address
	 * @return bool
	 */
	protected function _isAddressBillingOnly(Mage_Customer_Model_Address_Abstract $address)
	{
		return $this->_isBillingAddress($address) && !$this->_isAddressUsedForShipping($address);
	}
	/**
	 * Determine if the address is to be saved in the address book as part of
	 * onepage checkout.
	 * @param Mage_Customer_Model_Address_Abstract $address
	 * @return bool
	 */
	protected function _isAddressBeingSaved()
	{
		$request = Mage::app()->getRequest();
		// get billing post data or shipping post data or emtpy array
		$data = $request->getPost('billing') ?: $request->getPost('shipping', array());
		// was the "save_in_address_book" checkbox submitted
		$postFlag = isset($data['save_in_address_book']) && $data['save_in_address_book'];

		// during checkout, the only two "types" of checkout that would actually allow
		// saving addresses in the address book are METHOD_REGISTER and METHOD_CUSTOMER
		$checkoutMethod = $this->_getCheckoutMethod();
		$canSaveAddressesInCheckout = $checkoutMethod === Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER ||
			$checkoutMethod === Mage_Checkout_Model_Type_Onepage::METHOD_CUSTOMER;
		return $postFlag && $canSaveAddressesInCheckout;
	}

	/**
	 * Determine if the address is from the customers address book or is a new address
	 * @param Mage_Customer_Model_Address_Abstract $address
	 * @return bool
	 */
	protected function _isAddressFromAddressBook(Mage_Customer_Model_Address_Abstract $address)
	{
		return $address->getId() && $address->getCustomerId() && $address->getCustomerAddressId();
	}

	/**
	 * Determine if an address needs to be validated
	 * Some conditions, like an address being saved in the address book,
	 * always require validation.
	 * Others conditions, like using an address for billing address only
	 * or being from the address book, indicate that validation is not required.
	 * @param Mage_Customer_Model_Address_Abstract
	 * @return bool
	 */
	public function shouldValidateAddress(Mage_Customer_Model_Address_Abstract $address)
	{
		$log = Mage::helper('ebayenterprise_magelog');
		if ($this->_hasAddressBeenValidated($address)) {
			$log->logDebug('[%s] No validation - already validated', array(__CLASS__));
			return false;
		}
		if ($this->_isCheckoutAddress($address)) {
			if ($this->_isAddressFromAddressBook($address)) {
				$log->logDebug('[%s] No validation - from address book', array(__CLASS__));
				return false;
			}
			if ($this->_isAddressBeingSaved()) {
				$log->logDebug('[%s] Require validation - saving address in address book', array(__CLASS__));
				return true;
			}
			if ($this->_isVirtualOrder()) {
				$log->logDebug('[%s] No validation - virtual order', array(__CLASS__));
				return false;
			}
			if ($this->_isAddressBillingOnly($address)) {
				$log->logDebug('[%s] No validation - billing only', array(__CLASS__));
				return false;
			}
			if ($this->_isMissingRequiredFields($address)) {
				$log->logDebug('[%s] No validation - missing required fields', array(__CLASS__));
				return false;
			}
		}
		return true;
	}

	/**
	 * Perform the call to EB2C and return the Address Validation Response model
	 * @param Mage_Customer_Model_Address_Abstract $address
	 * @return EbayEnterprise_Eb2cAddress_Model_Validation_Response|null
	 */
	protected function _makeRequestForAddress(Mage_Customer_Model_Address_Abstract $address)
	{
		$cfg = Mage::helper('eb2caddress')->getConfigModel();
		$xsd = $cfg->xsdFileAddressValidation;
		$uri = Mage::helper('eb2ccore')->getApiUri(
			EbayEnterprise_Eb2cAddress_Model_Validation_Request::API_SERVICE,
			EbayEnterprise_Eb2cAddress_Model_Validation_Request::API_OPERATION
		);
		$msg = Mage::getModel('eb2caddress/validation_request')->setAddress($address)->getMessage();
		$log = Mage::helper('ebayenterprise_magelog');
		$apiResponse = Mage::getModel('eb2ccore/api')->request($msg, $xsd, $uri);
		if (isset($apiResponse) && trim($apiResponse)) {
			return Mage::getModel('eb2caddress/validation_response', array('message' => $apiResponse));
		}
		$log->logWarn('[%s] Address validation service returned empty response.', array(__CLASS__));
		return null;
	}
	/**
	 * Validate an address via the EB2C Address Validation service.
	 * Calls the EB2C API and feeds the results into a response model.
	 * Will also ensure that the supplied address is populated with
	 * the response from EB2C and suggested addresses are stashed in the session
	 * for later use.
	 * @param Mage_Customer_Model_Address_Abstract $address
	 * @return string the error message generated in validation
	 */
	public function validateAddress(Mage_Customer_Model_Address_Abstract $address, $area=null)
	{
		$response        = null;
		$errorMessage    = null;
		$address         = $this->_updateAddressWithSelection($address);
		$adminValidation = false;

		if ($area === Mage_Core_Model_App_Area::AREA_ADMINHTML
				&& !$this->_isBillingAddress($address)
				&& !$this->_hasAddressBeenValidated($address))
		{
			Mage::helper('ebayenterprise_magelog')->logDebug('[%s] Admin Area Address Validation', array(__CLASS__));
			$adminValidation = true;
		}

		if ($adminValidation || $this->shouldValidateAddress($address)) {
			$this->clearSessionAddresses();
			$response = $this->_processRequest($address, $errorMessage);
		}
		$this->_updateSession($address, $response);
		return $errorMessage;
	}

	/**
	 * To reduce Cyclomatic Complexity error, this was broken out:
	 */
	protected function _processRequest($address, &$errorMessage)
	{
		$response = null;
		if ($response = $this->_makeRequestForAddress($address)) {
			// copy over validated address data
			if ($response->isAddressValid()) {
				$address->addData($response->getValidAddress()->getData());
			} else {
				$address->addData($response->getOriginalAddress()->getData());
				if ($address->getSameAsBilling()) {
					$address->setSameAsBilling(false);
				}
				$errorMessage = '';
				if ($response->hasAddressSuggestions()) {
					$errorMessage = Mage::helper('eb2caddress')->__(self::SUGGESTIONS_ERROR_MESSAGE);
				} else {
					$errorMessage = Mage::helper('eb2caddress')->__(self::NO_SUGGESTIONS_ERROR_MESSAGE);
				}
			}
		}
		return $response;
	}

	/**
	 * Compare a validated address to a potentially unvalidated address.
	 * The validated address should contain only the data that gets validated by
	 * EB2C, e.g. an address object returned by $this->_extractValidatedAddressData.
	 * @param Mage_Customer_Model_Address_Abstract $address
	 * @param Mage_Customer_Model_Address_Abstract $validatedAddress
	 * @return bool - true if they match, false if not
	 */
	protected function _compareAddressToValidatedAddress(
		Mage_Customer_Model_Address_Abstract $address,
		Mage_Customer_Model_Address_Abstract $validatedAddress
	)
	{
		$validatedData = $validatedAddress->getData();
		foreach ($validatedData as $key => $value) {
			// skip a few keys we don't care about when comparing the addresses
			if ($key === 'address_type') {
				continue;
			}
			if ((string) $address->getData($key) !== (string) $value) {
				return false;
			}
		}
		return !empty($validatedData);
	}

	/**
	 * Extract only the data from the addres that gets validated.
	 * The extracted data can be compared to the data in an existing
	 * @param Mage_Customer_Model_Address_Abstract $address
	 * @return Mage_Customer_Model_Address_Abstract - an address object containing only the data that gets validated by EB2c
	 */
	protected function _extractValidatedAddressData(Mage_Customer_Model_Address_Abstract $address)
	{
		$validatedAddress = Mage::getModel('customer/address')->setData(array(
			'street'       => $address->getData('street'),
			'city'         => $address->getCity(),
			'region_id'    => $address->getRegionId(),
			'country_id'   => $address->getCountryId(),
			'postcode'     => $address->getPostcode(),
			'address_type' => $address->getAddressType(),
		));
		return $validatedAddress;
	}

	/**
	 * Copy over address name data from the source to the dest address.
	 * @param Mage_Customer_Model_Address_Abstract $dest
	 * @param Mage_Customer_Model_Address_Abstract $source
	 * @return EbayEnterprise_Eb2cAddress_Model_Validator $this
	 */
	protected function _copyAddressName(
		Mage_Customer_Model_Address_Abstract $dest,
		Mage_Customer_Model_Address_Abstract $source
	)
	{
		$dest->addData(array(
			'prefix'     => $source->getPrefix(),
			'firstname'  => $source->getFirstname(),
			'middlename' => $source->getMiddlename(),
			'lastname'   => $source->getLastname(),
			'suffix'     => $source->getSuffix()
		));
		return $this;
	}

	/**
	 * Store the necessary addresses and address data in the session.
	 * Address are stored in a EbayEnterprise_Eb2cAddress_Model_Suggestion_Group.
	 * Addresses get merged with the submitted address to fill in any
	 * gaps between what the user gives us and what EB2C returns (like name and phone).
	 * @param Mage_Customer_Model_Address_Abstract $address
	 * @param EbayEnterprise_Eb2cAddress_Model_Validation_Response $response
	 * @return EbayEnterprise_Eb2cAddress_Model_Validator $this
	 */
	protected function _updateSession(
		Mage_Customer_Model_Address_Abstract $requestAddress,
		$response
	)
	{
		$addressCollection = $this->getAddressCollection();

		if ($response) {
			$originalAddress = $response->getOriginalAddress();
			$originalAddress->setStashKey('original_address');
			$this->_copyAddressName($originalAddress, $requestAddress);
			$addressCollection->setOriginalAddress($originalAddress);

			$suggestions = $response->getAddressSuggestions();
			foreach ($suggestions as $idx => $suggestion) {
				$suggestion->setStashKey('suggested_addresses/' . $idx);
				$this->_copyAddressName($suggestion, $requestAddress);
			}
			$addressCollection->setSuggestedAddresses($suggestions);

			$addressCollection->setResponseMessage($response);
			$addressCollection->setHasFreshSuggestions(true);
		} else {
			$addressCollection->unsOriginalAddress();
			$addressCollection->unsSuggestedAddresses();
			$addressCollection->unsResponseMessage();
			$addressCollection->unsHasFreshSuggestions();
		}

		$validationAddressExtract = $this->_extractValidatedAddressData($requestAddress);
		$addressCollection->addValidatedAddress($validationAddressExtract);
		// when the address is a billing address used for billing and shipping
		// add a validated address for billing and shipping
		if ($this->_isBillingAddress($requestAddress) && $this->_isAddressUsedForShipping($requestAddress)
		) {
			$addressCollection->addValidatedAddress(
				$validationAddressExtract->setAddressType(Mage_Customer_Model_Address::TYPE_SHIPPING)
			);
		}
		$this->_getSession()->setAddressValidationAddresses($addressCollection);

		return $this;
	}

	/**
	 * Return a Varien_Object containing stashed data about address validation and
	 * validated addresses. Most of the properties it contains are retrievable
	 * from this class so it is unlikely this will need to be called publicly.
	 *
	 * @return EbayEnterprise_Eb2cAddress_Model_Suggestion_Group
	 */
	public function getAddressCollection()
	{
		$collection = $this->_getSession()->getData(self::SESSION_KEY);
		return ($collection instanceof EbayEnterprise_Eb2cAddress_Model_Suggestion_Group)
			? $collection
			: Mage::getModel('eb2caddress/suggestion_group');
	}

	/**
	 * Get the address returned as the "original" address from EB2C.
	 * @param bool $keepFresh - flag passed to the session's method
	 * @return Mage_Customer_Model_Address
	 */
	public function getOriginalAddress($keepFresh=false)
	{
		return $this->getAddressCollection()->getOriginalAddress($keepFresh);
	}

	/**
	 * Get the suggested address returned by EB2C
	 * @param bool $keepFresh - flag passed to the session's method
	 * @return Mage_Customer_Model_Address[]
	 */
	public function getSuggestedAddresses($keepFresh=false)
	{
		return $this->getAddressCollection()->getSuggestedAddresses($keepFresh);
	}

	/**
	 * Get the validated_address object from the session, this will be
	 * just the address data from the last address validated by Eb2c
	 * @return Mage_Customer_Model_Address_Abstract
	 */
	public function getValidatedAddress($type)
	{
		return $this->getAddressCollection()->getValidatedAddress($type);
	}

	/**
	 * Return the address from the session represented by the given key.
	 * If no address for that key exists, returns null.
	 * @param string $key
	 * @return Mage_Customer_Model_Address
	 */
	public function getStashedAddressByKey($key)
	{
		return $this->getAddressCollection()->getData($key);
	}

	/**
	 * Returns whether or not there are address suggestions stored in the session
	 * and they should be shown to the user.
	 * @return bool
	 */
	public function hasSuggestions()
	{
		// when getting suggestions from the session, this should not flag the
		// addresses has having been used
		$suggestions = $this->getSuggestedAddresses(true);
		return !empty($suggestions);
	}

	/**
	 * Returns the result of validation from the response message.
	 * When there is no response message in the session, consider the address valid.
	 * When there is a response message in the session, it should accurately indicate
	 * if the address being validated by the request is valid.
	 * @return bool
	 */
	public function isValid()
	{
		$response = $this->getAddressCollection()->getResponseMessage();
		return !$response || $response->isAddressValid();
	}

	/**
	 * Returns whether or not the last set of suggestions are "fresh"
	 * e.g. whether or not they have been used on the frontend or chosen as
	 * the correct suggestion.
	 * @return bool
	 */
	public function hasFreshSuggestions()
	{
		return $this->getAddressCollection()->getHasFreshSuggestions();
	}

	/**
	 * Remove the collection of addresses from the session.
	 * @return EbayEnterprise_Eb2cAddress_Model_Validator $this
	 */
	public function clearSessionAddresses()
	{
		$this->_getSession()->unsetData(self::SESSION_KEY);
		return $this;
	}

	/**
	 * return true if the address contains enough data to be submitted for verification
	 * @return bool
	 */
	protected function _isMissingRequiredFields(Mage_Customer_Model_Address_Abstract $address)
	{
		$methods = array('getStreet1', 'getCity', 'getCountry');
		$hasMissingFieds = false;
		foreach ($methods as $method) {
			$hasMissingFieds = $hasMissingFieds || !$address->$method();
		}
		return $hasMissingFieds;
	}
}
