<?php

namespace Concordpay\Payment\Controller\Url;

use Magento\Authorizenet\Model\DirectPost;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * Class ConcordpaySuccess
 * @package Concordpay\Payment\Controller\Url
 */
class ConcordpaySuccess extends Action
{
    /** @var PageFactory */
    protected $resultPageFactory;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;
        parent::__construct($context);
    }

    /**
     * Load the page defined
     *
     * @return void
     */
    public function execute()
    {
        // Load model.
        /* @var $paymentMethod DirectPost */
        $paymentMethod = $this->_objectManager->create('Concordpay\Payment\Model\Concordpay');

        //get request data
        $data = $this->getRequest()->getPostValue();
        if (empty($data)) {
            $callback = json_decode(file_get_contents("php://input"));
            $data = [];
            foreach ($callback as $key => $val) {
                $data[$key] = $val;
            }
        }

        $response = $paymentMethod->processResponse($data);
//        return $this->resultPageFactory->create()->setPath('checkout/cart');
        if ($response) {
            $this->_redirect('checkout/onepage/success');
        } else {
            $this->_redirect('checkout/onepage/failure');
        }
    }
}
