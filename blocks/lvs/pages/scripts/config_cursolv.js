/**
 * 
 */
$(function() {
    $('#lvs_config_curso').submit(function() {
	if(!isFormValid()) { 
	    alert("Verifique os Campos em Vermelho !");
	    return false; 
	}
    });

    $('#lvs_horas_curso').keyup(function(event) {
	calc_porcentagem($(this), $('#lvs_horas_presenciais'), 20);
    });

    $('#lvs_horas_curso').keypress(function(event) {
	return mask(true, event, this, '####');
    });	

    $('#lvs_horas_presenciais').keypress(function(event) {
	return mask(true, event, this, '####');
    });
    
    $('#lvs_data_limite').keypress(function(event) {
	return mask(true, event, this, '##/##/####');
    });
    
    $('#lvs_data_limite').blur(function(event) {
	check_date(this);
    });
    
    $('#lvs_porcentagem_presencial').keyup(function(event) {
	check_porc($(this),$('#lvs_porcentagem_distancia'));
    });
    
    $('#lvs_porcentagem_presencial').keypress(function(event) {
	return mask(true, event, this);
    });
    
    $('#lvs_data_limite').blur(function(event) {
	check_date(this);
    });
});

function check_porc(field){
    var campo = field;
    // isNan retorna false se o argumento for num�rico
    if( !isNaN( campo.value ) ){
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
}

function check_porc(presencialField, distanciaField){
    horasPresencial = $(presencialField).val();
    horasDistancia = $(distanciaField).val();
    
    if( !isNaN(horasPresencial) && !isNaN(horasDistancia) ) {
        if( Number(horasPresencial) > 100 ) {
            alert( "Valor maior que 100%!" );

            $(presencialField).focus();
            $(presencialField).val("");
            $(presencialField).select();
        } else {
            var resto = 100 - Number(horasPresencial);
            $(distanciaField).val(resto);
        }
    } else {
        alert ('Somente Números');
	
        $(presencialField).focus();
	$(presencialField).val("");
	$(presencialField).select();
    }
}

function calc_porcentagem( sourceField, destinationField, percent ) {
    var value = ( Number(percent) * Number($(sourceField).val()) ) / 100;
    $(destinationField).val(value);
}

function desabilita_add(){
    var butaoadd		= document.getElementById( 'addativ' );
    butaoadd.disabled	= true;
}

function verifica( varativ, redirect ){
    var somatorio = 0;

    for( i = 1; i <= varativ; i++ ){
        var porcform	= 'porcAtividadepresencial'+i;
        var campoporc	= document.getElementById( porcform ).value;

        if (campoporc == ''){
            alert ('Campos vazios em Atividade nº'+i);
            return false;
        } else {
            somatorio = somatorio + Number(campoporc);
        }
    }

    if( somatorio == 100 ){
        if( redirect != '' ){
            location.href = redirect;
        }
        return true;
    } else if( somatorio < 100 ){
    	alert( 'Porcentagem total menor que 100%. Verifique os campos das porcentagens das respectivas atividades. \n Somatório atual: '+ somatorio + '%' );
        return false;
    } else {
    	alert( 'Porcentagem total maior que 100%. Verifique os campos das porcentagens das respectivas atividades. \n Somatório atual: '+ somatorio + '%' );
        return false;
    }
}