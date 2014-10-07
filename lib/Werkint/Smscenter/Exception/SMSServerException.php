<?
namespace Werkint\SMSCenter\Exception;

class SMSServerException extends \Exception
{
	/* http://smsc.ru/api/http/#answer */

	/** Ошибка в параметрах */
	const PARAMETERS_ERROR = 1;
	/** Неверный логин или пароль */
	const LOGIN_ERROR = 2;
	/** Недостаточно средств на счете Клиента */
	const INSUFFICIENT_FUNDS = 3;
	/** IP-адрес временно заблокирован из-за частых ошибок в запросах */
	const IP_BLOCKED = 4;
	/** Неверный формат даты */
	const INCORRECT_DATE_FORMAT = 5;
	/** Сообщение запрещено (по тексту или по имени отправителя) */
	const MESSAGE_DENIED = 6;
	/** Неверный формат номера телефона */
	const INCORRECT_PHONE_NUMBER_FORMAT = 7;
	/** Сообщение на указанный номер не может быть доставлено */
	const MESSAGE_CANT_DELIVERED = 8;
	/** Отправка более одного одинакового запроса на передачу SMS-сообщения либо более пяти одинаковых запросов на получение стоимости сообщения в течение минуты */
	const SPAM_BAN = 9;

}