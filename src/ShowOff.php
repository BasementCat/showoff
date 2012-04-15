<?php
	$DefaultConfig=array(
		'RootDir'=>dirname($_SERVER['SCRIPT_FILENAME']),
		'WebDir'=>dirname($_SERVER['SCRIPT_NAME']),
		'ImageTypes'=>array('jpe?g', 'gif', 'png', 'bmp')
	);
	if(!isset($CONFIG)) $CONFIG=array();
	$CONFIG=array_merge($DefaultConfig, $CONFIG);

	function isListedFile($file){
		static $REMatchFile;
		if(!$REMatchFile){
			global $CONFIG;
			$REMatchFile=sprintf("#\.(%s)$#i", implode("|", $CONFIG['ImageTypes']));
		}
		return file_exists($file)&&(is_dir($file)||preg_match($REMatchFile, $file));
	}

	function listDirectory($dir){
		global $CONFIG;
		$out=array();
		foreach(glob($dir.'/*') as $file){
			if(isListedFile($file)) $out[]=str_replace($CONFIG['RootDir'], '', $file);
		}
		return $out;
	}

	$requestFile=isset($_SERVER['PATH_INFO'])?$_SERVER['PATH_INFO']:'/';
	$requestLocalFile=$CONFIG['RootDir'].$requestFile;

	if(!isListedFile($requestLocalFile)){
		header('HTTP/1.1 404 Not Found');
		$TITLE='File Not Found';
		$PATH=array();
		$BODY=sprintf("The file you requested, %s, was not found.", $requestFile);
	}else{
		$tempPATH=preg_split("#[\\/]+#", preg_replace('#^[\\/]+|[\\/]+$#', '', $requestFile));
		if($tempPATH) array_unshift($tempPATH, '');
		$fullPath=array();
		$PATH=array();
		foreach($tempPATH as $part){
			$fullPath[]=$part;
			if($part==''){
				$PATH[]=array(implode('/', $fullPath), 'Root');
			}else{
				$PATH[]=array(implode('/', $fullPath), $part);
			}
		}
		$TITLE=array_pop($PATH);
		$TITLE=$TITLE[1];
		array_reverse($PATH);
		$BODY=null;
	}
?>
<html>
	<head>
		<title><?php echo $TITLE, ' - ShowOff'; ?></title>
	</head>
	<body>
		<h1><?php echo $TITLE; ?></h1>
		<div>
			<?php foreach($PATH as $parts): ?>
				/<a href="<?php echo $CONFIG['WebDir'], $parts[0]; ?>"><?php echo $parts[1]; ?></a>
			<?php endforeach; ?>
		</div>
		<div>
			<?php if($BODY): ?>
				<?php echo $BODY; ?>
			<?php elseif(is_dir($requestLocalFile)): ?>
				<?php var_dump(listDirectory($requestLocalFile)); ?>
			<?php endif; ?>
		</div>
	</body>
</html>