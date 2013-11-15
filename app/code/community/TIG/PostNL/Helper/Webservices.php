<?php
/**
 *                  ___________       __            __   
 *                  \__    ___/____ _/  |_ _____   |  |  
 *                    |    |  /  _ \\   __\\__  \  |  |
 *                    |    | |  |_| ||  |   / __ \_|  |__
 *                    |____|  \____/ |__|  (____  /|____/
 *                                              \/       
 *          ___          __                                   __   
 *         |   |  ____ _/  |_   ____ _______   ____    ____ _/  |_ 
 *         |   | /    \\   __\_/ __ \\_  __ \ /    \ _/ __ \\   __\
 *         |   ||   |  \|  |  \  ___/ |  | \/|   |  \\  ___/ |  |  
 *         |___||___|  /|__|   \_____>|__|   |___|  / \_____>|__|  
 *                  \/                           \/               
 *                  ________       
 *                 /  _____/_______   ____   __ __ ______  
 *                /   \  ___\_  __ \ /  _ \ |  |  \\____ \ 
 *                \    \_\  \|  | \/|  |_| ||  |  /|  |_| |
 *                 \______  /|__|    \____/ |____/ |   __/ 
 *                        \/                       |__|    
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-web at this URL: 
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to servicedesk@totalinternetgroup.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact servicedesk@totalinternetgroup.nl for more information.
 *
 * @copyright   Copyright (c) 2013 Total Internet Group B.V. (http://www.totalinternetgroup.nl)
 * @license     http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 */
 
/**
 * Helper class for CIF operations
 */
class TIG_PostNL_Helper_Webservices extends TIG_PostNL_Helper_Data
{
    /**
     * XML paths for security keys
     */
    const XML_PATH_EXTENSIONCONTROL_UNIQUE_KEY  = 'postnl/general/unique_key';
    const XML_PATH_EXTENSIONCONTROL_PRIVATE_KEY = 'postnl/general/private_key';
    
    /**
     * XML path to updateStatistics on/off switch
     */
    const XML_PATH_SEND_STATISTICS = 'postnl/advanced/send_statistics';
    
    /**
     * Log filename to log all webservices exceptions
     */
    const WEBSERVICES_EXCEPTION_LOG_FILE = 'TIG_PostNL_Webservices_Exception.log';
    
    /**
     * Log filename to webservices CIF calls
     */
    const WEBSERVICES_DEBUG_LOG_FILE = 'TIG_PostNL_Webservices_Debug.log';
    
    /**
     * Check if the extension has been activated with the extension control system by checking if the unique ket and private key
     * have been entered.
     * 
     * @param Mage_Core_Model_Website | null $website
     * 
     * @return boolean
     */
    public function canSendStatistics($website = null)
    {
        $storeId = Mage_Core_Model_App::ADMIN_STORE_ID;
        
        /**
         * First check if sending statistics is enabled
         */
        if ($website !== null) {
            /**
             * If a website was specified, check if the module may send statistics for that website
             */
            $sendStatistics = $website->getConfig(self::XML_PATH_SEND_STATISTICS);
        } else {
            /**
             * otherwise, check if ending statistics was enabled in default settings
             */
            $sendStatistics = Mage::getStoreConfig(self::XML_PATH_SEND_STATISTICS, $storeId);
        }
        
        if (!$sendStatistics) {
            return false;
        }
        
        /**
         * Check if the security keys have been entered.
         */
        $privateKey = Mage::getStoreConfig(self::XML_PATH_EXTENSIONCONTROL_PRIVATE_KEY, $toreId);
        $uniqueKey  = Mage::getStoreConfig(self::XML_PATH_EXTENSIONCONTROL_UNIQUE_KEY, $storeId);
        
        if (empty($privateKey) || empty($uniqueKey)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Logs a webservice request and response for debug purposes.
     * 
     * N.B.: if file logging is enabled, the log will be forced
     * 
     * @param SoapClient $client
     * 
     * @return TIG_PostNL_Helper_Webservices
     * 
     * @see Mage::log()
     * 
     * @todo add additional debug options
     * 
     */
    public function logWebserviceCall($client)
    {
        if (!$this->isLoggingEnabled()) { 
            return $this;
        }
        
        $requestXml = $this->formatXml($client->getLastRequest());
        $responseXML = $this->formatXml($client->getLastResponse());
        
        $logMessage = "Request sent:\n"
                    . $requestXml
                    . "\nResponse recieved:\n"
                    . $responseXML;
                    
        Mage::log($logMessage, Zend_Log::DEBUG, self::WEBSERVICES_DEBUG_LOG_FILE, true);
        
        return $this;
    }
    
    /**
     * Logs a webservice exception in the database and/or a log file
     * 
     * N.B.: if file logging is enabled, the log will be forced
     * 
     * @param Mage_Core_Exception | TIG_PostNL_Model_Core_Webservices_Exception $exception
     * 
     * @return TIG_PostNL_Helper_Webservices
     * 
     * @see Mage::logException()
     * 
     * @todo add additional debug options
     */
    public function logWebserviceException($exception)
    {
        if (!$this->isExceptionLoggingEnabled()) {
            return $this;
        }
        
        if ($exception instanceof TIG_PostNL_Model_Core_Webservices_Exception) {
            $requestXml = $this->formatXml($exception->getRequestXml());
            $responseXML = $this->formatXml($exception->getResponseXml());
            
            $logMessage = '';
            
            $errorNumbers = $exception->getErrorNumbers();
            if (!empty($errorNumbers)) {
                $errorNumbers = implode(', ', $errorNumbers);
                $logMessage .= "Error numbers recieved: {$errorNumbers}\n";
            }
            
            $logMessage .= "<<< REQUEST SENT >>>\n"
                        . $requestXml
                        . "\n<<< RESPONSE RECIEVED >>>\n"
                        . $responseXML;
                        
            Mage::log($logMessage, Zend_Log::ERR, self::WEBSERVICES_EXCEPTION_LOG_FILE, true);
        }
        
        Mage::log("\n" . $exception->__toString(), Zend_Log::ERR, self::WEBSERVICES_EXCEPTION_LOG_FILE, true);
        
        return $this;
    }
}