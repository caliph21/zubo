<?php
error_reporting(0);
$n = [
  
   'jsws' => "jswspro", //江苏卫视 
   'jsws4k'=> 'jsws4kpro', // 江苏卫视 4K ⭐
   'jscs' => "jscspro", //江苏城市 
   'jszy' => "jszypro", //江苏综艺 
   'jsys' => "jsyspro", //江苏影视 
   'jsxw' => "jsxwpro", //江苏新闻 
   'jsjy' => "jsjypro", //江苏教育
   'jsty' => "jsxxpro", //江苏体育休闲 
   'jsgj' => "jsgjpro", //江苏国际 
   'ymkt' => "ymktpro", //优漫卡通 
  
   ];

$id = isset($_GET['id'])?$_GET['id']:'jsws';

$txTime = dechex(floor(time())+180);
$txSecret = md5("HCPMPKxQNrKAyjzR67JG".$n[$id].$txTime);

$url = "https://litchi-play-encrypted-site.jstv.com/applive/{$n[$id]}.m3u8?txSecret={$txSecret}&txTime={$txTime}";

$burl = dirname($url)."/";
$php = "http://".$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'];
$ts = $_GET['ts'];
if(empty($ts)) {
     header('Content-Type: application/vnd.apple.mpegurl');
     print_r(preg_replace("/(.*?.ts)/i",$php."?ts=$burl$1",get($url)));
     } else {
       $data = get($ts);
       header('Content-Type: video/MP2T');
       echo $data;
       }


function get($url){
     $ch = curl_init($url);
     curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
     curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
     curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
     curl_setopt($ch, CURLOPT_REFERER, 'https://live.jstv.com/');
     $res = curl_exec($ch);
     curl_close($ch);
     return $res;
     }
?>