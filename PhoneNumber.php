<?php
namespace PYSys\Tools;

use Nette\InvalidArgumentException;

/**
 * Class PhoneNumber
 * @package PYSys\Tools
 */
class PhoneNumber extends \Nette\Object
{

	/**
	 * Countries
	 */
	const COUNTRY_CS = "cs";
	protected static $countries = array(
		self::COUNTRY_CS,
	);
	protected static $countryAreas = array(
		'cs' => '00420'
	);

	/**
	 * Formats
	 */
	const FORMAT_INTERNATIONAL = 0;	// 00420123456789
	const FORMAT_GSM = 1;			// +420123456789
	const FORMAT_FULL = 2;			// +420 123 456 789
	const FORMAT_CANONICAL = 3;		// +420 123 456 789
	const FORMAT_STRAIGHT = 4;		// 123456789
	protected static $formats = array(
		self::FORMAT_INTERNATIONAL,
		self::FORMAT_GSM,
		self::FORMAT_FULL,
		self::FORMAT_CANONICAL,
		self::FORMAT_STRAIGHT
	);


	/**
	 * Country Regulars
	 */
	protected static $formatReg = array(
		'cs' => '~^(([+]|[0]{2})[0-9]{3})*(\s)*[0-9]{3}(\s)*[0-9]{3}(\s)*[0-9]{3}$~'
	);
	protected static $areaReg = array(
		'cs' => '~^[+][0-9]{3}|[0]{2}[0-9]{3}~'
	);

	/**
	 * Messages
	 */
	protected static $messages = array(
		"NOT_SUPPORTED" => "Class PhoneNumber not support country ",
		"BAD_NUMBER" => "Passed phone number is not valid ",
	);

	/**
	 * Class settings
	 */
	protected $country = self::COUNTRY_CS;
	protected $number;
	protected $areaCode;
	protected $format = self::FORMAT_GSM;

	/**
	 * @param $phoneNumber
	 * @param string $country
	 * @return PhoneNumber
	 */
	static function from($phoneNumber,$country = self::COUNTRY_CS)
	{
		$obj = new static;
		$obj->setCountry($country);
		$obj->setNumber($phoneNumber);
		return $obj;
	}

	/**
	 * @return bool
	 */
	public function isValid() {
		return (bool) preg_match($this->formatReg[$this->country],$this->__toString());
	}

	/**
	 * @param $phone
	 * @param string $country
	 * @return bool
	 * @throws \Nette\InvalidArgumentException
	 */
	static function isPhone($phone,$country=self::COUNTRY_CS) {
		if(!self::isCountrySupported($country)) {
			throw new InvalidArgumentException(self::$messages['NOT_SUPPORTED'] . "'{$country}'");
		}
		return (bool) preg_match(self::$formatReg[$country],$phone);
	}

	/**
	 * @param $country
	 * @throws \Nette\InvalidArgumentException
	 */
	public function setCountry($country) {
		if(!$this->isCountrySupported($country)) {
			throw new InvalidArgumentException(self::$messages['NOT_SUPPORTED'] . "'{$country}'");
		}
		$this->country = $country;
	}

	/**
	 * @param $country
	 * @return bool
	 */
	public static function isCountrySupported($country) {
		if(!in_array($country,self::$countries)) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * @param $number
	 */
	public function setNumber($number) {
		if(!$this->isPhone($number)) {
			throw new InvalidArgumentException(self::$messages['BAD_NUMBER']);
		}
		$parts = $this->parseNumber($number);
		$this->setAreaPart($parts['area']);
		$this->setNumberPart($parts['number']);
	}

	/**
	 * @param $area
	 */
	public function setAreaPart($area) {
		$area = str_replace('+','00',$area);
		$this->areaCode = $area;
	}

	/**
	 * @param $number
	 */
	public function setNumberPart($number) {
		$this->number = $number;
	}

	/**
	 * @param $number
	 */
	protected function parseNumber($number) {
		$parts = array();
		if(preg_match(self::$areaReg[$this->country],$number,$matches)) {
			$parts['area'] = $matches[0];
			$parts['number'] = str_replace(array($matches[0],' '),'',$number);
		} else {
			$parts['area'] = self::$countryAreas[$this->country];
			$parts['number'] = str_replace(' ','',$number);
		}

		return $parts;
	}

	/**
	 * @return string
	 */
	public function getAreaPart() {
		return $this->areaCode;
	}

	/**
	 * @return string
	 */
	public function getNumberPart() {
		return $this->number;
	}

	/**
	 * @param $format
	 * @throws \Nette\InvalidArgumentException
	 */
	public function setFormat($format) {
		if(!in_array($format,self::$formats)) {
			throw new InvalidArgumentException("Use format from class");
		}
		$this->format = $format;
	}

	/**
	 * @return string
	 */
	public function __toString() {
		switch ($this->format) {
			case self::FORMAT_INTERNATIONAL: return $this->getAreaPart().$this->getNumberPart(); break;
			case self::FORMAT_GSM: return str_replace("00","+",$this->getAreaPart()).$this->getNumberPart(); break;
			case self::FORMAT_FULL: return str_replace("00","+",$this->getAreaPart())." ".number_format($this->getNumberPart(),0,'',' '); break;
			case self::FORMAT_CANONICAL: return str_replace("00","+",$this->getAreaPart())." ".number_format($this->getNumberPart(),0,'',' '); break;
			case self::FORMAT_STRAIGHT: return $this->getNumberPart(); break;
			default: return "";
		}
	}

}