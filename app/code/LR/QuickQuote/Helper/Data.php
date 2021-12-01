<?php


namespace LR\QuickQuote\Helper;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * @param \Magento\Framework\App\Helper\Context      $context         
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager    
     * @param \Magento\Customer\Model\Session            $customerSession 
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Customer\Model\Session $customerSession
    ) {
        parent::__construct($context);
        $this->storeManager    = $storeManager;
        $this->customerSession = $customerSession;
    }
   
    /**
     * @param  string $key
     * @param  null|int $store
     * @return null|string
     */
    public function getConfig($key, $store = null)
    {
		$store     = $this->storeManager->getStore($store);
		$websiteId = $store->getWebsiteId();
        $result = $this->scopeConfig->getValue(
            'quickquote/' . $key,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store);
        return $result;
    }

    /**
     * @return boolean
     */
    public function isEnabled()
    {
        return $this->getConfig('general/enabled');
    }

    /**
     * @return boolean
     */
    public function isInstanceSearchEnabled()
    {
        return false;
        return $this->getConfig('general/instant_search');
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->getConfig('general/title');   
    }

    /**
     * @return boolean
     */
    public function isGroupValid()
    {
        if ($this->getConfig('general/customer_groups')) {
            $customerGroupId = $this->customerSession->getCustomer()->getGroupId();
            $customerGroups  = explode(',', $this->getConfig('general/customer_groups'));
            return in_array($customerGroupId, $customerGroups);
        }
        return true;
    }
}