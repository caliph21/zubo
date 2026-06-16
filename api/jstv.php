<?php
error_reporting(0);
ini_set('display_errors', 0);

$n = [
    'jsws' => "jswspro",      // 江苏卫视 
    'jsws4k' => 'jsws4kpro', // 江苏卫视 4K ⭐
    'jscs' => "jscspro",     // 江苏城市 
    'jszy' => "jszypro",     // 江苏综艺 
    'jsys' => "jsyspro",     // 江苏影视 
    'jsxw' => "jsxwpro",     // 江苏新闻 
    'jsjy' => "jsjypro",     // 江苏教育
    'jsty' => "jsxxpro",     // 江苏体育休闲 
    'jsgj' => "jsgjpro",     // 江苏国际 
    'ymkt' => "ymktpro",     // 优漫卡通 
];

$id = isset($_GET['id']) ? $_GET['id'] : 'jsws';

// 检查频道ID是否存在
if (!isset($n[$id])) {
    header('HTTP/1.1 404 Not Found');
    echo 'Channel ID not found';
    exit;
}

$channelKey = $n[$id];

$txTime = dechex(floor(time()) + 180);
$txSecret = md5("HCPMPKxQNrKAyjzR67JG" . $channelKey . $txTime);

$m3u8Url = "https://litchi-play-encrypted-site.jstv.com/applive/{$channelKey}.m3u8?txSecret={$txSecret}&txTime={$txTime}";
$baseUrl = dirname($m3u8Url) . "/";

$currentScriptUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') 
    . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];

$ts = isset($_GET['ts']) ? $_GET['ts'] : '';

if (!empty($ts)) {
    // 代理 TS 分片
    $tsData = getContent($ts);
    if ($tsData) {
        header('Content-Type: video/MP2T');
        echo $tsData;
    } else {
        header('HTTP/1.1 502 Bad Gateway');
        echo 'Failed to fetch TS';
    }
    exit;
}

// 获取并代理 M3U8
$m3u8Content = getContent($m3u8Url);
if (!$m3u8Content) {
    header('HTTP/1.1 502 Bad Gateway');
    echo 'Failed to fetch M3U8';
    exit;
}

// 重写 TS 路径
$lines = explode("\n", $m3u8Content);
$output = [];
foreach ($lines as $line) {
    $trimmed = trim($line);
    if (empty($trimmed) || $trimmed[0] === '#') {
        $output[] = $line;
    } elseif (strpos($trimmed, '.ts') !== false) {
        // 处理相对路径和绝对路径
        if (strpos($trimmed, 'http') === 0) {
            $output[] = $currentScriptUrl . '?ts=' . urlencode($trimmed);
        } else {
            $output[] = $currentScriptUrl . '?ts=' . urlencode($baseUrl . $trimmed);
        }
    } else {
        $output[] = $line;
    }
}

header('Content-Type: application/vnd.apple.mpegurl');
header('Cache-Control: public, max-age=30');
echo implode("\n", $output);

function getContent($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_REFERER, 'https://live.jstv.com/');
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return false;
    }
    return $result;
}
?>