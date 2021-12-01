<?php

namespace LR\QuickQuote\Controller\Index;

class LoadProducts extends \Magento\Framework\App\Action\Action
{
   
    protected $productManagement;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \LR\QuickQuote\Model\ProductManagement $productManagement
    ) {
        parent::__construct($context);
        $this->productManagement = $productManagement;
    }

    /**
     * @return \Magento\Framework\Controller\Result\Raw
     */
    public function execute()
    {
        $result['status'] = false;
        try {
            $params = $this->getRequest()->getParams();
            
            $result['products'] = $this->productManagement->getProductList($params);
            $result['status']   = true;
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $result['message'] = $e->getMessage();
        } catch (\Exception $e) {
            $result['message'] = __('An error occurred while processing your request. Please try again later.');
        }

        $this->getResponse()->representJson(
            $this->_objectManager->get(\Magento\Framework\Json\Helper\Data::class)->jsonEncode($result)
        );
        return;
    }
}
