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

class EbayEnterprise_Eb2cInventory_Model_Quantity extends EbayEnterprise_Eb2cInventory_Model_Request_Abstract
{
    /**
     * @see EbayEnterprise_Eb2cInventory_Model_Request_Abstract
     */
    const OPERATION_KEY = 'check_quantity';
    /**
     * @see EbayEnterprise_Eb2cInventory_Model_Request_Abstract
     */
    const XSD_FILE_CONFIG = 'xsd_file_quantity';

    /**
     * Build quantity request message DOM Document
     * @param Mage_Sales_Model_Quote $quote Quote the request is for
     * @return DOMDocument The XML document, to be sent as request to eb2c
     */
    protected function _buildRequestMessage(Mage_Sales_Model_Quote $quote)
    {
        $domDocument = Mage::helper('eb2ccore')->getNewDomDocument();
        $quantityRequestMessage = $domDocument->addElement(
            'QuantityRequestMessage',
            null,
            Mage::helper('eb2cinventory')->getXmlNs()
        )->firstChild;
        $skuSet = array();
        foreach (Mage::helper('eb2cinventory')->getInventoriedItems($quote->getAllVisibleItems()) as $item) {
            $skuSet[] = $item->getSku();
        }
        foreach (array_unique($skuSet) as $idx => $sku) {
            $quantityRequestMessage->createChild('QuantityRequest', null, array('lineId' => 'item' . $idx, 'itemId' => $sku));
        }
        return $domDocument;
    }
    /**
     * Parse through XML response to get eb2c available stock for an item.
     * @param string $quantityResponseMessage the XML response from eb2c
     * @return array The available stock from eb2c for each item, keyed by itemId
     */
    public function getAvailableStockFromResponse($quantityResponseMessage)
    {
        $availableStock = array();
        if (trim($quantityResponseMessage) !== '') {
            $coreHlpr = Mage::helper('eb2ccore');
            $xpath = Mage::helper('eb2cinventory/quote')->getXpathForMessage($quantityResponseMessage);
            $quantities = $xpath->query('//a:QuantityResponse');
            foreach ($quantities as $quantity) {
                $availableStock[$quantity->getAttribute('itemId')] = (int) $coreHlpr->extractNodeVal($xpath->query('a:Quantity', $quantity));
            }
        }
        return $availableStock;
    }
    /**
     * Update the quote with a response from the service
     * @param  Mage_Sales_Model_Quote $quote           The quote object to update.
     * @param  string                 $responseMessage Response from the Quantity service.
     * @return EbayEnterprise_Eb2cInventory_Model_Quantity $this object
     */
    public function updateQuoteWithResponse(Mage_Sales_Model_Quote $quote, $responseMessage)
    {
        if ($responseMessage) {
            $availableStock = $this->getAvailableStockFromResponse($responseMessage);
            foreach (Mage::helper('eb2cinventory')->getInventoriedItems($quote->getAllVisibleItems()) as $item) {
                if (isset($availableStock[$item->getSku()])) {
                    if ($availableStock[$item->getSku()] === 0) {
                        Mage::helper('eb2cinventory/quote')->removeItemFromQuote($quote, $item);
                    } elseif ($availableStock[$item->getSku()] < $item->getQty()) {
                        Mage::helper('eb2cinventory/quote')->updateQuoteItemQuantity($quote, $item, $availableStock[$item->getSku()]);
                    }
                }
            }
        }
        return $this;
    }
}
