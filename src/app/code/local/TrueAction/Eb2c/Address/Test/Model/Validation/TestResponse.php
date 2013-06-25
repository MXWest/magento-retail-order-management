<?php

class TrueAction_Eb2c_Address_Test_Model_Validation_TestResponse
	extends EcomDev_PHPUnit_Test_Case
{

	/**
	 * @test
	 * @dataProvider dataProvider
	 */
	public function testIsValid($valid, $message)
	{
		$response = Mage::getModel('eb2caddress/validation_response');
		$response->setMessage($message);
		$this->assertEquals((bool) $valid, $response->isAddressValid());
	}

	/*
	 * @test
	 * @dataProvider dataProvider
	public function testIsValidLogged($valid, $message)
	{
		$response = Mage::getModel('eb2caddress/validation_response');
		$response->setMessage($message);
		$this->assertEquals((bool) $valid, $response->isAddressValid());
		$this->markTestIncomplete('Need to get data provider to work and test that messages are properly logged.');
	}
	 */

	/**
	 * Test creating a Mage_Customer_Model_Address from the response message.
	 * @test
	 */
	public function testGettingOriginalAddress()
	{
		$response = Mage::getModel('eb2caddress/validation_response');
		/* There must be a better way to do this but until I can figure one out
		 * this will have to do...xml response includes the following info on the
		 * original address:
		 * street address = 1671 Clark Street Rd\nOmnicare Building
		 * city = Auburn
		 * MainDivision = NY
		 * CountryCode = US
		 * PostalCode = 13025
		 */
		$response->setMessage('<?xml version="1.0" encoding="UTF-8"?><AddressValidationResponse xmlns="http://api.gsicommerce.com/schema/checkout/1.0"><Header><MaxAddressSuggestions>5</MaxAddressSuggestions></Header><RequestAddress><Line1>1671 Clark Street Rd</Line1><Line2>Omnicare Building</Line2><City>Auburn</City><MainDivision>NY</MainDivision><CountryCode>US</CountryCode><PostalCode>13025</PostalCode></RequestAddress><Result><ResultCode>C</ResultCode><ProviderResultCode>C</ProviderResultCode><ProviderName>Address Doctor</ProviderName><ErrorLocations><ErrorLocation>PostalCode</ErrorLocation></ErrorLocations><ResultSuggestionCount>1</ResultSuggestionCount><SuggestedAddresses><SuggestedAddress><Line1>1671 Clark Street Rd</Line1><City>Auburn</City><MainDivision>NY</MainDivision><CountryCode>US</CountryCode><PostalCode>13021-9523</PostalCode><FormattedAddress>1671 Clark Street RdOmnicare BuildingAuburn NY 13021-9523US</FormattedAddress><ErrorLocations><ErrorLocation>PostalCode</ErrorLocation></ErrorLocations></SuggestedAddress></SuggestedAddresses></Result></AddressValidationResponse>');
		$origAddress = $response->getOriginalAddress();
		$this->assertTrue($origAddress instanceof Mage_Customer_Model_Address);
		$this->assertSame($origAddress->getStreet(1), '1671 Clark Street Rd');
		$this->assertSame($origAddress->getStreet(2), 'Omnicare Building');
		$this->assertSame($origAddress->getCity(), 'Auburn');
		$this->assertSame($origAddress->getRegionId(), 43);
		$this->assertSame($origAddress->getCountryId(), 'US');
		$this->assertSame($origAddress->getPostcode(), '13025');
	}

	/**
	 * Test creating Mage_Customer_Model_Address objects from the response message
	 * for each of the suggested addresses.
	 * @test
	 */
	public function testGettingSuggestedAddresses()
	{
		$response = Mage::getModel('eb2caddress/validation_response');
		/* Following in suggested addresses:
		 * Suggestion 1:
		 *   Line1 = 1671 S Clark Street Rd
		 *   City = Foo
		 *   MainDivision = NY = 43
		 *   CountryCode = US
		 *   PostalCode = 13021-9523
		 * Suggestion 2:
		 *   Line1 = 1671 N Clark Street Rd
		 * City = Bar
		 * MainDivision = PA = 51
		 * CountryCode = US
		 * PostalCode = 19406-1234
		 */
		$response->setMessage('<?xml version="1.0" encoding="UTF-8"?><AddressValidationResponse xmlns="http://api.gsicommerce.com/schema/checkout/1.0"><Result><SuggestedAddresses><SuggestedAddress><Line1>1671 S Clark Street Rd</Line1><City>Foo</City><MainDivision>NY</MainDivision><CountryCode>US</CountryCode><PostalCode>13021-9523</PostalCode><FormattedAddress>1671 S Clark Street Rd\nAuburn NY 13021-9523\nUS</FormattedAddress><ErrorLocations><ErrorLocation>Line1</ErrorLocation><ErrorLocation>MainDivision</ErrorLocation><ErrorLocation>PostalCode</ErrorLocation></ErrorLocations></SuggestedAddress><SuggestedAddress><Line1>1671 N Clark Street Rd</Line1><City>Bar</City><MainDivision>PA</MainDivision><CountryCode>US</CountryCode><PostalCode>19406-1234</PostalCode><FormattedAddress>1671 N Clark Street Rd\nAuburn NY 13021-9511\nUS</FormattedAddress><ErrorLocations><ErrorLocation>Line1</ErrorLocation><ErrorLocation>City</ErrorLocation><ErrorLocation>MainDivision</ErrorLocation><ErrorLocation>PostalCode</ErrorLocation></ErrorLocations></SuggestedAddress></SuggestedAddresses></Result></AddressValidationResponse>');
		$suggestions = $response->getAddressSuggestions();
		$this->assertSame(count($suggestions), 2);
		$first = $suggestions[0];
		$this->assertTrue($first instanceof Mage_Customer_Model_Address);
		$this->assertSame($first->getCity(), 'Foo');

		$second = $suggestions[1];
		$this->assertTrue($second instanceof Mage_Customer_Model_Address);
		$this->assertSame($second->getRegionId(), 51);
	}
}
