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

	// внутренние коды сервис-провайдеров
	const MOB_TELE2 = 'MOB_TELE2';
	const MOB_BEELINE = 'MOB_BEELINE';
	const MOB_MTS = 'MOB_MTS';
	const MOB_MEGAFON = 'MOB_MEGAFON';
	const CARD_ALPHA = 'CARD_ALPHA';
	const CARD_ALL_RUS = 'CARD_ALL_RUS';
	const CARD_NOALPHA_RUS = 'CARD_NOALPHA_RUS';
	const UNKNOWN = 'UNKNOWN';

	// ID сервис-провайдеров в КредитПилоте
	const MOB_TELE2_ID1 = 262092827;
	const MOB_TELE2_ID2 = 750739964;
	const MOB_BEELINE_ID1 = 411492871;
	const MOB_BEELINE_ID2 = 890983449;
	const MOB_MTS_ID1 = 540792152;
	const MOB_MTS_ID2 = 648934845;
	const MOB_MEGAFON_ID1 = 354046137;
	const MOB_MEGAFON_ID2 = 453315258;

	const CARD_ALPHA_ID = 276255497;
	const CARD_ALL_RUS_ID = 119088347;
	const CARD_NOALPHA_RUS_ID = 821410268;
	const UNKNOWN_ID = 999999999;

	// имена сервис-провайдеров
	public static $serviceProvidersNames = array(
		self::MOB_TELE2_ID1       => 'Теле2 без комиссии',
		self::MOB_TELE2_ID2       => 'Теле2 без комиссии',
		self::MOB_BEELINE_ID1     => 'Билайн без комиссии',
		self::MOB_BEELINE_ID2     => 'Билайн без комиссии',
		self::MOB_MTS_ID1         => 'Мтс без комиссии',
		self::MOB_MTS_ID2         => 'Мтс без комиссии',
		self::MOB_MEGAFON_ID1     => 'Мегафон без комиссии',
		self::MOB_MEGAFON_ID2     => 'Мегафон без комиссии',
		self::CARD_ALPHA_ID       => 'Альфабанк пополнение карты',
		self::CARD_ALL_RUS_ID     => 'Российские банки пополнение карты',
		self::CARD_NOALPHA_RUS_ID => 'Россия пополнение карты (кроме Альфабанка)',
		self::UNKNOWN_ID          => 'Неизвестный сервисный номер',
	);

	// соответствие внутреннего кода и ID в КредитПилоте
	public static $serviceProvidersCodes = array(
		self::MOB_TELE2_ID1       => self::MOB_TELE2,
		self::MOB_TELE2_ID2       => self::MOB_TELE2,
		self::MOB_BEELINE_ID1     => self::MOB_BEELINE,
		self::MOB_BEELINE_ID2     => self::MOB_BEELINE,
		self::MOB_MTS_ID1         => self::MOB_MTS,
		self::MOB_MTS_ID2         => self::MOB_MTS,
		self::MOB_MEGAFON_ID1     => self::MOB_MEGAFON,
		self::MOB_MEGAFON_ID2     => self::MOB_MEGAFON,
		self::CARD_ALPHA_ID       => self::CARD_ALPHA,
		self::CARD_ALL_RUS_ID     => self::CARD_ALL_RUS,
		self::CARD_NOALPHA_RUS_ID => self::CARD_NOALPHA_RUS,
		self::UNKNOWN_ID          => self::UNKNOWN,
	);

	public static $errorCodes = array(
		//Ошибки при отправке платежа или при проверке состояния:
		-20101 => 'В системе нет пользователя с таким логином (неправильный логин или пароль)',
		-20102 => 'Пользователь отключен (заблокирован)',
		-20103 => 'Не найдена касса, соответствующая этому пользователю',
		-20110 => 'Оплата в пользу сервис-провайдера невозможна',
		-20117 => 'Недостаточно средств на счету дилера для проведения этого платежа',
		-20135 => 'Повторный платеж, повторите платеж позднее',
		-20136 => 'Превышение общей суммы ежедневных платежей провайдера)',
		-20137 => 'Разрешенное время работы пользователя истекло',
		-20139 => 'Ошибочная сумма платежа',
		-20140 => 'Ошибочный номер абонента',
		-20141 => 'В системе нет терминала с таким номером',
		-20150 => 'Платеж с таким идентификатором уже существует (не уникальный идентификатор платежа dealerTransactionId для успешных транзакций данного пользователя)',
		20000  => 'Платеж в очереди',
		20002  => 'Платеж в обработке',
		1      => 'Платеж проведен',
		0      => 'Откат транзакции',
		-100   => 'Откат транзакции',
		-200   => 'Откат транзакции',
		-300   => 'Недостаточно денег на счете дилера',
		-400   => 'Откат транзакции по требованию',
		-500   => 'Откат транзакции',
		-600   => 'Откат транзакции',
		2      => 'Состояние платежа неизвестно, сбой при осуществлении платежа в биллинг провайдера (в последствии состояние будет изменено на проведен или на один из откатов)',
		-20300 => 'Системный сбой',
		-20215 => 'Операция выполняется',
	);

	public $channelIds = array(
		self::CHANNEL_CREDIT_PILOT_MTS,
		self::CHANNEL_CREDIT_PILOT_MEGAFON,
		self::CHANNEL_CREDIT_PILOT_BEELINE,
		self::CHANNEL_CREDIT_PILOT_TELE2,
		self::CHANNEL_CREDIT_PILOT_BANK_CARD,
	);

	public $channelName = 'КредитПилот';

	public $serviceProviderId = null;

	protected $user = '';
	protected $password = '';

	protected $apiUrl = 'https://www.kp-dealer.ru:8080/KPDealerWeb/KPBossHttpServer';
	protected $apiUrlTest = 'https://test.creditpilot.ru:8080/KPDealerWeb/KPBossHttpServer';

	const PROVIDER_MOBILE_TEST = 540792152;
	const PROVIDER_CARD_TEST = 347781607;

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

	public function __construct($user, $password, $providers, $test = false)
	{
		$this->test = $test;
		$this->user = $user;
		$this->password = $password;

		parent::init();

		$this->workProviders = $providers;

		// включен тестовый режим, установим другие параметры API и провайдеры
		if ($this->test && empty($providers)) {

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
		$this->error = null;
		$this->serviceProviderId = null;

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
	 * @return string
	 */
	public function doTransfer($transferId, $numberPhoneCard, $channelId, $amount)
	{
		if (!$this->_setProvider($channelId)) {
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

		return current($result->billNumber);
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

		$methodArgs = array();

		if (!empty($fromDate) && !empty($toDate)) {
			$methodArgs = array(
				'fromDate' => urlencode($fromDate),
				'toDate'   => urlencode($toDate),
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

		$this->channelId = $channelId;

		$this->provider = $this->workProviders[$channelId];

		return true;

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
	 * @param int   $timeout
	 * @param int   $connectTimeout
	 *
	 * @return bool|stdClass
	 */
	private function _performRequest($actionName, $methodArgs = array(), $paymentId = null, $timeout = PaymentsInfo::C_CURL_TIMEOUT, $connectTimeout = PaymentsInfo::C_CURL_CONNECT_TIMEOUT)
	{
		$this->cleanup();

		$timeBeforeRequest = time();
		$url = $this->_buildUrl($actionName, $methodArgs);
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_USERPWD, $this->user . ":" . $this->password);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);

		$response = curl_exec($ch);

		$resultHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		$this->_doParseResponse($response, $actionName, $ch);
		$log = $this->_doPrepareLog($paymentId, $actionName, $url, $resultHttpCode, $timeBeforeRequest);

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
	 * @throws CreditPilotPaymentException
	 * @return PaymentsLog
	 */
	private function _doPrepareLog($paymentId, $actionName, $url, $resultHttpCode, $timeBeforeRequest)
	{
		if (!isset($this->paymentLog)) {
			$this->doEnableLogger();
		}

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

		$resultHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		if (empty($responseText) || $resultHttpCode != '200') {
			$this->_setError(PaymentsInfo::C_ERROR_TECHNICAL);

			return;
		}

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

		if (!$curlError && 'FINDPAY' == $actionName) {
			if (isset($responseXml->payment->userData->serviceProviderId)) {
				$this->serviceProviderId = (int)$responseXml->payment->userData->serviceProviderId;
			}
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
		$url = $this->apiUrl . '?actionName=' . $sActionName . $sMethodArgs;

		return $url;
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
			case -20135:
			case -20150:
				$this->error = PaymentsInfo::C_ERROR_PAYMENT_ID_ALREADY_EXIST;
				break;
			case -20101:
			case -20102:
			case -20103:
			case -20110:
			case -20137:
			case -20141:
			case -60100:
				$this->error = PaymentsInfo::C_ERROR_AUTHORIZATION_ERROR;
				break;
			case -999998:
				$this->error = PaymentsInfo::C_ERROR_TRANSFER_NOT_FOUND;
				break;
			case -20300:
				$this->error = PaymentsInfo::C_ERROR_TECHNICAL;
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

	/**
	 * Код ошибки КредитПилота
	 *
	 * @return mixed
	 */
	public function getCreditPilotErrorCode()
	{
		return $this->errorCode;
	}

	/**
	 * Сообщение об ошибке КредитПилота
	 *
	 * @return string|null
	 */
	public function getCreditPilotErrorMessage()
	{
		if (isset(self::$errorCodes[$this->errorCode])) {
			return self::$errorCodes[$this->errorCode];
		}

		return null;
	}

	/**
	 * Временная ли это шибка?
	 *
	 * @return bool
	 */
	public function isTempError()
	{
		$temporaryErrors = array(
			PaymentsInfo::C_ERROR_TECHNICAL,
			PaymentsInfo::C_ERROR_SERVER_IS_BUSY,
		);

		if (in_array($this->error, $temporaryErrors)) {
			return true;
		}

		return false;
	}

	/**
	 * @return null
	 */
	public function getServiceProviderId()
	{
		return $this->serviceProviderId;
	}

	/**
	 * @return null
	 */
	public function getServiceProviderName()
	{
		if (!isset($this->serviceProviderId)) {
			return self::$serviceProvidersNames[self::UNKNOWN_ID];
		}

		return isset(self::$serviceProvidersNames[$this->serviceProviderId])
			? self::$serviceProvidersNames[$this->serviceProviderId]
			: self::$serviceProvidersNames[self::UNKNOWN_ID];
	}

	/**
	 * @return null
	 */
	public function getServiceProviderCode()
	{
		if (!isset($this->serviceProviderId)) {
			return self::UNKNOWN;
		}

		return isset(self::$serviceProvidersCodes[$this->serviceProviderId])
			? self::$serviceProvidersCodes[$this->serviceProviderId]
			: self::UNKNOWN;
	}

	/**
	 * Получить ID провайдеров по номеру телефона
	 *
	 * @param string $phone
	 *
	 * @return array|null
	 */
	public function getProviderIds($phone)
	{
		$response = $this->_performProviderIdRequest($phone);

		if (!$response) {
			return null;
		}

		return $this->_doParseProviderIdResponse($response);
	}

	/**
	 * @param string $phone
	 *
	 * @return string|null
	 */
	private function _performProviderIdRequest($phone)
	{
		$url = $this->apiUrl . '?actionName=PHONERANGES&phoneNumber=' . $phone;
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_USERPWD, $this->user . ":" . $this->password);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, PaymentsInfo::C_CURL_TIMEOUT);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, PaymentsInfo::C_CURL_CONNECT_TIMEOUT);

		$response = curl_exec($ch);
		$resultHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close($ch);

		if ($resultHttpCode !== 200 || !$response) {
			$this->error = PaymentsInfo::C_ERROR_TECHNICAL;

			return null;
		}

		return $response;
	}

	/**
	 * @param string $response
	 *
	 * @return array|null
	 */
	private function _doParseProviderIdResponse($response)
	{
		/**
		 * @var \stdClass $responseXml
		 */
		$responseXml = @simplexml_load_string($response);

		// это не providerIds, а какая-то ошибка
		if (isset($responseXml->result)) {
			$this->_setError(PaymentsInfo::C_ERROR_UNRECOGNIZED);

			return null;
		}

		if (!isset($responseXml->phoneRange['providerIds'])) {
			return null;
		}

		return explode(',', strval($responseXml->phoneRange['providerIds']));
	}
}

/**
 * Class CreditPilotPaymentException
 */
class CreditPilotPaymentException extends \Exception
{
}