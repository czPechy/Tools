<?php
namespace PYSys\Tools;

use Nette\Object;
use Nette\Utils\ArrayHash;

/**
 * Class CsvParser
 * @package PYSys\Tools
 *
 * For parsing CSV files
 */
class CsvParser extends Object
{

	public static function from($file,$delimiter=";",$limit=null,$offset=null) {
		$file = fopen($file, "r");
		if (!$file) {
			throw new CsvParserException("Cannot read/open file");
		}
		$data = array();
		$columns = array();

		while($line = fgetcsv($file,4096, $delimiter)) {
			$columns = $line;
			break;
		}

		foreach ($columns as $key => $column) {
			$columns[$key] = iconv("windows-1250",'utf-8',$column);
		}

		$i=0;
		$l=0;
		while($line = fgetcsv($file,4096, $delimiter)) {
			$i++; if($offset !== null && $offset > $i) continue;
			$l++;
			$paired_colums = array();
			foreach ($columns as $key => $column) {
				if(isset($line[$key]))
					$paired_colums[$column] = iconv("windows-1250",'utf-8',$line[$key]);
					//$paired_colums[$column] = $line[$key];
			}
			$data[] = $paired_colums;
			if($limit !== null && $limit <= $l) break;
		}
		fclose($file);
		$return = new ArrayHash();
		$return->columns = ArrayHash::from($columns);
		$return->data = ArrayHash::from($data);
		return $return;
	}

	public static function to($filename,$csv_data) {
		if(file_exists($filename)) {
			unlink($filename);
		}
		file_put_contents($filename,"");
		foreach ($csv_data as $line) {
			$csv_line = iconv('utf-8','windows-1250',"\"".implode("\";\"",$line)."\"\r\n");
			file_put_contents($filename,$csv_line,FILE_APPEND);
		}
		return true;
	}

}
class CsvParserException extends \Exception {}