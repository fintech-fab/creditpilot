<?php
namespace FintechFab\Payments\CreditPilotPayment;

use Illuminate\Database\Schema\Blueprint;


/**
 *
 *
 * @method static CreditPilot whereRaw($sql, $bindings = array(), $boolean = 'and')
 *
 * @method static CreditPilot first()
 */
class CreditPilotLog extends \Illuminate\Database\Eloquent\Model
{

	protected $table = 'log_creditpilot';

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

	/**
	 *
	 */
	protected function autoMigrate()
	{
		$this->getConnection()->getSchemaBuilder()->create($this->getTable(), function (Blueprint $table) {
			$table->increments('id');
			$table->integer('level');
			$table->string('level_name');
			$table->string('transfer_id')->nullable();
			$table->text('message');
			$table->timestamps();
			$table->index('level');
			$table->index('level_name');
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