<?php

/**
 *  MyCurl - A portable cURL engine
 *
 *  MyCurl allows anyone to be able to use cURL, even if it is not installed
 *  on their server.  MyCurl is a OO version of cURL that uses fsockopen
 *  and other native functions to PHP to acheive the same results as
 *  an installed version of cURL would provide.
 *
 *  @author Justin Rainbow <justin@lazywebmastertools.com>
 *  @version v0.01-5 - 03/05/2007 09:27
 */ 
 
if (!function_exists('curl_init')): #used to avoid duplicate function errors

define('CURLOPT_URL',            0x00001);
define('CURLOPT_USERAGENT',      0x00002);
define('CURLOPT_POST',           0x00004);
define('CURLOPT_POSTFIELDS',     0x00008);
define('CURLOPT_RETURNTRANSFER', 0x00016);
define('CURLOPT_REFERER',        0x00032);
define('CURLOPT_HEADER',         0x00064);
define('CURLOPT_NOBODY',         0x00065);
define('CURLOPT_TIMEOUT',        0x00128);
define('CURLOPT_FOLLOWLOCATION', 0x00256);
define('CURLOPT_AUTOREFERER',    0x00512);
define('CURLOPT_PROXY',          0x01024);
define('CURLOPT_HTTPHEADER',     0x02048);

// curlinfo constants
define('CURLINFO_EFFECTIVE_URL',           0x000001); //Last effective URL
define('CURLINFO_HTTP_CODE',               0x000002); //Last received HTTP code
define('CURLINFO_FILETIME',                0x000003); //Remote time of the retrieved document, if -1 is returned the time of the document is unknown
define('CURLINFO_TOTAL_TIME',              0x000004); //Total transaction time in seconds for last transfer
define('CURLINFO_NAMELOOKUP_TIME',         0x000005); //Time in seconds until name resolving was complete
define('CURLINFO_CONNECT_TIME',            0x000006); //Time in seconds it took to establish the connection
define('CURLINFO_PRETRANSFER_TIME',        0x000007); //Time in seconds from start until just before file transfer begins
define('CURLINFO_STARTTRANSFER_TIME',      0x000008); //Time in seconds until the first byte is about to be transferred
define('CURLINFO_REDIRECT_TIME',           0x000009); //Time in seconds of all redirection steps before final transaction was started
define('CURLINFO_SIZE_UPLOAD',             0x000010); //Total number of bytes uploaded
define('CURLINFO_SIZE_DOWNLOAD',           0x000011); //Total number of bytes downloaded
define('CURLINFO_SPEED_DOWNLOAD',          0x000012); //Average download speed
define('CURLINFO_SPEED_UPLOAD',            0x000013); //Average upload speed
define('CURLINFO_HEADER_SIZE',             0x000014); //Total size of all headers received
define('CURLINFO_HEADER_OUT',              0x000015); //The request string sent. Available since PHP 6.0.0
define('CURLINFO_REQUEST_SIZE',            0x000016); //Total size of issued requests, currently only for HTTP requests
define('CURLINFO_SSL_VERIFYRESULT',        0x000017); //Result of SSL certification verification requested by setting CURLOPT_SSL_VERIFYPEER
define('CURLINFO_CONTENT_LENGTH_DOWNLOAD', 0x000018); //content-length of download, read from Content-Length: field
define('CURLINFO_CONTENT_LENGTH_UPLOAD',   0x000019); //Specified size of upload
define('CURLINFO_CONTENT_TYPE',            0x000020); //Content-type of downloaded object, NULL indicates server did not send valid Content-Type: header 
define('CURLINFO_REDIRECT_COUNT',          0x000021); //Number of redirections before final transaction

function curl_init( $url = false )
{
	return new MyCurl( $url );
}

function curl_setopt( &$ch, $name, $value )
{
	$ch->setopt($name, $value);
}

function curl_exec( $ch )
{
	return $ch->exec();
}

function curl_getinfo( $ch, $opt = false )
{
	return $ch->getinfo( $opt );	
}

function curl_close( &$ch )
{
	$ch = false;
}

endif;

function mycurl_init($url = false)
{
	return new MyCurl($url);
}

function mycurl_setopt(&$ch, $name, $value)
{
	$ch->setopt($name, $value);
}

function mycurl_exec($ch)
{
	return $ch->exec();
}

function mycurl_close(&$ch)
{
	$ch = false;
}


class MyCurl
{
	var $url = "";
	var $user_agent = "MyCurl v0.01 (http://lazywebmastertools.com/mycurl)";
	var $return_result = false;
	var $referrer = false;
	var $cookies_on = false;
	var $timer = array();
	var $redirction_steps = 0;

	var $content;
	
	var $timeout = 30;
	
	var $cookies;
	var $headers;
	var $method = "GET";

	/**
	 * headers
	 */
	var $last_modified;
	var $content_type;
	var $status;
	
	function MyCurl( $url = false )
	{
		$this->cookies = new myCurl_Cookies();
		$this->url = $url;
	}
	
	function setopt($name, $value = false)
	{
		switch ($name)
		{
			case CURLOPT_URL:
				$this->url = $value; break;
				
			case CURLOPT_USERAGENT:
				$this->user_agent = $value; break;
				
			case CURLOPT_POST:
				$this->method = ($value == true) ? "POST" : "GET"; break;
				
			case CURLOPT_POSTFIELDS:
				$this->post_data = $value; break;
				
			case CURLOPT_RETURNTRANSFER:
				$this->return_result = ($value == true); break;
				
			case CURLOPT_REFERER:
				$this->referrer = $value; break;
				
			case CURLOPT_HEADER:
				$this->options["header"] = ($value == true); break;
				
			case CURLOPT_HEADER:
				$this->options["nobody"] = ($value == true); break;
				
			case CURLOPT_HTTPHEADER:
				$this->header = $value; break;
				
			case CURLOPT_PROXY:
				list($this->proxy["host"], $this->proxy["port"]) = explode(":", $value);
				break;
				
			case CURLOPT_TIMEOUT:
				$this->timeout = ($value >= 0) ? $value : 30;
		}
	}
	
	function exec()
	{
		$errno = false;
		$errstr = false;
		$url = $this->url;
		$this->timer["start"] = time();
		
		$host = $this->get_host($url);
		$query = $this->get_query($url);
		
		$this->lookup_timer["start"] = time();
		
		if ($this->proxy["host"]) {
			$fp = fsockopen($this->proxy["host"], $this->proxy["port"], $errno, $errstr, $this->timeout);
			$request = $url;
		} else {
			$fp = fsockopen($host, 80, $errno, $errstr, $this->timeout);
			$request = $query;
		}
		
		$this->lookup_timer["end"] = time();
		
		if (!$fp) {
			trigger_error($errstr, E_WARNING);
			return;	
		}
		
		$headers =  $this->method . " $request HTTP/1.0\r\n";
		$headers .= "HOST: $host\r\n";
		
		if ($this->user_agent && isset($headers["User-Agent"]))
			$headers .= "User-Agent: " . $this->user_agent . "\r\n";
			
		if ($this->referrer)
			$headers .= "Referer: " . $this->referrer . "\r\n";
			
		if ( is_array( $this->header) )
		{
			foreach($this->header as $key => $value)
			{
				$headers .= "$key: $value\r\n";
			}
		}
			
		if ($this->method == "POST") {
			$headers .= "Content-Type: application/x-www-form-urlencoded\r\n";
			$headers .= "Content-Length: " . strlen($this->post_data) . "\r\n";
		}
		
		if ($this->cookies_on)
			$headers .= $this->cookies->create_header();
		
		$headers .= "Connection: Close\r\n\r\n";
		
		if ($this->method == "POST")
			$headers .= $this->post_data;
		
		$headers .= "\r\n\r\n";
		
		$this->last_header = $headers;
		
		fwrite($fp, $headers);
		
		$raw_data = "";
		
		while(!feof($fp)) {
			$raw_data .= fread($fp, 512);
		}
		fclose($fp);
		
		$this->_parse_raw_data($raw_data);
				
		if ($this->options["header"])
			$this->content = $raw_data;
		
		$this->timer["end"] = time();
		
		if (!$this->options["nobody"])
			return;
		
		if ($this->return_result)
			return $this->content;
			
		echo $this->content;
	}
	
	function getinfo( $opt = false )
	{
		$return = array();
		
		if ( $opt == CURLINFO_EFFECTIVE_URL || !$opt )
			$return["url"] = $this->url;
		
		if ( $opt == CURLINFO_HTTP_CODE || !$opt )
			$return["http_code"] = $this->status_code;
			
		if ( $opt == CURLINFO_FILETIME || !$opt )
			$return["filetime"] = $this->last_modified | -1;
			
		if ( $opt == CURLINFO_TOTAL_TIME || !$opt )
			$return["total_time"] = $this->timer["end"] - $this->timer["start"];
			
		if ( $opt == CURLINFO_NAMELOOKUP_TIME || !$opt )
			$return["namelookup_time"] = $this->lookup_timer["end"] - $this->lookup_timer["start"];
			
		if ( $opt == CURLINFO_CONNECT_TIME || !$opt )
			$return["connect_time"] = $this->lookup_timer["end"] - $this->lookup_timer["start"];
			
		if ( $opt == CURLINFO_REDIRECT_TIME || !$opt )
			$return["redirect_time"] = $this->lookup_timer["last_redirect"] - $this->timer["start"];
			
		if ( $opt == CURLINFO_HEADER_SIZE || !$opt )
			$return["header_size"] = strlen($this->header_data);
			
		if ( $opt == CURLINFO_HEADER_OUT || !$opt )
			$return["header_out"] = trim( $this->last_header );
			
		if ( $opt == CURLINFO_REQUEST_SIZE || !$opt )
			$return["request_size"] = trim( strlen( $this->last_header ) );
			
		if ( $opt == CURLINFO_CONTENT_LENGTH_DOWNLOAD || !$opt )
			$return["download_content_length"] = $this->headers["content-length"] | 0;
			
		if ( $opt == CURLINFO_CONTENT_TYPE || !$opt )
			$return["content_type"] = $this->headers["content-type"];
			
		if ( $opt == CURLINFO_REDIRECT_COUNT || !$opt )
			$return["redirect_count"] = $this->redirection_steps;
			
		if ( $opt )	
			return current( $return );
		
		return $return;
	}
	
	function get_host($url)
	{
		$url = str_replace(array("http://", "https://"), "", $url);
		$tmp = explode("/", $url);
		
		return $tmp[0];
	}
	
	function get_query($url)
	{
		$url = str_replace(array("http://", "https://"), "", $url);
		$tmp = explode("/", $url, 2);
		 
		return "/".$tmp[1];
	}
	
	function _parse_raw_data($raw_data)
	{
		$array = explode("\r\n\r\n", $raw_data, 2);
		
		$this->header_data = $array[0];
		$this->content = $array[1];
		
		$this->_parse_headers($array[0]);
	}
	
	function _parse_headers($raw_headers)
	{
		$raw_headers = trim($raw_headers);
		
		$headers = explode("\r\n", $raw_headers);
		
		foreach($headers as $header)
		{
			if (preg_match("|http/1\.. (\d+)|i", $header, $match)) {
				$this->status_code = $match[1];
				continue;
			}
			
			$header_array = @explode(":", $header);
			
			$header_name = trim($header_array[0]);
			$header_value = trim($header_array[1]);
			
			if (preg_match("|set-cookie2?|i", $header_name))
				$this->cookies->add($header_value);

			else if (preg_match("|last-modified|i", $header_name))
				$this->last_modified = $header_value;

			if ($header_name > "")
				$this->headers[strtolower($header_name)] = $header_value;
		}
		
		if ($this->headers["location"] > "") {
			$this->url = $this->headers["location"];
			$this->exec();
			
			$this->redirection_steps++;
			$this->timer["last_redirect"] = time();
		}
	}
}

class myCurl_Cookies
{
	var $cookies;
	
	function myCurl_Cookies()
	{
		
	}
	
	function add($cookie)
	{
		list($data, $etc) = explode(";", $cookie, 2);
		list($name, $value) = explode("=", $data);
		
		$this->cookies[trim($name)] = trim($value);
	}
	
	function create_header()
	{
		if (count($this->cookies) == 0 || !is_array($this->cookies)) return "";
		
		$output = "";
		
		foreach($this->cookies as $name => $value) {
			$output .= "$name=$value; ";
		}
		
		return "Cookies: $output\r\n";
	}
}
?>
