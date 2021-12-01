<?php

namespace LR\QuickQuote\Plugin\Controller\Product;

use \Magento\Framework\Controller\ResultFactory;

class View
{
    /**
     * @var ResultFactory
     */
    protected $resultFactory;

    /**
     * @var \Magento\Framework\Registry
     */
    protected $_coreRegistry;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_subjectManager;

    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    protected $productFactory;

    /**
     * @var \Magento\Framework\Pricing\PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var \Magento\Framework\View\DesignInterface
     */
    protected $_design;

  
    protected $httpContext;

    protected $cacheManager;

  
    protected $coreHelper;

    public function __construct(
        ResultFactory $resultFactory,
        \Magento\Framework\Registry $coreRegistry,
        \Magento\Framework\ObjectManagerInterface $subjectManager,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\View\DesignInterface $design,
        \Magento\Framework\App\Http\Context $httpContext,
        \Magento\Framework\App\CacheInterface $cacheManager,
        \Magezon\Core\Helper\Data $coreHelper
    ) {
        $this->resultFactory   = $resultFactory;
        $this->_coreRegistry   = $coreRegistry;
        $this->_subjectManager = $subjectManager;
        $this->productFactory  = $productFactory;
        $this->priceCurrency   = $priceCurrency;
        $this->_storeManager   = $storeManager;
        $this->_design         = $design;
        $this->httpContext     = $httpContext;
        $this->cacheManager    = $cacheManager;
        $this->coreHelper      = $coreHelper;
    }

    /**
     * Initialize requested product object
     *
     * @return ModelProduct
     */
    protected function _initProduct($controller)
    {
        $productId  = (int)$controller->getRequest()->getParam('id');
        /** @var \Magento\Catalog\Helper\Product $product */
        $product = $this->productFactory->create();
        return $product->load($productId);
    }

	public function aroundExecute(
		\Magento\Catalog\Controller\Product\View $subject,
		\Closure $proceed
	) {
        $params  = $subject->getRequest()->getParams();
        if (isset($params['lr_qq'])) {
            $product = $this->_initProduct($subject);
            if ($product->getId()) {
                $html = $this->getHtmlFromCache($subject);
                if ($html) {
                    $data['html'] = $html;
                    $subject->getResponse()->representJson(
                        $this->_subjectManager->get('Magento\Framework\Json\Helper\Data')->jsonEncode($data)
                    );
                    return;
                }
            }
        }

        $result  = $proceed();

        $product = $this->getProduct();
        if (isset($params['lr_qq']) && $product) {
            $resultLayout = $this->resultFactory->create(ResultFactory::TYPE_LAYOUT);
            $resultLayout->addHandle('quickquote_popup');
            $resultLayout->addHandle('quickquote_popup_' . $product->getTypeId());
            $html = $result->getLayout()->getBlock('ajax.product.info')->toHtml();
            $this->saveToCache($subject, $html);
            $data['html'] = $html;
            $subject->getResponse()->representJson(
                $this->_subjectManager->get('Magento\Framework\Json\Helper\Data')->jsonEncode($data)
            );
            return;
        }

		return $result;
	}

    /**
     * @return \Magento\Catalog\Model\Product
     */
    public function getProduct()
    {
        return $this->_coreRegistry->registry('current_product');
    }

    /**
     * @return string
     */
    public function getHtmlFromCache($subject)
    {
        $product = $this->_initProduct($subject);

        $html = $this->cacheManager->load($this->getCacheKeyInfo($subject));
        if ($html) {
            $data = $this->coreHelper->unserialize($html);
            return $data['html'];
        }
    }

    public function saveToCache($subject, $html) {
        $this->cacheManager->save(
            $this->coreHelper->serialize(['html' => $html]),
            $this->getCacheKeyInfo($subject),
            [
                \Magento\Catalog\Model\Product::CACHE_TAG,
                \Magento\Framework\App\Cache\Type\Block::CACHE_TAG
            ]
        );
    }

    public function getCacheKeyInfo($subject)
    {
        $cache = [
            'QUICKQUOTE_PRODUCT_CACHE_HTML',
            $this->priceCurrency->getCurrency()->getCode(),
            $this->_storeManager->getStore()->getId(),
            $this->_design->getDesignTheme()->getId(),
            $this->httpContext->getValue(\Magento\Customer\Model\Context::CONTEXT_GROUP),
            $subject->getRequest()->getParam('id')
        ];
        return implode('_', $cache);
    }
}