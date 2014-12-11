<?php
use FintechFab\Payments\CreditPilotPayment\CreditPilotPayment;
use FintechFab\Payments\CreditPilotPayment\CreditPilot;
use FintechFab\Payments\PaymentsInfo;
use FintechFab\Payments\Test\TestCase;

/**
 * Class CreditPilotTest
 */
class CreditPilotTest extends TestCase
{
	/**
	 * @var CreditPilotPayment
	 */
	private $creditPilotPayment;

	public function setUp()
	{
		parent::setUp();

		if (!isset($this->creditPilotPayment)) {
            $testUser = '';
            $testPassword = '';

			$this->creditPilotPayment = new CreditPilotPayment($testUser, $testPassword, null, true);
		}
	}

	public static function setUpBeforeClass()
	{
		$testUser = '';
		$testPassword = '';

		$creditPilotPayment = new CreditPilotPayment($testUser, $testPassword, null, true);

		// prepare test DB connection
		$creditPilotPayment->connectDb('mysql', 'localhost', 'creditpilot', 'creditpilot', 'creditpilot', 'test_');

		// truncate test table
		CreditPilot::truncate();
	}

	/**
	 * Тест трансфера на мобильный телефон
	 */
	public function testOne()
	{
		$transferId = time();

		$creditPilotPayment = $this->creditPilotPayment;

		$amount = round(rand(0, 10000) / 100, 2);

		$result = $creditPilotPayment->doTransfer($transferId, '9055555555', CreditPilotPayment::CHANNEL_CREDIT_PILOT_BEELINE, $amount);

		$this->assertEmpty($creditPilotPayment->getErrorMessage(), $creditPilotPayment->getErrorMessage());

		$this->assertTrue($result);

		$transfer = CreditPilot::whereRaw('transfer_queue_id = ' . $transferId)->first();

		$status = $creditPilotPayment->getTransferStatus($transferId, CreditPilotPayment::CHANNEL_CREDIT_PILOT_BEELINE, $transfer->bill_number);

		$this->assertEquals(PaymentsInfo::C_STATUS_WAITING, $status);

		$this->assertEmpty($creditPilotPayment->getErrorMessage(), $creditPilotPayment->getErrorMessage());
	}

	/**
	 * Тест трансфера на банковскую карту
	 */
	public function testTwo()
	{
		$transferId = time();

		$creditPilotPayment = $this->creditPilotPayment;

		$amount = round(rand(0, 10000) / 100, 2);

		$result = $creditPilotPayment->doTransfer($transferId, '4652060320881342', CreditPilotPayment::CHANNEL_CREDIT_PILOT_BANK_CARD, $amount);

		$this->assertEmpty($creditPilotPayment->getErrorMessage(), $creditPilotPayment->getErrorMessage());

		$this->assertTrue($result);

		$transfer = CreditPilot::whereRaw('transfer_queue_id = ' . $transferId)->first();

		$status = $creditPilotPayment->getTransferStatus($transferId, CreditPilotPayment::CHANNEL_CREDIT_PILOT_BANK_CARD, $transfer->bill_number);

		$this->assertEquals(PaymentsInfo::C_STATUS_WAITING, $status);

		$this->assertEmpty($creditPilotPayment->getErrorMessage(), $creditPilotPayment->getErrorMessage());
	}

	/**
	 * Тест ошибки ID трансфера (уже существует)
	 */
	public function testThree()
	{
		$transferId = 1;

		$creditPilotPayment = $this->creditPilotPayment;

		$amount = round(rand(0, 10000) / 100, 2);

		$creditPilotPayment->doTransfer($transferId, '4652060320881342', CreditPilotPayment::CHANNEL_CREDIT_PILOT_BANK_CARD, $amount);

		$this->assertNotEmpty($creditPilotPayment->getErrorMessage());

		$this->assertEquals(-20150, $creditPilotPayment->getCreditPilotErrorCode());
		$this->assertEquals('Платеж с таким идентификатором уже существует (не уникальный идентификатор платежа dealerTransactionId для успешных транзакций данного пользователя)', $creditPilotPayment->getCreditPilotErrorMessage());
	}

	/**
	 * Пробуем сделать платеж на тот же номер с той же суммой, должна быть не временная ошибка "платеж уже существует"
	 */
	public function testFour()
	{
		$transferId = time();

		$creditPilotPayment = $this->creditPilotPayment;

		$amount = round(rand(0, 10000) / 100, 2);

		$result = $creditPilotPayment->doTransfer($transferId, '4652060320881342', CreditPilotPayment::CHANNEL_CREDIT_PILOT_BANK_CARD, $amount);

		$this->assertEmpty($creditPilotPayment->getErrorMessage(), $creditPilotPayment->getErrorMessage());

		$this->assertTrue($result);

		$transfer = CreditPilot::whereRaw('transfer_queue_id = ' . $transferId)->first();

		$status = $creditPilotPayment->getTransferStatus($transferId, CreditPilotPayment::CHANNEL_CREDIT_PILOT_BANK_CARD, $transfer->bill_number);

		$this->assertEquals(PaymentsInfo::C_STATUS_WAITING, $status);

		sleep(2);
		$status = $creditPilotPayment->getTransferStatus($transferId, CreditPilotPayment::CHANNEL_CREDIT_PILOT_BANK_CARD, $transfer->bill_number);

		$this->assertEquals(PaymentsInfo::C_STATUS_PAID, $status);


		$this->assertEmpty($creditPilotPayment->getErrorMessage(), $creditPilotPayment->getErrorMessage());

		$transferId = time();

		// пробуем платеж на тот же номер с той же суммой, но с другим ID - должна быь постоянная ошибка
		$result = $creditPilotPayment->doTransfer($transferId, '4652060320881342', CreditPilotPayment::CHANNEL_CREDIT_PILOT_BANK_CARD, $amount);

		$this->assertNotEmpty($creditPilotPayment->getErrorMessage(), $creditPilotPayment->getErrorMessage());
		$this->assertEquals(    PaymentsInfo::C_ERROR_PAYMENT_ID_ALREADY_EXIST ,$creditPilotPayment->getErrorCode());

		$this->assertFalse($result);;

		$this->assertFalse($creditPilotPayment->isTempError());
	}
}
