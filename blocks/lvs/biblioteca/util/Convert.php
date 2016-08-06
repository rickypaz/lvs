<?php
namespace uab\ifce\lvs\util;

/**
 *	Conversor de objetos e arrays
 * 	
 * 	@see http://www.php.net/manual/en/language.types.object.php#102735
 */
final class Convert {
	
	/**
	 * 	Convert a stdClass to an Array
	 * 
	 * 	@param stdClass $Class
	 * 	@return array
	 */
	static public function object_to_array(stdClass $Class){
		// Typecast to (array) automatically converts stdClass -> array.
		$Class = (array)$Class;
	
		// Iterate through the former properties looking for any stdClass properties.
		// Recursively apply (array).
		foreach($Class as $key => $value) {
			if(is_object($value)&&get_class($value)==='stdClass'){
				$Class[$key] = self::object_to_array($value);
			}
		}
		
		return $Class;
	}

	/**
	 * 	Convert an Array to stdClass
	 * 
	 * 	@param array $array
	 * 	@return StdClass
	 */
	static public function array_to_object(array $array)
	{
		foreach($array as $key => $value) {
			if(is_array($value)){
				$array[$key] = self::array_to_object($value);
			}
		}
		
		return (object)$array;
	}
}
?>