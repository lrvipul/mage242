<?php

namespace LR\QuickQuote\Controller\Index;

use \Magento\Framework\Exception\LocalizedException;

class Item extends \Magento\Framework\App\Action\Action
{
	
    protected $objectFactory;

    protected $configurationPool;

    protected $itemProcessor;

    protected $layoutFactory;

    protected $productFactory;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\DataObject\Factory $objectFactory,
        \Magento\Catalog\Helper\Product\ConfigurationPool $configurationPool,
        \Magento\Quote\Model\Quote\Item\Processor $itemProcessor,
        \Magento\Framework\View\LayoutFactory $layoutFactory,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Framework\Pricing\Helper\Data $pricingHelper
    ) {
        parent::__construct($context);
		$this->objectFactory     = $objectFactory;
		$this->configurationPool = $configurationPool;
		$this->itemProcessor     = $itemProcessor;
		$this->layoutFactory     = $layoutFactory;
		$this->productFactory    = $productFactory;
		$this->pricingHelper     = $pricingHelper;
    }

    public function getQty()
    {
		$data = $this->getRequest()->getParams();
    	$qty = 1;
		if (isset($data['qty'])) $qty = $buyRequest['qty'] = $data['qty'];
		return $qty;
    }

    public function execute()
    {
        $result['status'] = false;
		$data = $this->getRequest()->getParams();
		$resultRedirect = $this->resultRedirectFactory->create();
		
        if ($data) {
            try {
				$product = $this->productFactory->create();
				$product->load($data['product']);

				$options = [];
				if (!isset($data['options'])) $data['options'] = '';
				parse_str($data['options'], $options);
				if (isset($options['super_group']) && $options['super_group']) {
					$price = 0;
					$html = '<dl class="item-options">';
					foreach ($options['super_group'] as $productId => $qty) {
						if ($qty) {
							$product1 = $this->productFactory->create();
							$product1->load($productId);
							$buyeRequest = $this->objectFactory->create([
								'product' => $productId,
								'qty' => $qty
							]);
							$item = $this->getItem($product1, $buyeRequest);
							$html .= '<dt>' . $qty . ' x ' . $product1->getName() . '</dt>';
							$html .= '<dd>' . $this->pricingHelper->currency($item->getProduct()->getFinalPrice(), true, false) . '</dd>';
							$price += $item->getProduct()->getFinalPrice() * $qty;
						}
					}
					$html .= '<dl>';
					$result['price']   = $price;
					$result['options'] = $html;
				} else {
					$options = $this->objectFactory->create($options);
					$item = $this->getItem($product, $options);
					if ($item) {
						if((isset($data['newprice']) && $data['newprice'] > 0))
						{
							$result['price'] = $data['newprice'];
						}
						else
						{
							$result['price'] = $item->getProduct()->getFinalPrice($this->getQty());
						}
						
						$optionList = $this->configurationPool->getByProductType($item->getProductType())->getOptions($item);
						$layout = $this->layoutFactory->create();
						$block  = $layout->createBlock('\Magento\Framework\View\Element\Template');
						$block->setTemplate('LR_QuickQuote::product/options.phtml')->setOptionList($optionList);
						$result['options'] = $block->toHtml();
					}
				}
				$result['status'] = true;
            } catch (LocalizedException $e) {
                $result['message'] = $e->getMessage();
            } catch (\Exception $e) {
            	$result['message'] = __('Something went wrong while processing the request.');
            }
        }
        $this->getResponse()->representJson(
            $this->_objectManager->get(\Magento\Framework\Json\Helper\Data::class)->jsonEncode($result)
        );
        return;
    }

	
	public function getItem($product, $buyRequest)
	{
		$data = $this->getRequest()->getParams();
		$candidate = $this->getCandidate($product, $buyRequest);
		if (!is_string($candidate)) {
			$item = $this->itemProcessor->init($candidate, $buyRequest);
			$item->setOptions($candidate->getCustomOptions());
			$item->setProduct($product);
			$item->setQty($this->getQty());
			return $item;
		}
	}

	public function getCandidate($product, $buyRequest)
	{
		$cartCandidates = $product->getTypeInstance()->prepareForCartAdvanced($buyRequest, $product, \Magento\Catalog\Model\Product\Type\AbstractType::PROCESS_MODE_FULL);

		/**
		 * Error message
		 */
		if (is_string($cartCandidates) || $cartCandidates instanceof \Magento\Framework\Phrase) {
		    return strval($cartCandidates);
		}

		/**
		 * If prepare process return one object
		 */
		if (!is_array($cartCandidates)) {
		    $cartCandidates = [$cartCandidates];
		}

		return $cartCandidates[0];
	}
}