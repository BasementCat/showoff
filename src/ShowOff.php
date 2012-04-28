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

	function url($what, $qs=array(), $include_index_php=true){
		global $CONFIG;
		$qs=http_build_query($qs);
		return implode('', array(
			$CONFIG['WebDir'],
			$CONFIG['CleanURLs']?'':($include_index_php?'/index.php':''),
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
				echo '<div class="clickable thumb">';
				if(is_dir($file)){
					printf('<div class="thumb_gallery"><a href="%s">%s</a>', url($file), basename($file));
				}else{
					printf('<div class="thumb_image"><a href="%s"><img src="%s" /></a>', url($file), url($file, array('view'=>'thumb')));
				}
				echo '</div></div>', "\n";
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
			}else{
				$prevImg=null;
				$nextImg=null;
				$curDir=listDirectory(dirname($requestLocalFile));
				foreach($curDir as $i=>$img){
					if($img==ltrim($requestFile, '/')){
						//printf("%s == %s<br />", $img, ltrim($requestFile, '/'));
						if($i>0) $prevImg=$curDir[$i-1];
						if($i<count($curDir)-1) $nextImg=$curDir[$i+1];
					}
				}
				printf('<div class="clickable adjacent_image"><a href="%s">&laquo;</a></div>', url($prevImg));
				printf('<div class="clickable small_image"><a href="%s"><img src="%s" /></a></div>', url($requestFile, array(), false), url($requestFile, array('view'=>'small')));
				printf('<div class="clickable adjacent_image"><a href="%s">&raquo;</a></div>', url($nextImg));
			}
		}
		$BODY=ob_get_contents();
		ob_end_clean();
	}
?>
<html>
	<head>
		<title><?php echo $TITLE, ' - ShowOff'; ?></title>
		<style type="text/css">
			<?php
				list($stThumbWidth, $stThumbHeight)=explode('x', $CONFIG['ThumbnailSize']);
				list($stSmallWidth, $stSmallHeight)=explode('x', $CONFIG['SmallSize']);
			?>
			body{
				background: #272727;
				color: #fff;
			}
			div.thumb_image{
				width: <?php echo $stThumbWidth; ?>;
				height: <?php echo $stThumbHeight; ?>;
			}
			div.small_image{
				width: <?php echo $stSmallWidth; ?>;
				height: <?php echo $stSmallHeight; ?>;
			}
			div.adjacent_image{
				height: <?php echo $stSmallHeight; ?>;
			}
			div.clickable{
				display: block;
				float: left;
				padding: 15px;
				margin: 6px;
				background: #373737;
				border-radius: 9px;
			}
			div.clickable:hover{
				background: #666;
			}
			div.clickable a{
				color: #fff;
				text-decoration: underline;
			}
			div.clickable a:hover{
				text-decoration: none;
			}
			div.adjacent_image a{
				text-decoration: none;
				height: 100%;
				display: block;
				font-size: 50px;
			}
			div#body{
				overflow: auto;
				width: 990px;
				float: none;
				margin: 0 auto;
				text-align: center;
			}
		</style>
	</head>
	<body>
		<h1><?php echo $TITLE; ?></h1>
		<div>
			<?php foreach($PATH as $parts): ?>
				/<a href="<?php echo $CONFIG['WebDir'], $parts[0]; ?>"><?php echo $parts[1]; ?></a>
			<?php endforeach; ?>
		</div>
		<div id="body">
		<?php
			echo $BODY;
		?>
		</div>
	</body>
</html>