<?php
namespace FintechFab\Payments\CreditPilotPayment;

use Illuminate\Database\Schema\Blueprint;

class CreditPilotProviderParams extends \Illuminate\Database\Eloquent\Model
{

	protected $table = 'creditpilot_provider_params';

	/**
	 * @param array $attributes
	 */
	public function __construct(array $attributes = array())
	{
		parent::__construct($attributes);


		if ($this->isNeedMigrate()) {
			$this->autoMigrate();
		}
	}

	protected function autoMigrate()
	{
		$this->getConnection()->getSchemaBuilder()->create($this->getTable(), function (Blueprint $table) {
			$table->increments('id');
			$table->integer('provider_id');
			$table->decimal('sum_min');
			$table->decimal('sum_max');
			$table->timestamps();
		});
	}

	/**
	 * @return bool
	 */
	protected function isNeedMigrate()
	{
		// if this model has not table, need migrate
		return !$this->getConnection()->getSchemaBuilder()->hasTable($this->getTable());
	}

}