<?php
namespace Werkint\SMSCenter;

use Werkint\SMSCenter\Exception\ServerException;
use Werkint\SMSCenter\Exception\SMSServerException;
use Werkint\SMSCenter\Response\IncomingMessage;
use Werkint\SMSCenter\Response\MessageError;
use Werkint\SMSCenter\Response\MessageInfo;
use Werkint\SMSCenter\Response\PhoneOperator;

/**
 * Smscenter.
 *
 * @author Bogdan Yurov <bogdan@yurov.me>
 */
class SMSCenter implements SMSCenterInterface
{
	/** API url */
	const BASE_URL = 'https://smsc.ru/sys/';
	/** Post message command */
	const SEND_COMMAND = 'send';
	/** Get message command */
	const GET_COMMAND = 'get';

	/** Get account balance from server command */
	const BALANCE_COMMAND = 'balance';

	/** Get message status from server command */
	const STATUS_COMMAND = 'status';

	/** Get operator's info from server command */
	const INFO_COMMAND = 'info';

	/** Messages default charset */
	const CHARSET = 'utf-8';

	/** Incoming date format */
	const DATE_FORMAT = 'd.m.Y H:i:s';

	/** Incoming timezone */
	const DATE_TIMEZONE = 'Europe/Moscow';

	/** Usual message */
	const FORMAT_NORMAL = 0;
	/** Flash (popup message) */
	const FORMAT_FLASH = 1;
	/** WAP-PUSH message*/
	const FORMAT_PUSH = 2;
	/** HLR-query to get phone info*/
	const FORMAT_HRL = 3;
	/** Binary message*/
	const FORMAT_BIN = 4;
	/** Binary message in HEX*/
	const FORMAT_BIN_HEX = 5;
	/** Ping-sms*/
	const FORMAT_PING = 6;

	/**
	 * Query parameters formats
	 *
	 * @var array
	 * @var ['name'] - parameter name
	 * @var ['value'] - parameter value
	 */
	protected $formats = [
			self::FORMAT_NORMAL => ['name' => 'bin', 'value' => '0'],
			self::FORMAT_FLASH => ['name' => 'flash', 'value' => '1'],
			self::FORMAT_PUSH => ['name' => 'push', 'value' => '1'],
			self::FORMAT_HRL => ['name' => 'hlr', 'value' => '1'],
			self::FORMAT_BIN => ['name' => 'bin', 'value' => '1'],
			self::FORMAT_BIN_HEX => ['name' => 'bin', 'value' => '2'],
			self::FORMAT_PING => ['name' => 'ping', 'value' => '1']
	];

	protected $login;
	protected $password;
	protected $sender;

	/**
	 * @param string $login
	 * @param string $password
	 * @param string $sender
	 */
	public function __construct(
			$login,
			$password,
			$sender
	) {
//		TODO optional: read from config/parameters
		$this->login = $login;
		$this->password = $password;
		$this->sender = $sender;
	}

	/**
	 * {@inheritdoc}
	 */
	public function sendMessages(
			array $phones,
			$format = self::FORMAT_NORMAL,
			$message = null,
			$time = null
//        \DateTime $time = null
	) {
		$params = [
				'phones' => join(';', $phones),
				'mes' => (string)$message,
				'sender' => $this->sender,
				'charset' => static::CHARSET,
		];

		if (!empty($time)) {
//            $time = $time->format('DDMMYYhhmm, h1-h2, 0ts, +m');
//	        TODO check match smscenter time format
			$params['time'] = $time;
		}

		$params[$this->formats[$format]['name']] = $this->formats[$format]['value'];

		$ret = $this->query(self::SEND_COMMAND, $params);

		return (int)$ret->id;
	}

	/**
	 * {@inheritdoc}
	 */
	public function checkNumber($phoneNumber) {
		$params = [
				'phones' => $phoneNumber,
				$this->formats[self::FORMAT_HRL]['name'] => $this->formats[self::FORMAT_HRL]['value']
		];

		$ret = $this->query(self::SEND_COMMAND, $params);

		return (bool)$ret->id;
	}


	/**
	 * {@inheritdoc}
	 */
	public function getIncomingMessages(
			$hours = 24
	) {
		$hours = min($hours, 70);
		$list = $this->query(self::GET_COMMAND, [
				'get_answers' => '1',
				'hour' => $hours,
		]);

		$ret = [];
		foreach ($list as $row) {
			$ret[] = new IncomingMessage(
					$row->id,
					$this->getDate($row->sent),
					$this->getDate($row->received),
					$row->message,
					$row->phone,
					$row->to_phone
			);
		}

		return $ret;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getBalance() {
		$ret = $this->query(self::BALANCE_COMMAND);

		return (float)$ret->balance;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getStatus(
			$phone,
			$messageId,
			$moreInfo = false
	) {
		$ret = $this->query(self::STATUS_COMMAND, [
				'phone' => $phone,
				'id' => $messageId,
				'all' => ($moreInfo ? '2' : '0'),
		]);
		if ($ret->status > 1) {
			return new MessageError(
					$messageId,
					$ret->status,
					$ret->err
			);
		} else {
			return new MessageInfo(
					$messageId,
					$ret->status,
					$this->getDate($ret->last_date)
			);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function getPhoneOperator(
			$phone
	) {
		$ret = $this->query(self::INFO_COMMAND, [
				'phone' => $phone,
				'get_operator' => '1',
		]);

		return new PhoneOperator(
				$ret->operator,
				$ret->region
		);
	}

	/**
	 * Internal method for querying
	 *
	 * @param string $command
	 * @param array $params
	 * @return mixed
	 * @throws \Exception
	 */
	protected function query(
			$command,
			array $params = []
	) {
		$params = array_merge($params, [
				'fmt' => '3',
				'login' => $this->login,
				'psw' => md5($this->password),
		]);
		$url = static::BASE_URL . $command . '.php';

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$ret = curl_exec($ch);

		if (!$ret) {
			throw new ServerException('Empty server response');
		}
		$ret = json_decode($ret);
		if (!empty($ret->error_code) && !empty($ret->error)) {
			throw new SMSServerException('Error ' . $ret->error_code . ': ' . $ret->error, $ret->error_code);
		}

		return $ret;
	}

	/**
	 * Internal method for populating date with TZ
	 *
	 * @param string $date
	 * @return \DateTime
	 */
	protected function getDate($date) {
		$date = \DateTime::createFromFormat(
				static::DATE_FORMAT,
				$date,
				new \DateTimeZone(static::DATE_TIMEZONE)
		);
		return $date;
	}


}
