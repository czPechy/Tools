<?php
namespace PYSys\Tools;
use Nette\Utils\ArrayHash;
use Nette\Utils\Strings;

/**
 * Class Ares
 * @package PYSys\Tools
 *
 * Class for getting data from ARES Database
 */
class Ares {

	/** @var string Url to call methods */
	protected static $url = "http://wwwinfo.mfcr.cz/cgi-bin/ares/darv_bas.cgi?ico=";

	/**
	 * Returning data from ARES database
	 * @param $ico
	 * @return ArrayHash
	 * @throws AresException
	 */
	public static function getByICO($ico) {
		$data = self::getData(self::$url.$ico);
		$xml = self::parseXml($data);
		if(!empty($xml) && !empty($xml->E->EK)) {
			throw new AresException('Error occurred while finding ICO in ARES');
		} elseif(empty($xml)) {
			throw new AresException('ARES Database is not available');
		}

		$name_arr = explode(' ',strval($xml->VBAS->OF));
		$first_name = array_shift($name_arr);
		$last_name = implode(' ',$name_arr);
		$excludeUpper = array("s.r.o.","a.s.");

		$ares_data							= new ArrayHash();
		$ares_data->ico 					= strval($xml->VBAS->ICO);
		$ares_data->dic 					= strval($xml->VBAS->DIC);
		$ares_data->company 				= strval($xml->VBAS->OF);
		$ares_data->first_name				= Strings::firstUpper(Strings::lower($first_name));
		$ares_data->last_name				= (!in_array($last_name,$excludeUpper) ? Strings::firstUpper(Strings::lower($last_name)) : Strings::lower($last_name));
		$ares_data->address_street			= strval($xml->VBAS->AA->NU);
		$ares_data->address_street_number	= strval($xml->VBAS->AA->CO);
		$ares_data->address_citypart		= strval($xml->VBAS->AA->NMC);
		$ares_data->address_citypart_number	= strval($xml->VBAS->AA->CD);
		$ares_data->address_fullnumber		=
			($ares_data->address_citypart_number || $ares_data->address_street_number ?
				($ares_data->address_citypart_number && $ares_data->address_street_number ?
					$ares_data->address_citypart_number."/".$ares_data->address_street_number :
					($ares_data->address_street_number ?
						$ares_data->address_street_number :
						$ares_data->address_citypart_number
					)
				) :
				""
			);
		$ares_data->address_town			= strval($xml->VBAS->AA->N);
		$ares_data->address_post			= strval($xml->VBAS->AA->PSC);
		$ares_data->address_country			= strval($xml->VBAS->AA->NS);

		return $ares_data;
	}

	/**
	 * Get content of url
	 * @param $url
	 * @return string
	 */
	protected static function getData($url) {
		return @file_get_contents($url);
	}

	/**
	 * Parsing XML from ARES
	 * @param $content
	 * @return null|obj
	 */
	protected static function parseXml($content) {
		if(!$content) {
			return null;
		}
		$xml = @simplexml_load_string($content);
		if(!$xml) {
			return null;
		}
		$namespaces = $xml->getDocNamespaces();
		$result = $xml->children($namespaces['are']);
		return $result->children($namespaces['D']);
	}

}
class AresException extends \Exception {}