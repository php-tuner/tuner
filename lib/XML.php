<?php
// XML
class XML {

	/**
	 * Encode an object as XML string
	 *
	 * @param Object $obj
	 * @param string $root_node
	 * @return string $xml
	 */
	public function encodeObj($obj, $root_node = 'response', $encoding = "utf-8") {
		$xml = "<?xml version=\"1.0\" encoding=\"{$encoding}\"?>" . PHP_EOL;
		$xml .= self::encode($obj, $root_node, $depth = 0);
		return $xml;
	}

	/**
	 * Encode an object as XML string
	 *
	 * @param Object|array $data
	 * @param string $root_node
	 * @param int $depth Used for indentation
	 * @return string $xml
	 */
	private function encode($data, $node, $depth) {
		$xml = str_repeat("\t", $depth);
		$xml .= "<{$node}>" . PHP_EOL;
		foreach ($data as $key => $val) {
			if (is_array($val) || is_object($val)) {
				$xml .= self::encode($val, $key, ($depth + 1));
			} else {
				$xml .= str_repeat("\t", ($depth + 1));
				$xml .= "<{$key}>" . htmlspecialchars($val) . "</{$key}>" . PHP_EOL;
			}
		}
		$xml .= str_repeat("\t", $depth);
		$xml .= "</{$node}>" . PHP_EOL;
		return $xml;
	}

}