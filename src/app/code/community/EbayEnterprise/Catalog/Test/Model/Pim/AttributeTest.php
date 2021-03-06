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

class EbayEnterprise_Catalog_Test_Model_Pim_AttributeTest extends EbayEnterprise_Eb2cCore_Test_Base
{
    /**
     * verify the constructor allows for magento factory initialization
     */
    public function testConstructor()
    {
        $dom = Mage::helper('eb2ccore')->getNewDomDocument();
        $xpath = 'foo';
        $sku = 'somesku';
        $language = 'en-US';
        $value = $dom->createDocumentFragment();
        $value->appendChild($dom->createElement('Foo', 'bar'));
        $model = Mage::getModel('ebayenterprise_catalog/pim_attribute', array(
            'destination_xpath' => $xpath, 'sku' => $sku, 'language' => $language, 'value' => $value
        ));
        $this->assertSame($xpath, $model->destinationXpath);
        $this->assertSame($sku, $model->sku);
        $this->assertSame($language, $model->language);
        $this->assertSame($value, $model->value);

        $model = Mage::getModel('ebayenterprise_catalog/pim_attribute', array(
            'destination_xpath' => $xpath, 'sku' => $sku, 'value' => $value
        ));
        $this->assertNull($model->language);
    }
    public function testStringifyValue()
    {
        $attrModel = $this->getModelMockBuilder('ebayenterprise_catalog/pim_attribute')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();
        $dom = Mage::helper('eb2ccore')->getNewDomDocument();

        $n = $dom->createDocumentFragment();
        $n->appendChild($dom->createElement('Foo', 'Value'));
        $n->appendChild($dom->createElement('Bar', 'Thing'));
        $attrModel->value = $n;
        $this->assertSame(
            '<Foo>Value</Foo><Bar>Thing</Bar>',
            EcomDev_Utils_Reflection::invokeRestrictedMethod($attrModel, '_stringifyValue')
        );
        $n = $dom->createCDATASection('Some String Value');
        $attrModel->value = $n;
        $this->assertSame(
            'Some String Value',
            EcomDev_Utils_Reflection::invokeRestrictedMethod($attrModel, '_stringifyValue')
        );
        $n = $dom->createElement('Foo', 'Bar');
        $attrModel->value = $n;
        $this->assertSame(
            '<Foo>Bar</Foo>',
            EcomDev_Utils_Reflection::invokeRestrictedMethod($attrModel, '_stringifyValue')
        );
    }
    public function testToString()
    {
        $dom = Mage::helper('eb2ccore')->getNewDomDocument();
        $a = Mage::getModel(
            'ebayenterprise_catalog/pim_attribute',
            array(
                'destination_xpath' => 'ItemId',
                'sku' => '45-12345',
                'value' => $dom->createElement('Foo', 'Value')
            )
        );
        $this->assertSame('0ItemId<Foo>Value</Foo>', (string) $a);
        $a = Mage::getModel(
            'ebayenterprise_catalog/pim_attribute',
            array(
                'destination_xpath' => 'CustomAttributes/Attribute',
                'sku' => '45-12345',
                'language' => 'en-US',
                'value' => $dom->createElement('Foo', 'Value')
            )
        );
        $this->assertSame('0CustomAttributes/Attributeen-US<Foo>Value</Foo>', (string) $a);
        $a = Mage::getModel(
            'ebayenterprise_catalog/pim_attribute',
            array(
                'destination_xpath' => 'CustomAttributes/Attribute',
                'sku' => '45-12345',
                'language' => 'en-US',
                'value' => $dom->createCDATASection('Foo')
            )
        );
        $this->assertSame('0CustomAttributes/Attributeen-USFoo', (string) $a);
        $fragment = $dom->createDocumentFragment();
        $fragment->appendChild($dom->createElement('Foo', 'Bar'));
        $fragment->appendChild($dom->createElement('Baz'));
        $a = Mage::getModel(
            'ebayenterprise_catalog/pim_attribute',
            array(
                'destination_xpath' => 'CustomAttributes/Attribute',
                'sku' => '45-12345',
                'language' => 'en-US',
                'value' => $fragment
            )
        );
        $this->assertSame('0CustomAttributes/Attributeen-US<Foo>Bar</Foo><Baz></Baz>', (string) $a);
    }
    /**
     * Provide constructor args to the PIM Attribute model that should be expected
     * to trigger an error.
     * @return array
     */
    public function provideConstructorArgs()
    {
        return array(
            array(array(), 'User Error: EbayEnterprise_Catalog_Model_Pim_Attribute::__construct missing arguments: destination_xpath, sku, value are required'),
        );
    }
    /**
     * An error should be triggered, captured as an Exception in the test, if the
     * constructor is called without all required key/value pairs.
     * @dataProvider provideConstructorArgs
     */
    public function testConstructInvalidArgs($constructorArgs, $message)
    {
        $this->setExpectedException('Exception', $message);
        Mage::getModel('ebayenterprise_catalog/pim_attribute', $constructorArgs);
    }
}
