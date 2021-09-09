<?php

namespace Concordpay\Payment\Block\Widget;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Http\Context;
use Magento\Framework\View\Element\Template;
use Magento\Sales\Model\Order\Config;
use Magento\Sales\Model\OrderFactory;
use Concordpay\Payment\Model\Concordpay;

/**
 * Abstract class for Cash On Delivery and Bank Transfer payment method form
 */
class Redirect extends Template
{
    /**
     * @var Concordpay
     */
    protected $Config;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var Session
     */
    protected $_customerSession;

    /**
     * @var OrderFactory
     */
    protected $_orderFactory;

    /**
     * @var Config
     */
    protected $_orderConfig;

    /**
     * @var Context
     */
    protected $httpContext;

    /**
     * @var string
     */
    protected $_template = 'html/concordpay_form.phtml';

    /**
     * @param Template\Context                  $context
     * @param \Magento\Checkout\Model\Session   $checkoutSession
     * @param Session                           $customerSession
     * @param OrderFactory                      $orderFactory
     * @param Config                            $orderConfig
     * @param Context                           $httpContext
     * @param Concordpay                        $paymentConfig
     * @param array                             $data
     */
    public function __construct(
        Template\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        Session $customerSession,
        OrderFactory $orderFactory,
        Config $orderConfig,
        Context $httpContext,
        Concordpay $paymentConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_checkoutSession = $checkoutSession;
        $this->_customerSession = $customerSession;
        $this->_orderFactory    = $orderFactory;
        $this->_orderConfig     = $orderConfig;
        $this->_isScopePrivate  = true;
        $this->httpContext      = $httpContext;
        $this->Config           = $paymentConfig;
    }

    /**
     * Get instructions text from config
     *
     * @return null|string
     */
    public function getGateUrl()
    {
        //print_r ($this->Config->getGateUrl()); die;
        return $this->Config->getGateUrl();
    }

    /**
     * Получить сумму к оплате
     *
     * @return float|null
     */
    public function getAmount()
    {
        $orderId = $this->_checkoutSession->getLastOrderId();
        if ($orderId) {
            $incrementId = $this->_checkoutSession->getLastRealOrderId();

            return $this->Config->getAmount($incrementId);
        }

        return null;
    }

    /**
     * Получить данные формы
     *
     * @return string|null
     */
    public function getPostData()
    {
        $orderId = $this->_checkoutSession->getLastOrderId();
        if ($orderId) {
            $incrementId = $this->_checkoutSession->getLastRealOrderId();
            $fields      = $this->Config->getPostData($incrementId);

            return $this->Config->getFormFields($fields);
        }

        return null;
    }

    /**
     * Получить Pay URL
     *
     * @return string
     */
    public function getPayUrl()
    {
        $baseUrl = $this->getUrl("concordpay/url");

        //print_R ($baseUrl);die;
        return "{$baseUrl}concordpaysuccess";
    }

    /**
     * @return \Magento\Sales\Model\Order|null
     */
    public function getLastOrder()
    {
        $orderId  = $this->_checkoutSession->getLastOrderId();
        $order    = $this->_orderFactory->create();
        if ($order->getResource()) {
            $order->getResource()->load($order, $orderId);
        }

        return $order;
    }

    /**
     * @param string $template
     * @return Template
     */
    public function setTemplate($template)
    {
        $order = $this->getLastOrder();
        if (!$order) {
            return parent::setTemplate('');
        }
        $payment = $order->getPayment();
        if (!$payment) {
            return parent::setTemplate('');
        }

        $method = $payment->getMethodInstance();
        if ($method && $method->getCode() === Concordpay::METHOD_CODE) {
            return parent::setTemplate($template);
        }

        return parent::setTemplate('');
    }
}
