CreditPilot Payment
=========

# Требования

- php >=5.4.0
- MySQL Database
- composer


# Установка

### Composer

	"repositories": [
      {
       "url": "https://github.com/fintech-fab/creditpilot.git",
       "type": "git"
      }
     ],
     "require": {
        "fintech-fab/creditpilot": "dev-master"
     }

	composer update

### Использование

```PHP
	use FintechFab\Payments\CreditPilotPayment\CreditPilotPayment;
	use FintechFab\Payments\CreditPilotPayment\CreditPilot;

	// установим ID провайдеров для каждого канала, в примере тестовые ID
	$providers = array(
    		CreditPilotPayment::CHANNEL_CREDIT_PILOT_TELE2     => 540792152,
    		CreditPilotPayment::CHANNEL_CREDIT_PILOT_MTS       => 540792152,
    		CreditPilotPayment::CHANNEL_CREDIT_PILOT_BEELINE   => 540792152,
    		CreditPilotPayment::CHANNEL_CREDIT_PILOT_MEGAFON   => 540792152,
    		CreditPilotPayment::CHANNEL_CREDIT_PILOT_BANK_CARD => 657871990,
	);

	$creditPilotPayment = new CreditPilotPayment('user', 'password', $providers);

	// если используется без Laravel, то создаем коннект к БД, в Laravel будет использован Eloquent и connectDb() не нужен
	$creditPilotPayment->connectDb('mysql', 'localhost', 'creditpilot', 'creditpilot', 'creditpilot', 'tbl_');

	// уникальный ID трансфера
	$transferId = '12345678';

	// отправляем деньги на мобильный Билайн
	$result = $creditPilotPayment->doTransfer($transferId, '9055555555', CreditPilotPayment::CHANNEL_CREDIT_PILOT_BEELINE, '123');

	if($result === true){
		// получаем информацию о трансфере из БД
		$transfer = CreditPilot::whereRaw('transfer_queue_id = ' . $transferId)->first();

		// запрашиваем статус трансфера
		$status = $creditPilotPayment->getTransferStatus($transferId, CreditPilotPayment::CHANNEL_CREDIT_PILOT_BEELINE, $transfer->bill_number);

		//после getTransferStatus можно получить информацию о сервис-провайдере платежа

		$serviceProviderId = $this->getServiceProviderId(); // ID провайдера в КредитПилоте, для одного провайдера может быть 2 разных ID
        $serviceProviderCode = $this->getServiceProviderCode(); // внутренний код провайдера в библиотеке, всегда 1 код на провайдера
        $serviceProviderName = $this->getServiceProviderName(); // текстовое имя сервис-провайдера
	}

```



