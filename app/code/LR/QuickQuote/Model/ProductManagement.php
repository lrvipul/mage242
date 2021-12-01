<?php

namespace LR\QuickQuote\Model;

use Magento\Catalog\Helper\ImageFactory as HelperFactory;

class ProductManagement
{
    const QUICKQUOTE_PRODUCT_LIST = 'QUICKQUOTE_PRODUCT_LIST';

    protected $helperFactory;

    protected $_catalogConfig;

    protected $catalogProductVisibility;

    protected $productCollectionFactory;

    protected $cacheManager;

    protected $layout;

    protected $priceCurrency;

    protected $_storeManager;

   
    protected $_design;

    protected $httpContext;

   
    protected $coreHelper;

    protected $configurableTypeResource;

    
    protected $dataHelper;

    
    public function __construct(
        HelperFactory $helperFactory,
        \Magento\Catalog\Model\Config $catalogConfig,
        \Magento\Catalog\Model\Product\Visibility $catalogProductVisibility,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\Framework\App\CacheInterface $cacheManager,
        \Magento\Framework\View\LayoutInterface $layout,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\View\DesignInterface $design,
        \Magento\Framework\App\Http\Context $httpContext,
        \Magento\Catalog\Helper\Product $productHelper,
        \Magezon\Core\Helper\Data $coreHelper,
        \Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable $configurableTypeResource,
        \LR\QuickQuote\Helper\Data $dataHelper
    ) {
        $this->helperFactory            = $helperFactory;
        $this->_catalogConfig           = $catalogConfig;
        $this->catalogProductVisibility = $catalogProductVisibility;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->cacheManager             = $cacheManager;
        $this->layout                   = $layout;
        $this->productHelper            = $productHelper;
        $this->coreHelper               = $coreHelper;
        $this->priceCurrency            = $priceCurrency;
        $this->_storeManager            = $storeManager;
        $this->_design                  = $design;
        $this->httpContext              = $httpContext;
        $this->configurableTypeResource = $configurableTypeResource;
        $this->dataHelper               = $dataHelper;
    }

    /**
     * @return string
     */
    public function getCacheKey()
    {
        $cache = [
            self::QUICKQUOTE_PRODUCT_LIST,
            $this->priceCurrency->getCurrency()->getCode(),
            $this->_storeManager->getStore()->getId(),
            $this->_design->getDesignTheme()->getId(),
            $this->httpContext->getValue(\Magento\Customer\Model\Context::CONTEXT_GROUP)
        ];
        return implode('_', $cache);
    }

    /**
     * @param  \Magento\Catalog\Model\Product $product
     * @param  array
     * @return array
     */
    public function getProductData($product)
    {
        $canConfigure = $product->canConfigure();
        if (!$canConfigure) {
            $canConfigure = $product->getRequiredOptions();
        }

        $helper = $this->helperFactory->create()->init($product, 'product_thumbnail_image');
        $priceHtml = $this->getProductPriceHtml(
            $product,
            \Magento\Catalog\Pricing\Price\FinalPrice::PRICE_CODE,
            \Magento\Framework\Pricing\Render::ZONE_ITEM_LIST
        );
        if (!$priceHtml) {
            $priceHtml = '';
        }

        return [
            'label'        => $product->getName() . ' ' . $product->getSku(),
            'id'           => $product->getId(),
            'sku'          => strtolower($product->getSku()),
            'name'         => $product->getName(),
            'value'        => $product->getId(),
            'img'          => $helper->getUrl(),
            'price'        => $product->getPrice(),
            'canConfigure' => $canConfigure ? true : false,
            'url'          => $product->getProductUrl(),
            'openmodal'    => $this->isOpenModal($product),
            'priceHtml'    => $priceHtml,
            'type'         => $product->getTypeId(),
            'visible'      => true
        ];
    }

    /**
     * @param  \Magento\Catalog\Model\Product $product
     * @param  array
     * @return array
     */
    public function getProductDataInfo($product)
    {
        $canConfigure = $product->canConfigure();
        if (!$canConfigure) {
            $canConfigure = $product->getRequiredOptions();
        }

        $helper = $this->helperFactory->create()->init($product, 'product_thumbnail_image');
        $priceHtml = $this->getProductPriceHtml(
            $product,
            \Magento\Catalog\Pricing\Price\FinalPrice::PRICE_CODE,
            \Magento\Framework\Pricing\Render::ZONE_ITEM_LIST
        );
        if (!$priceHtml) {
            $priceHtml = '';
        }

        return [
            'label'        => $product->getName() . ' ' . $product->getSku(),
            'id'           => $product->getId(),
            'sku'          => strtolower($product->getSku()),
            'name'         => $product->getName(),
            'value'        => $product->getId(),
            'img'          => $helper->getUrl(),
            //'uom'          => $product->getResource()->getAttribute('product_uom')->getFrontend()->getValue($product),
            'price'        => $product->getPrice(),
            'canConfigure' => $canConfigure ? true : false,
            'url'          => $product->getProductUrl(),
            'openmodal'    => $this->isOpenModal($product),
            'priceHtml'    => $priceHtml,
            'type'         => $product->getTypeId(),
            'visible'      => true
        ];
    }

    /**
     * @return array
     */
    public function getProductList($params)
    {
        $store = $this->_storeManager->getStore();
        if (!isset($params['search']) && !isset($params['skus'])) {
            $productList = $this->cacheManager->load($this->getCacheKey());
            if ($productList) {
                return $this->coreHelper->unserialize($productList);
            }
        }
        $collection = $this->productCollectionFactory->create();
        if ($this->dataHelper->isInstanceSearchEnabled()) {
            $collection->setVisibility($this->catalogProductVisibility->getVisibleInCatalogIds());   
        } else {
            $collection->joinAttribute('visibility', 'catalog_product/visibility', 'entity_id', null, 'inner');    
        }
        $collection = $this->_addProductAttributesAndPrices($collection)->addStoreFilter();
        if (isset($params['search'])) {
            $collection->addAttributeToFilter([
                ['attribute' => 'name', 'like' => '%' . $params['search'] . '%'],
                ['attribute' => 'sku', 'like' => '%' . $params['search'] . '%']
            ]);
        }
        if (isset($params['skus'])) {
            $collection->addAttributeToFilter('sku', ['in' => $params['skus']]);
        } 
        $list = [];
        $parents = [];
        foreach ($collection as $product) {
            if ($this->productHelper->canShow($product)) {
                $list[$product->getId()] = $this->getProductDataInfo($product);
            } else {
                $parentIds = $this->configurableTypeResource->getParentIdsByChild($product->getId());
                $parents = array_merge($parents, $parentIds);
            }
        }

        if ($parents) {
            foreach ($parents as $k => $_id) {
                if (isset($list[$_id])) {
                    unset($parents[$k]);
                }
            }
            $parents = array_values($parents);
            if ($parents) {
                $collection2 = $this->productCollectionFactory->create();
                $collection2 = $this->_addProductAttributesAndPrices($collection2)->addStoreFilter();
                $collection2->addFieldToFilter('entity_id', ['in' => $parents]);
                foreach ($collection2 as $_product) {
                    $_data  = $this->getProductDataInfo($_product);
                    $_data['search'] = $params['search'];
                    $list[$_product->getId()] = $_data;
                }
            }
        }

        $list = array_values($list);
        if (!isset($params['search'])) {
            $this->cacheManager->save(
                $this->coreHelper->serialize($list),
                $this->getCacheKey(),
                [
                    \Magento\Catalog\Model\Product::CACHE_TAG,
                    \Magento\Framework\App\Cache\Type\Block::CACHE_TAG
                ]
            );
        }

      /*  echo "<pre>";
        print_r($list);
        die();
*/
        return $list;
    }

    public function getProductPriceHtml(
        \Magento\Catalog\Model\Product $product,
        $priceType,
        $renderZone = \Magento\Framework\Pricing\Render::ZONE_ITEM_LIST,
        array $arguments = []
    ) {
        if (!isset($arguments['zone'])) {
            $arguments['zone'] = $renderZone;
        }

        /** @var \Magento\Framework\Pricing\Render $priceRender */
        $priceRender = $this->layout->getBlock('product.price.render.default');
        $price       = '';

        if (!$priceRender) {
            $priceRender = $this->layout->createBlock(
                \Magento\Framework\Pricing\Render::class,
                'product.price.render.default',
                ['data' => ['price_render_handle' => 'catalog_product_prices']]
            );
        }

        $price = $priceRender->render($priceType, $product, $arguments);
        return $price;
    }

    /**
     * Add all attributes and apply pricing logic to products collection
     * to get correct values in different products lists.
     * E.g. crosssells, upsells, new products, recently viewed
     *
     * @param \Magento\Catalog\Model\ResourceModel\Product\Collection $collection
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    protected function _addProductAttributesAndPrices(
        \Magento\Catalog\Model\ResourceModel\Product\Collection $collection
    ) {
        return $collection
            ->addMinimalPrice()
            ->addFinalPrice()
            ->addTaxPercents()
            ->addAttributeToSelect( $this->_catalogConfig->getProductAttributes())
            ->addUrlRewrite();
    }

    /**
     * Extend for party extension
     * @param \Magento\Catalog\Model\Product  $product
     * @return boolean
     */
    public function isOpenModal($product) {
        return false;
    }
}
