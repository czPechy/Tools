<?php
namespace PYSys\Tools;

use Nette\Object;
use Nette\Utils\ArrayHash;
use Nette\Utils\Strings;

class Mailchimp extends Object
{
	private $api_key;
	private $api_endpoint = 'https://<dc>.api.mailchimp.com/3.0/';
	private $verify_ssl = false;

	const TYPE_GET = 0;
	const TYPE_POST = 1;
	const TYPE_PATCH = 2;

	const MEMBER_SUBSCRIBED = "subscribed";
	const MEMBER_UNSUBSCRIBED = "unsubscribed";
	const MEMBER_PENDING = "pending";
	const MEMBER_CLEANED = "cleaned";
	const MEMBER_NOTEXISTS = "notexists";

	/** @var ArrayHash|false */
	public $last_result;

	/**
	 * Create a new instance
	 */
	function __construct($api_key)
	{
		try {
			$this->api_key = trim($api_key);
			list(, $datacentre) = explode('-', $this->api_key);
			$this->api_endpoint = str_replace('<dc>', $datacentre, $this->api_endpoint);
		} catch (\Exception $e) {
			throw new MailchimpException("Unable to set API key");
		}
	}

	/**
	 *
	 * Check, if email exist in list and return status
	 *
	 * @param $list
	 * @param $email
	 * @return string
	 * @throws MailchimpException
	 * @throws \Exception
	 */
	public function checkSubscriber($list, $email) {
		if(empty($list)) {
			throw new MailchimpException("Undefined Mailchimp list");
		}

		$email = Strings::lower($email);

		try {
			$this->call('lists/'.$list.'/members/' . md5($email), array(), self::TYPE_GET);
		} catch (MailchimpException $e) {
			if($e->getCode() != 404) {
				throw $e;
			}
			return self::MEMBER_NOTEXISTS;
		}

		return $this->last_result->status;
	}

	/**
	 *
	 * Add or update email for subscribe newsletters
	 *
	 * @param $list
	 * @param $email
	 * @return bool
	 * @throws MailchimpException
	 * @throws \Exception
	 */
	public function addSubscriber($list, $email) {
		if(empty($list)) {
			throw new MailchimpException("Undefined Mailchimp list");
		}

		$email = Strings::lower($email);
		$member_status = $this->checkSubscriber($list, $email);

		if($member_status == self::MEMBER_NOTEXISTS) {
			$result = $this->call('lists/'.$list.'/members/', array(
				'email_address' => $email,
				'status' => self::MEMBER_SUBSCRIBED
			), self::TYPE_POST);

			return ($result->status == self::MEMBER_SUBSCRIBED ? true : false);
		} elseif ($member_status != self::MEMBER_SUBSCRIBED) {
			$result = $this->call('lists/'.$list.'/members/' . md5($email), array(
				"status" => self::MEMBER_SUBSCRIBED,
			), self::TYPE_PATCH);

			return ($result->status == self::MEMBER_SUBSCRIBED ? true : false);
		}

		return true;
	}

	/**
	 *
	 * Unsubscribe email from list
	 *
	 * @param $list
	 * @param $email
	 * @return bool
	 * @throws MailchimpException
	 * @throws \Exception
	 */
	public function delSubscriber($list, $email) {
		if(empty($list)) {
			throw new MailchimpException("Undefined Mailchimp list");
		}

		$email = Strings::lower($email);
		$member_status = $this->checkSubscriber($list, $email);

		if($member_status != self::MEMBER_UNSUBSCRIBED && $member_status != self::MEMBER_NOTEXISTS) {
			$result = $this->call('lists/'.$list.'/members/' . md5($email), array(
				"status" => self::MEMBER_UNSUBSCRIBED,
			), self::TYPE_PATCH);

			return ($result->status == self::MEMBER_UNSUBSCRIBED ? true : false);
		}

		return true;
	}

	/**
	 * Call an API method. Every request needs the API key, so that is added automatically -- you don't need to pass it in.
	 * @param  string $method The API method to call, e.g. 'lists/list'
	 * @param  array  $args   An array of arguments to pass to the method. Will be json-encoded for you.
	 * @return array          Associative array of json decoded API response.
	 */
	public function call($method, $args=array(), $type=self::TYPE_POST)
	{
		return $this->_raw_request($method, $args, $type);
	}

	/**
	 * Performs the underlying HTTP request. Not very exciting
	 * @param  string $method The API method to be called
	 * @param  array  $args   Assoc array of parameters to be passed
	 * @param  int  $type   Type of request
	 * @throws MailchimpException Errors
	 * @return ArrayHash Result
	 */
	private function _raw_request($method, $args=array(), $type=self::TYPE_POST)
	{
		$url = $this->api_endpoint . $method;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'Authorization: apikey ' . $this->api_key
		));
		curl_setopt($ch, CURLOPT_USERAGENT, 'PHP-MCAPI/3.0');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_POST, ($type == self::TYPE_POST ? true : false));
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verify_ssl);
		if($type != self::TYPE_GET) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($args));
		}
		if($type == self::TYPE_PATCH) {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
		}
		$result = curl_exec($ch);
		curl_close($ch);

		$this->last_result = ($result && json_decode($result) ? ArrayHash::from(json_decode($result)) : false);

		if(!$this->last_result) {
			throw new MailchimpException("Cannot connect to Mailchimp");
		}

		if(($type == self::TYPE_POST && !empty($this->last_result->code) && $this->last_result->code != 200) ||
			($type == self::TYPE_POST && !empty($this->last_result->status) && $this->last_result->status != 200) ||
			($type == self::TYPE_GET && !empty($this->last_result->status) && is_int($this->last_result->status) && $this->last_result->status != 200)) {

			throw new MailchimpException($this->last_result->title . " | " . $this->last_result->detail, (!empty($this->last_result->code) ? $this->last_result->code : $this->last_result->status));

		}

		return $this->last_result;
	}

}

class MailchimpException extends \Exception {}