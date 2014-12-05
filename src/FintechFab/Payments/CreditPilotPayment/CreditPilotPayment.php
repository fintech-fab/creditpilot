<?php
namespace FintechFab\Payments\CreditPilotPayment;

use FintechFab\Payments\EloquentHandler\EloquentHandler;
use FintechFab\Payments\PaymentChannelAbstract;
use FintechFab\Payments\PaymentsInfo;
use FintechFab\Payments\PaymentsLog;
use Illuminate\Database\Capsule\Manager as Capsule;
use Monolog\Logger;
use stdClass;

/**
 * Class CreditPilotPayment
 *
 * @package FintechFab\Payments\CreditPilotPayment
 */
class CreditPilotPayment extends PaymentChannelAbstract
{
	const CHANNEL_CREDIT_PILOT_MTS = 1;
	const CHANNEL_CREDIT_PILOT_MEGAFON = 2;
	const CHANNEL_CREDIT_PILOT_BEELINE = 3;
	const CHANNEL_CREDIT_PILOT_TELE2 = 4;
	const CHANNEL_CREDIT_PILOT_BANK_CARD = 5;

	public $channelIds = array(
		self::CHANNEL_CREDIT_PILOT_MTS,
		self::CHANNEL_CREDIT_PILOT_MEGAFON,
		self::CHANNEL_CREDIT_PILOT_BEELINE,
		self::CHANNEL_CREDIT_PILOT_TELE2,
		self::CHANNEL_CREDIT_PILOT_BANK_CARD,
	);

	public $channelName = 'КредитПилот';

	protected  $user = '';
	protected  $password = '';

	protected  $apiUrl = 'https://www.kp-dealer.ru:8080/KPDealerWeb/KPBossHttpServer';
	protected  $apiUrlTest = 'https://test.creditpilot.ru:8080/KPDealerWeb/KPBossHttpServer';

	const PROVIDER_MOBILE_TEST = 540792152;
	const PROVIDER_CARD_TEST = 100318717;

	private static $providers = array(
		self::CHANNEL_CREDIT_PILOT_TELE2     => self::PROVIDER_MOBILE_TEST,
		self::CHANNEL_CREDIT_PILOT_MTS       => self::PROVIDER_MOBILE_TEST,
		self::CHANNEL_CREDIT_PILOT_BEELINE   => self::PROVIDER_MOBILE_TEST,
		self::CHANNEL_CREDIT_PILOT_MEGAFON   => self::PROVIDER_MOBILE_TEST,
		self::CHANNEL_CREDIT_PILOT_BANK_CARD => self::PROVIDER_CARD_TEST,
	);

	private static $providerNames = array(
		self::CHANNEL_CREDIT_PILOT_TELE2     => 'Теле 2',
		self::CHANNEL_CREDIT_PILOT_MTS       => 'МТС',
		self::CHANNEL_CREDIT_PILOT_BEELINE   => 'Билайн',
		self::CHANNEL_CREDIT_PILOT_MEGAFON   => 'Мегафон',
		self::CHANNEL_CREDIT_PILOT_BANK_CARD => 'Visa/MasterCard'
	);

	private $provider = null;
	private $providerMinSum = null;
	private $providerMaxSum = null;
	private $workProviders = array();

	protected $error = null;

	/**
	 * @param $channelId
	 *
	 * @return string
	 */
	private static function getShortChannelName($channelId)
	{
		if (isset(self::$providerNames[$channelId])) {
			return self::$providerNames[$channelId];
		}

		return false;
	}

	/**
	 *
	 */
	public function __construct($user, $password, $providers, $test = false)
	{
		$this->test = $test;
        $this->user = $user;
        $this->password = $password;

		parent::init();

		$this->workProviders = $providers;

		// включен тестовый режим, установим другие параметры API и провайдеры
		if ($this->test) {

			// установим тестовых провайдеров
			$this->workProviders = self::$providers;
		}
	}

	/**
	 * @param        $driver
	 * @param        $host
	 * @param        $username
	 * @param        $password
	 * @param        $database
	 * @param string $prefix
	 */
	public function connectDb($driver, $host, $username, $password, $database, $prefix = '')
	{
		$capsule = new Capsule;

		$capsule->addConnection([
			'driver'    => $driver,
			'host'      => $host,
			'database'  => $database,
			'username'  => $username,
			'password'  => $password,
			'charset'   => 'utf8',
			'collation' => 'utf8_unicode_ci',
			'prefix'    => $prefix,
		]);

		$capsule->bootEloquent();

	}

	public function doEnableLogger()
	{
		// create a log channel
		$logger = new Logger('payments_log');

		//Create MysqlHandler
		$eloquentHandler = new EloquentHandler('\FintechFab\Payments\CreditPilotPayment\CreditPilotLog');

		$logger->pushHandler($eloquentHandler);

		$this->paymentLog = new PaymentsLog($logger);
	}

	protected function cleanup()
	{

		$this->provider = null;
		$this->providerMinSum = null;
		$this->providerMaxSum = null;
		$this->error = null;

		parent::cleanup();

	}

	/**
	 * Выполнить перевод
	 *
	 * @param $transferId      (ID перевода)
	 * @param $numberPhoneCard (Номер телефона)
	 * @param $channelId
	 * @param $amount          (Сумма)
	 *
	 * @return bool
	 */
	public function doTransfer($transferId, $numberPhoneCard, $channelId, $amount)
	{
		if (!$this->_setProvider($channelId)) {
			return false;
		}
		// проверяем ограничения по сумме
		if ($amount < $this->providerMinSum) {
			$this->_setError(0);

			return false;
		}
		if ($amount > $this->providerMaxSum) {
			$this->_setError(1);

			return false;
		}

		// проверка возможности платежа
		$oCheckResponse = $this->_checkCanTransfer($transferId, $numberPhoneCard, $amount);

		if (!$oCheckResponse) {
			return false;
		}

		/**
		 * требуется переустановить провайдера! не смотря на то, что уже это сделано выше, обязательно повторить,
		 * т.к. произошел cleanup() во время запроса на проверку возможности платежа
		 */
		if (!$this->_setProvider($channelId)) {
			return false;
		}

		$methodArgs = array(
			'dealerTransactionId' => $transferId,
			'serviceProviderId'   => $this->provider,
			'fullAmount'          => $amount,
			'amount'              => $amount,
			'phoneNumber'         => $numberPhoneCard,
		);

		$result = $this->_performRequest('PAY', $methodArgs, $transferId);

		if ($this->isError()) {
			return false;
		}


		$creditPilot = new CreditPilot();
		$creditPilot->setRawAttributes(array(
			'request_name'      => 'PAY',
			'transfer_queue_id' => $transferId,
			'phone'             => $numberPhoneCard,
			'bill_number'       => current($result->billNumber),
			'amount'            => $amount,
			'status'            => PaymentsInfo::C_STATUS_CREATED,
			'from_date'         => self::timetostr(strtotime(current($result->tsDateSp))),
			'to_date'           => self::timetostr(strtotime(current($result->tsDateDealer))),
		));

		$creditPilot->save();

		return true;
	}

	/**
	 * Получить статус перевода
	 *
	 * @param     $transferId (ID перевода)
	 * @param     $channelId
	 * @param int $billNumber (ID перевода, полученного в результате doTransfer)
	 *
	 * @param     $fromDate   (дата - с, полученная в результате doTransfer)
	 * @param     $toDate     (дата - по, полученная в результате doTransfer)
	 *
	 * @return bool|int
	 */
	public function getTransferStatus($transferId, $channelId, $billNumber, $fromDate = null, $toDate = null)
	{
		if (!$this->_setProvider($channelId)) {
			return false;
		}

		$creditPilotTransfer = CreditPilot::whereRaw('transfer_queue_id = ' . $transferId . ' AND bill_number = ' . $billNumber)
			->first();

		if (empty($creditPilotTransfer)) {
			return false;
		}

		if (empty($fromDate) || empty($toDate)) {
			$methodArgs = array(
				'serviceProviderId' => $this->provider,
			);
		} else {
			$methodArgs = array(
				'fromDate'          => urlencode($fromDate),
				'toDate'            => urlencode($toDate),
				'serviceProviderId' => $this->provider,
			);
		}

		if ($billNumber > 0) {
			$methodArgs['billNumber'] = $billNumber;
		} else {
			$methodArgs['dealerTransactionId'] = $transferId;
		}

		$result = $this->_performRequest('FINDPAY', $methodArgs, $transferId);

		if (!$this->isError()) {

			switch ($result->payment->result['resultCode']) {

				case 20000:
				case 20002:
					$creditPilotTransfer->status = PaymentsInfo::C_STATUS_WAITING;
					break;
				case 1:
					$creditPilotTransfer->status = PaymentsInfo::C_STATUS_PAID;
					break;
				case 2:
					$creditPilotTransfer->status = PaymentsInfo::C_STATUS_UNRECOGNIZED;
					break;
				// отказ
				default:
					$creditPilotTransfer->status = PaymentsInfo::C_ERROR_TRANSFER_SYSTEM;
			}

			$creditPilotTransfer->save();

			return $creditPilotTransfer->status;
		}

		if ($this->isError()) {
			return $this->getErrorCode();
		}

		return false;
	}

	/**
	 * Проверка на возможность осуществления перевода
	 *
	 * @param $transferId
	 * @param $phone
	 * @param $channelId
	 * @param $amount
	 *
	 * @return bool
	 */
	public function checkCanTransfer($transferId, $phone, $channelId, $amount)
	{
		if (!$this->_setProvider($channelId)) {
			return false;
		}

		return $this->_checkCanTransfer($transferId, $phone, $amount);
	}

	/**
	 * @return null
	 */
	public function getChannelName()
	{
		return self::getShortChannelName($this->channelId);
	}

	/**
	 * Установить ID провайдера
	 *
	 * @param $channelId
	 *
	 * @throws CreditPilotPaymentException
	 * @return bool
	 */
	private function _setProvider($channelId)
	{

		$providerId = null;

		//ищем провайдер в базе
		if (!in_array($channelId, array_keys($this->workProviders))) {
			throw new CreditPilotPaymentException('Провайдера не существует, канал ' . $channelId);

		}

		$providerId = $this->workProviders[$channelId];

		// не найден у агрегатора
		if (!$this->_getProviderData($providerId)) {
			return false;
		}

		$this->channelId = $channelId;

		return true;

	}

	/**
	 * Получить данные провайдера
	 *
	 * @param $providerId
	 *
	 * @return bool
	 */
	private function _getProviderData($providerId)
	{

		// сохраняем список провайдеров в статике
		static $providers = null;
		if (null === $providers) {

			// получаем список провайдеров от внешней системы
			/**
			 * @var \SimpleXMLElement $response
			 */
			$response = $this->_performRequest('providers2');
			if (!$this->isError()) {
				$providers = $response->provider;
			} else {
				$providers = array();
			}
		}

		$this->provider = $providerId;

		if ($providers) {
			foreach ($providers as $provider) {
				// наш провайдер есть в списке, определяем лимиты платежа
				if ($provider->id == $providerId) {
					$this->providerMinSum = $provider->minsum;
					$this->providerMaxSum = $provider->maxsum;

					return true;
				}
			}
		}

		return false;

	}

	/**
	 * Проверка на возможность осуществления перевода
	 *
	 * @param $transferId
	 * @param $phone
	 * @param $amount
	 *
	 * @return bool
	 */
	public function _checkCanTransfer($transferId, $phone, $amount)
	{
		$actionName = 'PREPARE';

		$methodArgs = array(
			'dealerTransactionId' => $transferId,
			'serviceProviderId'   => $this->provider,
			'amount'              => $amount,
			'phoneNumber'         => $phone,
		);

		$response = $this->_performRequest($actionName, $methodArgs, $transferId);

		return (!$this->isError())
			? $response
			: false;

	}

	/**
	 * Выполнить запрос
	 *
	 * @param       $actionName
	 * @param array $methodArgs
	 * @param       $paymentId
	 *
	 * @return bool|StdClass
	 */
	private function _performRequest($actionName, $methodArgs = array(), $paymentId = null)
	{
		$this->cleanup();

		$timeBeforeRequest = time();
		$sUrl = $this->_buildUrl($actionName, $methodArgs);
		$ch = curl_init($sUrl);

		curl_setopt($ch, CURLOPT_USERPWD, $this->user . ":" . $this->password);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, PaymentsInfo::C_CURL_TIMEOUT);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, PaymentsInfo::C_CURL_CONNECT_TIMEOUT);

		$response = curl_exec($ch);

//		if ($actionName !== 'providers2') {
//			echo $response;
//		}

		$resultHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		$this->_doParseResponse($response, $actionName, $ch);
		$log = $this->_doPrepareLog($paymentId, $actionName, $sUrl, $resultHttpCode, $timeBeforeRequest);

		curl_close($ch);

		// ошибка
		if ($this->isError()) {
			$log->writeFail();

			return false;
		}

		// все хорошо
		$log->writeSuccessful();

		return $this->response;

	}

	/**
	 * @param $paymentId
	 * @param $actionName
	 * @param $url
	 * @param $resultHttpCode
	 * @param $timeBeforeRequest
	 *
	 * @return PaymentsLog
	 */
	private function _doPrepareLog($paymentId, $actionName, $url, $resultHttpCode, $timeBeforeRequest)
	{
		$this->paymentLog->prepare(
			$this->channelId,
			$this->getChannelName(),
			$paymentId,
			$actionName,
			$url,
			'http_' . $resultHttpCode,
			$this->responseText,
			$this->errorCode,
			$this->responseError,
			$timeBeforeRequest,
			time()
		);

		return $this->paymentLog;

	}

	/**
	 * @param $responseText
	 * @param $actionName
	 * @param $ch
	 *
	 * @return stdClass
	 */
	private function _doParseResponse(&$responseText, $actionName, $ch)
	{

		/**
		 * @var stdClass $responseXml
		 */

		$curlError = curl_error($ch);
		$responseXml = null;

		if ($responseText) {
			$responseXml = @simplexml_load_string($responseText);
		}

		// объект ответа
		$this->response = $responseXml;

		// оригинальный текст ответа для логов
		$this->responseText = $responseText;

		// внятный код результата запроса (общий)
		$resultCode = (isset($responseXml->result['resultCode']))
			? $responseXml->result['resultCode']
			: false;
		$existResultCode = strlen($resultCode) > 0;

		// внятный ответ при проверке платежа
		if (!$curlError && !$existResultCode && 'FINDPAY' == $actionName) {
			$resultCode = (isset($responseXml->payment->result['resultCode']))
				? $responseXml->payment->result['resultCode']
				: false;
			if (!isset($responseXml->payment)) {
				$resultCode = '-999998';
			}
			$existResultCode = strlen($resultCode) > 0;
		}

		// внятный ответ при запросе на провайдеров
		if (!$existResultCode && 'providers2' == $actionName) {
			if (isset($responseXml->provider)) {
				$resultCode = 0;
				$existResultCode = true;
			}
		}

		// ответ невнятный, так и запишем
		if (!$existResultCode) {

			$this->_setError(-999999);
			$responseText = trim($responseText . ' неожиданный ответ от внешней системы');
			if ($curlError) {
				$responseText .= ' [curl_error] ' . $curlError;
			}
			$this->responseText = $responseText;

			return;

		}

		// при проверке статуса ошибка меньше нуля
		if ($actionName == 'FINDPAY') {
			if ($resultCode < 0) {
				$this->_setError($resultCode);
			}

			return;
		}

		// в остальных ситуациях - не 0 - это ошибка
		// ошибка проверки платежа или самого платежа
		if ($resultCode != 0) {
			$this->_setError($resultCode);
		}

		return;

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
	 * Установить ошибку
	 *
	 * @param $errorCode
	 */
	private function _setError($errorCode)
	{
		switch ($errorCode) {
			case 0:
				$this->error = PaymentsInfo::C_ERROR_INVOICE_AMOUNT_TOO_LOW;
				break;
			case 1:
				$this->error = PaymentsInfo::C_ERROR_INVOICE_AMOUNT_TOO_HIGH;
				break;
			// ошибочный номер абонента
			case -20140:
				$this->error = PaymentsInfo::C_STATUS_ERROR;
				break;
			case -20150:
				$this->error = PaymentsInfo::C_ERROR_PAYMENT_ID_ALREADY_EXIST;
				break;
			case -60100:
				$this->error = PaymentsInfo::C_ERROR_AUTHORIZATION_ERROR;
				break;
			case -999998:
				$this->error = PaymentsInfo::C_ERROR_TRANSFER_NOT_FOUND;
				break;
			default:
				$this->error = PaymentsInfo::C_ERROR_UNRECOGNIZED;
				break;
		}

		$this->errorCode = (string)$errorCode;
		$this->responseError = PaymentsInfo::$aErrorMessages[$this->error];

	}

	/**
	 * Получить код ошибки или null, если ее нет
	 *
	 * @return null|integer
	 */
	public function getErrorCode()
	{
		return $this->error;
	}
}

/**
 * Class CreditPilotPaymentException
 */
class CreditPilotPaymentException extends \Exception
{
}