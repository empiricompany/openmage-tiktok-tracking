<?php

/**
 * MM_TikTokTracking Helper
 * 
 * Provides configuration, data extraction, validation, and hashing methods
 * for TikTok Pixel tracking integration.
 */
class MM_TikTokTracking_Helper_Data extends Mage_Core_Helper_Abstract
{
    const XML_PATH_ENABLED = 'mm_tiktok_tracking/general/enabled';
    const XML_PATH_PIXEL_ID = 'mm_tiktok_tracking/general/pixel_id';
    const XML_PATH_ADVANCED_MATCHING = 'mm_tiktok_tracking/advanced/enable_advanced_matching';
    const XML_PATH_DEBUG_MODE = 'mm_tiktok_tracking/advanced/debug_mode';
    
    /**
     * Check if module is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled($storeId = null)
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_ENABLED, $storeId);
    }
    
    /**
     * Get TikTok Pixel ID from configuration
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getPixelId($storeId = null)
    {
        if (!$this->isEnabled($storeId)) {
            return null;
        }
        
        return Mage::getStoreConfig(self::XML_PATH_PIXEL_ID, $storeId);
    }
    
    /**
     * Check if Advanced Matching is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isAdvancedMatchingEnabled($storeId = null)
    {
        if (!$this->isEnabled($storeId)) {
            return false;
        }
        
        return Mage::getStoreConfigFlag(self::XML_PATH_ADVANCED_MATCHING, $storeId);
    }
    
    /**
     * Check if Debug Mode is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isDebugModeEnabled($storeId = null)
    {
        if (!$this->isEnabled($storeId)) {
            return false;
        }
        
        return Mage::getStoreConfigFlag(self::XML_PATH_DEBUG_MODE, $storeId);
    }
    
    /**
     * Log data to tiktok_tracking.log file
     *
     * @param mixed $data
     * @return void
     */
    public function log($data)
    {
        // Zend_Log::DEBUG is available in OpenMage, Mage::LOG_DEBUG in Maho
        $level = defined('Zend_Log::DEBUG') ? Zend_Log::DEBUG : Mage::LOG_DEBUG;
        Mage::log($data, $level, 'tiktok_tracking.log');
    }
    
    /**
     * Get current product data from registry
     *
     * @return array|null
     */
    public function getCurrentProductData()
    {
        $product = Mage::registry('current_product');
        
        if (!$product || !$product->getId()) {
            return null;
        }
        
        return [
            'id' => $product->getId(),
            'sku' => $product->getSku(),
            'name' => $product->getName(),
            'price' => $product->getFinalPrice(),
            'currency' => Mage::app()->getStore()->getCurrentCurrencyCode()
        ];
    }
    
    /**
     * Validate product data for TikTok tracking
     *
     * @param array $productData
     * @return bool|array True if valid, array of errors otherwise
     */
    public function validateProductData($productData)
    {
        $errors = [];
        
        if (empty($productData['sku'])) {
            $errors[] = 'SKU is required';
        }
        
        if (!isset($productData['price']) || $productData['price'] < 0) {
            $errors[] = 'Price must be positive';
        }
        
        if (empty($productData['currency'])) {
            $errors[] = 'Currency is required';
        }
        
        return empty($errors) ? true : $errors;
    }
    
    /**
     * Hash email for Advanced Matching (SHA-256)
     *
     * @param string $email
     * @return string|null
     */
    public function hashEmail($email)
    {
        if (empty($email)) {
            return null;
        }
        
        // Normalize: lowercase + trim
        $normalized = trim(strtolower($email));
        
        return hash('sha256', $normalized);
    }
    
    /**
     * Hash phone for Advanced Matching (SHA-256)
     *
     * @param string $phone
     * @return string|null
     */
    public function hashPhone($phone)
    {
        if (empty($phone)) {
            return null;
        }
        
        // Normalize: remove non-digits, lowercase, trim
        $normalized = preg_replace('/[^0-9]/', '', trim(strtolower($phone)));
        
        return hash('sha256', $normalized);
    }
}
