<?php

namespace FintechFab\Payments;

/**
 * Class PaymentsInfo
 */
class PaymentsInfo
{

	const C_ERROR_REQUEST_INCORRECT = 1; //Неверный запрос
	const C_ERROR_AUTHORIZATION_ERROR = 2; //Ошибка авторизации
	const C_ERROR_SERVER_IS_BUSY = 3; //Сервер занят, повторите запрос позже

	const C_ERROR_PAYMENT_NOT_FOUND = 4; //Счет/перевод не найден
	const C_ERROR_PAYMENT_ID_ALREADY_EXIST = 5; //Счет/перевод с таким id уже существует
	const C_ERROR_INVOICE_AMOUNT_TOO_LOW = 6; //Сумма слишком мала
	const C_ERROR_INVOICE_AMOUNT_TOO_HIGH = 7; //Сумма слишком велика
	const C_ERROR_ACCOUNT_DOES_NOT_EXIST = 8; //Аккаунт не существует

	const C_ERROR_REQUEST_DENIED = 9; //Запрос запрещен
	const C_ERROR_ACCOUNT_INSUFFICIENT_FUNDS = 10; //Недостаточно средств на счету

	const C_ERROR_TRANSFER_ISSUER = 11; //Отказ со стороны банка-эмитента
	const C_ERROR_TRANSFER_SYSTEM = 12; //Ошибка на стороне банка, через который осуществляется перевод
	const C_ERROR_TRANSFER_USER = 13; //Ошибка со стороны пользователя
	const C_ERROR_TRANSFER_NOT_FOUND = 14; //Платеж во внешней системе не найден

	const C_ERROR_DEBITING_ISSUER = 15; //Отказ со стороны банка-эмитента
	const C_ERROR_DEBITING_SYSTEM = 16; //Ошибка на стороне банка, через который осуществляется перевод
	const C_ERROR_DEBITING_USER = 17; //Ошибка со стороны пользователя

	const C_ERROR_TECHNICAL = 8888; //Техническая ошибка
	const C_ERROR_UNRECOGNIZED = 9999; //Неизвестная ошибка

	const C_STATUS_CREATED = 0; // Создан в системе
	const C_STATUS_WAITING = 1; //Ожидает оплаты
	const C_STATUS_PAID = 2; //Оплачен
	const C_STATUS_REJECTED = 3; //Отклонен
	const C_STATUS_ERROR = 4; //Ошибка
	const C_STATUS_EXPIRED = 5; //Время на оплату счета истекло
	const C_STATUS_CANCELLED = 6; //Отменен
	const C_STATUS_PROCESSED = 7; //Оплачен и обработан системой
	const C_STATUS_CASHBACK = 8; //Деньги возвращены
	const C_STATUS_UNRECOGNIZED = 999; //Неизвестный статус

	public static $aStatuses = array(
		self::C_STATUS_CREATED      => 'создан',
		self::C_STATUS_WAITING      => 'ожидание оплаты',
		self::C_STATUS_PAID         => 'оплачен',
		self::C_STATUS_REJECTED     => 'сброшен',
		self::C_STATUS_ERROR        => 'ошибка',
		self::C_STATUS_EXPIRED      => 'просрочен',
		self::C_STATUS_CANCELLED    => 'отменен',
		self::C_STATUS_PROCESSED    => 'в обработке',
		self::C_STATUS_CASHBACK     => 'возврат',
		self::C_STATUS_UNRECOGNIZED => 'неизвестно',
	);

	const C_CURL_CONNECT_TIMEOUT = 10; //таймаут для подключения curl
	const C_CURL_TIMEOUT = 20; //таймаут для получения ответа curl

	public static $aErrorMessages = array(
		self::C_ERROR_REQUEST_INCORRECT          => 'Некорректный запрос',
		self::C_ERROR_AUTHORIZATION_ERROR        => 'Ошибка авторизации',
		self::C_ERROR_SERVER_IS_BUSY             => 'Сервер занят, повторите запрос позже',
		self::C_ERROR_PAYMENT_NOT_FOUND          => 'Платеж не найден',
		self::C_ERROR_PAYMENT_ID_ALREADY_EXIST   => 'Данный платеж уже существует',
		self::C_ERROR_INVOICE_AMOUNT_TOO_LOW     => 'Сумма слишком мала',
		self::C_ERROR_INVOICE_AMOUNT_TOO_HIGH    => 'Сумма слишком велика',
		self::C_ERROR_ACCOUNT_DOES_NOT_EXIST     => 'Аккаунт не существует',
		self::C_ERROR_REQUEST_DENIED             => 'Запрос запрещен',
		self::C_ERROR_ACCOUNT_INSUFFICIENT_FUNDS => 'Недостаточно средств на счету',
		self::C_ERROR_TRANSFER_ISSUER            => 'Ошибка на стороне банка-эмитента',
		self::C_ERROR_TRANSFER_SYSTEM            => 'Ошибка на стороне банка, через который осуществляется перевод',
		self::C_ERROR_TRANSFER_USER              => 'Ошибка на стороне пользователя',
		self::C_ERROR_TECHNICAL                  => 'Техническая ошибка',
		self::C_ERROR_UNRECOGNIZED               => 'Неизвестная ошибка',
		self::C_ERROR_TRANSFER_NOT_FOUND         => 'Платеж не найден',
		self::C_ERROR_DEBITING_ISSUER            => 'Списание: ошибка на стороне банка-эмитента',
		self::C_ERROR_DEBITING_SYSTEM            => 'Списание: ошибка на стороне банка, через который осуществляется списание',
		self::C_ERROR_DEBITING_USER              => 'Списание: ошибка на стороне пользователя',
	);

	/**
	 * @param $iErrorCode
	 *
	 * @return bool
	 */
	public static function checkIsErrorCode($iErrorCode)
	{
		if (array_key_exists($iErrorCode, self::$aErrorMessages)) {
			return true;
		}

		return false;
	}

	/**
	 * @param $iErrorCode
	 *
	 * @return string
	 */
	public static function getErrorMessage($iErrorCode)
	{
		if (array_key_exists($iErrorCode, self::$aErrorMessages)) {
			return self::$aErrorMessages[$iErrorCode];
		}

		return 'Неизвестный код ошибки';
	}


}