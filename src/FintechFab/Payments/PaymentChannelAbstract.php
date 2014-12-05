<?php

namespace FintechFab\Payments;

use stdClass;

/**
 * Class PaymentChannelAbstract
 *
 * @package FintechFab\Payments
 */
class PaymentChannelAbstract implements PaymentChannelInterface
{

	/**
	 * @var bool включен тестовый режим?
	 */
	public $test = false;

	/**
	 * @var string боевая ссылка к АПИ
	 * @example https://www.kp-dealer.ru:8080/KPDealerWeb/KPBossHttpServer
	 */
	public $apiUrl;

	/**
	 * @var string тестовая ссылка к АПИ
	 * @example https://test.creditpilot.ru:8080/KPDealerWeb/KPBossHttpServer
	 */
	public $apiUrlTest;

	/**
	 * запись в логе после выполнения запросов
	 *
	 * @var PaymentsLog
	 */
	public $paymentLog;

	/**
	 * @var integer id канала
	 */
	protected $channelId;

	/**
	 * @var string название канала
	 */
	protected $channelName = '';

	/**
	 * @var stdClass
	 */
	protected $response;

	protected $responseCode;

	protected $errorCode;

	protected $responseError;

	protected $responseText;

	/**
	 * инициализация
	 */
	public function init()
	{

		if ($this->test || !$this->apiUrl) {
			$this->apiUrl = $this->apiUrlTest;
		}

		$this->cleanup();

	}

	/**
	 * очистка данных от возможных предыдущих обработок
	 */
	protected function cleanup()
	{
		$this->response = null;
		$this->responseCode = null;
		$this->errorCode = null;
		$this->responseText = null;
		$this->responseError = null;

	}

	/**
	 * @return PaymentsLog
	 */
	public function getLog()
	{
		return $this->paymentLog;
	}

	/**
	 * Сгенерировать URL для запроса
	 *
	 * @param $sActionName
	 * @param $aMethodArgs
	 *
	 * @return string
	 */
	protected function _buildUrl($sActionName, $aMethodArgs = array())
	{
		$sMethodArgs = '&' . http_build_query($aMethodArgs);
		$sMethodArgs = rtrim($sMethodArgs, '&');
		$sUrl = $this->apiUrl . '?actionName=' . $sActionName . $sMethodArgs;

		return $sUrl;
	}

	/**
	 * была ошибка?
	 *
	 * @return string
	 */
	public function isError()
	{
		return !!$this->errorCode;
	}

	/**
	 * текст ошибки (если она была)
	 *
	 * @return string
	 */
	public function getErrorMessage()
	{
		if ($this->isError()) {
			return '[' . $this->errorCode . ']' . $this->responseError;
		}

		return '';
	}

	/**
	 * @param int $time
	 *
	 * @return string
	 */
	public static function timetostr($time = null)
	{
		if ($time === null) {
			$time = time();
		}

		return date("Y-m-d H:i:s", $time);
	}

	public function getResponseText()
	{
		return $this->responseText;
	}
}
