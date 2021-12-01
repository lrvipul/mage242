<?php

namespace LR\QuickQuote\Controller\Index;

use Magento\Catalog\Helper\ImageFactory as HelperFactory;

class LoadChildrens extends \Magento\Framework\App\Action\Action
{

    protected $scopeConfig;

    protected $helperFactory;

    protected $productManagement;

    protected $productFactory;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        HelperFactory $helperFactory,
        \LR\QuickQuote\Model\ProductManagement $productManagement,
        \Magento\Catalog\Model\ProductFactory $productFactory
    ) {
        parent::__construct($context);
        $this->scopeConfig       = $scopeConfig;
        $this->helperFactory     = $helperFactory;
        $this->productManagement = $productManagement;
        $this->productFactory    = $productFactory;
    }

    private function usedGroupedImage()
    {
        return $this->scopeConfig->getValue(
            \Magento\GroupedProduct\Block\Cart\Item\Renderer\Grouped::CONFIG_THUMBNAIL_SOURCE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return \Magento\Framework\Controller\Result\Raw
     */
    public function execute()
    {
    	$result['status'] = false;
    	try {
            $post = $this->getRequest()->getPostValue();

            if (isset($post['type']) && isset($post['id'])) {
                $childrens = [];
                $product = $this->productFactory->create();
                $product->load($post['id']);
                $helper = $this->helperFactory->create()->init($product, 'product_thumbnail_image');

                switch ($post['type']) {
                    case 'grouped':
                        $associatedProducts = $product->getTypeInstance(true)->getAssociatedProducts($product);
                        foreach ($associatedProducts as $product) {
                            $item = $this->productManagement->getProductData($product);
                            if ($this->usedGroupedImage()) {
                                $item['img'] = $helper->getUrl();
                            }
                            $childrens[] = $item;
                        }
                    break;
                }
                $result['childrens'] = $childrens;
                $result['status'] = true;
            }
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