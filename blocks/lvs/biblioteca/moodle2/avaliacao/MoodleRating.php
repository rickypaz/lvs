<?php 
require_once 'avaliacao/LmsRating.php';

/**
 * class MoodleRating
 *
 *	@todo essa classe é usada?!?!
 */
class MoodleRating implements LmsRating {

	public function removerAvaliacao( $avaliacao ) {
		trigger_error("Implement " . __FUNCTION__);
	}
	
	public function salvarAvaliacao( $avaliacao ) {
		trigger_error("Implement " . __FUNCTION__);
	}

}
?>