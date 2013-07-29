<?php
class TrueAction_Eb2cTax_Overrides_Model_Observer
{
	protected $_tax;

	/**
	 * Get helper tax instantiated object.
	 *
	 * @return TrueAction_Eb2cTax_Overrides_Helper_Data
	 */
	protected function _getTaxHelper()
	{
		if (!$this->_tax) {
			$this->_tax =Mage::helper('tax');
		}
		return $this->_tax;
	}

    /**
     * Put quote address tax information into order
     *
     * @param Varien_Event_Observer $observer
     */
    public function salesEventConvertQuoteAddressToOrder(Varien_Event_Observer $observer)
    {
        $address = $observer->getEvent()->getAddress();
        $order = $observer->getEvent()->getOrder();

        $taxes = $address->getAppliedTaxes();
        if (is_array($taxes)) {
            if (is_array($order->getAppliedTaxes())) {
                $taxes = array_merge($order->getAppliedTaxes(), $taxes);
            }
            $order->setAppliedTaxes($taxes);
            $order->setConvertingFromQuote(true);
        }
    }

    /**
     * Save order tax information
     *
     * @param Varien_Event_Observer $observer
     */
    public function salesEventOrderAfterSave(Varien_Event_Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();

        if (!$order->getConvertingFromQuote() || $order->getAppliedTaxIsSaved()) {
            return;
        }

        $getTaxesForItems   = $order->getQuote()->getTaxesForItems();
        $taxes              = $order->getAppliedTaxes();

        $ratesIdQuoteItemId = array();
        if (!is_array($getTaxesForItems)) {
            $getTaxesForItems = array();
        }
        foreach ($getTaxesForItems as $quoteItemId => $taxesArray) {
            foreach ($taxesArray as $group) {
                if (count($group['rates']) == 1) {
                    $ratesIdQuoteItemId[$group['id']][] = array(
                        'id'        => $quoteItemId,
                        'percent'   => $group['percent'],
                        'code'      => $group['rates'][0]['code']
                    );
                } else {
                    $percentDelta   = $group['percent'];
                    $percentSum     = 0;
                    foreach ($group['rates'] as $rate) {
                        $ratesIdQuoteItemId[$group['id']][] = array(
                            'id'        => $quoteItemId,
                            'percent'   => $rate['percent'],
                            'code'      => $rate['code']
                        );
                        $percentSum += $rate['percent'];
                    }

                    if ($percentDelta != $percentSum) {
                        $delta = $percentDelta - $percentSum;
                        foreach ($ratesIdQuoteItemId[$group['id']] as &$rateTax) {
                            if ($rateTax['id'] == $quoteItemId) {
                                $rateTax['percent'] = (($rateTax['percent'] / $percentSum) * $delta)
                                        + $rateTax['percent'];
                            }
                        }
                    }
                }
            }
            // scratch notes
			// 	$a = array('group_id'=>array( // if the number of rates in a group is 1
			// 		array(
			// 			'id'      => 'quote_item_id',
			// 			'percent' => 'group_percent',
			// 			'code'    => 'group_rate_code',
			// 		)
			// 	));

			// 	$a = array('group_id'=>array( // if the number of rates in a group is 1
			// 		array(
			// 			'id'      => 'quote_item_id',
			// 			'percent' => 'group_rate_percent',
			// 			'code'    => 'group_rate_code',
			// 		),
			// 	));
			// 	$percentSum += 'group_rate_percent';
			// }
        }



        foreach ($taxes as $id => $row) {
            foreach ($row['rates'] as $tax) {
                if (is_null($row['percent'])) {
                    $baseRealAmount = $row['base_amount'];
                } else {
                    if ($row['percent'] == 0 || $tax['percent'] == 0) {
                        continue;
                    }
                    $baseRealAmount = $row['base_amount'] / $row['percent'] * $tax['percent'];
                }
                $hidden = (isset($row['hidden']) ? $row['hidden'] : 0);
                $data = array(
                    'order_id'          => $order->getId(),
                    'code'              => $tax['code'],
                    'title'             => $tax['title'],
                    'hidden'            => $hidden,
                    'percent'           => $tax['percent'],
                    'priority'          => $tax['priority'],
                    'position'          => $tax['position'],
                    'amount'            => $row['amount'],
                    'base_amount'       => $row['base_amount'],
                    'process'           => $row['process'],
                    'base_real_amount'  => $baseRealAmount,
                );

                $result = Mage::getModel('tax/sales_order_tax')->setData($data)->save();

                if (isset($ratesIdQuoteItemId[$id])) {
                    foreach ($ratesIdQuoteItemId[$id] as $quoteItemId) {
                        if ($quoteItemId['code'] == $tax['code']) {
                            $item = $order->getItemByQuoteItemId($quoteItemId['id']);
                            if ($item) {
                                $data = array(
                                    'item_id'       => $item->getId(),
                                    'tax_id'        => $result->getTaxId(),
                                    'tax_percent'   => $quoteItemId['percent']
                                );
                                Mage::getModel('tax/sales_order_tax_item')->setData($data)->save();
                            }
                        }
                    }
                }
            }
        }
        $order->setAppliedTaxIsSaved(true);
    }



	// TODO: ADD SHIPPING METHOD EVENT
	// TODO: EACH OF THESE EVENTS SHOULD BOIL DOWN TO 3 CASES: 1 ITEM CHANGED FORCE RESEND; 2 ITEM CHANGED CHECKED RESEND; ADDRESS CHECK
	public function salesEventItemAdded(Varien_Event_Observer $observer)
	{
		Mage::log('salesEventItemAdded');
		$this->_getTaxHelper()->getCalculator()
			->getTaxRequest()
			->invalidate();
	}

	public function cartEventProductUpdated(Varien_Event_Observer $observer)
	{
		Mage::log('cartEventProductUpdated');
		$this->_getTaxHelper()->getCalculator()
			->getTaxRequest()
			->invalidate();
	}

	public function salesEventItemRemoved(Varien_Event_Observer $observer)
	{
		Mage::log('salesEventItemRemoved');
		$this->_getTaxHelper()->getCalculator()
			->getTaxRequest()
			->invalidate();
	}

	public function salesEventItemQtyUpdated(Varien_Event_Observer $observer)
	{
		Mage::log('salesEventItemQtyUpdated');
		$quoteItem = $observer->getEvent()->getItem();
		if (!is_a($quoteItem, 'Mage_Sales_Model_Quote_Item')) {
			Mage::log(
				'EB2C Tax Error: quoteCollectTotalsBefore: did not receive a Mage_Sales_Model_Quote_Item object',
				Zend_Log::WARN
			);
		} else {
			$this->_getTaxHelper()->getCalculator()
				->getTaxRequest()
				->checkItemQty($quoteItem);
		}
	}

	/**
	 * Reset extra tax amounts on quote addresses before recollecting totals
	 *
	 * @param Varien_Event_Observer $observer
	 * @return Mage_Tax_Model_Observer
	 */
	public function quoteCollectTotalsBefore(Varien_Event_Observer $observer)
	{
		Mage::log('send tax request event');
		/* @var $quote Mage_Sales_Model_Quote */
		$quote = $observer->getEvent()->getQuote();
		if (is_a($quote, 'Mage_Sales_Model_Quote')) {
			foreach ($quote->getAllAddresses() as $address) {
				$address->setExtraTaxAmount(0);
				$address->setBaseExtraTaxAmount(0);
			}
			// checking address
			$this->_getTaxHelper()->getCalculator()
				->getTaxRequest()
				->checkAddresses($quote);
			// checking ShippingOrigin Address
			$this->_getTaxHelper()->getCalculator()
				->getTaxRequest()
				->checkShippingOriginAddresses($quote);
			// checking AdminOrigin Address
			$this->_getTaxHelper()->getCalculator()
				->getTaxRequest()
				->checkAdminOriginAddresses();
		} else {
			Mage::log(
				'EB2C Tax Error: quoteCollectTotalsBefore: did not receive a Mage_Sales_Model_Quote object',
				Zend_Log::WARN
			);
		}
		return $this;
	}

	/**
	 * send a tax request for the quote and set the reponse in the calculator.
	 *
	 * @param Varien_Event_Observer $observer
	 * @return Mage_Tax_Model_Observer
	 */
	public function taxEventSendRequest(Varien_Event_Observer $observer)
	{
		Mage::log('send tax request event');
		/* @var $quote Mage_Sales_Model_Quote */
		$quote = $observer->getEvent()->getQuote();
		if (is_a($quote, 'Mage_Sales_Model_Quote')) {
			$this->_fetchTaxDutyInfo($quote);
		} else {
			Mage::log(
				'EB2C Tax Error: taxEventSendRequest: did not receive a Mage_Sales_Model_Quote object',
				Zend_Log::WARN
			);
		}
		return $this;
	}

	/**
	 * attempt to send a request for taxes.
	 * @param  Mage_Sales_Model_Quote $quote
	 */
	protected function _fetchTaxDutyInfo(Mage_Sales_Model_Quote $quote)
	{
		try {
			$helper = $this->_getTaxHelper();
			$calc = $helper->getCalculator();
			$request = $calc->getTaxRequest($quote);
			if ($request && $request->isValid()) {
				Mage::log(
					'sending taxduty request for quote ' . $quote->getId(),
					Zend_Log::DEBUG
				);
				$response = $helper->sendRequest($request);
				$calc->setTaxResponse($response);
			}
		} catch (Exception $e) {
			Mage::log(
				'Unable to send TaxDutyQuote request: ' . $e->getMessage(),
				Zend_Log::WARN
			);
		}
	}

	/**
	 * checking quote item discount
	 *
	 * @param Varien_Event_Observer $observer
	 * @return Mage_Tax_Model_Observer
	 */
	public function salesRuleEventItemProcessed(Varien_Event_Observer $observer)
	{
		Mage::log('salesrule_validator_process', Zend_Log::DEBUG);
		/* @var $quote Mage_Sales_Model_Quote_Item_Abstract */
		$item = $observer->getEvent()->getItem();
		if (is_a($item, 'Mage_Sales_Model_Quote_Item_Abstract')) {
			$this->_getTaxHelper()->getCalculator()
				->getTaxRequest()
				->checkDiscounts($item);
		} else {
			Mage::log(
				'EB2C Tax Error: salesRuleEventItemProcessed: did not receive a Mage_Sales_Model_Quote_Item_Abstract object',
				Zend_Log::WARN
			);
		}
		return $this;
	}

// place holder functions
//

	/**
	 * @codeCoverageIgnore
	 */
	public function addTaxPercentToProductCollection($observer)
	{
		return $this;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function prepareCatalogIndexPriceSelect($observer)
	{
		return $this;
	}
}