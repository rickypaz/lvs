/**
 * Mascara Din�mica
 */
 
function check_date(field){
var checkstr = "0123456789";
var DateField = field;
var Datevalue = "";
var DateTemp = "";
var seperator = "/";
var day;
var month;
var year;
var leap = 0;
var err = 0;
var i;
var data = new Date();
var ano = data.getFullYear();
  err = 0;
   DateValue = DateField.value;
   /* Delete all chars except 0..9 */
   for (i = 0; i < DateValue.length; i++) {
	  if (checkstr.indexOf(DateValue.substr(i,1)) >= 0) {
		 DateTemp = DateTemp + DateValue.substr(i,1);
	  }
   }
   DateValue = DateTemp;
   /* Always change date to 8 digits - string*/
   /* if year is entered as 2-digit / always assume 20xx */
   if (DateValue.length == 6) {
	  DateValue = DateValue.substr(0,4) + '20' + DateValue.substr(4,2); }
   if (DateValue.length != 8) {
	  err = 19;}
   /* year is wrong if year = 0000 */
   year = DateValue.substr(4,4);
   if (year == 0) {
	  err = 20;
   }
   if (year < ano) {
	  err = 32;
   }

   /* Validation of month*/
   month = DateValue.substr(2,2);
   if ((month < 1) || (month > 12)) {
	  err = 21;
   }
   /* Validation of day*/
   day = DateValue.substr(0,2);
   if (day < 1) {
	 err = 22;
   }
   /* Validation leap-year / february / day */
   if ((year % 4 == 0) || (year % 100 == 0) || (year % 400 == 0)) {
	  leap = 1;
   }
   if ((month == 2) && (leap == 1) && (day > 29)) {
	  err = 23;
   }
   if ((month == 2) && (leap != 1) && (day > 28)) {
	  err = 24;
   }
   /* Validation of other months */
   if ((day > 31) && ((month == "01") || (month == "03") || (month == "05") || (month == "07") || (month == "08") || (month == "10") || (month == "12"))) {
	  err = 25;
   }
   if ((day > 30) && ((month == "04") || (month == "06") || (month == "09") || (month == "11"))) {
	  err = 26;
   }
   /* if 00 ist entered, no error, deleting the entry */
   if ((day == 0) && (month == 0) && (year == 00)) {
	  err = 0; day = ""; month = ""; year = ""; seperator = "";
   }
   /* if no error, write the completed date to Input-Field (e.g. 13.12.2001) */
   if (err == 0) {
	  DateField.value = day + seperator + month + seperator + year;
	  return true;
   }
   /* Error-message if err != 0 */
   else {
	  alert("Formato de Data incorreto!");
	  if (err == 32){
		   alert("Formato de Ano menor que o atual!");
		  }
	  DateField.select();
//	  DateField.focus();
	  //field.value="";
	  //return false;
	  var browserName=navigator.appName; 
	  if (browserName=="Netscape"){
		 DateField.value="";
		 DateField.select();
		 //window.setTimeout("DateField.select()", 100);
	  }	else { 
	  	if (browserName=="Microsoft Internet Explorer") {
	       DateField.value="";
  		   DateField.select();
 	    } else {
      	  alert("What ARE you browsing with here?");
        }
	  }

   }
} 
 
if (document.layers)
	window.captureEvents(Event.KEYDOWN | Event.KEYUP);

function limparCampoDeData( event, field ){
	var teclaPressionada;

	if (event.srcElement)
		teclaPressionada = event.keyCode;
	else if (event.target)
		teclaPressionada = event.which;

	field.value = String.fromCharCode( teclaPressionada );
	return;
}

function mask(isNum, event, field, mask, maxLength, lim, maxValue) {
	var keyCode;
	
	if (event.srcElement)
	    keyCode = event.keyCode;
	else if (event.target)
	    keyCode = event.which;
		
	var maskStack = new Array();
		
	var isDynMask = false;
	
	if (mask.indexOf('[') != -1)
	    isDynMask = true;
				
	var length = mask.length;
	
	for (var i = 0; i < length; i++)
	    maskStack.push(mask.charAt(i));
		
	var value = field.value;
	var i = value.length;
	
	if (keyCode == 0 || keyCode == 8)
	    return true;

	console.debug(keyCode);
	
	//código adaptado para aceitar X (mai�sculo) ou x (min�sculo), al�m de n�meros
	if (!lim) { 
		
	    if (isNum && (keyCode < 44 || keyCode > 57 || keyCode == 47 || keyCode == 45)){
		return false;
	    }

	    if( mask == "##/##/####" ){
		if( i == 10 ){
		    limparCampoDeData( event, field );
		}
	    }	
	} else { 
	    if (isNum && (keyCode < 48 || keyCode > maxValue)){
		if( keyCode > maxValue ){
		    alert( "Maior valor permitido para esta atividade: " + String.fromCharCode(maxValue) );
		}
		return false;
	    }
	}	
	
	if (!isDynMask && i < length) {
	    if (maskStack.toString().indexOf(String.fromCharCode(keyCode)) != -1 && keyCode != 8 && keyCode != 44) { 
		return false;
	    } else {
		if (keyCode != 8) {
		    if (maskStack[i] != '#') {
			var old = field.value;
			field.value = old + maskStack[i];
		    }			
		}
		
		if (autoTab(field, keyCode, length)) {
		    if (!document.layers) {
			return true;
		    } else if (keyCode != 8) {
			field.value += String.fromCharCode(keyCode);
			return false;
		    } else {
			return true;
		    }
		} else {
		    return false;
		}
	    }	
	} else if (isDynMask) { 
						
		var maskChars = "";
		for (var j = 0; j < maskStack.length; j++)
			if (maskStack[j] != '#' && maskStack[j] != '[' && maskStack[j] != ']')
				maskChars += maskStack[j];

		var tempValue = "";
		for (var j = 0; j < value.length; j++) {
			if (maskChars.indexOf(value.charAt(j)) == -1)
				tempValue += value.charAt(j);
		}
		
		value = tempValue + String.fromCharCode(keyCode);
						
		if (maskChars.indexOf(String.fromCharCode(keyCode)) != -1) {
			return false;
		} else {
		
			var staticMask = mask.substring(mask.indexOf(']') + 1);
			var dynMask = mask.substring(mask.indexOf('[') + 1, mask.indexOf(']'));
		
			var realMask = new Array;
		
			if (mask.indexOf('[') == 0) {
				var countStaticMask = staticMask.length - 1;
				var countDynMask = dynMask.length - 1;
				for (var j = value.length - 1; j >= 0; j--) {
					if (countStaticMask >= 0) {
						realMask.push(staticMask.charAt(countStaticMask));
						countStaticMask--; 
					} 
					if (countStaticMask < 0) {
						if (countDynMask >= 0) {
							if (dynMask.charAt(countDynMask) != '#') {
								realMask.push(dynMask.charAt(countDynMask));
								countDynMask--;
							}
						}
						if (countDynMask == -1) {
							countDynMask = dynMask.length - 1;
						}
						realMask.push(dynMask.charAt(countDynMask));
						countDynMask--; 
					}
				}
			}
			
			var result = "";
				
			var countValue = 0;
			while (realMask.length > 0) {
				var c = realMask.pop();	
				if (c == '#') {
					result += value.charAt(countValue);
					countValue++;	
				} else {
					result += c;
				}
			}
			
			field.value = result;
		
			if (maxLength != undefined &&  value.length == maxLength) {
				
				var form = field.form;
				for (var i = 0; i < form.elements.length; i++) {
					if (form.elements[i] == field) {
						field.blur();
						//if alterado para quando a m�scara for utilizada no �ltimo campo, n�o d� mensagem de erro quando tentar colocar o foco no "Salvar"
						//if (form.elements[i + 1] != null)										 
						if ((form.elements[i + 1] != null) && (form.elements[i + 1].name != "METHOD"))
							form.elements[i + 1].focus();
						break;
					}
				}
			}
			
			return false;
		}
	} else {
		
		return false;
	}
	
	
	function autoTab(field, keyCode, length) {
		var i = field.value.length;
			
		if (i == length - 1) {
		
			field.value += String.fromCharCode(keyCode);
		
			var form = field.form;
			for (var i = 0; i < form.elements.length; i++) {
				if (form.elements[i] == field) {
					field.blur();										 
					//if alterado para quando a m�scara for utilizada no �ltimo campo, n�o d� mensagem de erro quando tentar colocar o foco no "Salvar"
					//if (form.elements[i + 1] != null)
					if ((form.elements[i + 1] != null) && (form.elements[i + 1].name != "METHOD"))
						form.elements[i + 1].focus();
					break;
				}
			}
			
			return false;
		} else {
			return true;
		}	
	}
}


//Bloco de c?digo para esconder e mostra form
var Ver4 = parseInt(navigator.appVersion) >= 4
var IE4 = ((navigator.userAgent.indexOf("MSIE") != -1) && Ver4)
var block = "formulario";
function esconde() {	document.form.style.visibility = "hidden" }
function mostra() { document.form.style.visibility = "visible" }
//Bloco de c?digo para esconder e mostra form

// C?digo para o teclado 
function tecladown (digito){
	if (digito == ''){
		document.form.senha.value = '';
		return;	
	}
	var pass = document.form.senha.value;
	if (pass.length >= 8){
		return;
	}
	document.form.senha.value = document.form.senha.value + digito;
}
function teclaclick(tecla){
	return false;
}
function teclaup(tecla){
	tecladown(tecla);
}

function FormataDado(campo,tammax,pos,teclapres){
	var keyCode;
	if (teclapres.srcElement)
		keyCode = teclapres.keyCode;
	else if (teclapres.target)
		keyCode = teclapres.which;
	
	if (keyCode == 0 || keyCode == 8)
		return true;
		
	if ((keyCode < 48 || keyCode > 57) && (keyCode != 88) && (keyCode != 120))
		return false;

		var tecla = keyCode;
		vr = document.formContaBrasil.numeroContratoOrigem.value;
		vr = vr.replace( "-", "" );
		vr = vr.replace( "/", "" );
		tam = vr.length ;
		
		if (tam < tammax && tecla != 8){ tam = vr.length + 1 ; }
		
		if (tecla == 8 ){ tam = tam - 1 ; }
		if ( tecla == 8 || tecla == 88 || tecla >= 48 && tecla <= 57 || tecla >= 96 && tecla <= 105 || tecla == 120){
			if ( tam <= 2 ){
		 		document.formContaBrasil.numeroContratoOrigem.value = vr ;}
			if ( tam > pos && tam <= tammax ){
				document.formContaBrasil.numeroContratoOrigem.value = vr.substr( 0, tam - pos ) + '-' + vr.substr( tam - pos, tam );}
		}
}



//funcoes para verificar a hora 
          function mascara_hora(hora, field){ 
		   	  var horafield = field;
              var myhora = ''; 
              myhora = myhora + hora; 
              if (myhora.length == 2){ 
                  myhora = myhora + ':'; 
                  horafield.value = myhora; 
              } 
              if (myhora.length == 5){ 
                  verifica_hora(horafield); 
              } 
          } 
           
          function verifica_hora(field){ 
		  	  var horafield = field;
              hrs = (horafield.value.substring(0,2)); 
              minu = (horafield.value.substring(3,5)); 
			  if ((!isNaN (hrs)) && (!isNaN(minu)) ){               
              //alert('hrs '+ hrs); 
              //alert('min '+ min); 
               
              situacao = ""; 
              // verifica data e hora 
              if ((hrs < 00 ) || (hrs > 23) || ( minu < 00) ||( minu > 59)){ 
                  situacao = "falsa"; 
               } 
               
              if (horafield.value == "") { 
                  situacao = "falsa"; 
               } 

              if (situacao == "falsa") { 
                  alert("Hora inv�lida!"); 
                  horafield.focus(); 
				  horafield.value="";
		 		  horafield.select();
               } 
			  } 
			  else {
			  	  alert("Hora inv�lida!"); 
                  horafield.focus(); 
				  horafield.value="";
		 		  horafield.select();
			  
			  }
        } 

