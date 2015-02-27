<?php
ini_set("display_error", true);
error_reporting(E_ALL);
set_time_limit(0);

define ('COOKIE', 'COOKIE HERE');
define ('USERAGENT', 'Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/37.0.2017.2 Safari/537.36');
define ('TIEBA_ID', "TIEBA账号")

$tbsList = array(
);
/*
if ($_GET['del'] != 'zsx!') {
exit;
}
$cookie_list = explode(";", COOKIE);
foreach ($cookie_list as $name => $value) {
$cookie = explode("=", $value);
//var_dump($cookie[0]); //exit;
setcookie(trim($cookie[0]), $cookie[1], time() + 7200, "/", "baidu.com");
}

exit;
 */
ob_end_flush();
print str_repeat(" ", 4096);
?>
<!doctype html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="stylesheet" href="http://cdn.bootcss.com/twitter-bootstrap/3.0.3/css/bootstrap.min.css">
	<link href="http://v3.bootcss.com/docs-assets/css/docs.css" rel="stylesheet">
	<title>删帖</title>
</head>

<body>
	<div class="navbar navbar-default navbar-fixed-top" role="navigation">
		<div class="container">
			<div class="navbar-header">
				<button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
					<span class="sr-only">切换</span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
				</button>
				<a class="navbar-brand" href="#">贴吧删帖器</a>
			</div>
			<div class="navbar-collapse collapse">
				<ul class="nav navbar-nav">
					<li class="active">
						<a href="#">本页</a>
					</li>
				</ul>
			</div>
			<!--/.nav-collapse -->
		</div>
	</div>
	<br/>
	<div class="container">
		<div class="jumbotron">
<?php
search_thread();
ob_end_flush();
?>
		</div>
	</div>
</body>
</html>
<?php
$MOBILE = false;

function delete_thread($tid, $pid, $fid, $tieba, $title) {
	global $MOBILE;
	if (!$MOBILE) {
		view_thread($tid, $pid, $fid, $tieba, $title, get_tbs());
	} else if (view_thread_mobile($tid, $pid, $fid, $tieba, $title, get_tbs())) {
		output_data('', '', '今日帖子已达到删除上限！', 'info');
		return false;
	}
	return true;
}

function search_thread() {
	get_tbs(true);

	while (true) {
		$data = get_search_list();
		foreach ($data as $name => $ser) {
			if (checkIgnore($ser)) {
				continue;
			}
			delete_thread($ser[0], $ser[1], $ser[2], $ser[3], $ser[3] . " - " . $ser[4]);
		}

	}
	return true;
}

function checkIgnore($data) {
	$ignore = array('353940'); //百度MP3试听
	if (array_search($data[3], $ignore)) {
		return true;
	}

	return false;
}
/*
 */
function get_search_list() {
	$url = "http://tieba.baidu.com/f/search/ures?ie=utf-8&kw=&qw=&rn=30&un=" . TIEBA_ID . "&sm=0";
	$n = new network;
	$n->open("GET", $url);
	$n->setRequestHeader("Cookie", COOKIE);
	$n->setRequestHeader("Content-Type", "charset=UTF-8");
	$n->setRequestHeader("User-Agent", USERAGENT);
	$n->send();
	$return = $n->responseText;
	$return = iconv('gbk', 'utf-8', $return);
	$matches = array();
	preg_match_all("/href=\"(\/p\/(\d+)\?pid=(\d+)\&cid=#(\d+))\".+?>(.+?)<\/a>.+?p_violet\">(.+?)<\/font>/i", $return, $matches);

	$ret = array();
	if (count($matches[1]) > 0) {
		for ($i = 0; $i < count($matches[1]); $i++) {
			$ret[] = array($matches[2][$i], $matches[3][$i], get_fid($matches[6][$i]), $matches[6][$i], $matches[5][$i]);
		}
	}
	return $ret;
	//var_dump($ret);
	//exit;
}

function get_fid($tieba_name) {
	global $tbsList;
	if (isset($tbsList[$tieba_name])) {
		return $tbsList[$tieba_name];
	}
	$url = "http://tieba.baidu.com/f?ie=utf-8&kw=" . urlencode($tieba_name);
	$n = new network;
	$n->open("GET", $url);
	$n->setRequestHeader("Cookie", COOKIE);
	$n->setRequestHeader("Content-Type", "charset=UTF-8");
	$n->setRequestHeader("User-Agent", USERAGENT);
	$n->send();
	$return = $n->responseText;
	//$return = iconv('gbk', 'utf-8', $return);
	$match = array();
	preg_match("/forum_id\":(\d+)/", $return, $match);
	if (count($match) > 0) {
		$tbsList[$tieba_name] = $match[1];
		output_data('', '', "得到【" . $tieba_name . '】贴吧TID = ' . $match[1], 'info');
	}
	return $tbsList[$tieba_name];
}

function get_tbs($first_run = FALSE) {
	$n = new network;
	$n->open("GET", "http://tieba.baidu.com/dc/common/tbs");
	$n->setRequestHeader("Cookie", COOKIE);
	$n->setRequestHeader("User-Agent", USERAGENT);
	$n->send();
	$return_array = json_decode($n->responseText);
	if ($first_run) {
		if ($return_array->is_login == 1) {
			output_data('', '', 'Cookie验证成功，状态已登录。', 'info');
		} else {
			output_data('', '', 'Cookie验证失败！', 'info');
			exit;
		}
	} else {
		output_data('', '', '临时tbs获取成功：' . $return_array->tbs, 'info');
	}
	return $return_array->tbs;
}

function view_thread($tid, $ppid, $fid, $tieba, $title, $tbs) {

	$post_params = array(
		"ie" => "utf-8",
		"tbs" => $tbs,
		"kw" => $tieba,
		"fid" => $fid,
		"tid" => $tid,
		"pid" => $ppid,
		"user_name" => TIEBA_ID,
		"delete_my_post" => 1,
		"delete_my_thread" => 0,
		"is_vipdel" => 1,
		"is_finf" => "false",
	);

	if (preg_match("/回复/si", $title)) {
		$post_params["is_vipdel"] = 0;
	}

	$n = null;
	$n = new network;
	$n->open("POST", "http://tieba.baidu.com/f/commit/post/delete");
	$n->setRequestHeader("Cookie", COOKIE);
	$n->setRequestHeader("User-Agent", USERAGENT);
	$n->setRequestHeader("Content-Type", "application/x-www-form-urlencoded; charset=UTF-8");
	$n->setRequestHeader("Origin", "http://tieba.baidu.com");
	$n->send(http_build_query($post_params));
	@$return_array = json_decode($n->responseText);
	output_data($title, "", $n->responseText, 'pc');

	if (is_object($return_array)) {
		if ($return_array->no > 0) {
			global $MOBILE;
			$MOBILE = true;
			view_thread_mobile($tid, $ppid, $fid, $tieba, $title, $tbs);
			return true;
		}
	}

	return false;
}

function view_thread_mobile($tid, $ppid, $fid, $tieba, $title, $tbs) {

	$post_params = array(
		"ntn" => "bdPLW",
		"tn" => "baiduManagerSubmit",
		"delete_my_post" => 1,
		"tbs" => $tbs,
		"z" => $tid,
		"fid" => $fid,
		"word" => $tieba,
		"lp" => 6076,
		"pid" => $ppid,
		"sc" => $ppid,
		"lm" => $fid,
		"is_vipdel" => 1,
	);

	if (preg_match("/回复/si", $title)) {
		$post_params["is_vipdel"] = 0;
	}

	$n = null;
	$n = new network;
	$n->open("GET", "http://tieba.baidu.com/mo/q/m?&" . http_build_query($post_params));
	$n->setRequestHeader("Cookie", COOKIE);
	$n->setRequestHeader("User-Agent", USERAGENT);
	$n->setRequestHeader("Content-Type", "application/x-www-form-urlencoded; charset=UTF-8");
	$n->setRequestHeader("Origin", "http://tieba.baidu.com");
	$n->send();
	@$return_array = json_decode($n->responseText);
	output_data($title, '', $n->responseText, 'mobile');

	if (is_object($return_array)) {
		if ($return_array->no === 0) {
			return false;
		}
	}

	return true;
}

function output_data($title, $url, $responseText, $del_type) {

	if ($del_type == 'mobile' || $del_type == 'pc') {
		echo '<div class="row">	';
		echo '	<div class="col-md-8">' . $title . '</div>';
		echo '</div>';
		echo '<div class="bs-callout bs-callout-danger">	<p>' . $responseText . '</p></div>';
		//echo '</div>';
	} else if ($del_type == 'info') {
		echo '<div class="bs-callout bs-callout-warning"><p>' . $responseText . '</p></div>';
	}

	flush();
	//ob_flush();
}

?>
<?php

class network {

	private $readyState = 0; #状态
	private $responseBody = NULL; #返回的二进制
	private $responseStream = NULL; #返回的数据流
	private $responseText = ''; #返回的数据
	private $responseXML = NULL; #尝试把responseText格式化为XMLDom
	private $status = 0; #状态码
	private $statusText = ''; #状态码文本
	private $responseVersion = ''; #返回的HTTP版体

	private $option = array();
	private $url = '';
	private $postdata = array();
	private $httpheader = array();
	private $responseHeader = array();
	private $isgzip = false;
	private $maxredirs = 0;
	private $parsed_url = array();

	/**
	 * @param $property_name
	 * @param $value
	 * @throws Exception
	 */
	public function __set($property_name, $value) {
		throw new Exception($property_name . ' readonly');
	}

	/**
	 * @param $property_name
	 * @return mixed
	 */
	public function __get($property_name) {
		if (strtolower($property_name) == 'responsexml') {
			$w = new DOMDocument();
			return $w->
			loadXML($this->responseText);
		} elseif (strtolower($property_name) == 'scheme' ||
			strtolower($property_name) == 'host' ||
			strtolower($property_name) == 'port' ||
			strtolower($property_name) == 'user' ||
			strtolower($property_name) == 'pass' ||
			strtolower($property_name) == 'path' ||
			strtolower($property_name) == 'query' ||
			strtolower($property_name) == 'fragment') {
			if (isset($this->parsed_url[strtolower($property_name)])) {
				return $this->parsed_url[strtolower($property_name)];
			}

		} else {
			return $this->$property_name;
		}
	}

	/**
	 *
	 */
	public function abort() {

	}

	/**
	 * @return string
	 */
	public function getAllResponseHeaders() {
		return implode("\r\n", $this->responseHeader);
	}

	/**
	 * @param $bstrHeader
	 * @return string
	 */
	public function getResponseHeader($bstrHeader) {
		$name = strtolower($bstrHeader);
		foreach ($this->responseHeader as $w) {
			if (strtolower(substr($w, 0, strpos($w, ':'))) == $name) {
				return substr(strstr($w, ': '), 2);
			}
		}
		return '';
	}

	/**
	 * @param $resolveTimeout
	 * @param $connectTimeout
	 * @param $sendTimeout
	 * @param $receiveTimeout
	 */
	public function setTimeOuts($resolveTimeout, $connectTimeout, $sendTimeout, $receiveTimeout) {

	}

	/**
	 * @param $bstrMethod
	 * @param $bstrUrl
	 * @param bool $varAsync
	 * @param string $bstrUser
	 * @param string $bstrPassword
	 * @return bool
	 * @throws Exception
	 */
	public function open($bstrMethod, $bstrUrl, $varAsync = true, $bstrUser = '', $bstrPassword = '') {
		//Async无用
		//初始化变量
		$this->reinit();
		$method = strtoupper($bstrMethod);
		$this->option['method'] = $method;
		$this->parsed_url = parse_url($bstrUrl);

		if (!$this->parsed_url) {
			throw new Exception('URL Syntax Error!');
		} else {
			if ($bstrUser != '') {
				$bstrUrl = substr($bstrUrl, 0, strpos($bstrUrl, ':')) . '://' . $bstrUser . ':' . $bstrPassword . '@' . substr($bstrUrl, strpos($bstrUrl, '/') + 2);
			}
			$this->url = $bstrUrl;
			if (!isset($this->parsed_url['port'])) {
				if ($this->parsed_url['scheme'] == 'https') {
					$this->parsed_url['port'] = 443;
				} else {
					$this->parsed_url['port'] = 80;
				}
			}
		}

		return true;
	}

	/**
	 * @param string $varBody
	 */
	public function send($varBody = '') {
		$data = $varBody;
		if (is_array($data)) {
			$data = http_build_query($data);
		}

		if ($this->option['method'] == 'POST') {

			if ($data == '') {
				$data = http_build_query($this->postdata);
			}
			$this->option['content'] = $data;

			$this->httpheader[] = 'Content-Type: application/x-www-form-urlencoded';
			$this->httpheader[] = 'Content-Length: ' . strlen($data);

		}

		$this->option['header'] = implode("\r\n", $this->httpheader);
		//$this->httpheader[] = 'Referer: ' . 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];

		if ($this->maxredirs > 0) {
			$this->option['follow_location'] = 1;
			$this->option['max_redirects'] = $this->maxredirs;
		} else {
			$this->option['follow_location'] = 0;
			$this->option['max_redirects'] = 0;
		}

		$http_response_header = null;
		$this->responseText = file_get_contents(($this->isgzip == true ? 'compress.zlib://' : '') . $this->url, false, stream_context_create(array('http' => $this->option)));

		$this->responseHeader = $http_response_header;

		if (isset($this->responseHeader[0])) {
			$this->statusText = $this->responseHeader[0];
			$a = explode(' ', $this->statusText);
			if (isset($a[0])) {
				$this->responseVersion = $a[0];
			}

			if (isset($a[1])) {
				$this->status = $a[1];
			}

			unset($this->responseHeader[0]);
		}

	}

	/**
	 * @param $bstrHeader
	 * @param $bstrValue
	 * @param bool $append
	 * @return bool
	 */
	public function setRequestHeader($bstrHeader, $bstrValue, $append = false) {
		if ($append == false) {
			$this->httpheader[$bstrHeader] = $bstrHeader . ': ' . $bstrValue;
		} else {
			if (isset($this->httpheader[$bstrHeader])) {
				$this->httpheader[$bstrHeader] = $this->httpheader[$bstrHeader] . $bstrValue;
			} else {
				$this->httpheader[$bstrHeader] = $bstrHeader . ': ' . $bstrValue;
			}
		}
		return true;
	}

	/**
	 * @param $bstrItem
	 * @param $bstrValue
	 */
	public function add_postdata($bstrItem, $bstrValue) {
		array_push($this->postdata, array(
			$bstrItem => $bstrValue,
		));
	}

	/**
	 *
	 */
	private function reinit() {
		$this->readyState = 0; #状态
		$this->responseBody = NULL; #返回的二进制
		$this->responseStream = NULL; #返回的数据流
		$this->responseText = ''; #返回的数据
		$this->responseXML = NULL; #尝试把responseText格式化为XMLDom
		$this->status = 0; #状态码
		$this->statusText = ''; #状态码文本

		$this->option = array();
		$this->url = '';
		$this->postdata = array();
		$this->httpheader = array();
		$this->responseHeader = array();
		$this->setRequestHeader('User-Agent', 'this-is-zsx');
		$this->setMaxRedirs(2);
	}

	/**
	 * 启用Gzip
	 */
	public function enableGzip() {
		if (extension_loaded('zlib')) {
			$this->isgzip = true;
		}
	}

	/**
	 * @param int $n
	 */
	public function setMaxRedirs($n = 0) {
		$this->maxredirs = $n;
	}
}

?>