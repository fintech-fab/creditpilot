<?php
/**
 * Created by PhpStorm.
 * User: popov
 * Date: 04.12.14
 * Time: 13:47
 */

namespace FintechFab\Payments\EloquentHandler;

use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;

/**
 * Class EloquentHandler
 *
 * @package FintechFab\Payments\EloquentHandler
 */
class EloquentHandler extends AbstractProcessingHandler
{

	protected $model;

	/**
	 * @param string   $class
	 * @param bool|int $level
	 * @param bool     $bubble
	 */
	public function __construct($class, $level = Logger::DEBUG, $bubble = true)
	{
		$this->model = $class;

		parent::__construct($level, $bubble);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function write(array $record)
	{
		$model = $this->createModel();
		$model->level = $record['level'];
		$model->level_name = $record['level_name'];
		$model->message = $record['message'];

		if (isset($record['context']) && isset($record['context']['transfer_id'])) {
				$model->transfer_id = $record['context']['transfer_id'];
		}

		$model->save();
	}

	/**
	 * Create a new instance of the model.
	 *
	 * @return \Illuminate\Database\Eloquent\Model
	 */
	protected function createModel()
	{
		$class = '\\' . ltrim($this->model, '\\');

		return new $class;
	}

}