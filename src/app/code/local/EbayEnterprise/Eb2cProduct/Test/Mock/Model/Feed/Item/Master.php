<?php
/**
 * @codeCoverageIgnore
 */
class EbayEnterprise_Eb2cProduct_Test_Mock_Model_Feed_Item_Master extends EcomDev_PHPUnit_Test_Case
{
	/**
	 * replacing by mock of the EbayEnterprise_Eb2cProduct_Model_Feed_Item_Master class
	 *
	 * @return void
	 */
	public function replaceByMockWithInvalidProductId()
	{
		$mock = $this->getModelMockBuilder('eb2cproduct/feed_item_master')
			->setMethods(array('_loadProductBySku'))
			->getMock();

		$mockCatalogModelProduct = new EbayEnterprise_Eb2cProduct_Test_Mock_Model_Catalog_Product();
		$mock->expects($this->any())
			->method('_loadProductBySku')
			->will($this->returnValue($mockCatalogModelProduct->buildCatalogModelProductWithInvalidProductId()));

		$this->replaceByMock('model', 'eb2cproduct/feed_item_master', $mock);
	}

	/**
	 * replacing by mock of the EbayEnterprise_Eb2cProduct_Model_Feed_Item_Master class
	 *
	 * @return void
	 */
	public function replaceByMockWithValidProductId()
	{
		$mock = $this->getModelMockBuilder('eb2cproduct/feed_item_master')
			->setMethods(array('_loadProductBySku'))
			->getMock();

		$mockCatalogModelProduct = new EbayEnterprise_Eb2cProduct_Test_Mock_Model_Catalog_Product();
		$mock->expects($this->any())
			->method('_loadProductBySku')
			->will($this->returnValue($mockCatalogModelProduct->buildCatalogModelProductWithValidProductId()));

		$this->replaceByMock('model', 'eb2cproduct/feed_item_master', $mock);
	}

	/**
	 * replacing by mock of the EbayEnterprise_Eb2cProduct_Model_Feed_Item_Master class
	 *
	 * @return void
	 */
	public function replaceByMockWithValidProductIdSetColorDescriptionThrowException()
	{
		$mock = $this->getModelMockBuilder('eb2cproduct/feed_item_master')
			->setMethods(array('_loadProductBySku'))
			->getMock();

		$mockCatalogModelProduct = new EbayEnterprise_Eb2cProduct_Test_Mock_Model_Catalog_Product();
		$mock->expects($this->any())
			->method('_loadProductBySku')
			->will($this->returnValue($mockCatalogModelProduct->buildCatalogModelProductWithValidProductIdSetColorDescriptionThrowException()));

		$this->replaceByMock('model', 'eb2cproduct/feed_item_master', $mock);
	}

	/**
	 * replacing by mock of the EbayEnterprise_Eb2cProduct_Model_Feed_Item_Master class
	 *
	 * @return void
	 */
	public function replaceByMockWithInvalidProductException()
	{
		$mock = $this->getModelMockBuilder('eb2cproduct/feed_item_master')
			->setMethods(array('_loadProductBySku'))
			->getMock();

		$mockCatalogModelProduct = new EbayEnterprise_Eb2cProduct_Test_Mock_Model_Catalog_Product();
		$mock->expects($this->any())
			->method('_loadProductBySku')
			->will($this->returnValue($mockCatalogModelProduct->buildCatalogModelProductWithInvalidProductException()));

		$this->replaceByMock('model', 'eb2cproduct/feed_item_master', $mock);
	}

	/**
	 * replacing by mock of the EbayEnterprise_Eb2cProduct_Model_Feed_Item_Master class
	 *
	 * @return void
	 */
	public function replaceByMockWithValidProductException()
	{
		$mock = $this->getModelMockBuilder('eb2cproduct/feed_item_master')
			->setMethods(array('_loadProductBySku'))
			->getMock();

		$mockCatalogModelProduct = new EbayEnterprise_Eb2cProduct_Test_Mock_Model_Catalog_Product();
		$mock->expects($this->any())
			->method('_loadProductBySku')
			->will($this->returnValue($mockCatalogModelProduct->buildCatalogModelProductWithValidProductException()));

		$this->replaceByMock('model', 'eb2cproduct/feed_item_master', $mock);
	}

	/**
	 * replacing by mock of the EbayEnterprise_Eb2cProduct_Model_Feed_Item_Master class
	 *
	 * @return void
	 */
	public function replaceByMockWhereDeleteThrowException()
	{
		$mock = $this->getModelMockBuilder('eb2cproduct/feed_item_master')
			->setMethods(array('_loadProductBySku'))
			->getMock();

		$mockCatalogModelProduct = new EbayEnterprise_Eb2cProduct_Test_Mock_Model_Catalog_Product();
		$mock->expects($this->any())
			->method('_loadProductBySku')
			->will($this->returnValue($mockCatalogModelProduct->buildCatalogModelProductWhereDeleteThrowException()));

		$this->replaceByMock('model', 'eb2cproduct/feed_item_master', $mock);
	}
}