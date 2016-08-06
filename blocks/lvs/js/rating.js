var selectedRadios = new Array();

$(function() {
	$.each($(':radio'),function(index,radio) {
		var grupo = $(radio).attr('name');
		
		if( selectedRadios[grupo] == window.undefined) {
			selectedRadios[grupo] = new Object();
		}
	});
	
	$(':radio').click( function(event) {
		var reset = false;

		var grupo = $(this).attr('name');
		if(selectedRadios[grupo] == this) {
			$(this).attr('checked',false);
			selectedRadios[grupo] = new Object();
			reset = true;
		} else {
			selectedRadios[grupo] = this;
		}
		
		setarCampos(this,reset);
		enviarForm('../../rating/rate_ajax.php', campos, 'divResultado');
	});

});

var navegador = navigator.userAgent.toLowerCase(); 
var xmlhttp;

function criarObjetoXML() {
	if (navegador.indexOf('msie') != -1) {
		var controle = (navegador.indexOf('msie 5') != -1) ? 'Microsoft.XMLHTTP' : 'Msxml2.XMLHTTP';

		try {
			xmlhttp = new ActiveXObject(controle);
		} catch (e) { }

	} else {
		xmlhttp = new XMLHttpRequest();
	}
}

function enviarForm(url, campos, destino) {
	
	var elemento = document.getElementById(destino);
	criarObjetoXML();

	if (!xmlhttp) {
		return;
	}

	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 || xmlhttp.readyState == 0) {
			if (xmlhttp.status == 200) {
				elemento.innerHTML = xmlhttp.responseText; // FIXME retirar essa porcaria
				var response = $.parseJSON(xmlhttp.responseText);
				if(response.success == true) {
					enviarForm('genericRating.php', campos, 'divResultado');
				}
			}
		}
	};

	xmlhttp.open('GET', url + '?' + campos, true);
	xmlhttp.send();
}

function setarCampos(radio,reset) {
	grupo = $(radio).attr('name');
	item = grupo.split('_');
	itemid = item[1];
	
	campos = "contextid=" + $('#contextid_'+itemid).val() + 
				"&itemid=" + $('#itemid_'+itemid).val() + 
				"&sesskey=" + $('#sesskey_'+itemid).val() + 
				"&rateduserid=" + $('#rateduserid_'+itemid).val() + 
				"&scaleid=" + $('#scaleid_'+itemid).val() + "&";
	
	if( reset ) {
		campos = campos + "rating=-999";
	} else {
		notaLikert = $('#' + radio.id + 'h').val();
		campos = campos + "ratinglv="+encodeURI(radio.value).toUpperCase() + "&rating="+notaLikert;
	}
}