/**
 * 
 */
M.block_lvs = {
		rating: {
			radioChecked: null,
			baseurl: null
		},

		qtde_novas_atividades: 0,

		somaPorcentagens: function(Y) {
			tbody = Y.Node.one('#lvs_lista_presenciais').one('tbody');
			soma_porcentagens = 0;

			porcentagens = tbody.all('.lvs_porcentagem').each( function(node, index, nodelist) {
				soma_porcentagens += ( node.get('value') ) ? parseFloat(node.get('value')) : 0;
			});
			tbody.one('#lvs_soma_porcentagens').setContent(soma_porcentagens);

			if(soma_porcentagens != 100)  {
				Y.Node.one('#lvs_config_presencial').one('#lvs_config_presencial_submit').set('disabled', 'disabled');
				if(soma_porcentagens == 0) str = "";
				else(soma_porcentagens > 100 ) ? str = "Soma das porcentagens é maior que 100% !!!" : str = "Soma das porcentagens é menor que 100% !!!";
				tbody.one('#lvs_msg').setContent(str);
			} else {
				Y.Node.one('#lvs_config_presencial').one('#lvs_config_presencial_submit').set('disabled', null);
				tbody.one('#lvs_msg').setContent("");
			}
		},

		somaPorcentagensDistancia: function(Y) {
			tbody = Y.Node.one('#lvs_config_distancia').one('tbody');
			soma_porcentagens = 0;

			porcentagens = tbody.all('.lvs_porcentagem').each( function(node, index, nodelist) {
				soma_porcentagens += ( node.get('value') ) ? parseFloat(node.get('value')) : 0;
			});
			tbody.one('#lvs_soma_porcentagens').setContent(soma_porcentagens);

			if(soma_porcentagens != 100)  {
				Y.Node.one('#lvs_config_distancia').one('#lvs_config_distancia_submit').set('disabled', 'disabled');

				(soma_porcentagens > 100 ) ? str = "Soma das porcentagens é maior que 100% !!!" : str = "Soma das porcentagens é menor que 100% !!!";
				tbody.one('#lvs_msg').setContent(str);
			} else {
				Y.Node.one('#lvs_config_distancia').one('#lvs_config_distancia_submit').set('disabled', null);
				tbody.one('#lvs_msg').setContent("");
			}
		},

		somaPorcentagensQuizzes: function(Y) {
			var quantidade_presencial = Y.Node.one('#quantidadePresencial');
			var tbody = Y.Node.one('#lvs_quizzes_presenciais').one('tbody');
			var soma_porcentagens = 0;

			tbody.all('.lvs_porcentagem').each( function(node, index, nodelist) {
				soma_porcentagens += ( node.get('value') ) ? parseFloat(node.get('value')) : 0;
			});

			tbody.one('#lvs_soma_porcentagens').setContent(soma_porcentagens);
			
			if(soma_porcentagens != 100 && quantidade_presencial.get('value') > 0)  
			{
				Y.Node.one('#lvs_quizzes_submit').set('disabled', 'disabled');
			} else {
				Y.Node.one('#lvs_quizzes_submit').set('disabled', null);
			}
		}
};

M.block_lvs.config_cursolv = function(Y) {

	Y.on('submit', function(e) {
		if(!isFormValid()) { 
			alert("Verifique os Campos em Vermelho !");
			e.preventDefault();
		}
	}, '#lvs_config_curso');

	Y.on('keyup', function(e) {
		var horas_presenciais = Y.one('#lvs_horas_presenciais');
		M.block_lvs.calc_porcentagem(Y.Node.getDOMNode(this), Y.Node.getDOMNode(horas_presenciais), 20);
	}, '#lvs_horas_curso');

	Y.on('keypress', function(e) {
		if(!mask(true, e, Y.Node.getDOMNode(this), '####')) {
			e.preventDefault();
		}
	}, '#lvs_horas_curso');

	Y.on('keypress', function(e) {
		if(!mask(true, e, Y.Node.getDOMNode(this), '####')) {
			e.preventDefault();
		}
	}, '#lvs_horas_presenciais');

	Y.on('keypress', function(e) {
		if(!mask(true, e, Y.Node.getDOMNode(this), '##/##/####')) {
			e.preventDefault();
		}
	}, '#lvs_data_limite');

	Y.on('blur', function(e) {
		check_date(Y.Node.getDOMNode(this));
	}, '#lvs_data_limite');

	Y.on('keyup', function(e) {
		var porcentagem_presenciais = Y.one('#lvs_porcentagem_distancia');
		M.block_lvs.check_porc_complementar(Y.Node.getDOMNode(this), Y.Node.getDOMNode(porcentagem_presenciais));
	}, '#lvs_porcentagem_presencial');

	Y.on('keypress', function(e) {
		if(!mask(true, e, Y.Node.getDOMNode(this))) {
			e.preventDefault();
		}
	}, '#lvs_porcentagem_presencial');
};

M.block_lvs.config_presencial = function(Y, base_url) {

	M.block_lvs.somaPorcentagens(Y);

	var numero_atividades = Y.one("#lvs_numero_atividades");
	var curso = Y.one('#lvs_curso_id');

	Y.on('click', function(e) {
		location.href= base_url + '/course/view.php?id=' + curso.get('value');
	}, "#lvs_voltar");

	Y.on('click', function(e) {
		if( M.block_lvs.qtde_novas_atividades > 0 ) {
			alert('Salve as novas atividades criadas primeiro!');
			e.preventDefault();
		}
	}, ".editing_update");

	Y.on('click', function(e) {
		if( M.block_lvs.qtde_novas_atividades > 0 ) {
			alert('Salve as novas atividades criadas primeiro!');
			e.preventDefault();
		} else if(!confirm('Deseja realmente excluir essa atividade ?')) {
			e.preventDefault();
		}
	}, ".editing_delete");

	Y.on('click', function(e) {
		numero_atividade = parseInt(numero_atividades.get('value')) + (++M.block_lvs.qtde_novas_atividades);
		tbody = Y.Node.one('#lvs_lista_presenciais').one('tbody');

		nova_atividade = Y.Node.one('#lvs_nova_atividade_model').cloneNode(true);
		nova_atividade.setStyle('display', null);

		nova_atividade.set('id', null);
		nova_atividade.generateID();

		nova_atividade.one('#lvs_curso_presencial')
		.set('id', 'lvs_curso_presencial_' + numero_atividade)
		.set('name', 'presencial[' + numero_atividade + '][id_curso]');

		nova_atividade.one('#lvs_titulo_presencial')
		.set('id', 'lvs_titulo_presencial_' + numero_atividade)
		.set('name', 'presencial[' + numero_atividade + '][nome]')
		.set('required', 1);

		nova_atividade.one('#lvs_descricao_presencial')
		.set('id', 'lvs_descricao_presencial_' + numero_atividade)
		.set('name', 'presencial[' + numero_atividade + '][descricao]')
		.set('required', 1);

		nova_atividade.one('#lvs_porcentagem_presencial')
		.addClass('lvs_porcentagem')
		.set('id', 'lvs_porcentagem_presencial_' + numero_atividade)
		.set('name', 'presencial[' + numero_atividade + '][porcentagem]');

		nova_atividade.one('#lvs_max_faltas_presencial')
		.addClass('lvs_maxfaltas')
		.set('id', 'lvs_max_faltas_presencial_' + numero_atividade)
		.set('name', 'presencial[' + numero_atividade + '][max_faltas]')
		.set('required', 1);

		nova_atividade.one('#lvs_remover_presencial').set('id', 'lvs_remover_presencial_' + numero_atividade).on('click', function(e) {
			confirmacao = confirm('Deseja realmente excluir essa nova atividade?');

			if(confirmacao) {
				$atividade = this.ancestor('tr');
				tbody.removeChild($atividade);
				M.block_lvs.qtde_novas_atividades--;

				total_atividades = parseInt(numero_atividades.get('value')) + M.block_lvs.qtde_novas_atividades;
				if(total_atividades == 0) {
					contenthtml = "<tr class='r0'><td colspan='5' style='text-align: center;' class='cell c0'>Nenhuma atividade presencial para esse curso</td></tr>";
					tbody.insert(contenthtml, 0);
				}
			}

			M.block_lvs.somaPorcentagens(Y);
		});

		if(numero_atividade == 1) {
			item = tbody.get('children').item(0);
			item.replace(nova_atividade);
		} else {
			item = tbody.get('children').item( tbody.get('children').size()-2 );
			tbody.insertBefore(nova_atividade, item);
		}
	}, "#lvs_add_atividade");

	YUI().use('node-event-delegate', 'event-key', function (Y) {
		tbody = Y.Node.one('#lvs_lista_presenciais').one('tbody');

		tbody.delegate('keyup', function(e) {
			M.block_lvs.check_porc(Y.Node.getDOMNode(this));
			M.block_lvs.somaPorcentagens(Y);
		}, '.lvs_porcentagem');

		tbody.delegate('keypress', function(e) {
			console.debug('recolocar máscara!');
			//		if(!mask(true, e, Y.Node.getDOMNode(this), '#', 2, true)) {
			//		    e.preventDefault();
			//		}
		}, '.lvs_maxfaltas');
	});

};

M.block_lvs.config_distancia = function(Y, base_url) {
	var curso = Y.one('#lvs_curso_id').get('value');

	M.block_lvs.somaPorcentagensDistancia(Y);

	Y.on('submit', function(e) {
		if(!isFormValid()) { 
			e.preventDefault();
		}
	}, '#lvs_config_distancia');

	Y.on('click', function(e) {
		location.href= base_url + '/course/view.php?id=' + curso;
	}, '#lvs_voltar');

	YUI().use('node-event-delegate', 'event-key', function (Y) {
		tbody = Y.Node.one('#lvs_config_distancia').one('tbody');

		tbody.delegate('keyup', function(e) {
			M.block_lvs.check_porc(Y.Node.getDOMNode(this));
			M.block_lvs.somaPorcentagensDistancia(Y);
		}, '.lvs_porcentagem');

	});

};

M.block_lvs.check_porc = function(field) {
	var campo = field;

	// isNan retorna false se o argumento for num�rico
	if( !isNaN( campo.value ) ) {
		if( Number( campo.value ) > 100 ){
			alert( "Valor maior que 100%!" );
			campo.focus();
			campo.value="";
			campo.select();
		}
	} else {
		alert( 'Somente Números' );
		campo.focus();
		campo.value="";
		campo.select();
	}
};

M.block_lvs.check_porc_complementar = function( presencialField, distanciaField ) {
	if( !isNaN( presencialField.value ) && !isNaN( distanciaField.value ) ) {
		if( Number( presencialField.value ) > 100 ) {
			alert( "Valor maior que 100%!" );

			presencialField.focus();
			presencialField.value = "";
			presencialField.select();

		} else {
			var resto = 100 - Number( presencialField.value );
			distanciaField.value = resto;
		}
	} else {
		alert ('Somente Números');
		presencialField.focus();
		presencialField.value="";
		presencialField.select();
	}
};

M.block_lvs.desabilita_add = function() {
	var butaoadd = document.getElementById('addativ');
	butaoadd.disabled = true;
};

M.block_lvs.calc_porcentagem = function( sourceField, destinationField, percent ) {
	var value = ( Number(percent) * Number( sourceField.value )) /100 ;
	destinationField.value = value;
};

M.block_lvs.importar_quizzes = function(Y) {
	M.block_lvs.somaPorcentagensQuizzes(Y);

	YUI().use('node-event-delegate', 'event-key', function (Y) {
		var tbody_quizzes = Y.Node.one('#lvs_quizzes').one('tbody');
		var tbody_distancia = Y.Node.one('#lvs_quizzes_distancia').one('tbody');
		var tbody_presencial = Y.Node.one('#lvs_quizzes_presenciais').one('tbody');

		tbody_quizzes.delegate('click', function(e) {
			var row = this.ancestor().ancestor();
			var hiddens = row.one('td').get('children');
			var tipo_importacao = row.get('children').item(2).one('select').get('selectedIndex');
			var contador = row.one('td').one('input').get('value');	
			
			if (tipo_importacao == 1) { // importar como atividade a distancia
				var quiz_distancia = Y.Node.create('<tr style="text-align:center;"></tr>');

				hiddens.item(4).set('value', 1); // altera o campo distancia para 1
				row.one('td').appendChild('<input type="hidden" name="quiz[' + contador + '][acao]" value="IMPORTAR" />');

				quiz_distancia.appendChild( row.one('td').cloneNode(true) );
				quiz_distancia.appendChild( '<td>' + row.get('children').item(1).get('text') + '</td>' );
				quiz_distancia.appendChild( '<td><a class="lvs_quizlv_remover" style="cursor: pointer">(remover)</a></td>' );
				
				tbody_distancia.appendChild(quiz_distancia);

				row.remove();
				
				Y.one('#quantidadeDistancia').set('value', parseInt(Y.one('#quantidadeDistancia').get('value')) + 1);

			} else if (tipo_importacao == 2) { // importar como atividade presencial
				
				var quiz_presencial = Y.Node.create('<tr style="text-align:center;"></tr>');
				var colPorcentagem = Y.Node.create('<td><input type="text" class="lvs_porcentagem" name="quiz['+ contador +'][presencial][porcentagem]" value="0"/></td>');
				var colMaxFaltas   = Y.Node.create('<td><input type="text" class="lvs_max_faltas" name="quiz['+ contador +'][presencial][max_faltas]" value="1"></td>');

				hiddens.item(4).set('value', 0); // altera o campo distancia para 0
				row.one('td').appendChild('<input type="hidden" name="quiz[' + contador + '][presencial][nome]" value="' + hiddens.item(5).get('value') + '" />');
				row.one('td').appendChild('<input type="hidden" name="quiz[' + contador + '][presencial][descricao]" value="" />');
				row.one('td').appendChild('<input type="hidden" name="quiz[' + contador + '][acao]" value="IMPORTAR" />');

				quiz_presencial.appendChild( row.one('td').cloneNode(true) );
				quiz_presencial.appendChild( '<td>' + row.get('children').item(1).get('text') + '</td>' );
				quiz_presencial.appendChild( colPorcentagem );
				quiz_presencial.appendChild( colMaxFaltas );
				quiz_presencial.appendChild( '<td><a class="lvs_quizlv_remover" style="cursor: pointer;">(remover)</a></td>' );
				tbody_presencial.get('children').item(tbody_presencial.get('children').size()-1).insert(quiz_presencial, 'before');

				row.remove();
				Y.one('#quantidadePresencial').set('value', parseInt(Y.one('#quantidadePresencial').get('value')) + 1);
			}
			
			Y.one('#quantidadeQuizzes').set('value', parseInt(Y.one('#quantidadeQuizzes').get('value')) - 1);
			
			M.block_lvs.controleQuizzes(Y);
			M.block_lvs.somaPorcentagensQuizzes(Y);

		}, '.lvs_quizlv_adicionar');

		tbody_distancia.delegate('click', function(e) {
			var row = this.ancestor().ancestor();
			var quiz = Y.Node.create('<tr style="text-align:center;"></tr>');
			var select = '<select><option value="0">Selecionar...</option><option value="1" disabled>Distância</option><option value="2">Presencial</option></select>';

			row.one('td').get('children').pop().remove();

			quiz.appendChild( row.one('td').cloneNode(true) );
			quiz.appendChild( '<td>' + row.get('children').item(1).get('text') + '</td>' );
			quiz.appendChild( '<td>' + select + '</td>' );
			quiz.appendChild( '<td><a class="lvs_quizlv_adicionar" style="cursor: pointer;">(importar)</a></td>' );
			tbody_quizzes.appendChild(quiz);

			row.remove();

			
			Y.one('#quantidadeDistancia').set('value', parseInt(Y.one('#quantidadeDistancia').get('value')) - 1);
			Y.one('#quantidadeQuizzes').set('value', parseInt(Y.one('#quantidadeQuizzes').get('value')) + 1);
			
			M.block_lvs.controleQuizzes(Y);
		}, '.lvs_quizlv_remover');

		tbody_presencial.delegate('click', function(e) {
			var row = this.ancestor().ancestor();	     
			var contador = row.one('td').one('input').get('value');
			var quiz = Y.Node.create('<tr style="text-align:center;"></tr>');
			var select = '<select><option value="0">Selecionar...</option><option value="1" disabled>Distância</option><option value="2">Presencial</option></select>';

			row.one('td').get('children').item(5).set('name', 'quiz[' + contador + '][nome]');
			row.one('td').get('children').item(6).set('name', 'quiz[' + contador + '][descricao]');
			row.one('td').get('children').pop().remove();

			quiz.appendChild( row.one('td').cloneNode(true) );
			quiz.appendChild( '<td>' + row.get('children').item(1).get('text') + '</td>' );
			quiz.appendChild( '<td>' + select + '</td>' );
			quiz.appendChild( '<td><a class="lvs_quizlv_adicionar" style="cursor: pointer;">(importar)</a></td>' );	     
			tbody_quizzes.appendChild(quiz);

			row.remove();
			
			Y.one('#quantidadePresencial').set('value', parseInt(Y.one('#quantidadePresencial').get('value')) - 1);
			Y.one('#quantidadeQuizzes').set('value', parseInt(Y.one('#quantidadeQuizzes').get('value')) + 1);

			M.block_lvs.controleQuizzes(Y);
			M.block_lvs.somaPorcentagensQuizzes(Y);
		}, '.lvs_quizlv_remover');

		tbody_presencial.delegate('keyup', function(e) {
			M.block_lvs.check_porc(Y.Node.getDOMNode(this));
			M.block_lvs.somaPorcentagensQuizzes(Y);
		}, '.lvs_porcentagem');
	});

};

M.block_lvs.controleQuizzes = function(Y) {

	var quantidade_disponiveis = Y.Node.one('#quantidadeQuizzes');
	var quantidade_distancia = Y.Node.one('#quantidadeDistancia');
	var quantidade_presencial = Y.Node.one('#quantidadePresencial');
	
	if (quantidade_distancia.get('value') == 0) {
		Y.one('#MSG_ZERO_QUIZ_DISTANCIA').getDOMNode().style.setProperty('display', '');
	} else {
		Y.one('#MSG_ZERO_QUIZ_DISTANCIA').getDOMNode().style.setProperty('display', 'none');
	}
	
	if (quantidade_presencial.get('value') == 0) {
		Y.one('#MSG_ZERO_QUIZ_PRESENCIAIS').getDOMNode().style.setProperty('display', '');
	} else {
		Y.one('#MSG_ZERO_QUIZ_PRESENCIAIS').getDOMNode().style.setProperty('display', 'none');
	}
	
	if (quantidade_disponiveis.get('value') == 0) {
		Y.one('#MSG_ZERO_QUIZ_DISPONIVEIS').getDOMNode().style.setProperty('display', '');
	} else {
		Y.one('#MSG_ZERO_QUIZ_DISPONIVEIS').getDOMNode().style.setProperty('display', 'none');
	}
	
};

M.block_lvs.gravaNotaFinal = function(Y) {
		YUI().use('node-event-delegate', 'event-key', function (Y) {
			tabelaNotas = Y.Node.one('#tabelaNotas');

			tabelaNotas.delegate('keyup', function(e) {
				return M.block_lvs.limiteMaximo(this, 0, 10);
			}, '.limiteMaximo');
		});
};

M.block_lvs.limiteMaximo = function (field, minimo, maximo) {
	valor = field.get('value');

	if( valor > maximo ){
		alert( "Maior valor permitido para nota: " + maximo + "\nPor favor, redigite o valor da nota." );
		field.set('value', '');
		return false;
	}
	
	if( valor < minimo ){
		alert( "Menor valor permitido para nota: " + minimo + "\nPor favor, redigite o valor da nota." );
		field.set('value', '');
		return false;
	}

	return true;
};

M.block_lvs.npresencial = function(Y) {
	var max_faltas = Y.one('#lvs_max_faltas');

	Y.on('keypress', function(e) {
		if(!mask(true, e, Y.Node.getDOMNode(this), '###')) {
			e.preventDefault();
		}
	}, '.nota');

	Y.on('blur', function(e) {
		if(!M.block_lvs.limiteMaximo(Y.Node.getDOMNode(this), 10)) {
			e.preventDefault();
		}
	}, '.nota');

	Y.on('keypress', function(e) {
		if(!mask(true, e, Y.Node.getDOMNode(this), '#', 1, true, max_faltas.get('value'))) {
			e.preventDefault();
		}
	}, '.nrfaltas');

};

M.block_lvs.ratinglvs = function(Y, base_url, ajax_form) {
	M.block_lvs.rating.baseurl = base_url;

	YUI().use('node-event-delegate', 'event-key', function (Y) {
		Y.one('body').delegate('click', function(e) {
			var reset = false;
			var itemid = null;

			if( M.block_lvs.rating.radioChecked == Y.Node.getDOMNode(this).value ) {
				Y.Node.getDOMNode(this).checked = false;
				M.block_lvs.rating.radioChecked = null;
				reset = true;
			} else {
				M.block_lvs.rating.radioChecked = Y.Node.getDOMNode(this).value;
			}

			itemid = Y.Node.getDOMNode(this).name.substring(6);

			if(ajax_form) {
				M.block_lvs.rating.enviarForm(Y, this.ancestor('form'));
			}
		}, '.notalv');
	});

};

M.block_lvs.rating.enviarForm = function(Y, form) {
	Y.use("node", "io", "json-parse", function(Y) {		
		Y.io(M.block_lvs.rating.baseurl + '/blocks/lvs/pages/rateajax.php', {
			method: 'POST',
			form: { id: form.get('id') },
			on: {
				success: function (id, response, args) {
					var data = null;
					try {
						data = Y.JSON.parse(response.responseText);
						form.ancestor('div').one('#lvs_avaliacaoatual').insert(data.avaliacao, 'replace');
					} catch (e) {
						console.debug("JSON Parse failed!");
					}
				},
				failure: function (id, result) {
				}
			}
		});	
	});
};