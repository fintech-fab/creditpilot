<?php

namespace FintechFab\Payments;

interface PaymentChannelInterface
{

	public function init();

	public function getLog();

	public function isError();

	public function getErrorMessage();

}