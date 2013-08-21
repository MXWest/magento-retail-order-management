<?php
/**
 * @category   TrueAction
 * @package    TrueAction_Eb2c
 * @copyright  Copyright (c) 2013 True Action Network (http://www.trueaction.com)
 */
class TrueAction_Eb2cProduct_Test_Model_AttributesTest extends TrueAction_Eb2cCore_Test_Base
{
	public static $modelClass = 'TrueAction_Eb2cProduct_Model_Attributes';

	/**
	 * verify attributes are applied on the specified attributeset.
	 * attributeset is a valid attribute set model
	 * attribute to add is tax_code
	 * attribute doesnt exist
	 * attribute not already applied to attribute set
	 * group is testgroup
	 * group already exists
	 */
	public function testApplyDefaultAttributes()
	{
		$attrCode     = 'tax_code';
		$hasGroup     = true;
		$attrSetId    = 1;
		$groupId      = 2;
		$entityAttrId = 3;
		$entityTypeId = 9;
		$groupName    = 'Prices';
		$attrSet      = $this->_buildModelMock('eav/entity_attribute_set', array(
			'load' => $this->returnSelf(),
			'getEntityTypeId' => $this->returnValue($entityTypeId),
			'getId' => $this->returnValue($attrSetId),
		));
		$methods = array('getId', 'getAttributeGroupId', 'getGroup', 'hasGroup', 'setAttributeGroupId', 'setAttributeSetId', 'save');
		$attr = $this->getResourceModelMock('catalog/eav_attribute', $methods);
		$attr->expects($this->atLeastOnce())
			->method('hasGroup')
			->will($this->returnValue($hasGroup));
		$attr->expects($this->once())
			->method('getGroup')
			->will($this->returnValue($groupName));
		$attr->expects($this->never())
			->method('getAttributeGroupId')
			->will($this->returnValue($groupId));
		$attr->expects($this->once())
			->method('setAttributeGroupId')
			->with($this->identicalTo($groupId))
			->will($this->returnSelf());
		$attr->expects($this->once())
			->method('setAttributeSetId')
			->with($this->identicalTo($attrSetId))
			->will($this->returnSelf());
		$attr->expects($this->once())
			->method('save')
			->will($this->returnSelf());

		$methods = array('getId');
		$entityAttr = $this->getResourceModelMock('catalog/eav_attribute', $methods);
		$entityAttr->expects($this->once())
			->method('getId')
			->will($this->returnValue(null));

		$methods = array('setEntityTypeFilter', 'setCodeFilter', 'getFirstItem');
		$collection = $this->getResourceModelMock('catalog/product_attribute_collection', $methods);
		$collection->expects($this->once())
			->method('setEntityTypeFilter')
			->with($this->identicalTo($entityTypeId))
			->will($this->returnSelf());
		$collection->expects($this->once())
			->method('setCodeFilter')
			->with($this->identicalTo($attrCode))
			->will($this->returnSelf());
		$collection->expects($this->once())
			->method('getFirstItem')
			->will($this->returnValue($entityAttr));
		$this->replaceByMock('resource_model', 'catalog/product_attribute_collection', $collection);

		$attrGroup = $this->_buildModelMock('eav/entity_attribute_group', array(
			'getId' => $this->returnValue($groupId),
		));

		$methods = array('_loadDefaultAttributesConfig', '_getOrCreateAttribute', '_getAttributeGroup', '_getAttributeSet');
		$model = $this->getModelMock('eb2cproduct/attributes', $methods);

		$configNode = new Varien_SimpleXml_Element(self::$configXml);
		list($defaultNode) = $configNode->xpath('default');
		$attrConfig  = $this->getModelMock('core/config', array('getNode'));
		$attrConfig->expects($this->any())
			->method('getNode')
			->with($this->identicalTo('default'))
			->will($this->returnValue($defaultNode));
		$model->expects($this->atLeastOnce())
			->method('_loadDefaultAttributesConfig')->will($this->returnValue($attrConfig));

		$model->expects($this->once())
			->method('_getOrCreateAttribute')
			->with($this->identicalTo($attrCode), $this->identicalTo($entityTypeId), $this->anything())
			->will($this->returnValue($attr));

		$model->expects($this->once())
			->method('_getAttributeSet')
			->with($this->identicalTo($attrSet))
			->will($this->returnValue($attrSet));
		$model->expects($this->once())
			->method('_getAttributeGroup')
			->with($this->identicalTo($groupName), $this->identicalTo($attrSetId))
			->will($this->returnValue($attrGroup));

		$model->applyDefaultAttributes($attrSet);
	}

	/**
	 * verify a group is returned when successful and null when unsuccessful.
	 * @loadExpectation
	 * @dataProvider dataProvider
	 */
	public function testGetAttributeGroup($groupFound)
	{
		$groupFieldName = 'attribute_group_name';
		$groupName      = 'group';
		$attributeSetId = 1;
		$groupId       = 2;
		$e = $this->expected('%s-%s-%s', $groupName, $attributeSetId, (int)$groupFound);
		$mockCollection = $this->getResourceModelMockBuilder('eav/entity_attribute_group_collection')
			->disableOriginalConstructor()
			->setMethods(array('setAttributeSetFilter', 'load', 'addFieldToFilter', 'getFirstItem'))
			->getMock();
		$mock          = $this->getModelMockBuilder('eav/entity_attribute_group')
			->disableOriginalConstructor()
			->setMethods(array('getResourceCollection', 'getId'))
			->getMock();

		// mock out the collection methods
		$mockCollection->expects($this->once())->method('setAttributeSetFilter')
			->with($this->identicalTo($attributeSetId))
			->will($this->returnSelf());
		$mockCollection->expects($this->once())->method('addFieldToFilter')
			->with($this->identicalTo($groupFieldName), $this->equalTo($e->getGroupNameFilter()))
			->will($this->returnSelf());
		$mockCollection->expects($this->once())->method('load')
			->will($this->returnSelf());
		$mockCollection->expects($this->once())->method('getFirstItem')
			->will($this->returnValue($mock));

		// mock out the model methods
		$mock->expects($this->once())->method('getResourceCollection')
			->will($this->returnValue($mockCollection));
		$mock->expects($this->once())->method('getId')
			->will($this->returnValue($groupFound ? $groupId : null));

		$this->replaceByMock('model' ,'eav/entity_attribute_group', $mock);
		$model = Mage::getModel('eb2cproduct/attributes');
		$val   = $this->_reflectMethod($model, '_getAttributeGroup')->invoke($model, $groupName, $attributeSetId);
		if ($groupFound) {
			$this->assertInstanceOf('Mage_Eav_Model_Entity_Attribute_Group', $val);
		} else {
			$this->assertNull($val);
		}
	}

	/**
	 * verify a the model field name is returned when it is defined in the map
	 * and the input field name is returned if not in the map.
	 * @dataProvider dataProvider
	 */
	public function testGetMappedFieldName($fieldName, $expected)
	{
		$map   = array('field_in_map' => 'model_field_name');
		$model = Mage::getModel('eb2cproduct/attributes');
		$this->_reflectProperty($model, '_fieldNameMap')->setValue($model, $map);
		$modelFieldName = $this->_reflectMethod($model, '_getMappedFieldName')
			->invoke($model, $fieldName);
		$this->assertSame($expected, $modelFieldName);
	}

	/**
	 * verify a the function returns a value in the correct format for the field as
	 * per the mapping
	 * @dataProvider dataProvider
	 */
	public function testGetMappedFieldValue($fieldName, $data, $expected)
	{
		$xml      = "<?xml version='1.0'?>\n<{$fieldName}>{$data}</{$fieldName}>";
		$dataNode = new Varien_SimpleXml_Element($xml);
		$model    = Mage::getModel('eb2cproduct/attributes');
		$value    = $this->_reflectMethod($model, '_getMappedFieldValue')
			->invoke($model, $fieldName, $dataNode);
		$this->assertSame($expected, $value);
	}

	/**
	 * verify a the correct field name for the frontend type is returned.
	 * @dataProvider dataProvider
	 */
	public function testGetDefaultValueFieldName($frontendType, $expected)
	{
		$model    = Mage::getModel('eb2cproduct/attributes');
		$value    = $this->_reflectMethod($model, '_getDefaultValueFieldName')
			->invoke($model, $frontendType);
		$this->assertSame($expected, $value);
	}

	/**
	 * verify a new model is returned and contains the correct data for each field
	 * @loadExpectation
	 */
	public function testGetOrCreateAttribute()
	{
		$dataNode     = new Varien_SimpleXml_Element(self::$configXml);
		$taxCodeNode  = $dataNode->xpath('/eb2cproduct_attributes/default/tax_code');
		$attrCode     = 'tax_code';
		$entityTypeId = '9';
		$this->assertSame(1, count($taxCodeNode));
		list($taxCodeNode) = $taxCodeNode;
		$this->assertInstanceOf('Varien_SimpleXml_Element', $taxCodeNode);
		$this->assertSame('tax_code', $taxCodeNode->getName());
		$model = Mage::getModel('eb2cproduct/attributes');
 		$attrModel = $this->_reflectMethod($model, '_getOrCreateAttribute')
 			->invoke($model, $attrCode, $entityTypeId, $taxCodeNode);
		$this->assertInstanceOf('Mage_Catalog_Model_Resource_Eav_Attribute', $attrModel);
		$this->assertNotNull($attrModel->getId());
		$e = $this->expected('tax_code');
		$this->assertEquals($e->getData(), $attrModel->getData());
	}

	/**
	 * verify the cache is used to get the new model without reprocessing the
	 * config.
	 * verify the new model will not be the the instance in the cache.
	 */
	public function testGetOrCreateAttributeCache()
	{
		// setup input data
		$dataNode = new Varien_SimpleXml_Element(self::$configXml);
		$taxCodeNode  = $dataNode->xpath('/eb2cproduct_attributes/default/tax_code');
		$attrCode     = 'tax_code';
		$entityTypeId = 9;
		$this->assertSame(1, count($taxCodeNode));
		list($taxCodeNode) = $taxCodeNode;
		$this->assertInstanceOf('Varien_SimpleXml_Element', $taxCodeNode);
		$this->assertSame($attrCode, $taxCodeNode->getName());

		// mock functions to make sure they're not called
		$model = $this->getModelMock('eb2cproduct/attributes', array('_getDefaultValueFieldName', '_getMappedFieldName', '_getMappedFieldValue'));
		$model->expects($this->never())->method('_getDefaultValueFieldName');
		$model->expects($this->never())->method('_getMappedFieldName');
		$model->expects($this->never())->method('_getMappedFieldValue');

		// mock up the cache
		$dummyObject = new Varien_Object();
		$this->_reflectProperty($model, '_prototypeCache')
			->setValue($model, array($attrCode => $dummyObject));
 		$attrModel = $this->_reflectMethod($model, '_getOrCreateAttribute')
 			->invoke($model, $attrCode, $entityTypeId, $taxCodeNode);
		$this->assertInstanceOf('Varien_Object', $attrModel);
		$this->assertSame($dummyObject, $attrModel);
	}

	/**
	 * verify a new model is returned and contains the correct data for each field
	 * @loadExpectation
	 */
	public function testGetPrototypeData()
	{
		$dataNode = new Varien_SimpleXml_Element(self::$configXml);
		$result   = $dataNode->xpath('/eb2cproduct_attributes/default/tax_code');
		// start precondition checks
		$this->assertSame(1, count($result));
		list($taxCodeNode) = $result;
		$this->assertInstanceOf('Varien_SimpleXml_Element', $taxCodeNode);
		$this->assertSame('tax_code', $taxCodeNode->getName());
		// end preconditions checks

		$model = Mage::getModel('eb2cproduct/attributes');
 		$attrData = $this->_reflectMethod($model, '_getPrototypeData')
 			->invoke($model, $taxCodeNode);
		$this->assertNotEmpty($attrData);
		$e = $this->expected('tax_code');
		$this->assertEquals($e->getData(), $attrData);
	}

	public function callbackGetModuleDir($dir, $module)
	{
		$vfs = $this->getFixture()->getVfs();
		$url = $vfs->url('app/code/local/TrueAction');
		return $url . DS . $module . DS . 'etc';
	}

	/**
	 * verify the default config in the config.xml can be overridden by another xml file.
	 * @loadExpectation attributesConfig.yaml
	 * @dataProvider provideOverrideXmlVfsStructure
	 */
	public function testLoadDefaultAttributesConfig($expectation, $vfsStructure)
	{
		$model  = Mage::getModel('eb2cproduct/attributes');
		$config = $this->_reflectMethod($model, '_loadDefaultAttributesConfig')->invoke($model);
		$this->assertInstanceOf('Mage_Core_Model_Config', $config);
		$e           = $this->expected($expectation);
		$configArray = $config->getNode('default')->asArray();
		$this->assertSame($e->getData('tax_code'), $configArray['tax_code']);
	}

	/**
	 * verify a list of default codes is generated from the config.
	 * @loadExpectation testGetDefaultAttributesCodeList.yaml
	 */
	public function testGetDefaultAttributesCodeList()
	{
		$model  = Mage::getModel('eb2cproduct/attributes');
		$fn     = $this->_reflectMethod($model, 'getDefaultAttributesCodeList');
		$result	= $fn->invoke($model);
		$e      = $this->expected('default');
		$this->assertSame($e->getData(), $result);
	}

	/**
	 * verify the list of codes can be filtered by group.
	 * @loadExpectation testGetDefaultAttributesCodeList.yaml
	 */
	public function testGetDefaultAttributesCodeListFilterByGroup()
	{
		$model  = Mage::getModel('eb2cproduct/attributes');
		$fn     = $this->_reflectMethod($model, 'getDefaultAttributesCodeList');
		$result	= $fn->invoke($model, 'Prices');
		$e      = $this->expected('prices');
		$this->assertSame($e->getData(), $result);
	}

	public function provideOverrideXmlVfsStructure()
	{
		return array(
			array('base_config', $this->_getOverrideXmlVfsStructure()),
		);
	}

	protected function _getOverrideXmlVfsStructure(array $etcContents = array())
	{
		return array(
			'app' => array(
				'code' => array(
					'local' => array(
						'TrueAction' => array(
							'Eb2cProduct' => array(
								'etc' => $etcContents
			))))));
	}

	public static $configXml  = '
		<eb2cproduct_attributes>
			<default>
				<tax_code>
					<scope>Store</scope>
					<label>Tax Code2</label>
					<group>Prices</group>
					<input_type>boolean</input_type>
					<unique>Y</unique>
					<product_types><![CDATA[simple,configurable,virtual,bundle,downloadable]]></product_types>
					<default><![CDATA[N]]></default>
				</tax_code>
			</default>
		</eb2cproduct_attributes>';
}
