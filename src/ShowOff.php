<?php
	$DefaultConfig=array(
		'RootDir'=>dirname($_SERVER['SCRIPT_FILENAME']),
		'WebDir'=>dirname($_SERVER['SCRIPT_NAME']),
		'CleanURLs'=>false,
		'ImageTypes'=>array('jpe?g', 'gif', 'png', 'bmp'),
		'MetaDirName'=>'.so-meta',
		'EnableCache'=>true,
		'CacheFileType'=>'jpeg',
		'ThumbnailSize'=>'160x120',
		'SmallSize'=>'800x600'
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
			if(isListedFile($file)) $out[]=ltrim(str_replace($CONFIG['RootDir'], '', $file), '\\/');
		}
		return $out;
	}

	function url($what, $qs=array()){
		global $CONFIG;
		$qs=http_build_query($qs);
		return implode('', array(
			$CONFIG['WebDir'],
			$CONFIG['CleanURLs']?'':'/index.php',
			strlen($what)&&$what[0]=='/'?'':'/',
			$what,
			$qs?'?':'',
			$qs
		));
	}

	function showImage($fullfile, $size){
		global $CONFIG;
		$cacheDir=sprintf("%s/%s/cache", dirname($fullfile), $CONFIG['MetaDirName']);
		$cacheName=sprintf("%s/%s-%s.%s", $cacheDir, basename($fullfile), $size, $CONFIG['CacheFileType']);
		if($CONFIG['EnableCache']&&file_exists($cacheName)){
			header(sprintf("Content-type: image/%s", $CONFIG['CacheFileType']));
			echo file_get_contents($cacheName);
		}else{
			$full_im=imagecreatefromstring(file_get_contents($fullfile));
			$full_width=imagesx($full_im);
			$full_height=imagesy($full_im);
			list($max_width, $max_height)=explode('x', $size);
			$aspect=$full_width/$full_height;
			if($full_width>$max_width){
				$full_width=$max_width;
				$full_height=$full_width/$aspect;
			}
			if($full_height>$max_height){
				$full_height=$max_height;
				$full_width=$full_height*$aspect;
			}
			$im=imagecreatetruecolor($full_width, $full_height);
			imagealphablending($im, true);
			imagesavealpha($im, true);
			imagecopyresampled($im, $full_im, 0, 0, 0, 0, $full_width, $full_height, imagesx($full_im), imagesy($full_im));
			imagedestroy($full_im);
			header(sprintf("Content-type: image/%s", $CONFIG['CacheFileType']));
			call_user_func('image'.$CONFIG['CacheFileType'], $im);
			if($CONFIG['EnableCache']){
				if(!file_exists($cacheDir)){
					mkdir($cacheDir, 0775, true);
				}
				call_user_func('image'.$CONFIG['CacheFileType'], $im, $cacheName);
			}
			imagedestroy($im);
		}
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
		ob_start();
		if(is_dir($requestLocalFile)){
			//var_dump(listDirectory($requestLocalFile));
			foreach(listDirectory($requestLocalFile) as $file){
				if(is_dir($file)){
					printf('<a href="%s">%s</a>', url($file), basename($file));
				}else{
					printf('<img src="%s" />', url($file, array('view'=>'thumb')));
				}
			}
		}else{
			if(isset($_GET['view'])){
				switch($_GET['view']){
					case 'thumb':
						showImage($requestLocalFile, $CONFIG['ThumbnailSize']);
						break;
					case 'small':
						showImage($requestLocalFile, $CONFIG['SmallSize']);
						break;
				}
				ob_end_flush();
				exit(0);
			}
		}
		$BODY=ob_get_contents();
		ob_end_clean();
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
		<?php
			echo $BODY;
		?>
		</div>
	</body>
</html>