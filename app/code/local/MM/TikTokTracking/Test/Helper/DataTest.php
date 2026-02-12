<?php

/**
 * Test class for MM_TikTokTracking_Helper_Data
 */
class MM_TikTokTracking_Test_Helper_DataTest extends PHPUnit_Framework_TestCase
{
    protected $_helper;
    
    protected function setUp()
    {
        parent::setUp();
        // Note: In a real OpenMage environment with EcomDev_PHPUnit, we would use Mage::helper()
        // For now, we'll create a basic instance for testing
        $this->_helper = new MM_TikTokTracking_Helper_Data();
    }
    
    /**
     * @test
     */
    public function testIsEnabled_WhenConfigDisabled_ReturnsFalse()
    {
        // This test will fail because Helper class doesn't exist yet
        $result = $this->_helper->isEnabled();
        
        $this->assertFalse($result);
    }
    
    /**
     * @test
     */
    public function testIsEnabled_WhenConfigEnabled_ReturnsTrue()
    {
        // This test will fail because Helper class doesn't exist yet
        $result = $this->_helper->isEnabled();
        
        $this->assertTrue($result);
    }
    
    /**
     * @test
     */
    public function testGetCurrentProductData_WhenNoProduct_ReturnsNull()
    {
        // No product registered, should return null
        $result = $this->_helper->getCurrentProductData();
        
        $this->assertNull($result);
    }
    
    /**
     * @test
     */
    public function testValidateProductData_WhenValid_ReturnsTrue()
    {
        $productData = [
            'sku' => 'TEST-001',
            'price' => 49.99,
            'currency' => 'EUR'
        ];
        
        $result = $this->_helper->validateProductData($productData);
        
        $this->assertTrue($result);
    }
    
    /**
     * @test
     */
    public function testValidateProductData_WhenMissingSku_ReturnsErrors()
    {
        $productData = [
            'sku' => '',
            'price' => 49.99,
            'currency' => 'EUR'
        ];
        
        $result = $this->_helper->validateProductData($productData);
        
        $this->assertIsArray($result);
        $this->assertContains('SKU is required', $result);
    }
    
    /**
     * @test
     */
    public function testValidateProductData_WhenInvalidPrice_ReturnsErrors()
    {
        $productData = [
            'sku' => 'TEST-001',
            'price' => -10,
            'currency' => 'EUR'
        ];
        
        $result = $this->_helper->validateProductData($productData);
        
        $this->assertIsArray($result);
        $this->assertContains('Price must be positive', $result);
    }
    
    /**
     * @test
     */
    public function testHashEmail_ReturnsSha256Hash()
    {
        $email = 'Test@Example.COM';
        
        $result = $this->_helper->hashEmail($email);
        
        // Email should be normalized (lowercase + trim) then hashed
        $expected = hash('sha256', 'test@example.com');
        $this->assertEquals($expected, $result);
        $this->assertEquals(64, strlen($result)); // SHA-256 = 64 char hex
    }
    
    /**
     * @test
     */
    public function testHashEmail_WhenEmpty_ReturnsNull()
    {
        $result = $this->_helper->hashEmail('');
        
        $this->assertNull($result);
    }
}
