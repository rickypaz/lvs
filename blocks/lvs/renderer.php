<?php
use uab\ifce\lvs\avaliacao\AvaliacaoLv;

require_once($CFG->dirroot.'/blocks/lvs/componentes/CarinhasImagens.php');
require_once($CFG->dirroot.'/blocks/lvs/pages/Template.class.php');

class block_lvs_renderer extends plugin_renderer_base {

	private $mostraAvaliacaoAtual = true;

	/**
	 * 	@see plugin_renderer_base::render()
	 * 	@deprecated
	 */
	public function render(renderable $widget) {
		$rendermethod = 'render_'.get_class($widget);

		if(strcmp(get_class($widget), "rating_lv") != 0) {
			throw new coding_exception('This renderer can only be used by rating_lv widget');
		}

		if($widget->extended)
			return $this->render_feedback_lv_estendido($widget);

		return $this->render_feedback_lv($widget);
	}

	public function avaliacaoAtual($rating) {
		if(is_numeric($rating)) {
			return $this->avaliacaoAtualPorNotaLV($rating);
		} else if(is_object($rating) && strcmp(get_class($rating), "rating_lv") == 0) {
			return $this->avaliacaoAtualPorRatingLV($rating);
		}
		return 'It cant show current grade!';
	}

	public function carinhasHtml(rating_lv $rating) {
		$carinhas = Carinhas::recuperarCarinhas();
		$carinhas_imgs = array();
		$carinhas_inputs = array();
		$table_carinhas = new html_table();
		$table_carinhas->align = array("center", "center", "center", "center", "center", "center");
			
		foreach ($carinhas as $carinha) {
			$carinhas_imgs[] = html_writer::empty_tag('img', array('src'=>$carinha['caminho'], 'name'=>$carinha['descricao'], 'alt'=>$carinha['descricao']));

			if($rating->rating !== null && $carinha['valor'] == $rating->rating)
				$carinhas_inputs[] = html_writer::empty_tag('input', array('type'=>'radio', 'name'=>"rating$rating->itemid", 'value'=>$carinha['valor'], 'class'=>'notalv', 'checked'=>'checked'));
			else
				$carinhas_inputs[] = html_writer::empty_tag('input', array('type'=>'radio', 'name'=>"rating$rating->itemid", 'value'=>$carinha['valor'], 'class'=>'notalv'));
		}
			
		$table_carinhas->data[] = $carinhas_imgs;
		$table_carinhas->data[] = $carinhas_inputs;
			
		return html_writer::table($table_carinhas);
	}

	public function carinhasHtmlEstendido(rating_lv $rating) {
		global $CFG;

		$output = Carinhas::carinhasHtml($rating);
		// 		$carinhas = Carinhas::recuperarCarinhas();

		// 		$escala = array_reverse(EscalaLikert::getExtendedValues());
		// 		$cores = $this->coresCarinhasEstendidas();
		// 		$carinhas_imgs = $carinhas_inputs = array();

		// 		$table_carinhas = new html_table();
		// 		$table_carinhas->align = array("center", "center", "center", "center", "center", "center");
			
		// 		$ic = 0;

		// 		foreach ($carinhas as $carinha) {
		// 			$table_inputs = new html_table();
		// 			$row = new html_table_row();
		// 			$numero_inputs = ($carinha['valor'] !== EscalaLikert::NEUTRO) ? 5 : 1;
		// 			$table_inputs->align = array_fill(0, $numero_inputs, "center");
		// 			$carinhas_imgs[] = html_writer::empty_tag('img', array('src'=>$carinha['caminho'], 'name'=>$carinha['descricao'], 'alt'=>$carinha['descricao']));

		// 			for($i=0; $i<$numero_inputs; $i++) {
		// 				$title = ($carinha['valor'] != EscalaLikert::NEUTRO) ? $escala[($ic*5) + $i] : '-';
		// 				$value = $escala[($ic*5) + $i];

		// 				$cell = new html_table_cell();
		// 				$cell->style = "background-color:$cores[$value]";

		// 				if($rating->rating !== null && $value == $rating->rating)
			// 					$cell->text = html_writer::empty_tag('input', array('type'=>'radio', 'name'=>"rating", 'value'=>$value, 'class'=>'notalv', 'title'=>$title, 'checked'=>'checked'));
			// 				else
				// 					$cell->text = html_writer::empty_tag('input', array('type'=>'radio', 'name'=>"rating", 'value'=>$value, 'class'=>'notalv', 'title'=>$title));
				
				// 				$row->cells[] = $cell;
				// 			}

				// 			$table_inputs->data[] = $row;

				// 			$carinhas_inputs[] = html_writer::table($table_inputs);
				// 			$ic++;
				// 		}

				// 		$table_carinhas->data[] = $carinhas_imgs;
				// 		$table_carinhas->data[] = $carinhas_inputs;

				return $output;
	}
	
	public function exibirForm( AvaliacaoLv $avaliacao ) {
		if ( $avaliacao->isCarinhasEstendido() )
			return $this->render_feedback_lv_estendido( $avaliacao );
		
		return $this->render_feedback_lv( $avaliacao );
	}

	public function render_feedback_lv( AvaliacaoLv $avaliacao ) {
		global $CFG, $USER;
	
		$ratingmanager = new rating_lv_manager();
		// Initialise the JavaScript so ratings can be done by AJAX.
		$ratingmanager->initialise_rating_javascript($this->page);
	
		$ratinghtml = ''; //the string we'll return
	
		if ($rating->count > 0) {
			$countstr = "({$rating->count})";
		} else {
			$countstr = '-';
		}
	
		list($type, $name) = normalize_component($rating->component);
		$usuario_eh_coordenador = has_capability("mod/$name:viewanyrating", $rating->context, $rating->itemuserid);
	
		if(!$usuario_eh_coordenador) {
			$formstart = null;
	
			// if the item doesn't belong to the current user, the user has permission to rate
			// and we're within the assessable period
			if ($rating->user_can_rate()) {
				$rateurl = $rating->get_rate_url();
				$inputs = $rateurl->params();
	
				//start the rating form
				$formattrs = array(
						'id'     => "formratinglv{$rating->itemid}",
						'method' => 'post',
						'action' => $rateurl->out_omit_querystring()
						);
				$formstart  = html_writer::start_tag('form', $formattrs);
				$formstart .= html_writer::start_tag('div', array('class' => 'ratingform'));
	
				// add the hidden inputs
				foreach ($inputs as $name => $value) {
					$attributes = array('type' => 'hidden', 'class' => 'ratinginput', 'name' => $name, 'value' => $value);
					$formstart .= html_writer::empty_tag('input', $attributes);
				}
	
				$ratinghtml = $formstart . $this->carinhasHtml($rating);
	
				if(true || !$ratingmanager->isAjaxEnabled()) { // fixme retirar o true!!
					//output submit button
					$ratinghtml .= html_writer::start_tag('span', array('class'=>"ratingsubmit"));
					$attributes = array('type' => 'submit', 'class' => 'postratingmenusubmit', 'id' => 'postratingsubmit'.$rating->itemid, 'value' => s(get_string('rate', 'rating')));
					$ratinghtml .= html_writer::empty_tag('input', $attributes);
					$ratinghtml .= html_writer::end_tag('span');
				}
					
				$ratinghtml .= html_writer::end_tag('div');
				$ratinghtml .= html_writer::end_tag('form');
			} else if($this->mostraAvaliacaoAtual) {
				if($USER->id == $rating->itemuserid || has_capability("mod/$name:viewanyrating", $rating->context))
					$ratinghtml = $this->avaliacaoAtual($rating);
			}
		}
	
		return $ratinghtml;
	}
	
	public function render_feedback_lv_estendido(renderable $rating) {
		global $CFG, $USER;
	
		$ratingmanager = new rating_lv_manager();
		// Initialise the JavaScript so ratings can be done by AJAX.
		//$ratingmanager->initialise_rating_javascript($this->page);
	
		$strrate = get_string("rate", "rating");
		$ratinghtml = ''; //the string we'll return
	
		if ($rating->count > 0) {
			$countstr = "({$rating->count})";
		} else {
			$countstr = '-';
		}
	
		if($this->mostraAvaliacaoAtual)
			$ratinghtml = $this->avaliacaoAtual($rating);
	
		$formstart = null;
	
		// if the item doesn't belong to the current user, the user has permission to rate
		// and we're within the assessable period
		if ($rating->user_can_rate()) {
			$rateurl = $rating->get_rate_url();
			$inputs = $rateurl->params();
	
			//start the rating form
			$formattrs = array(
					'id'     => "postrating{$rating->itemid}",
					'class'  => 'postratingform',
					'method' => 'post',
					'action' => $rateurl->out_omit_querystring()
					);
			$formstart  = html_writer::start_tag('form', $formattrs);
			$formstart .= html_writer::start_tag('div', array('class' => 'ratingform'));
	
			// add the hidden inputs
			foreach ($inputs as $name => $value) {
				$attributes = array('type' => 'hidden', 'class' => 'ratinginput', 'name' => $name, 'value' => $value);
				$formstart .= html_writer::empty_tag('input', $attributes);
			}
	
			if (empty($ratinghtml)) {
				$ratinghtml .= $strrate.': ';
			}
				
			$ratinghtml = $formstart . $ratinghtml . $this->carinhasHtmlEstendido($rating);
	
			//output submit button
			$ratinghtml .= html_writer::start_tag('span', array('class'=>"ratingsubmit"));
	
			$attributes = array('type' => 'submit', 'class' => 'postratingmenusubmit', 'id' => 'postratingsubmit'.$rating->itemid, 'value' => s(get_string('rate', 'rating')));
			$ratinghtml .= html_writer::empty_tag('input', $attributes);
	
			$ratinghtml .= html_writer::end_tag('span');
			$ratinghtml .= html_writer::end_tag('div');
			$ratinghtml .= html_writer::end_tag('form');
		}
	
		return $ratinghtml;
	}
	
	private function avaliacaoAtualPorNotaLV($rating, $extended = true) {
		$carinha = (!$extended) ? Carinhas::recuperarCarinha($rating) : Carinhas::recuperarExtendedCarinha($rating);
		$ratinghtml = html_writer::label('Avaliação Atual:', '');
	
		if($rating !== null) {
			$ratinghtml .= html_writer::empty_tag('img', array('src'=>$carinha['caminho'], 'name'=>$carinha['descricao'], 'alt'=>$carinha['descricao']));
		} else {
			$ratinghtml .= '<i> Não avaliado</i>';
		}
	
		return $ratinghtml;
	}
	
	private function avaliacaoAtualPorRatingLV(rating_lv $rating) {
		$carinha = (!$rating->extended) ? Carinhas::recuperarCarinha($rating->rating) : Carinhas::recuperarExtendedCarinha($rating->rating);
		$ratinghtml = html_writer::label('Avaliação Atual:', '');
	
		if($rating->rating !== null) {
			$ratinghtml .= html_writer::empty_tag('img', array('src'=>$carinha['caminho'], 'name'=>$carinha['descricao'], 'alt'=>$carinha['descricao']));
		} else {
			$ratinghtml .= '<i> Não avaliado</i>';
		}
	
		return $ratinghtml;
	}
	
	private function coresCarinhasEstendidas() {
		$cores = array(
				EscalaLikert::NEUTRO => "#FFFFFF", //Branco
				"0.00" => "#FF0000", // Vermelho
				"0.70" => "#FF0000", // Vermelho
				"1.31" => "#FF0000", // Vermelho
				"1.95" => "#FF6347", // Vermelho
				"2.59" => "#FF6347", // Vermelho
				"3.21" => "#EE7600", // Laranja
				"3.83" => "#EE7600", // Laranja
				"4.42" => "#FFA500", // Laranja
				"5.00" => "#FFD700", // Laranja
				"5.56" => "#FFD700", // Laranja
				"6.09" => "#FFFF00", // Amarelo
				"6.59" => "#FFFF00", // Amarelo
				"7.07" => "#FFF68F", // Amarelo
				"7.52" => "#FFFF00", // Amarelo
				"7.93" => "#FFF68F", // Amarelo
				"8.31" => "#C0FF3E", // Verde
				"8.66" => "#C0FF3E", // Verde
				"8.97" => "#00EE00", // Verde
				"9.24" => "#4EEE94", // Verde
				"9.47" => "#66CDAA", // Verde
				"9.66" => "#87CEFA", // Azul
				"9.81" => "#87CEFA", // Azul
				"9.91" => "#1E90FF", // Azul
				"9.98" => "#1E90FF", // Azul
				"10.00" => "#0000FF" // Azul
		);
	
		return $cores;
	}

}
?>