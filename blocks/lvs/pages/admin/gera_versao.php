<?php

require_once('../../../../config.php');

// @lvs ENCONTRAR SOLUCAO UNIVERSAO PARA COMPACTAR PASTAS

$date = date('d-m-y');
$default = '/geraversao/LV Plugin_3.0 - '.$date.'/moodle';

$treeDiretory = array(
    array('/blocks/lvs', $default . '/blocks/lvs'),
    //array('/rating/lvs' , $default.'/rating/lvs'),
    //array('/lang/pt_br_utf8', $default . '/lang/pt_br_utf8'),
    array('/mod/chatlv', $default . '/mod/chatlv'),
    array('/mod/forumlv', $default . '/mod/forumlv'),
    array('/mod/tarefalv', $default . '/mod/tarefalv'),
    array('/mod/wikilv', $default . '/mod/wikilv'),
    //array('/theme/standard', $default . '/theme/standard')
);


//$configpath = '/var/www/moodle2';
$configpath = '/var/www/moodle';
createVersionLV($configpath,$treeDiretory);

function dircpy($basePath, $source, $dest, $overwrite = false){

	ini_set('display_errors',1);
    ini_set('display_startup_errors',1);
	error_reporting(E_ALL);


	if(!is_dir($basePath . $dest))
		mkdir($basePath . $dest);

	if($handle = opendir($basePath . $source)){
		while(false !== ($file = readdir($handle))){
			if($file != '.' && $file != '..'){
				$path = $source . '/' . $file;
				if(is_file($basePath . $path)){
					if(!is_file($basePath . $dest . '/' . $file) || $overwrite){
						if(!@copy($basePath . $path, $basePath . $dest . '/' . $file)){
							echo '<font color="red">File ('.$path.') could not be copied, likely a permissions problem.</font>';
							echo '<br/>';
					    }
					    else{
					    	echo 'File ('.$path.') copied.';
						    echo '<br/>';
					    }
					}
				} elseif(is_dir($basePath . $path)){
					if(!is_dir($basePath . $dest . '/' . $file))
						mkdir($basePath . $dest . '/' . $file);
					dircpy($basePath, $path, $dest . '/' . $file, $overwrite);
				}
			}
		}
		closedir($handle);
	}
}

function createDirRecursive($rootDir,$dirPath,$createdPath = ''){
	ini_set('display_errors',1);
	ini_set('display_startup_errors',1);
	error_reporting(E_ALL);
	
	if($dirPath != ""){
		$dirPath = substr($dirPath, 1);
		$dirs = explode('/',$dirPath);
		$realPath = str_replace("//", "/", $rootDir.'/'.$createdPath.'/'.$dirs[0]);
		if (!is_dir($realPath)){
			echo "created Path: ".$realPath;
			echo "<br/>";
			mkdir($realPath,0777);
		}
		$createdPath = $createdPath.'/'.$dirs[0];
		$dirs[0] = '';
		$dirPath = trim(implode ( '/' , $dirs ),'');
		createDirRecursive($rootDir,$dirPath,$createdPath);
	}
}

function createVersionLV($dirRoot,$treeDiretory){
	ini_set('display_errors',1);
	ini_set('display_startup_errors',1);
	error_reporting(E_ALL);
	
	foreach($treeDiretory as $diretory) {
		createDirRecursive($dirRoot,$diretory[1]);
		dircpy($dirRoot,$diretory[0],$diretory[1],true);
	}
	echo '<center><font color="green">Operation Success !!!.</font></center>';
}
