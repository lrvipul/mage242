<?php

namespace LR\QuickQuote\Controller;

use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;
use Magento\Framework\DataObject;

class Router implements RouterInterface
{
    
    protected $dispatched;

    protected $actionFactory;

    protected $dataHelper;


    public function __construct(
        ActionFactory $actionFactory,
        \LR\QuickQuote\Helper\Data $dataHelper
    ) {
        $this->actionFactory = $actionFactory;
        $this->dataHelper    = $dataHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function match(RequestInterface $request)
    {
        if (!$this->dispatched && $this->dataHelper->isEnabled()) {
            $pathInfo = trim($request->getPathInfo(), '/');
            $result   = $this->processUrlKey($pathInfo);

            if ($result) {
                $request->setModuleName($result->getModuleName())
                    ->setControllerName($result->getControllerName())
                    ->setActionName($result->getActionName());
                if ($params = $result->getParams()) {
                    $request->setParams($params);
                }

                $request->setAlias(\Magento\Framework\Url::REWRITE_REQUEST_PATH_ALIAS, $pathInfo);
                $request->setDispatched(true);
                $this->dispatched = true;
                return $this->actionFactory->create(
                    'Magento\Framework\App\Action\Forward',
                    ['request' => $request]
                );
            }
        }
    }

    /**
     * @param  string $identifier
     * @return boolean
     */
    protected function processUrlKey($identifier)
    {
        $result = false;
        $route  = $this->dataHelper->getConfig('general/route');
        if ($route) {
            $paths = explode("/", $identifier);
            if (count($paths) == 1 && ($identifier == $route)) {
                $result = new DataObject([
                    'module_name'     => 'quickquote',
                    'controller_name' => 'index',
                    'action_name'     => 'index'
                ]);
            }
        }
        return $result;
    }
}
