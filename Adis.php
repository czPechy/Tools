<?php
namespace PYSys\Tools;

/**
 * Class Adis
 * @package PYSys\Tools
 *
 * Class for getting data from ADIS Database
 */
class Adis {

	const PAYER_FOUND = "found";
	const PAYER_NOT_FOUND = "not_found";

	/** @var \SoapClient */
	protected static $adis = null;

	protected static function init()
	{
		try {
			self::$adis = new \SoapClient('http://adisrws.mfcr.cz/adistc/axis2/services/rozhraniCRPDPH.rozhraniCRPDPHSOAP?wsdl');
		} catch (\Exception $e) {
			throw new AdisException("Cannot connect or parse Adis server");
		}
	}

	/**
	 * Searching DIC
	 * @param array|int|string $dic
	 * @return array
	 * @throws AdisException
	 */
	public static function search($dic) {
		self::init();

		if(!is_array($dic)) {
			$dic = array($dic);
		}

		try {
			/** @var \stdClass $list */
			$list = self::$adis->__soapCall("getStatusNespolehlivyPlatce",array("dph"=>$dic));
		} catch (\Exception $e) {
			throw new AdisException("Error in getting list");
		}

		$payers = array();
		if($list->status->statusCode === 0 && isset($list->statusPlatceDPH)) {
			if(is_array($list->statusPlatceDPH)) {
				foreach($list->statusPlatceDPH as $payer) {
					if(isset($payer->nespolehlivyPlatce) && $payer->nespolehlivyPlatce === "NENALEZEN") {
						$unreliablePayer = new \stdClass();
						$unreliablePayer->dic = $payer->dic;
						$unreliablePayer->status = self::PAYER_NOT_FOUND;
						$unreliablePayer->unreliable = null;
						$unreliablePayer->published = null;
						$unreliablePayer->numberFu = null;
						$payers[] = $unreliablePayer;
					}

					$unreliablePayer = new \stdClass();
					$unreliablePayer->dic = $payer->dic;
					$unreliablePayer->status = self::PAYER_FOUND;
					$unreliablePayer->unreliable = $payer->nespolehlivyPlatce == "ANO" ? true : false;
					$unreliablePayer->published = $unreliablePayer->unreliable ? $payer->datumZverejneniNespolehlivosti : null;
					$unreliablePayer->numberFu = $unreliablePayer->unreliable ? $payer->cisloFu : null;
					$payers[] = $unreliablePayer;
				}
			} else {
				if(isset($list->statusPlatceDPH->nespolehlivyPlatce) && $list->statusPlatceDPH->nespolehlivyPlatce === "NENALEZEN") {
					$unreliablePayer = new \stdClass();
					$unreliablePayer->dic = $list->statusPlatceDPH->dic;
					$unreliablePayer->status = self::PAYER_NOT_FOUND;
					$unreliablePayer->unreliable = null;
					$unreliablePayer->published = null;
					$unreliablePayer->numberFu = null;
					$payers[] = $unreliablePayer;
				} else {
					$unreliablePayer = new \stdClass();
					$unreliablePayer->dic = $list->statusPlatceDPH->dic;
					$unreliablePayer->status = self::PAYER_FOUND;
					$unreliablePayer->unreliable = $list->statusPlatceDPH->nespolehlivyPlatce == "ANO" ? true : false;
					$unreliablePayer->published = $unreliablePayer->unreliable ? $list->statusPlatceDPH->datumZverejneniNespolehlivosti : null;
					$unreliablePayer->numberFu = $unreliablePayer->unreliable ? $list->statusPlatceDPH->cisloFu : null;
					$payers[] = $unreliablePayer;
				}
			}
		} else {
			throw new AdisException("Bad parameters");
		}
		return $payers;

	}

	/**
	 * Return full list of unreliable payers
	 * @return array
	 * @throws AdisException
	 */
	public static function unreliablePayers() {
		self::init();
		try {
			/** @var \stdClass $list */
			$list = self::$adis->getSeznamNespolehlivyPlatce();
		} catch (\Exception $e) {
			throw new AdisException("Error in getting list");
		}

		$unreliablePayers = array();
		if(isset($list->statusPlatceDPH)) {
			foreach($list->statusPlatceDPH as $payer) {
				$unreliablePayer = new \stdClass();
				$unreliablePayer->dic = $payer->dic;
				$unreliablePayer->unreliable = $payer->nespolehlivyPlatce == "ANO" ? true : false;
				$unreliablePayer->published = $payer->datumZverejneniNespolehlivosti;
				$unreliablePayer->numberFu = $payer->cisloFu;
				$unreliablePayers[] = $unreliablePayer;
			}
		}
		return $unreliablePayers;
	}

}
class AdisException extends \Exception {}