<?php

namespace FintechFab\Payments;

use Monolog\Formatter\JsonFormatter;
use Monolog\Logger;

/**
 * Class PaymentsLog
 *
 * @package FintechFab\Payments
 */
class PaymentsLog
{
	protected $logger = null;

	protected $transfer_id;

	protected $message = null;

	protected $requestAction = null;

	/**
	 * @param Logger $logger
	 */
	public function __construct(Logger $logger)
	{
		$this->logger = $logger;
	}

	/**
	 * подготовить запись в лог
	 *
	 * @param int    $channelId
	 * @param string $channelName
	 * @param int    $transferId
	 * @param string $requestAction
	 * @param string $requestText
	 * @param string $responseStatus
	 * @param        $responseText
	 * @param        $errorCode
	 * @param        $responseError
	 * @param int    $requestTime
	 * @param int    $responseTime
	 */
	public function prepare($channelId, $channelName, $transferId, $requestAction, $requestText, $responseStatus, $responseText, $errorCode, $responseError, $requestTime, $responseTime)
	{
		$attributes = array(
			'channel_id'      => $channelId,
			'transfer_id'     => $transferId,
			'channel_name'    => $channelName,
			'request_action'  => $requestAction,
			'request_text'    => $requestText,
			'response_status' => $responseStatus,
			'response_text'   => $responseText,
			'error_code'      => $errorCode,
			'response_error'  => $responseError,
			'dt_request'      => PaymentChannelAbstract::timetostr($requestTime),
			'dt_response'     => PaymentChannelAbstract::timetostr($responseTime),
		);

		$jsonFormatter = new JsonFormatter;

		$this->message = $jsonFormatter->format($attributes);
		$this->requestAction = $requestAction;

		$this->transfer_id = $transferId;
	}

	public function writeFail()
	{
		$this->logger->addError($this->message, ['transfer_id' => $this->transfer_id]);
	}

	public function writeSuccessful()
	{
		if ($this->_isLogAction()) {
			$this->logger->addInfo($this->message, ['transfer_id' => $this->transfer_id]);
		}
	}

	/**
	 * некоторые действия не логируем в базу
	 *
	 * @return bool
	 */
	private function _isLogAction()
	{

		if ($this->requestAction == 'providers2') {
			return false;
		}

		return true;

	}
}