<?php
namespace PYSys\BankAccount;

use Nette\Http\Url;
use Nette\Object;
use Nette\Utils\ArrayHash;

/**
 * Class Raiffeisenbank
 * @package PYSys\BankAccount
 */
class Raiffeisenbank extends Object {

	/* SETTINGS */
	private $shopName;
	private $accountPassword;
	private $creditAccount;
	private $creditBank;

	/* SERVERS */
	/** @var int Selected server to connection */
	protected $server;
	const SERVER_DEVELOPMENT 		= 0;
	const SERVER_1 					= 1;
	const SERVER_2 					= 2;
	const SERVER_3 					= 3;
	const SERVER_4 					= 4;
	protected $servers = array(
		self::SERVER_DEVELOPMENT 	=> 'klient2.rb.cz/test_shop',
		self::SERVER_1 				=> 'klient1.rb.cz/ebts',
		self::SERVER_2 				=> 'klient2.rb.cz/ebts',
		self::SERVER_3 				=> 'klient3.rb.cz/ebts',
		self::SERVER_4 				=> 'klient4.rb.cz/ebts'
	);

	/* METHODS */
	const METHOD_PAYMENT 			= 0;
	const METHOD_GETPAYMENTS 		= 1;
	protected $method_url = array(
		self::METHOD_PAYMENT 		=> "/owa/shop.payment",
		self::METHOD_GETPAYMENTS 	=> "/owa/shop.getpayments"
	);

	/* LISTTYPES */
	const LISTTYPE_OLD 				= 'OLD';
	const LISTTYPE_HTML 			= 'HTML';
	const LISTTYPE_PLAIN 			= 'PLAIN';

	/**
	 * @param $server
	 * @param $shopName
	 * @param $accountPassword
	 * @param $creditAccount
	 * @param $creditBank
	 */
	public function __construct($server, $shopName, $accountPassword, $creditAccount, $creditBank) {
		$this->server				= $server;
		$this->shopName 			= $shopName;
		$this->accountPassword 		= $accountPassword;
		$this->creditAccount 		= $creditAccount;
		$this->creditBank 			= $creditBank;
	}

	/**
	 * @param \DateTime $from
	 * @param \DateTime $to
	 * @return array RaiffeisenbankPayment
	 * @throws RaiffeisenbankException
	 */
	public function getPayments(\DateTime $from=null, \DateTime $to=null) {
		$url = $this->generateUrl(self::METHOD_GETPAYMENTS);
		if ($from) {
			$url->appendQuery("paidfrom=" . $from->form('d.m.Y'));
		}
		if ($to) {
			$url->appendQuery("paidto=" . $to->form('d.m.Y'));
		}

		$response = trim(@file_get_contents($url));
		$response = @iconv('WINDOWS-1250', 'UTF-8', $response);
		if(!$this->checkErrors($response)) {
			return array();
		}

		$lines = explode("\n", str_replace(array("\n", "\r\n"), "\n", $response));
		$payments = array();
		foreach($lines as $line) {
			$payments[] = RaiffeisenbankPayment::fromLine($line);
		}

		return $payments;
	}

	/**
	 * @param $response
	 * @return bool
	 * @throws RaiffeisenbankException
	 */
	public function checkErrors($response) {
		if(empty($response)) {
			return false; // Unavailable server or empty list
		} elseif (strpos($response,'lze volat') !== false) {
			throw new RaiffeisenbankException('Reached 60s call limit');
		} elseif (strpos($response,'nelze') !== false) {
			throw new RaiffeisenbankException('Shop or Account not exists');
		} elseif (strpos($response,'heslo') !== false) {
			throw new RaiffeisenbankException('Bad account password');
		}
		return true;
	}

	/**
	 * @param $variable_symbol
	 * @return bool|Payment
	 * @throws RaiffeisenbankException
	 */
	public function getPaymentByVariableSymbol($variable_symbol) {
		$url = $this->generateUrl(self::METHOD_GETPAYMENTS);
		$url->appendQuery("varsymbol=" . $variable_symbol);

		$response = trim(@file_get_contents($url));
		$response = @iconv('WINDOWS-1250', 'UTF-8', $response);
		if(!$this->checkErrors($response)) {
			return false;
		}

		return RaiffeisenbankPayment::fromLine(trim($response));
	}

	/**
	 * @param int $method
	 * @return Url
	 */
	protected function generateUrl($method) {
		$url = new Url("https://" . $this->servers[$this->server] . $this->method_url[$method]);
		$url->appendQuery("shopname=" . $this->shopName);
		$url->appendQuery("password=" . $this->accountPassword);
		$url->appendQuery("creditaccount=" . $this->creditAccount);
		$url->appendQuery("creditbank=" . $this->creditBank);
		$url->appendQuery("listtype=" . self::LISTTYPE_PLAIN);
		return $url;
	}

}

/**
 * Class Payment
 * @package PYSys\BankAccount
 */
class RaiffeisenbankPayment extends ArrayHash {

	const STATUS_NOTPAID = 0;
	const STATUS_PARTLY_PAID = 1;
	const STATUS_PAID = 2;
	const STATUS_SUSPENDED = 3;
	const STATUS_STORNO = 4;
	const STATUS_CLEARING_WAITING = 5;

	protected static $columns = array(
		"maturity_first_day",
		"maturity_last_day",
		"amount_prescribed",
		"currency_code",
		"amount_transferred",
		"transfer_date",
		"client_account",
		"client_bank",
		"seller_account",
		"seller_bank",
		"variable_symbol",
		"constant_symbol",
		"note",
		"status",
		"account_name",
		"transaction_id"
	);

	/**
	 * @param $line
	 * @return static
	 */
	public static function fromLine($line) {
		$payment = new static;
		$i = 0;
		foreach (explode(';',$line) as $col) {
			switch (self::$columns[$i]) {
				case 'maturity_first_day': $ecol = new \DateTime($col); break;
				case 'maturity_last_day': $ecol = new \DateTime($col); break;
				case 'transfer_date': $ecol = new \DateTime($col); break;
				case 'amount_prescribed': $ecol = str_replace(array(' ',','),array('','.'),($col)); break;
				case 'amount_transferred': $ecol = str_replace(array(' ',','),array('','.'),($col)); break;
				default: $ecol = trim($col);
			}
			$payment->{self::$columns[$i]} = $ecol;
			$i++;
		}
		return $payment;
	}

	/**
	 * @return bool
	 */
	public function isPaid() {
		return ($this->status == self::STATUS_PAID);
	}

	/**
	 * @return string
	 */
	public function getId() {
		return $this->transaction_id;
	}

	/**
	 * @return string
	 */
	public function getVariableSymbol() {
		return $this->variable_symbol;
	}

	/**
	 * @return string
	 */
	public function getAmount() {
		return $this->amount_transferred;
	}

}

/**
 * Class RaiffeisenbankException
 * @package PYSys\BankAccount
 */
class RaiffeisenbankException extends \Exception {}