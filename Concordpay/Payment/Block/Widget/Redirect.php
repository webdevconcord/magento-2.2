<?php

namespace Concordpay\Payment\Block\Widget;

/**
 * Abstract class for Cash On Delivery and Bank Transfer payment method form
 */
use \Magento\Framework\View\Element\Template;


class Redirect extends Template {
	/**
	 * @var \Concordpay\Payment\Model\Concordpay
	 */
	protected $Config;

	/**
	 * @var \Magento\Checkout\Model\Session
	 */
	protected $_checkoutSession;

	/**
	 * @var \Magento\Customer\Model\Session
	 */
	protected $_customerSession;

	/**
	 * @var \Magento\Sales\Model\OrderFactory
	 */
	protected $_orderFactory;

	/**
	 * @var \Magento\Sales\Model\Order\Config
	 */
	protected $_orderConfig;

	/**
	 * @var \Magento\Framework\App\Http\Context
	 */
	protected $httpContext;

	/**
	 * @var string
	 */
	protected $_template = 'html/concordpay_form.phtml';


	/**
	 * @param Template\Context $context
	 * @param \Magento\Checkout\Model\Session $checkoutSession
	 * @param \Magento\Customer\Model\Session $customerSession
	 * @param \Magento\Sales\Model\OrderFactory $orderFactory
	 * @param \Magento\Sales\Model\Order\Config $orderConfig
	 * @param \Magento\Framework\App\Http\Context $httpContext
	 * @param \Concordpay\Payment\Model\Concordpay $paymentConfig
	 * @param array $data
	 */
	public function __construct(
		\Magento\Framework\View\Element\Template\Context $context,
		\Magento\Checkout\Model\Session $checkoutSession,
		\Magento\Customer\Model\Session $customerSession,
		\Magento\Sales\Model\OrderFactory $orderFactory,
		\Magento\Sales\Model\Order\Config $orderConfig,
		\Magento\Framework\App\Http\Context $httpContext,
		\Concordpay\Payment\Model\Concordpay $paymentConfig,
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
	public function getGateUrl() {
		//print_r ($this->Config->getGateUrl()); die;
		return $this->Config->getGateUrl();
	}


	/**
	 * Получить сумму к оплате
	 *
	 * @return float|null
	 */
	public function getAmount() {
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
	 * @return array|null
	 */
	public function getPostData() {
		$orderId = $this->_checkoutSession->getLastOrderId();
		if ($orderId) {
			$incrementId = $this->_checkoutSession->getLastRealOrderId();
			$fields = $this->Config->getPostData($incrementId);
			return $this->Config->getFormFields($fields);
		}

		return null;
	}


	/**
	 * Получить Pay URL
	 *
	 * @return array
	 */
	public function getPayUrl() {

		$baseUrl = $this->getUrl("concordpay/url");

		//print_R ($baseUrl);die;
		return "{$baseUrl}concordpaysuccess";
	}
}