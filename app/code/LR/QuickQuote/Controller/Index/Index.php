<?php

namespace LR\QuickQuote\Controller\Index;

class Index extends \Magento\Framework\App\Action\Action
{  
   
    protected $resultPageFactory;

    protected $resultForwardFactory;

    protected $dataHelper;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Controller\Result\ForwardFactory $resultForwardFactory,
        \LR\QuickQuote\Helper\Data $dataHelper
    ) {
        parent::__construct($context);
        $this->resultPageFactory    = $resultPageFactory;
        $this->resultForwardFactory = $resultForwardFactory;
        $this->dataHelper           = $dataHelper;
    }

    public function execute()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $customerSession = $objectManager->get('\Magento\Customer\Model\Session');
        $urlInterface = $objectManager->get('\Magento\Framework\UrlInterface');

        if (!$customerSession->isLoggedIn()) {
            $customerSession->setAfterAuthUrl($urlInterface->getCurrentUrl());
            $customerSession->authenticate();
        }
        /** @var \Magento\Framework\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set($this->dataHelper->getTitle());
        if (!$this->dataHelper->isEnabled() || !$this->dataHelper->isGroupValid()) {
            return $resultForward->forward('noroute');
        }
        return $resultPage;
    }
}