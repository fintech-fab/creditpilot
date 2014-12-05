<?php
namespace FintechFab\Payments\CreditPilotPayment;

use Illuminate\Database\Schema\Blueprint;


/**
 * @property integer $id
 * @property string  $request_name
 * @property integer $transfer_queue_id
 * @property string  $phone
 * @property string  $bill_number
 * @property float   $amount
 * @property integer $status
 * @property string  $from_date
 * @property string  $to_date
 * @property string  $created_at
 * @property string  $updated_at
 *
 *
 * @method static CreditPilot whereRaw($sql, $bindings = array(), $boolean = 'and')
 *
 * @method static CreditPilot first()
 */
class CreditPilot extends \Illuminate\Database\Eloquent\Model
{

	protected $table = 'creditpilot';

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
			$table->string('request_name');
			$table->integer('transfer_queue_id');
			$table->string('phone', 10);
			$table->string('bill_number');
			$table->decimal('amount', 10, 2);
			$table->integer('status');
			$table->timestamp('from_date');
			$table->timestamp('to_date');
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