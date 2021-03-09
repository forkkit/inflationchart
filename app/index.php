<?

	// <router>
		if($_GET['url']) {
			$query=explode('-',str_replace('/','',$_GET['url']));
			if($query[0]) {
				$_GET['stock']=$query[0];
			}
			if($query[2]) {
				$_GET['m']=$query[2];
			}
		}
	// </router>


	// <config>
		// $stocks=array(
		// 	'sp500',
		// 	'dji',
		// 	'nasdaq',
		// 	'asia',
		// 	'singapore',
		// 	'china',
		// 	'gold',
		// 	'silver',
		// 	'home',
		// 	'food',
		// 	'food_and_home',
		// 	'bigmac',
		// 	'gdp',
		// 	'income',
		// 	'btc',
		// 	'eth',
		// 	'tsla',
		// );

		// $m_adjusteds=array(
		// 	'm1',
		// 	'm2',
		// 	'm3',
		// 	'mb',
		// 	'cpi',
		// 	'gold',
		// 	'silver',
		// 	'food',
		// 	'food_and_home',
		// 	'bigmac',
		// 	'btc',
		// 	'eth',
		// 	'population',
		// 	'home',
		// 	'income',
		// 	'china',
		// 	'sp500'
		// );

		/* 2021-03-07 this removes the need for the above, we only put the data in the page for the req'd
		   stocks and adjusteds, but this also means every dropdown change we need to reload page */
		if(empty($_GET['stock'])) {
			$_GET['stock']='sp500';
		}
		if(empty($_GET['m'])) {
			$_GET['m']='m1';
		}
		$stocks=array($_GET['stock']);
		$m_adjusteds=array($_GET['m']);
		$adjusteds=array($_GET['m']);
	// </config>

	// <get data>
		$dbFile=__DIR__.'/../data/m1chart.db';
		$dir = 'sqlite:/'.$dbFile;
		$chartDb	= new PDO($dir) or exit(68); /* db erorr */
		$chartDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		// $query=$chartDb->prepare("SELECT * FROM m1chart WHERE epoch>:epoch ORDER BY epoch ASC");
		// if($_GET['time']!='all' && !empty($_GET['time'])) {
		// 	$query->bindValue(':epoch',strtotime('-'.$_GET['time']));
		// }
		// else {
		// 	// default 10 years back
		// 	$query->bindValue(':epoch',0);
		// }
		// $query=$chartDb->prepare("SELECT * FROM m1chart ORDER BY epoch ASC");
		$query=$chartDb->prepare("SELECT * FROM m1chart ORDER BY epoch ASC");
		$query->execute();
		$data=$query->fetchAll(PDO::FETCH_ASSOC);
	// </get data>


	// <data start/end times>
		// also used for sitemap
		$dataStartTimes=array();

		// find the first timestamp of a non-empty value of each data set
		// so we can change the X axis to start at the first point of data
		// e.g. BTC starts in 2009 not 2000

		foreach($data as $row) {
			foreach($stocks as $stock) {
				if(!empty($row[$stock]) && ($row['epoch']<$dataStartTimes[$stock] || empty($dataStartTimes[$stock]))) {
					$dataStartTimes[$stock]=$row['epoch'];
				}
			}
			foreach($adjusteds as $adjusted) {
				if(!empty($row[$adjusted]) && ($row['epoch']<$dataStartTimes[$adjusted] || empty($dataStartTimes[$adjusted]))) {
					$dataStartTimes[$adjusted]=$row['epoch'];
				}
			}
			foreach($m_adjusteds as $m) {
				foreach($stocks as $stock) {
					if(!empty($row[$stock]) && !empty($row[$m]) && ($row['epoch']<$dataStartTimes[$m.'_adj_'.$stock] || empty($dataStartTimes[$m.'_adj_'.$stock]))) {
						$dataStartTimes[$m.'_adj_'.$stock]=$row['epoch'];
					}
				}
			}
		}

		$dataEndTimes=array();

		// find the last timestamp of a non-empty value of each data set
		// so we can change the X axis to end at the last point of data

		foreach($data as $row) {
			foreach($stocks as $stock) {
				if(!empty($row[$stock]) && ($row['epoch']>$dataStartTimes[$stock] || empty($dataStartTimes[$stock]))) {
					$dataEndTimes[$stock]=$row['epoch'];
				}
			}
			foreach($adjusteds as $adjusted) {
				if(!empty($row[$adjusted]) && ($row['epoch']>$dataStartTimes[$adjusted] || empty($dataStartTimes[$adjusted]))) {
					$dataEndTimes[$adjusted]=$row['epoch'];
				}
			}
			foreach($m_adjusteds as $m) {
				foreach($stocks as $stock) {
					if(!empty($row[$stock]) && !empty($row[$m]) && ($row['epoch']>$dataStartTimes[$m.'_adj_'.$stock] || empty($dataStartTimes[$m.'_adj_'.$stock]))) {
						$dataEndTimes[$m.'_adj_'.$stock]=$row['epoch'];
					}
				}
			}
		}
	// </data start/end times>



//TODO
// find newest start time and remove older data than that so we don't have weirdly scaled charts if combo data of datasets that have lots of data and few data



	// <sitemap>
		if($_GET['url']=='sitemap.xml') {
			header('Content-type: application/xml');
			echo '<?xml version="1.0" encoding="UTF-8"?>'?>
			<?
			foreach($m_adjusteds as $m) {
				foreach($stocks as $stock) {
					?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
						<url>
							<loc>
								https://m1chart.com/<?=$stock?>-in-<?=$m?>
							</loc>
							<changefreq>
								weekly
							</changefreq>
							<priority>
								1
							</priority>
							<lastmod>
								<?
								echo date('c',$dataEndTimes[$m.'_adj_'.$stock]);
								?>
							</lastmod>
						</url>
					</urlset><?
				}
			}			
			exit;
		}
	// </sitemap>

	// <screenshot with API Flash>
		if($_GET['action']=='screenshot') {
			require_once(__DIR__.'/config.php');
			$screenshotUrl='https://'.$_SERVER['SERVER_NAME'].$_GET['uri'].'&layout=screenshot&cache='.date('Y-m');
			$url='https://api.apiflash.com/v1/urltoimage?quality=80&width=1250&height=667&format=jpeg&access_key='.$apiflash_key.'&url='.urlencode($screenshotUrl);
			$response=curl_get_contents($url);
			if(!$response) {
				echo "Can't connect to API Flash";
			}
			else {
				if(!empty(json_decode($response,true))) {
					echo $screenshotUrl;
					echo "\n\n";
					// json response
					echo $response;
					exit;
				}
				header("Content-type: image/jpeg");
				echo $response;
			}
			exit;
		}
	// </screenshot with API Flash>


	// <get adjusteds that we haven't had in stocks yet, to display as blue line>
		$adjusteds=array_unique(array_merge($stocks,$m_adjusteds));
	// </get adjusteds that we haven't had in stocks yet, to display as blue line>

	// <get latest for each data set>
		// $latest=array();
		// foreach($m_adjusteds as $m) {
		// 	$query=$chartDb->prepare("SELECT * FROM m1chart WHERE ".$m." IS NOT NULL AND ".$m." IS NOT '' ORDER BY epoch DESC LIMIT 1");
		// 	$query->execute();
		// 	$latest[$m]=$query->fetchAll(PDO::FETCH_ASSOC)[0][$m];
		// }
		// foreach($stocks as $stock) {
		// 	$query=$chartDb->prepare("SELECT * FROM m1chart WHERE ".$stock." IS NOT NULL AND ".$stock." IS NOT '' ORDER BY epoch DESC LIMIT 1");
		// 	$query->execute();
		// 	$latest[$stock]=$query->fetchAll(PDO::FETCH_ASSOC)[0][$stock];
		// }
	// </get latest for each data set>

	// <get first for each data set>
		// $first=array();
		// foreach($m_adjusteds as $m) {
		// 	$query=$chartDb->prepare("SELECT * FROM m1chart WHERE epoch>:epoch AND ".$m." IS NOT NULL AND ".$m." IS NOT '' ORDER BY epoch ASC LIMIT 1");
		// 	if($_GET['time']!='all' && !empty($_GET['time'])) {
		// 		$query->bindValue(':epoch',strtotime('-'.$_GET['time']));
		// 	}
		// 	else {
		// 		// default 10 years back
		// 		$query->bindValue(':epoch',0);
		// 	}
		// 	$query->execute();
		// 	$first[$m]=$query->fetchAll(PDO::FETCH_ASSOC)[0][$m];
		// }
		// foreach($stocks as $stock) {
		// 	$query=$chartDb->prepare("SELECT * FROM m1chart WHERE epoch>:epoch AND ".$stock." IS NOT NULL AND ".$stock." IS NOT '' ORDER BY epoch ASC LIMIT 1");
		// 	if($_GET['time']!='all' && !empty($_GET['time'])) {
		// 		$query->bindValue(':epoch',strtotime('-'.$_GET['time']));
		// 	}
		// 	else {
		// 		// default 10 years back
		// 		$query->bindValue(':epoch',0);
		// 	}
		// 	$query->execute();
		// 	$first[$stock]=$query->fetchAll(PDO::FETCH_ASSOC)[0][$stock];
		// }
	// </get first for each data set>
		


	$newData=array();
	foreach($data as $row) {

		foreach($m_adjusteds as $m) {
			foreach($stocks as $stock) {
				// $row[$m.'_adj_'.$stock]=$row[$stock]/$row[$m]*$latest[$m];
				// $row[$m.'_adj_'.$stock]=($row[$stock]/$row[$m])*$first[$stock]/$first[$m];
				// $row[$m.'_adj_'.$stock]=($row[$stock]/$row[$m])/($first[$stock]/$first[$m]);
				// $row[$m.'_adj_'.$stock]=$row[$stock]/$row[$m];

				// echo $m.'_adj_'.$stock.'='.$row[$stock].'['.$stock.']'.'/'.$row[$m].'['.$m.']';
				// echo "<br/>\n";


				$row[$m.'_adj_'.$stock]=$row[$stock]/$row[$m];
				if(
					empty($row[$m.'_adj_'.$stock]) || 
					is_nan($row[$m.'_adj_'.$stock]) || 
					is_infinite($row[$m.'_adj_'.$stock]) ||
					!is_numeric($row[$m.'_adj_'.$stock])
				) {
					unset($row[$m.'_adj_'.$stock]);
				}
			}
		}
		array_push($newData,$row);
	}
	$data=$newData;




	$new=array();
	foreach($stocks as $stock) {
		$new[$stock]=array();
		foreach($data as $row) {
			array_push($new[$stock],$row[$stock]);
		}
	}
	foreach($m_adjusteds as $m) {
		$new[$m]=array();
		foreach($data as $row) {
			array_push($new[$m],$row[$m]);
		}
	}
	foreach($m_adjusteds as $m) {
		foreach($stocks as $stock) {
			$new[$m.'_adj_'.$stock]=array();
			foreach($data as $row) {
				array_push($new[$m.'_adj_'.$stock],$row[$m.'_adj_'.$stock]);
			}
		}
	}
	// echo json_encode($new);
	// exit;




	// <make strings numbers>
		$newData=array();
		foreach($data as $row) {
			$newRow=array();
			foreach($row as $key => $value) {
				if($key=='date') {
					$newRow[$key]=$value;
				}
				else {
					$newValue=(float) $value;
					$newRow[$key]=$newValue;
				}
			}
			array_push($newData,$newRow);
		}
		$data=$newData;
	// </make strings numbers>

	// <set show/hide stock/adjusted from URL>
		if(isset($_GET['show_stock'])){
			if($_GET['show_stock']==1) {
				$show_stock=1;
			}
			else {
				$show_stock=0;
			}
		}
		else {
			// default if not set to show
			$show_stock=1;
		}

		if(isset($_GET['show_adjusted'])){
			if($_GET['show_adjusted']==1) {
				$show_adjusted=1;
			}
			else {
				$show_adjusted=0;
			}
		}
		else {
			// default if not set to show
			$show_adjusted=1;
		}

		if(isset($_GET['show_adjuster'])){
			if($_GET['show_adjuster']==1) {
				$show_adjuster=1;
			}
			else {
				$show_adjuster=0;
			}
		}
		else {
			// default if not set to show
			$show_adjuster=0;
		}


		if(isset($_GET['logarithmic'])){
			if($_GET['logarithmic']==1) {
				$logarithmic=1;
			}
			else {
				$logarithmic=0;
			}
		}
		else {
			// default if not set to not logarithmic, e.g. linear
			$logarithmic=0;
		}
	// </set show/hide stock/adjusted from URL>



	/*if(empty($_GET) && !$data[30]['sp500']) {
		echo "<center><strong>🚨 Note: I detected that Google Finance historical data is down, so some indices like S&P500, DJI etc. are rekt and site may look broken, check back in a few hours</strong></center>";
		echo '<hr>';
		$logarithmic=0;
		$_GET['m']='gold';
		$_GET['stock']='income';
	}*/

	// echo "<center><strong>🚨 Note: I broke some charts while importing new data, trying to find the bug then it'll work again! -Pieter</strong></center>";
	// echo '<hr>';


	$page['title']='💰'."M1 Chart: The stock market adjusted for the US-dollar money supply M1 (and more) (by @levelsio)";
	$page['description']="This chart shows the price of stock markets adjusted for inflation of the US dollar money supply in M1, M2 and the money base (MB).".'. Money printer goes brrrrrrrrr.';

	// ob_start("sanitizeOutput");

	if($_GET['m'] || $_GET['stock']) {
		$page['title']='💰'.strtoupper($_GET['stock']).' Price in '.strtoupper($_GET['m']);
		$page['description']="This chart shows the price of ".strtoupper($_GET['stock'])." measured in ".strtoupper($_GET['m']).', to adjust it for inflation. Money printer goes brrrrrrrrr.';
	}

?><!doctype html>
<html class="<?=$_GET['layout']?>">
<!--

	(m) MIT License

	Please steal my code but credit me, @levelsio with a link to https://twitter.com/levelsio if you use this to make something!

	Made with vanilla HTML, vanilla CSS, vanilla JS with jQuery and vanilla PHP.

	🌙 Ad lunam!

-->
<meta charset="UTF-8" />
<script src="https://m1chart.com/assets/jquery.js?<?=filemtime(__DIR__.'/../assets/jquery.js');?>"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
<script src="https://m1chart.com/assets/chartjs.js?<?=filemtime(__DIR__.'/../assets/chartjs.js');?>"></script>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:site" content="@levelsio">
<meta name="twitter:creator" content="@levelsio">
<meta name="twitter:title" content="<?=$page['title']?>">
<meta name="twitter:description" content="<?=$page['description']?>" />
<meta name="twitter:image:src" content="https://m1chart.com/?action=screenshot&uri=<?=urlencode($_SERVER['REQUEST_URI'])?>">
<meta property="og:type" content="website"/>
<meta property="og:title" content="<?=$page['title']?>"/>
<meta property="og:image" content="https://m1chart.com/?action=screenshot&uri=<?=urlencode($_SERVER['REQUEST_URI'])?>"/>
<meta property="og:description" content="<?=$page['description']?>" />
<meta property="og:url" content="https://m1chart.com<?=$_SERVER['REQUEST_URI']?>">
<meta name="twitter:url" content="https://m1chart.com<?=$_SERVER['REQUEST_URI']?>">
<link rel="icon" href="/assets/favicon.png"/>
<link rel="shortcut icon" href="/assets/favicon.png"/>
<link rel="preload" href="/assets/DMSans-Regular.ttf" as="font" type="font/ttf" crossorigin>
<link rel="preload" href="/assets/DMSans-Bold.ttf" as="font" type="font/ttf" crossorigin>
<link rel="preload" href="/assets/iosevka-custom-extended.woff2" as="font" type="font/woff2" crossorigin>
<!-- <link rel="preload" href="/assets/iosevka-custom-extendedbold.woff2" as="font" type="font/woff2" crossorigin> -->
<script async defer src="https://scripts.simpleanalyticscdn.com/latest.js"></script>
<noscript><img src="https://queue.simpleanalyticscdn.com/noscript.gif" alt=""/></noscript>
<title>
	<?=$page['title']?>
</title>
<style>
	@font-face{
		font-family:"DM Sans";
		font-style:normal;
		font-display:swap;
		font-weight:400;
		src:url("/assets/DMSans-Regular.ttf") format("truetype");
	}

	@font-face {
		font-family:"DM Sans";
		font-style:normal;
		font-display:swap;
		font-weight:700;
		src:url("/assets/DMSans-Bold.ttf") format("truetype");
	}

	@font-face {
		font-family:"Iosevka Custom";
		font-display:swap;
		font-weight:400;
		font-stretch:expanded;
		font-style:normal;
		src:url("/assets/iosevka-custom/woff2/iosevka-custom-extended.woff2") format("woff2"),url("/assets/iosevka-custom/woff/iosevka-custom-extended.woff") format("woff"),url("/assets/iosevka-custom/ttf/iosevka-custom-extended.ttf") format("truetype");
	}

	@font-face {
		font-family:"Iosevka Custom";
		font-display:swap;
		font-weight:700;
		font-stretch:expanded;
		font-style:normal;
		src:url("/assets/iosevka-custom/woff2/iosevka-custom-extendedbold.woff2") format("woff2"),url("/assets/iosevka-custom/woff/iosevka-custom-extendedbold.woff") format("woff"),url("/assets/iosevka-custom/ttf/iosevka-custom-extendedbold.ttf")
	}
	.side span.quote,
	.side a {
		/*font-family:"Iosevka Custom",monospace,sans-serif;*/
	}
	.side .fed {
		width:100%;
		max-width:200px;
		margin:0 auto;
		display:block;
	}
	.side .fed-wrapper {
		border-top:1px solid #2a2a2a;
		border-bottom:1px solid #2a2a2a;
	}
	@media (max-width:800px) {
		.side .fed-wrapper {
			border:none;
			border:1px solid #2a2a2a;
			display:table;
			margin:0 auto;
			border-radius:0;
			overflow:hidden;
		}
	}
	.side p {
		line-height:1.75;
		font-size:14px;
		font-weight:100;
		font-family:"DM Sans",sans-serif;
		color:rgb(186,186,186);
	}
	.side p {
		padding:14px;
		padding-top:0;
	}
	body {
		margin:0;
		padding:0;
		font-family:"Iosevka Custom",monospace,sans-serif;
		text-align:left;
		font-size:14px;
		background:#000;
		color:rgb(211,211,211);
		background:linear-gradient(180deg,#0a1325 0,#000000);
	}
	h1.selectors {
		width:calc(100% - 75px);
		margin:0 auto;
		margin-top:28px;
		display:block;
		margin-left:75px;
		margin-right:75px;
	}
	h1.selectors,
	h1 select {
		font-family:"Iosevka Custom",monospace,sans-serif;
		font-size:26px;
		color:rgb(211,211,211);
		color:#ff4742;
		z-index:1;
		position:relative;
	}
	select {
		outline:none;
		-webkit-appearance:none;
		background:none;
		padding:7px;
		padding-left:0;
		padding-right:0;
		/*border-radius:7px;*/
		color:rgb(186,186,186);
		border:1px solid rgb(186,186,186);
		border-color:#ff4742;
		color:#ff4742;
		<?if($_GET['layout']=='screenshot'){?>
			border-width:4px;
			font-weight:800;
		<?}?>
		cursor:pointer;
		margin:0;
	}
	select:hover {
		opacity:0.75;
	}
	select.adjustment_selector {
		color:#ff4742;
		border-color:#ff4742;
		color:#42a5ff;
		border-color:#42a5ff;

		border-left:none;
		border-right:none;
		border-top:none;
	}
	select.adjustment_selector:hover {
		background:#ff4742;
		color:#060b16;
		background:#42a5ff;
	}
	select.stock_selector {
		border-left:none;
		border-right:none;
		border-top:none;
		color:rgb(43,222,115);
		border-color:rgb(43,222,115);
	}
	select.stock_selector:hover {
		background:rgb(43,222,115);
		color:#060b16;
	}
	select.time_selector:hover {
		background:#fff;
		color:#060b16;
	}
	.adjustment_selector_wrapper,
	.stock_selector_wrapper {
		display:inline-block;
		margin:7px;
	}
	.heading_above {
		text-transform:uppercase;
		color:#fff;
		font-size:12px;
		font-weight:normal;
		text-align:left;
		color:rgb(211,211,211);
	}
	a {
		color:rgb(211,211,211);
		color:#ff4742;
	}

	.side {
		width:200px;
		border-left:1px solid #2a2a2a;
		height:100%;
		background:#000;
		overflow-y:scroll;
		overflow-x:hidden;
		z-index:1;
		position:fixed;
		right:0;
		display:none;
	}
	.main {
		/*width:calc(100% - 200px - 28px);*/
		height:100vh;
		/*margin-right:calc(200px + 28px);*/
		overflow-y:hidden;
		background:none;
	}

	.main .chart {
		margin:28px;
		height:calc(100vh - 200px);
	}

	.logo,
	.by-levelsio {
		border:1px solid #2a2a2a;
		padding:7px;
		font-size:12px;
		/*border-radius:7px;*/
		background:#0a1325;
		z-index:1;
	}

	.logo {
		position:fixed;
		top:14px;
		left:14px;
		color:rgb(211,211,211);
		text-decoration:none;
		z-index:0;
	}

	.by-levelsio {
		position:fixed;
		text-decoration:none;
		color:rgb(211,211,211);
		bottom:14px;
		left:14px;
	}

	.legend {
		color:rgb(211,211,211);
		left:120px;
		top:155px;
		/*background:linear-gradient(180deg,#0a1325 0,#0a1325);*/
		background:#0a1325;
		position:fixed;
		border:1px solid #2a2a2a;
		z-index:1;
		/*width:calc(100% - 200px - 1px - 7px - 7px);*/
		width:200px;
		padding:7px;
		padding-top:4px;
		padding-bottom:10px;
		position:fixed;
		/*background:#000;*/
		text-align:center;
		box-shadow:1px 2px 4px rgba(0,0,0,1);
		/*border-top:1px solid #2a2a2a;*/
		-webkit-touch-callout: none;
		-webkit-user-select: none;
		-khtml-user-select: none;
		-moz-user-select: none;
		-ms-user-select: none;
		user-select: none;
		text-align:left;
		font-size:16px;
	}
	.legend .show_stock_legend:hover,
	.legend .show_adjusted_legend:hover,
	.legend .show_adjuster_legend:hover,
	.legend .logarithmic_legend:hover {
		opacity:0.75 !important;
	}
	.legend span {
		cursor:pointer;
	}
	.legend input[type="checkbox"] {
		position:relative;
		top:2px;
		cursor:pointer;
	}


	a:hover {
		opacity:0.75;
	}
	a:active {
		opacity:0.5;
	}

	.tweet-wrapper {
		padding:14px;
		padding-top:0;
		padding-bottom:0;
	}
	.youtube-wrapper {
		pointer-events:none;
		max-width:100%;
		padding:0px;
		border-bottom:1px solid #2a2a2a;
		padding-top:0;
		padding-bottom:0;
		margin-top:-14px;
	}

	.side span.quote {
		/*color:#fff;*/
		/*line-height:1.25;*/
		/*display:inline;*/
	}

	@media (max-width:600px) {
		.by-levelsio {
			position:relative;
			margin:0 auto;
			left:auto;
			bottom:auto;
			display:table;
			background:none;
			margin-bottom:-14px;
			border:none;
			font-size:12px;
			color:rgb(186,186,186);
		}
		.main .chart {
			margin:7px;
		}
	}
	@media (max-width:1000px) {

		h1.selectors,
		h1.selectors select {
			font-size:20px;
		}
	}
	.logo {
		display:none;
	}

	html.screenshot .by-levelsio,
	html.screenshot .legend,
	html.screenshot .side {
		display:none;
	}
	html.screenshot h1.selectors,
	html.screenshot h1.selectors select {
		font-size:35px;
	}
	html.screenshot h1.selectors {
		margin:28px;
	}
	html.screenshot .main .chart {
		height:calc(100vh - 150px);
	}
	html.screenshot .main {
		width:100%;
		height:100vh;
		position:relative;
	}
	@media (max-width:800px) {
		.youtube-wrapper {
			display:none;
		}
		.legend {
			position:relative;
		}
		.main {
			width:100%;
			height:auto;
			position:relative;
		}
		.side {
			width:100%;
			border:none;
			background:none;
			overflow:none;
			height:auto;
			padding-bottom:28px;
			position:relative;
		}
		h1.selectors {
			width:auto;
			margin:0;
		}
		.legend {
			position:relative;
			left:auto;
			right:auto;
			top:auto;
			bottom:auto;
			width:auto;
			background:none;
			border:none;
			text-align:center;
			padding:14px;
		}
		
		h1.selectors,
		h1.selectors select {
			font-size:16px;
		}
		select {
			margin-top:0;
			margin-left:0;
			margin-right:0;
		}
		body {
			padding:0;
			padding-top:14px;
			text-align:center;
		}
		.hide_on_mobile {
			display:none;
		}
		h1.selectors .slash {
			display:none;
		}
		h1.selectors select {
			border:1px solid;
			padding:7px;
			margin:7px;
		}
		.heading_above {
			text-align:center;
		}
		.mobile_line_break {
			display:block;
		}
		.main .chart,
		.main #chart {
			height:50vh;
		}
	}
</style>

<div class="main">
	<center>
		<h1 class="selectors">
			<div class="stock_selector_wrapper">
				<div class="heading_above">
					Show the price of
				</div>
				<select class="stock_selector">
					<option value="sp500" <?if(empty($_GET['stock']) || $_GET['stock']=='sp500'){?>selected<?}?>>🇺🇸S&P500</option>
					<option value="dji" <?if($_GET['stock']=='dji'){?>selected<?}?>>🇺🇸DJI</option>
					<option value="nasdaq" <?if($_GET['stock']=='nasdaq'){?>selected<?}?>>🤖NASDAQ</option>
					<option value="gdp" <?if($_GET['stock']=='gdp'){?>selected<?}?>>💰US GDP</option>
					<option value="income" <?if($_GET['stock']=='income'){?>selected<?}?>>💰Avg US Income</option>
					<option value="oil" <?if($_GET['stock']=='oil'){?>selected<?}?>>🛢Oil</option>
					<option value="gold" <?if($_GET['stock']=='gold'){?>selected<?}?>>🏆Gold</option>
					<option value="silver" <?if($_GET['stock']=='silver'){?>selected<?}?>>🥈Silver</option>
					<option value="asia" <?if($_GET['stock']=='asia'){?>selected<?}?>>🌏Asia ex-JP</option>
					<option value="china" <?if($_GET['stock']=='china'){?>selected<?}?>>🇨🇳China SSE</option>
					<option value="home" <?if($_GET['stock']=='home'){?>selected<?}?>>🏡Avg US Home</option>
					<option value="food" <?if($_GET['stock']=='food'){?>selected<?}?>>🥩Food Price</option>
					<option value="bigmac" <?if($_GET['stock']=='bigmac'){?>selected<?}?>>🍔Big Mac Index</option>
					<option value="btc" <?if($_GET['stock']=='btc'){?>selected<?}?>>🥇BTC</option>
					<option value="eth" <?if($_GET['stock']=='eth'){?>selected<?}?>>🏅ETH</option>
					<option value="tsla" <?if($_GET['stock']=='tsla'){?>selected<?}?>>🚗$TSLA</option>
				</select>
			</div>
		<span><span class="mobile_line_break"></span><span class="slash"> / </span><span class="mobile_line_break"></span></span>
			<div class="adjustment_selector_wrapper">
				<div class="heading_above">
					As measured in
				</div>
				<select class="adjustment_selector">
					<option value="mb" <?if($_GET['m']=='mb'){?>selected<?}?>>💸 M0: Cash</option>
					<option value="m1" <?if(empty($_GET['m']) /* default to mb */ || $_GET['m']=='m1'){?>selected<?}?>>💳 M1: Cash + Bank</option>
					<option value="m3" <?if($_GET['m']=='m3'){?>selected<?}?>>💰 M3: All Money</option>
					<option value="cpi" <?if($_GET['m']=='cpi'){?>selected<?}?>>🛒Consumer Price Index</option>
					<option value="sp500" <?if(empty($_GET['m']) || $_GET['m']=='sp500'){?>selected<?}?>>🇺🇸S&P500</option>
					<option value="levels" <?if($_GET['m']=='levels'){?>selected<?}?>>🐩 Levels Inflation Index</option>
					<option value="oil" <?if($_GET['m']=='oil'){?>selected<?}?>>🛢Oil</option>
					<option value="gold" <?if($_GET['m']=='gold'){?>selected<?}?>>🏆Gold</option>
					<option value="silver" <?if($_GET['m']=='silver'){?>selected<?}?>>🥈Silver</option>
					<option value="home" <?if($_GET['m']=='home'){?>selected<?}?>>🏡Avg US Home</option>
					<option value="food" <?if($_GET['m']=='food'){?>selected<?}?>>🥩Food price</option>
					<option value="food_and_home" <?if($_GET['m']=='food_and_home'){?>selected<?}?>>🥩Food + 🏡Avg US Home</option>
					<option value="bigmac" <?if($_GET['m']=='bigmac'){?>selected<?}?>>🍔Big Mac Index</option>
					<option value="btc" <?if($_GET['m']=='btc'){?>selected<?}?>>🥇BTC</option>
					<option value="eth" <?if($_GET['m']=='eth'){?>selected<?}?>>🏅ETH</option>
					<option value="population" <?if($_GET['m']=='population'){?>selected<?}?>>🌍Population</option>
					<option value="income" <?if($_GET['m']=='income'){?>selected<?}?>>💰Avg US Income</option>
				</select>
			</div>

			<?/*  <select class="time_selector">
				<option value="1 year" <?if($_GET['time<span class="mobile_line_break"></span>']=='1 year'){?>selected<?}?>>last 1 year</option>
				<option value="5 years" <?if($_GET['time']=='5 years'){?>selected<?}?>>last 5 years</option>
				<option value="10 years" <?if($_GET['time']=='10 years'){?>selected<?}?>>last 10 years</option>
				<option value="all" <?if(empty($_GET['time']) || $_GET['time']=='all'){?>selected<?}?>>last 20 years</option>
			</select>*/?>
		</h1>
	</center>


	<a href="https://twitter.com/levelsio" class="by-levelsio">
		by @levelsio
	</a>

	<a href="https://m1chart.com" class="logo">
		M1 Chart
	</a>
	
	<script>
		var chart;
		var adjusted_selected='';
		var adjusted_selected_label='';
		var stock_selected='';
		var stock_selected_label='';
		var show_stock=<?=$show_stock?>;
		var show_adjusted=<?=$show_adjusted?>;
		var show_adjuster=<?=$show_adjuster?>;
		var logarithmic=<?=$logarithmic?>;

		$(window).bind('popstate',function() {
			/* reload page if user presses back or forward etc, so that the URL they go to is actually loaded */
			window.location.reload();
		});

		var animationDataBufferIterator=0;
		var animationInterval;
		var animationDataAdjustedBuffer=0;
		var animationDataAdjusterBuffer=0;
		var animationDataStockBuffer=0;
		var animationDatasetIndex=0;
		var animationFindDatasetIndexIterator=0;
		var animationWhatAreWeAnimating='';


		var dataStartTimes=<?=json_encode($dataStartTimes);?>;

		var dataEndTimes=<?=json_encode($dataEndTimes);?>;


		function str_replace(search, replace, subject, count) {
			/*Copyright (c) 2007-2016 Kevin van Zonneveld (https://kvz.io) 
			and Contributors (https://locutus.io/authors)

			Permission is hereby granted, free of charge, to any person obtaining a copy of
			this software and associated documentation files (the "Software"), to deal in
			the Software without restriction, including without limitation the rights to
			use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
			of the Software, and to permit persons to whom the Software is furnished to do
			so, subject to the following conditions:

			The above copyright notice and this permission notice shall be included in all
			copies or substantial portions of the Software.

			THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
			IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
			FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
			AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
			LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
			OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
			SOFTWARE.*/
			var i = 0,
				j = 0,
				temp = '',
				repl = '',
				sl = 0,
				fl = 0,
				f = [].concat(search),
				r = [].concat(replace),
				s = subject,
				ra = Object.prototype.toString.call(r) === '[object Array]',
				sa = Object.prototype.toString.call(s) === '[object Array]';
			s = [].concat(s);
			if (count) {
				this.window[count] = 0;
			}

			for (i = 0, sl = s.length; i < sl; i++) {
				if (s[i] === '') {
					continue;
				}
				for (j = 0, fl = f.length; j < fl; j++) {
					temp = s[i] + '';
					repl = ra ? (r[j] !== undefined ? r[j] : '') : r[0];
					s[i] = (temp)
						.split(f[j])
						.join(repl);
					if (count && s[i] !== temp) {
						this.window[count] += (temp.length - s[i].length) / f[j].length;
					}
				}
			}
			return sa ? s : s[0];
		}

		function number_format(number, decimals, dec_point, thousands_sep) {
			/*Copyright (c) 2007-2016 Kevin van Zonneveld (https://kvz.io) 
			and Contributors (https://locutus.io/authors)

			Permission is hereby granted, free of charge, to any person obtaining a copy of
			this software and associated documentation files (the "Software"), to deal in
			the Software without restriction, including without limitation the rights to
			use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
			of the Software, and to permit persons to whom the Software is furnished to do
			so, subject to the following conditions:

			The above copyright notice and this permission notice shall be included in all
			copies or substantial portions of the Software.

			THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
			IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
			FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
			AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
			LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
			OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
			SOFTWARE.*/

			number = (number + '')
				.replace(/[^0-9+\-Ee.]/g, '');
			var n = !isFinite(+number) ? 0 : +number,
				prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
				sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
				dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
				s = '',
				toFixedFix = function(n, prec) {
					var k = Math.pow(10, prec);
					return '' + (Math.round(n * k) / k)
						.toFixed(prec);
				};
			
			s = (prec ? toFixedFix(n, prec) : '' + Math.round(n))
				.split('.');
			if (s[0].length > 3) {
				s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
			}
			if ((s[1] || '')
				.length < prec) {
				s[1] = s[1] || '';
				s[1] += new Array(prec - s[1].length + 1)
					.join('0');
			}
			return s.join(dec);
		}

		function date(format, timestamp) {
			/*Copyright (c) 2007-2016 Kevin van Zonneveld (https://kvz.io) 
			and Contributors (https://locutus.io/authors)

			Permission is hereby granted, free of charge, to any person obtaining a copy of
			this software and associated documentation files (the "Software"), to deal in
			the Software without restriction, including without limitation the rights to
			use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
			of the Software, and to permit persons to whom the Software is furnished to do
			so, subject to the following conditions:

			The above copyright notice and this permission notice shall be included in all
			copies or substantial portions of the Software.

			THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
			IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
			FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
			AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
			LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
			OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
			SOFTWARE.*/
			
			var that = this;
			var jsdate, f;
			// Keep this here (works, but for code commented-out below for file size reasons)
			// var tal= [];
			var txt_words = [
			'Sun', 'Mon', 'Tues', 'Wednes', 'Thurs', 'Fri', 'Satur',
			'January', 'February', 'March', 'April', 'May', 'June',
			'July', 'August', 'September', 'October', 'November', 'December'
			];
			// trailing backslash -> (dropped)
			// a backslash followed by any character (including backslash) -> the character
			// empty string -> empty string
			var formatChr = /\\?(.?)/gi;
			var formatChrCb = function(t, s) {
			return f[t] ? f[t]() : s;
			};
			var _pad = function(n, c) {
			n = String(n);
			while (n.length < c) {
				n = '0' + n;
			}
			return n;
			};
			f = {
			// Day
			d: function() { // Day of month w/leading 0; 01..31
				return _pad(f.j(), 2);
			},
			D: function() { // Shorthand day name; Mon...Sun
				return f.l()
				.slice(0, 3);
			},
			j: function() { // Day of month; 1..31
				return jsdate.getDate();
			},
			l: function() { // Full day name; Monday...Sunday
				return txt_words[f.w()] + 'day';
			},
			N: function() { // ISO-8601 day of week; 1[Mon]..7[Sun]
				return f.w() || 7;
			},
			S: function() { // Ordinal suffix for day of month; st, nd, rd, th
				var j = f.j();
				var i = j % 10;
				if (i <= 3 && parseInt((j % 100) / 10, 10) == 1) {
				i = 0;
				}
				return ['st', 'nd', 'rd'][i - 1] || 'th';
			},
			w: function() { // Day of week; 0[Sun]..6[Sat]
				return jsdate.getDay();
			},
			z: function() { // Day of year; 0..365
				var a = new Date(f.Y(), f.n() - 1, f.j());
				var b = new Date(f.Y(), 0, 1);
				return Math.round((a - b) / 864e5);
			},

			// Week
			W: function() { // ISO-8601 week number
				var a = new Date(f.Y(), f.n() - 1, f.j() - f.N() + 3);
				var b = new Date(a.getFullYear(), 0, 4);
				return _pad(1 + Math.round((a - b) / 864e5 / 7), 2);
			},

			// Month
			F: function() { // Full month name; January...December
				return txt_words[6 + f.n()];
			},
			m: function() { // Month w/leading 0; 01...12
				return _pad(f.n(), 2);
			},
			M: function() { // Shorthand month name; Jan...Dec
				return f.F()
				.slice(0, 3);
			},
			n: function() { // Month; 1...12
				return jsdate.getMonth() + 1;
			},
			t: function() { // Days in month; 28...31
				return (new Date(f.Y(), f.n(), 0))
				.getDate();
			},

			// Year
			L: function() { // Is leap year?; 0 or 1
				var j = f.Y();
				return j % 4 === 0 & j % 100 !== 0 | j % 400 === 0;
			},
			o: function() { // ISO-8601 year
				var n = f.n();
				var W = f.W();
				var Y = f.Y();
				return Y + (n === 12 && W < 9 ? 1 : n === 1 && W > 9 ? -1 : 0);
			},
			Y: function() { // Full year; event.g. 1980...2010
				return jsdate.getFullYear();
			},
			y: function() { // Last two digits of year; 00...99
				return f.Y()
				.toString()
				.slice(-2);
			},

			// Time
			a: function() { // am or pm
				return jsdate.getHours() > 11 ? 'pm' : 'am';
			},
			A: function() { // AM or PM
				return f.a()
				.toUpperCase();
			},
			B: function() { // Swatch Internet time; 000..999
				var H = jsdate.getUTCHours() * 36e2;
				// Hours
				var i = jsdate.getUTCMinutes() * 60;
				// Minutes
				var s = jsdate.getUTCSeconds(); // Seconds
				return _pad(Math.floor((H + i + s + 36e2) / 86.4) % 1e3, 3);
			},
			g: function() { // 12-Hours; 1..12
				return f.G() % 12 || 12;
			},
			G: function() { // 24-Hours; 0..23
				return jsdate.getHours();
			},
			h: function() { // 12-Hours w/leading 0; 01..12
				return _pad(f.g(), 2);
			},
			H: function() { // 24-Hours w/leading 0; 00..23
				return _pad(f.G(), 2);
			},
			i: function() { // Minutes w/leading 0; 00..59
				return _pad(jsdate.getMinutes(), 2);
			},
			s: function() { // Seconds w/leading 0; 00..59
				return _pad(jsdate.getSeconds(), 2);
			},
			u: function() { // Microseconds; 000000-999000
				return _pad(jsdate.getMilliseconds() * 1000, 6);
			},

			// Timezone
			e: function() { // Timezone identifier; event.g. Atlantic/Azores, ...
				// The following works, but requires inclusion of the very large
				// timezone_abbreviations_list() function.
				/*				return that.date_default_timezone_get();
				 */
				throw 'Not supported (see source code of date() for timezone on how to add support)';
			},
			I: function() { // DST observed?; 0 or 1
				// Compares Jan 1 minus Jan 1 UTC to Jul 1 minus Jul 1 UTC.
				// If they are not equal, then DST is observed.
				var a = new Date(f.Y(), 0);
				// Jan 1
				var c = Date.UTC(f.Y(), 0);
				// Jan 1 UTC
				var b = new Date(f.Y(), 6);
				// Jul 1
				var d = Date.UTC(f.Y(), 6); // Jul 1 UTC
				return ((a - c) !== (b - d)) ? 1 : 0;
			},
			O: function() { // Difference to GMT in hour format; event.g. +0200
				var tzo = jsdate.getTimezoneOffset();
				var a = Math.abs(tzo);
				return (tzo > 0 ? '-' : '+') + _pad(Math.floor(a / 60) * 100 + a % 60, 4);
			},
			P: function() { // Difference to GMT w/colon; event.g. +02:00
				var O = f.O();
				return (O.substr(0, 3) + ':' + O.substr(3, 2));
			},
			T: function() { // Timezone abbreviation; event.g. EST, MDT, ...
				// The following works, but requires inclusion of the very
				// large timezone_abbreviations_list() function.
				/*				var abbr, i, os, _default;
				if (!tal.length) {
				tal = that.timezone_abbreviations_list();
				}
				if (that.php_js && that.php_js.default_timezone) {
				_default = that.php_js.default_timezone;
				for (abbr in tal) {
					for (i = 0; i < tal[abbr].length; i++) {
					if (tal[abbr][i].timezone_id === _default) {
						return abbr.toUpperCase();
					}
					}
				}
				}
				for (abbr in tal) {
				for (i = 0; i < tal[abbr].length; i++) {
					os = -jsdate.getTimezoneOffset() * 60;
					if (tal[abbr][i].offset === os) {
					return abbr.toUpperCase();
					}
				}
				}
				*/
				return 'UTC';
			},
			Z: function() { // Timezone offset in seconds (-43200...50400)
				return -jsdate.getTimezoneOffset() * 60;
			},

			// Full Date/Time
			c: function() { // ISO-8601 date.
				return 'Y-m-d\\TH:i:sP'.replace(formatChr, formatChrCb);
			},
			r: function() { // RFC 2822
				return 'D, d M Y H:i:s O'.replace(formatChr, formatChrCb);
			},
			U: function() { // Seconds since UNIX epoch
				return jsdate / 1000 | 0;
			}
			};
			this.date = function(format, timestamp) {
			that = this;
			jsdate = (timestamp === undefined ? new Date() : // Not provided
				(timestamp instanceof Date) ? new Date(timestamp) : // JS Date()
				new Date(timestamp * 1000) // UNIX timestamp (auto-convert to int)
			);
			return format.replace(formatChr, formatChrCb);
			};
			return this.date(format, timestamp);
		}


		$(function() {

			/* initialize */
			updateChart();

			/* <events> */
				$('select.adjustment_selector').bind('change',function() {
					updateSelected();
					updateUrl();
					window.location.reload();
					// updateChart();
				});
				$('select.stock_selector').bind('change',function() {
					updateSelected();
					updateUrl();
					window.location.reload();
					// updateChart();
				});
				// $('select.time_selector').bind('change',function() {
				// 	updateSelected();
				// 	updateUrl();
				// 	window.location.reload();
				// });

				$('.legend span').bind('click',function(e) {
					e.stopPropagation();
					if($(this).data('type')=='logarithmic') {
						$('.legend input[type="checkbox"].logarithmic').click();
					}
					else {
						$('.legend input[type="checkbox"].show_'+$(this).data('type')).click();
					}
				});

				$('.legend input[type="checkbox"]').bind('change',function() {
					show_stock=$('.legend input[type="checkbox"].show_stock:checked').length;
					show_adjusted=$('.legend input[type="checkbox"].show_adjusted:checked').length;
					show_adjuster=$('.legend input[type="checkbox"].show_adjuster:checked').length;
					logarithmic=$('.legend input[type="checkbox"].logarithmic:checked').length;
					updateVisibility();
					updateUrl();
				});
			/* </events> */

		});

		var stock_max=0;
		var adjusted_max=0;
		var adjuster_max=0;
		var is_animating=0;

		/* <animation> */

			// <find currently visible dataset of stock and animate it>
				function animateStockLine() {
					is_animating=true;

					chart.options.scales.yAxes[0].ticks.max=0;
					chart.options.scales.yAxes[1].ticks.max=0;
					chart.options.scales.yAxes[2].ticks.max=0;

					animationDataBufferIterator=0;
					animationWhatAreWeAnimating='stock';

					animationFindDatasetIndexIterator=0;
					chart.data.datasets.forEach(function(dataset) {
						
						if(dataset.id==adjusted_selected+'_adjuster') {
							// console.log(chart.data.datasets[animationFindDatasetIndexIterator]['id'],chart.data.datasets[animationFindDatasetIndexIterator]['data']);

							/* <set chart max for adjuster> */
								adjuster_max=findMax(chart.data.datasets[animationFindDatasetIndexIterator]['data']);
							/* </set chart max for adjuster> */

/* TODO ANIMATE ADJUSTER BLUE LINE */

							/*animationDataAdjusterBuffer=chart.data.datasets[animationFindDatasetIndexIterator]['data'];
							chart.data.datasets[animationFindDatasetIndexIterator]['data']=[];*/
						}
						if(dataset.id==stock_selected) {
							// console.log(chart.data.datasets[animationFindDatasetIndexIterator]['id'],chart.data.datasets[animationFindDatasetIndexIterator]['data']);

							/* <set chart max for stock> */
								stock_max=findMax(chart.data.datasets[animationFindDatasetIndexIterator]['data']);
							/* </set chart max for stock> */

							if(!show_stock) {
								/* if stock is not shown, don't put data in buffer, because we won't animate it */
							}
							else {
								animationDataStockBuffer=chart.data.datasets[animationFindDatasetIndexIterator]['data'];
								chart.data.datasets[animationFindDatasetIndexIterator]['data']=[];
								animationDatasetIndex=animationFindDatasetIndexIterator;
							}
							requestAnimationFrame(animationStep);
						}
						
						if(dataset.id==adjusted_selected+'_adj_'+stock_selected) {
							// console.log(chart.data.datasets[animationFindDatasetIndexIterator]['id'],chart.data.datasets[animationFindDatasetIndexIterator]['data']);
							/* <set chart max for adjusted> */
								adjusted_max=findMax(chart.data.datasets[animationFindDatasetIndexIterator]['data']);
							/* </set chart max for adjusted> */

							animationDataAdjustedBuffer=chart.data.datasets[animationFindDatasetIndexIterator]['data'];
							chart.data.datasets[animationFindDatasetIndexIterator]['data']=[];
						}
					
						animationFindDatasetIndexIterator++;;
						
					});
				}
			// </find currently visible dataset of stock and animate it>

			// <find currently visible dataset of adjusted data and animate it>
				function animateAdjustedLine() {
					is_animating=true;

					// console.log('animateAdjustedLine');
					animationDataBufferIterator=0;
					animationWhatAreWeAnimating='adjusted';

					animationFindDatasetIndexIterator=0;
					chart.data.datasets.forEach(function(dataset) {

						if(dataset.id==adjusted_selected+'_adj_'+stock_selected) {
							animationDatasetIndex=animationFindDatasetIndexIterator;
							requestAnimationFrame(animationStep);
						}

						animationFindDatasetIndexIterator++;;
						
					});

					is_animating=false;
				}
			// </find currently visible dataset of adjusted data and animate it>

			function animationStep() {

				/* <do it N times so it's faster> */
					if(animationWhatAreWeAnimating=='stock') {
						var bufferToUse=animationDataStockBuffer;
					}
					if(animationWhatAreWeAnimating=='adjusted') {
						var bufferToUse=animationDataAdjustedBuffer
					}

					var i=0;
					while(i<25) {
						chart.data.datasets[animationDatasetIndex]['data'].push(bufferToUse[animationDataBufferIterator]);
						animationDataBufferIterator++;
						i++;
					}
				/* </do it N times so it's faster> */


				chart.update();


				/* <quit if data finished> */
					if(animationWhatAreWeAnimating=='stock' && !show_stock) {
						/* if stock not visible, skip straight to draw the line instantly then go animate adjusted line, otherwise we'd have wait for a line to be drawn that we cannot see anyway (stock) */
						window.requestAnimationFrame(animateAdjustedLine);
						// console.log('animateAdjustedLine 1');
					}
					else if(animationDataBufferIterator<bufferToUse.length) {
						/* continue animating because we're not done yet */
						window.requestAnimationFrame(animationStep);
						// console.log('animationStep 1');
					}
					else {
						/* if we finished, see if we are animating the stock line, so we can animate the adjusted line next */
						if(animationWhatAreWeAnimating=='stock') {
							window.requestAnimationFrame(animateAdjustedLine);
							// console.log('animateAdjustedLine 2');
						}
					}
				/* </quit if data finished> */
				
			}
			function findMax(array) {
				// console.log(array);
				var max=0;
				for (var i = 0; i < array.length; i++) {
					var value=parseFloat(array[i]);
					if(value>max) {
						max=value;
					}
				}
				return max;
			}
		/* </animation> */


		function updateVisibility() {
			// console.log('updateVisibility');

			if(logarithmic) {
				chart.options.scales.yAxes[0].type='logarithmic'
				chart.options.scales.yAxes[1].type='logarithmic'
				chart.options.scales.yAxes[2].type='logarithmic'
			}
			else {
				chart.options.scales.yAxes[0].type='linear'
				chart.options.scales.yAxes[1].type='linear'
				chart.options.scales.yAxes[2].type='linear'
			}



			if(show_stock) {
				$('.legend .show_stock_legend').css('opacity',1);
			}
			else {
				$('.legend .show_stock_legend').css('opacity',0.5);
			}
			if(show_adjusted) {
				$('.legend .show_adjusted_legend').css('opacity',1);
			}
			else {
				$('.legend .show_adjusted_legend').css('opacity',0.5);
			}
			if(show_adjuster) {
				$('.legend .show_adjuster_legend').css('opacity',1);
			}
			else {
				$('.legend .show_adjuster_legend').css('opacity',0.5);
			}
			if(logarithmic) {
				$('.legend .logarithmic_legend').css('opacity',1);
			}
			else {
				$('.legend .logarithmic_legend').css('opacity',0.5);
			}



			if(show_stock && show_adjusted) {
				// console.log('startTime = '+adjusted_selected+'_adj_'+stock_selected,date('Y-m-d',dataStartTimes[adjusted_selected+'_adj_'+stock_selected]));
				var startTime=dataStartTimes[adjusted_selected+'_adj_'+stock_selected];
				var endTime=dataEndTimes[adjusted_selected+'_adj_'+stock_selected];
			}
			else if(show_adjusted) {
				// console.log('startTime = '+adjusted_selected+'_adj_'+stock_selected,date('Y-m-d',dataStartTimes[adjusted_selected+'_adj_'+stock_selected]));
				var startTime=dataStartTimes[adjusted_selected+'_adj_'+stock_selected];
				var endTime=dataEndTimes[adjusted_selected+'_adj_'+stock_selected];
			}
			else if(show_stock) {
				// console.log('startTime = '+stock_selected,date('Y-m-d',dataStartTimes[stock_selected]));
				var startTime=dataStartTimes[stock_selected];
				var endTime=dataEndTimes[stock_selected];
			}
			else if(show_adjuster) {
				// console.log('startTime = '+adjusted_selected,date('Y-m-d',dataStartTimes[adjusted_selected]));
				var startTime=dataStartTimes[adjusted_selected];
				var endTime=dataEndTimes[adjusted_selected];
			}


			// <set minimum start of dataset time>
				chart.options.scales.xAxes[0].ticks.min=date('Y-m',startTime);
				chart.options.scales.xAxes[0].ticks.max=date('Y-m',endTime);
				// console.log("chart.options.scales.xAxes[0].ticks.min",date('Y-m',startTime));
				// console.log("chart.options.scales.xAxes[0].ticks.max",date('Y-m',endTime));
			// </set minimum start of dataset time>



			if(show_stock) {
				chart.options.scales.yAxes[0].display=true;
			}
			else {
				chart.options.scales.yAxes[0].display=false;
			}
			if(show_adjusted) {
				chart.options.scales.yAxes[1].display=true;
			}
			else {
				chart.options.scales.yAxes[1].display=false;
			}
			if(show_adjuster) {
				chart.options.scales.yAxes[2].display=true;
			}
			else {
				chart.options.scales.yAxes[2].display=false;
			}

			// <set maxes>
				chart.options.scales.yAxes[0].ticks.max=stock_max;
				chart.options.scales.yAxes[1].ticks.max=adjusted_max;
				chart.options.scales.yAxes[2].ticks.max=adjuster_max;
			// </set maxes>


			var iterator=0;
			chart.data.datasets.forEach(function(dataset) {

				if(dataset.id==stock_selected && !show_stock) {
					chart.data.datasets[iterator].hidden=true;
				}
				if(dataset.id==stock_selected && show_stock) {
					chart.data.datasets[iterator].hidden=false;
				}

				if(dataset.id==adjusted_selected+'_adj_'+stock_selected && !show_adjusted) {
					chart.data.datasets[iterator].hidden=true;
				}
				if(dataset.id==adjusted_selected+'_adj_'+stock_selected && show_adjusted) {
					chart.data.datasets[iterator].hidden=false;
				}

				if(dataset.id==adjusted_selected+'_adjuster' && !show_adjuster) {
					chart.data.datasets[iterator].hidden=true;
				}
				if(dataset.id==adjusted_selected+'_adjuster' && show_adjuster) {
					chart.data.datasets[iterator].hidden=false;
				}

				iterator++;
			});

			chart.update();
		}
		function updateUrl() {
			// console.log('updateUrl');
			// uri='/?m='+adjusted_selected+'&stock='+stock_selected+'&time='+time_selected+'&show_stock='+show_stock+'&show_adjusted='+show_adjusted+'&show_adjuster='+show_adjuster+'&logarithmic='+logarithmic;
			var params='';
			if(time_selected!='all') {
				params=params+'&time='+time_selected;
			}
			if(!show_stock) {
				params=params+'&show_stock='+show_stock;
			}
			if(!show_adjusted) {
				params=params+'&show_adjusted='+show_adjusted;
			}
			if(show_adjuster) {
				params=params+'&show_adjuster='+show_adjuster;
			}
			if(logarithmic) {
				params=params+'&logarithmic='+logarithmic;
			}
			params=params.substr(1);
			if(params) {
				params='/?'+params;
			}
			uri='/'+stock_selected+'-in-'+adjusted_selected+params;
			history.pushState(null,null,uri);

			/* <preload social image so it's available/cached when needed> */
				<?if($_GET['layout']!='screenshot'){ /* avoid recursive loop */ ?>
					setTimeout(function() {
						$.ajax({
							url: 'https://m1chart.com/?action=screenshot&uri='+encodeURIComponent(uri)
						});
					},5000);
				<?}?>
			/* </preload social image so it's available/cached when needed> */
		}

		function updateSelected() {
			// console.log('updateSelected');
			adjusted_selected=$('select.adjustment_selector').children("option:selected").val();
			adjusted_selected_label=$('select.adjustment_selector').children("option:selected").text();
			stock_selected=$('select.stock_selector').children("option:selected").val();
			stock_selected_label=$('select.stock_selector').children("option:selected").text();
			// time_selected=$('select.time_selector').children("option:selected").val();
			time_selected='all';
		}
		function decimalify(t) {
			/* by levelsio */
			if(t==0) {
				t=0;
			}
			else if(t>=1000000000000) {
				t=number_format(t/1000000000000,1)+'T';
			}
			else if(t>=1000000000) {
				t=number_format(t/1000000000,1)+'B';
			}
			else if(t>=1000000) {
				t=number_format(t/1000000,1)+'M';
			}
			else if(t<=0.00000000001) {
				t=number_format(t*1000000000000,1)+' Tth';
			}
			else if(t<=0.00000001) {
				t=number_format(t*1000000000,1)+' Bth';
			}
			else if(t<=0.00001) {
				t=number_format(t*1000000,1)+' Mth';
			}
			else if(t<=0.1) {
				t=number_format(t,2);
			}
			else if(t<=1) {
				t=number_format(t,2);
			}
			else if(t<=10) {
				t=number_format(t,2);
			}
			else {
				t=number_format(t);
			}

			t=str_replace('.00','',t);
			return t;
		}
		function updateChart() {
			// console.log('updateChart');
			updateSelected();
			
			document.title=stock_selected_label+' Price in '+adjusted_selected_label;

			$('.legend span.adjuster').text($('select.adjustment_selector').children("option:selected").text().replace('-adjusted',''));
			$('.legend span.adjusted').text($('select.adjustment_selector').children("option:selected").text());
			$('.legend span.stock').text($('select.stock_selector').children("option:selected").text());

			chart.data.datasets.forEach(function(dataset) {

				var notTheStockOrTheAdjustedStock=true;

				if(dataset.id==stock_selected) {
					/* make original stock low opacity, but show */
					dataset.hidden=false;
					dataset.borderColor='rgba(43,222,115,1)';
					notTheStockOrTheAdjustedStock=false;
				}

				if(dataset.id==adjusted_selected+'_adj_'+stock_selected) {
					/* make adjusted stock high opacity, and show */
					dataset.hidden=false;
					dataset.borderColor='rgba(255,71,66,1)';
					notTheStockOrTheAdjustedStock=false;
				}

				// <new, show adjusted nominal values too as blue line>
					if(dataset.id==adjusted_selected+'_adjuster') {
						dataset.hidden=false;
						dataset.borderColor='rgba(66, 165, 255)';
						notTheStockOrTheAdjustedStock=false;
					}
				// </new, show adjusted nominal values too as blue line>

				
				if(notTheStockOrTheAdjustedStock) {
					dataset.hidden=true;
					/* dataset.borderColor='rgba(255,71,66,0.1)'; */
				}
			});

			/*chart.update();*/

			animateStockLine();
			updateVisibility();
			updateUrl();

		}

	</script>

	<div class="chart">
		<canvas id="chart" width="500" height="600"></canvas>
	</div>


	<script>
		
		var ctx = document.getElementById("chart").getContext('2d');
		var gradientRed = ctx.createLinearGradient(0, 0, 0, 600);
		gradientRed.addColorStop(0.5, 'rgba(255,71,66,0.125)');	 
		gradientRed.addColorStop(1, 'rgba(255,71,66,0)');

		var gradientGreen = ctx.createLinearGradient(0, 0, 0, 600);
		gradientGreen.addColorStop(0, 'rgba(43,222,115,0.25)');	 
		gradientGreen.addColorStop(1, 'rgba(43,222,115,0)');
		
		var gradientBlue = ctx.createLinearGradient(0, 0, 0, 600);
		gradientBlue.addColorStop(0, 'rgba(25,25,125,0.5)');	 
		gradientBlue.addColorStop(1, 'rgba(25,25,125,0)');

		var chart = new Chart(ctx, {
			type: 'line',
			data: {
				labels:
					[<?
						foreach($data as $row) {
							if(empty($row['epoch'])) {
								continue;
							}
							echo '"'.date('Y-m',$row['epoch']).'"';
							/*?>new Date(<?=$row['epoch']?>*1000)<?*/
							// echo $row['epoch'];
							echo ',';
						}
					?>],
					datasets: [
						
						<?
						foreach($stocks as $stock) {?>
							{
								<?if($_GET['layout']=='screenshot'){?>
									borderWidth:4,
								<?} else {?>
									borderWidth:2,
								<?}?>
								hidden:true,
					 	 		id:'<?=$stock?>',
								label: '<?=strtoupper($stock)?>',
								borderColor: '#2bde73',
								backgroundColor: gradientGreen,
								yAxisID:'main',
								fill: true,
								data: 
								[
									<?
										unset($previousValue);
										foreach($data as $row) {
											if(empty($row['epoch'])) {
												continue;
											}
											if(!$row[$stock]) {
												if(!empty($previousValue) && $doubleEmptyValueLimiter<2) { 
													/* if missing data show previous value to fill in, because =GOOGLEFINANCE sometimes randomly misses single dates */
													echo $previousValue;
													$doubleEmptyValueLimiter++;
												}
												echo ',';
												continue;
											}
											echo $row[$stock];
											echo ',';
											$previousValue=$row[$stock];
											unset($doubleEmptyValueLimiter);
										}
									?>
								],
							},<?
						}?>

						
						<?
						foreach($adjusteds as $adjusted) {?>
							{
								<?if($_GET['layout']=='screenshot'){?>
									borderWidth:4,
								<?} else {?>
									borderWidth:2,
								<?}?>
								hidden:true,
					 	 		id:'<?=$adjusted?>_adjuster',
								label: '<?=strtoupper($adjusted)?>',
								borderColor: '#42a5ff',
								backgroundColor: gradientBlue,
								yAxisID:'adjuster',
								fill: true,
								data: 
								[
									<?
										unset($previousValue);
										foreach($data as $row) {
											if(!$row[$adjusted]) {
												if(!empty($previousValue) && $doubleEmptyValueLimiter<2) { 
													/* if missing data show previous value to fill in, because =GOOGLEFINANCE sometimes randomly misses single dates */
													echo $previousValue;
													$doubleEmptyValueLimiter++;
												}
												echo ',';
												continue;
											}
											echo $row[$adjusted];
											echo ',';
											$previousValue=$row[$adjusted];
											unset($doubleEmptyValueLimiter);
										}
									?>
								],
							},<?
						}?>
						

						<?foreach($m_adjusteds as $m) {
							foreach($stocks as $stock) {?>
								{
									<?if($_GET['layout']=='screenshot'){?>
										borderWidth:4,
									<?} else {?>
										borderWidth:2,
									<?}?>
									hidden:true,
						 	 		id:'<?=$m?>_adj_<?=$stock?>',
									label: 'in <?=strtoupper($m)?>',
									yAxisID:'adjusted',
									borderColor: '#ff4742',
									backgroundColor: gradientRed,
									fill: true,
									data: 
									[
										<?
											unset($previousValue);
											$doubleEmptyValueLimiter=0;
											foreach($data as $row) {
												if(empty($row['epoch'])) {
													continue;
												}
												if(!$row[$m.'_adj_'.$stock]) {
													if(!empty($previousValue) && $doubleEmptyValueLimiter<2) {
														/* if missing data show previous value to fill in, because =GOOGLEFINANCE sometimes randomly misses single dates */
														echo $previousValue;
														$doubleEmptyValueLimiter++;
													}
													echo ',';
													continue;
												}
												echo $row[$m.'_adj_'.$stock];
												echo ',';
												$previousValue=$row[$m.'_adj_'.$stock];
												unset($doubleEmptyValueLimiter);
											}
										?>
									],
								},<?
							 	}
						}?>





					]
			},
			
				options: {
					elements:{point:{radius:0},line: {
						tension:0.1
					}},
					animation: false,
					responsive:true,
					maintainAspectRatio:false,
					legend: {
						display: false
					},
					tooltips: {
						mode:'x-axis',
						intersect:false,
						callbacks: {
							labelColor: function(tooltipItem, chart) {
								var id=chart.config.data.datasets[tooltipItem.datasetIndex].id;
								if(id==adjusted_selected+'_adj_'+stock_selected) {
									return {
										borderColor:'#ff4742',
										backgroundColor:'#ff4742',
									};
								}
								else if(id==stock_selected) {
									return {
										borderColor:'#2bde73',
										backgroundColor:'#2bde73',
									};
								}
								else if(id==adjusted_selected+'_adjuster') {
									return {
										borderColor:'#42a5ff',
										backgroundColor:'#42a5ff',
									};
								}
								else {
									return {
										borderColor:'#000',
										backgroundColor:'#000',
									};
								}
				            },
							label: function(tooltipItem, data) {
								var label = data.datasets[tooltipItem.datasetIndex].label || '';

								t=decimalify(tooltipItem.yLabel);

								if(label.indexOf('in ')>-1) {
									label = t+' '+stock_selected_label+' / '+adjusted_selected_label;
								}
								else if(label.toUpperCase()==stock_selected.toUpperCase()) {
									label = '$'+t+' '+stock_selected_label;
								}
								else if(label.toUpperCase()==adjusted_selected.toUpperCase()) {
									label = '$'+t+' '+adjusted_selected_label;
								}
								else {
									label = '$'+t+' '+label;
								}
								return label;
							}
						}
					},
					scales: {
						xAxes: [{
							type: 'time',
							time: {
								format:'YYYY-MM',
								tooltipFormat:'YYYY-MM'
							},
							position: "bottom",
							gridLines:{
								tickMarkLength: 1,
								color: '#252525',
								zeroLineColor: '#252525'
							},
							ticks: {
								autoSkip:true, 
								maxTicksLimit:30,
								padding:14,
								fontFamily:'Iosevka Web, monospace',
								fontColor:'rgb(186,186,186)',
								labelOffset: 18,
								callback: function(t) {
									// this draws the date labels 2020-01 etc. on the horizontal X axis, from the unix epoch timestamp
									// return date('Y-m',t);
									return t;
								}
							}
						}],
						yAxes: [
							{
								id:'main',
								<?if($logarithmic){?>
									type: 'logarithmic',
								<?} else {?>
									type: 'linear',
								<?}?>
								display:false,
								stacked:false,
								position:'left',
								gridLines:{
									tickMarkLength: 1,
									color: '#252525',
									zeroLineColor: '#252525'
								},
								ticks: {
									maxRotation: 0,
									autoSkip:true,
									maxTicksLimit:12,
									beginAtZero: false, 
									/*min: 0,*/
									max:1,
									padding:14,
									fontFamily:'Iosevka Web, monospace',
									// fontColor:'rgb(186,186,186)',
									fontColor:'#2bde73',
									fontSize: 12,
									callback: function(t) {
										// return t;
										return '$'+decimalify(t);
									}
								}
							},
							{
								<?if($logarithmic){?>
									type: 'logarithmic',
								<?} else {?>
									type: 'linear',
								<?}?>
								display:false,
								id:'adjusted',
								stacked:false,
								position:'right',
								gridLines:{
									tickMarkLength: 1,
									color: '#252525',
									zeroLineColor: '#252525'
								},
								ticks: {
									maxRotation: 0,
									autoSkip:true,
									maxTicksLimit:12,
									beginAtZero: false,
									// min: 0, 
									max:1,
									padding:14,
									fontFamily:'Iosevka Web, monospace',
									fontColor:'#ff4742',
									fontSize: 12,
									callback: function(t) {
										// return t;
										return decimalify(t);
									}
								}
							},
							{
								<?if($logarithmic){?>
									type: 'logarithmic',
								<?} else {?>
									type: 'linear',
								<?}?>
								display:false,
								id: 'adjuster',
								stacked:false,
								position:'right',
								gridLines:{
									tickMarkLength: 1,
									color: '#252525',
									zeroLineColor: '#252525'
								},
								ticks: {
									maxRotation: 0,
									autoSkip:true,
									maxTicksLimit:12,
									beginAtZero: false,
									// min: 0, 
									max:1,
									padding:14,
									fontFamily:'Iosevka Web, monospace',
									fontColor:'#42a5ff',
									fontSize: 12,
									callback: function(t) {
										// return t;
										return '$'+decimalify(t);
									}
								}
							}

						]
					}
				}
		});
		</script>
	</div>




	<div class="legend">



		<span class="show_stock_legend">
			<input type="checkbox" class="show_stock" <?if($show_stock){?>checked<?}?>> <span data-type="stock" style="color:rgb(43,222,115)"><!-- 🟢 --><span data-type="stock" class="stock"></span></span> <span class="mobile_line_break"></span>
		</span>
		<br/>



		<!-- <span class="hide_on_mobile">&nbsp;|&nbsp;</span> -->

		<span class="show_adjuster_legend">
			<span class="mobile_line_break"></span> <input type="checkbox" class="show_adjuster" <?if($show_adjuster){?>checked<?}?>> <span style="color:#42a5ff" data-type="adjuster"><!-- 🔵 --><span class="adjuster" data-type="adjuster"></span></span> <span class="mobile_line_break"></span>
		</span>
		<br/>




		<!-- <span class="hide_on_mobile">&nbsp;|&nbsp;</span> -->

		<span class="show_adjusted_legend">
			<span class="mobile_line_break"></span> <input type="checkbox" class="show_adjusted" <?if($show_adjusted){?>checked<?}?>> <span style="color:rgb(255,71,66)" data-type="adjusted"><!-- 🔴  --><span class="stock" data-type="adjusted"></span> / <span class="adjusted" data-type="adjusted"></span></span> <span class="mobile_line_break"></span>
		</span>
		<br/>




		<!-- <span class="hide_on_mobile">&nbsp;|&nbsp;</span> -->

		<span class="logarithmic_legend">
			<span class="mobile_line_break"></span> <input type="checkbox" class="logarithmic" <?if($logarithmic){?>checked<?}?>> <span data-type="logarithmic">📐Logarithmic</span>
		</span>
		<br/>


	</div>



	<div class="side">
		<!-- <div class="youtube-wrapper">
			<iframe style="margin-bottom:-5px" width="100%" height="200" src="https://www.youtube.com/embed/W41vsTO2GHY?autoplay=1&controls=0&mute=1" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
		</div> -->
		<p>
			<strong style="color:#fff;">
				Most popular pages (via <a href="https://simpleanalytics.com/m1chart.com">Simple Analytics</a>)
			</strong><br/>
			<a href="/sp500-in-m1">S&P500 in M1</a><br/>
			<a href="/income-in-food">Avg US Income in Food</a><br/>
			<a href="/income-in-home">Avg US Income in Avg US Home Price</a><br/>
			<a href="/sp500-in-income">S&P500 in Avg US Income</a><br/>

			<a href="/sp500-in-btc?logarithmic=1">S&P500 in BTC</a><br/>
			<a href="/sp500-in-oil?logarithmic=1">S&P500 in Oil</a><br/>
			<a href="/sp500-in-gold?logarithmic=1">S&P500 in Gold</a><br/>
			<a href="/btc-in-m1">BTC in M1</a><br/>
			<a href="/china-in-btc?logarithmic=1">China SSE in BTC</a><br/>
			<a href="/food-in-btc?logarithmic=1">Food in BTC</a><br/>
			<a href="/bigmac-in-btc?logarithmic=1">Big Mac in BTC</a><br/>
			<a href="/dji-in-btc?logarithmic=1">DJI in BTC</a><br/>
			<a href="/gold-in-btc?logarithmic=1">Gold in BTC</a><br/>
			<a href="/btc-in-gold">BTC in Gold</a><br/>

<?/*
			<a href="/income-in-food_and_home">Avg US Income in Food + Avg US Home Price</a><br/>
			<a href="/income-in-bigmac">Avg US Income in Big Macs</a><br/>

			<a href="/home-in-m1">Avg US Home in M1</a><br/>
			<a href="/income-in-btc?logarithmic=1">Avg US Income in BTC</a><br/>
			<a href="/nasdaq-in-btc?logarithmic=1">NASDAQ in BTC</a><br/>
			<a href="/tsla-in-btc?logarithmic=1">TLSA in BTC</a><br/>
			<a href="/gdp-in-btc?logarithmic=1">GDP in BTC</a><br/>

			<a href="/sp500-in-bigmac">S&P500 in Big Macs</a><br/>
			<a href="/china-in-food">China SSE in Food</a><br/>
			<a href="/china-in-food_and_home">S&P500 in Food + Avg US Home</a><br/>
			
			<a href="/home-in-food">Avg US Home in Food</a><br/>
			<a href="/home-in-bigmac">Avg US Home in Big Macs</a><br/>
			<a href="/bigmac-in-cpi">Big Mac in CPI</a><br/>
			<a href="/bigmac-in-m1">Big Mac in M1</a><br/>
			<a href="/silver-in-gold">Silver in Gold</a><br/>
			
			*/?>
		</p>

		<!-- <div class="fed-wrapper">
			<img src="/assets/fed3.gif?<?=filemtime(__DIR__.'/../assets/fed3.gif');?>" class="fed" />
		</div> -->
		<p>
			<strong style="color:#fff;">
				What is this?
			</strong><br/>

			📈 This chart shows 🟢<span style="color:rgb(43,222,115)">the nominal price</span> vs. 🔴<span style="color:rgb(255,71,66)">the real value</span> (as adjusted for inflation) of the stock market (or another value you select). You can inflation-adjust it by the U.S.-dollar money supply M1, M2 or MB (the money base), CPI, Big Mac, Gold, BTC and ETH.
		</p>
		<p>
			📉 Combining data sets we can adjust the stock market and home prices with the actual money supply, and find that even if <span style="color:rgb(43,222,115)">it looks like stock markets and home prices are going up</span>, they <span style="color:rgb(255,71,66)">may actually be going down in real value</span>. 
		</p>

	

		<?/*<p>
			<strong style="color:#fff;">
				Explain like I'm 5 years old?
			</strong><br/>
			<strong>
				💣 TL;DR your money is getting worth less over time, and recently faster than before and this site provides evidence for it.
			</strong>
		</p>
		<p>
			👶 Let's start: select [M1-adjusted] [S&P500] in [last 20 years] first on top. M1 is the money base, which means all $ in circulation. S&P500 is the most important stock market index of 500 big American companies.
		</p>
		<p>
			The 🟢green line is the actual price of the S&P500 in history up to today. The 🔴red line converges to the same price the closer we get to the past (the left end of the chart). 
		</p>
		<p>
			But if you go forward in time (the right end of the chart) the lines start dispersing. The price of the S&P500 in the year 2000 is ~$1,400. But adjusted by the <a href="https://fred.stlouisfed.org/series/BOGMBASE">money base (MB)</a>, it is ~$400 in today's prices, or ~3x less. That's because in those 20 years, the MB grew by 8x. For every 1 dollar that was in existence in the year 2000, there's now 8 dollars. You could then expect the value of the S&P500 to also grow by at least 8x. That'd mean the value stayed at least the same. But the S&P500 only grew by 2.6x. 8x divided by 2.6x means there's been a decrease in real value of the S&P500 about ~3x (if adjusted by the money base, MB). That 3x is the same as the 3x we found comparing the price of the S&P500 in the year 2000 and the MB-adjusted price today.
		</p>
		<p>
			That decrease in real value is visible in the chart at specific moments. Look at the 🔴red line in 2008, when there was the <a href="https://en.wikipedia.org/wiki/Financial_crisis_of_2007%E2%80%932008">Financial Crisis</a> and at 2020 when the <a href="https://en.wikipedia.org/wiki/COVID-19_pandemic">COVID-19 Pandemic</a> started. That's moments when the Federal Reserve and other central banks started printing lots of money from thin air. You don't see that in the 🟢green line as that's the official prices. That doesn't mean it's some conspiracy. It just means the nominal/official prices of stock markets and stocks don't tell the whole story of the economy is actually growing or not.
		</p>
		<p>
			There's more indicators you can adjust by then just the money base. Try a few by clicking on the [-adjusted] select box top left and changing it. You can also change what you'd like to adjust by clicking the second select box. And you can change the time view with the third box. Happy researching!
		</p>*/?>
		<p>
			<strong style="color:#fff;">
				What data you use?
			</strong><br/>

			🇺🇸 S&P500, Dow Jones (DJI) and NASDAQ are the most common stock market indices, representing the performance of the United States, but in a way are so important they're quite benchmark of the West and the entire globe. Investors use these indices (plural of index) as a benchmark of the overall market conditions. The NASDAQ index especially is heavily weighted towards tech.<?/* Historical data for these is from <a href="https://google.com/finance">Google Finance</a>.*/?>
		</p>
		<p>
			🇨🇳 China SSE is the Shanghai Stock Exchange Composite Index, a.k.a. the main stock market of China. It's converted to USD with the CNY:USD rate of the historical date of each data point.<?/* Data is from <a href="https://finance.yahoo.com/quote/000001.SS/">Yahoo Finance</a>.*/?>
		</p>
		<p>
			🌏 Asia is the MSCI Asia (ex-Japan) index (in USD), which is a benchmark of over 1,000+ of the most important public companies all over Asia.
		</p>
		<p>
			💰 US GDP is the <a href="https://fred.stlouisfed.org/series/GDP">gross domestic product of the U.S.</a>.
		</p>
		<p>
			💰 Avg US Income is the <a href="https://fred.stlouisfed.org/series/MEHOINUSA646N">annual median U.S. household income</a>.
		</p>
		<p>
			🏆 Gold price (aka XAU) is from <a href="https://www.indexmundi.com/commodities/?commodity=gold&months=360">IndexMundi</a>. 
		</p>
		<p>
			🥇 BTC/ETH and $TSLA prices are from <a href="https://google.com/finance">Google Finance</a>.
		</p>
		<p>
			🛒 CPI is the consumer price index, a basket of goods (like milk, bread, meat etc.) that's commonly used as the official inflation number. It's heavily criticized though for underreporting actual inflation.
		</p>
		<p>
			🍔 Big Mac measures the average price of a Big Mac at McDonald's in the United States and is famously used in <a href="https://www.economist.com/big-mac-index">the Economist's Big Mac Index</a> to measure inflation.
		</p>
		<p>
			🥩 Food represents the <a href="http://www.fao.org/worldfoodsituation/foodpricesindex/en/">Food Price Index (FPI)</a> by the United Nations, a measure of the international prices of a basket of 5 food commodities which are: sugar, cereals, vegetable oils, meat and dairy.
		</p>
		<p>
			🏡 Avg US Home is the <a href="https://www.nar.realtor/topics/existing-home-sales">median U.S. single-family home price</a>, with historical data from <a href="https://dqydj.com/historical-home-prices/">DQYDJ</a>.
		</p>
		<p>
			🥩 Food + Avg US Home is a combination I made of the (global) Food Price Index (FPI) and the median U.S. single-family home price. Used as a benchmark of how much it costs to live. Caveat here is that while the food prices are worldwide, the home price is U.S.
		</p>
		<p>
			👩‍💻 Pop. is the world population from <a href="https://data.worldbank.org/indicator/SP.POP.TOTL">World Bank</a>. Population is in billions.
		</p>
		<?/*<div class="tweet-wrapper">
			<blockquote class="twitter-tweet" data-theme="dark"><p lang="en" dir="ltr">Praet: As a central bank, we can create money to buy assets <a href="https://twitter.com/hashtag/AskECB?src=hash&amp;ref_src=twsrc%5Etfw">#AskECB</a> <a href="https://t.co/zTQuU4y1ch">https://t.co/zTQuU4y1ch</a></p>&mdash; European Central Bank (@ecb) <a href="https://twitter.com/ecb/status/1105494215381913601?ref_src=twsrc%5Etfw">March 12, 2019</a></blockquote> <script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>
		</div>*/?>
		<p>
			🖨 As the Federal Reserve is printing money, it's expected that the real value of each US dollar decreases (called inflation). To estimate how much money is printed, I use <a href="https://fred.stlouisfed.org/series/M1">the Fed's M1, M2 and MB money supply data</a>. M1 is a measure of the money supply that includes physical currency and bank accounts. M2 is the same but also includes savings accounts (heavily simplified). The money base (MB) is the total amount of a currency that is either in general circulation in the hands of the public or in the commercial bank deposits held in the central bank's reserves. MB, M1 and M2 is in billions.
		</p>
		<p>
			❌ Caveats: this isn't financial advice and MB, M1 and M2 are limited measures of the money supply. That there's growing inflation due to printing of money I think we can all agree on though. I hope this site helps to visualize this a bit.
		</p>
		<p>
			🧨 <span class="quote">"The end game of rampant inflation is always war and/or revolution. Show me a regime change, and I will show you inflation. When you work your ass off only to stand still or get poorer, any “ism” that promises affordable food and shelter for the unwashed masses will reign supreme. If you are starving to death, nothing else matters except feeding your family. The symptoms of inflation are populism, social strife, food riots, high and rising financial asset prices, and income inequality. (..) Invest wisely and you can maintain or increase your standard of life against the rising fiat cost of energy. Invest poorly and the road to serfdom is real. You will find yourself working harder for a declining standard of living, and your fiat earnings and assets will not be able to keep up with the rising fiat cost of energy."</span> &mdash; <a href="https://blog.bitmex.com/pumping-iron/">Arthur Hayes</a>
		</p>
		<?/*<p>
			Then again the Fed doesn't agree:
		</p>
		<div class="youtube-wrapper">
			<iframe width="274" height="250" src="https://www.youtube.com/embed/SGNyCOlIEHY" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
		</div>*/?>
		<p>
			💬 <a href="https://news.ycombinator.com/item?id=26128388">Hacker News</a> has opinions about this site
		</p>
		<p>
			✨ Last updated: <?=date('Y-m-d',filemtime('index.php'))?>. 
		</p>
		<p>
			🧠 The database behind this is an open <a href="https://docs.google.com/spreadsheets/d/1xJGrHWj6uO6ykFPvht-RBG5qlLeO0axraxUJ9UzOhFo/edit?usp=sharing">Google Sheet</a> you can view. If you see any problems/bugs/errors with it, please let me know on Twitter below!
		</p>
		<p>
			👨‍🎨 Made by <a href="https://twitter.com/levelsio">@levelsio</a> (if you like it, tweet me a fun msg 😊). Inspired by <a href="https://stonksinbtc.xyz?ref=m1chart.com">Stonks in BTC</a> by <a href="https://twitter.com/dannyaziz97">Danny Aziz</a>, and <a href="https://cryptowat.ch">Cryptowatch</a>'s layout.
			
		</p>
	</div>
</div>
</html><?

function sanitizeOutput($buffer) {
	$search = array(
		'/\>[\S ]+/s',	// strip whitespaces after tags, except space
		'/[\S ]+\</s',	// strip whitespaces before tags, except space
		'/(\s)+/s'		 // shorten multiple whitespace sequences
	);
	$replace = array(
		'>',
		'<',
		'\\1'
	);
	$buffer = preg_replace($search, $replace, $buffer);
	return $buffer;
}
function curl_get_contents ($url) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$output = curl_exec($ch);
		curl_close($ch);
		return $output;
}

?>
