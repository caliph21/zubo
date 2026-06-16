<?php
error_reporting(0);
ini_set('display_errors', 0);

$n = [
    'jsws'   => 'jswspro',     // 江苏卫视
    'jsws4k' => 'jsws4kpro',   // 江苏卫视 4K
    'jscs'   => 'jscspro',     // 江苏城市
    'jszy'   => 'jszypro',     // 江苏综艺
    'jsys'   => 'jsyspro',     // 江苏影视
    'jsxw'   => 'jsxwpro',     // 江苏新闻
    'jsjy'   => 'jsjypro',     // 江苏教育
    'jsty'   => 'jsxxpro',     // 江苏体育休闲
    'jsgj'   => 'jsgjpro',     // 江苏国际
    'ymkt'   => 'ymktpro',     // 优漫卡通
];

$id = isset($_GET['id']) ? $_GET['id'] : 'jsws';
if (!isset($n[$id])) {
    header('HTTP/1.1 404 Not Found');
    echo 'Invalid channel id';
    exit;
}

$channelKey = $n[$id];
$txTime = dechex(time() + 180);
$txSecret = md5("HCPMPKxQNrKAyjzR67JG" . $channelKey . $txTime);
$m3u8Url = "https://litchi-play-encrypted-site.jstv.com/applive/{$channelKey}.m3u8?txSecret={$txSecret}&txTime={$txTime}";

// 获取 M3U8 内容（带上完整防盗链头）
$m3u8Content = fetchWithHeaders($m3u8Url);
if (!$m3u8Content) {
    header('HTTP/1.1 502 Bad Gateway');
    echo 'Failed to fetch M3U8';
    exit;
}

// 处理 TS 代理
$ts = isset($_GET['ts']) ? $_GET['ts'] : '';
if ($ts) {
    $tsUrl = urldecode($ts);
    $tsData = fetchWithHeaders($tsUrl);
    if ($tsData) {
        header('Content-Type: video/MP2T');
        echo $tsData;
    } else {
        header('HTTP/1.1 502 Bad Gateway');
        echo 'TS fetch failed';
    }
    exit;
}

// 重写 M3U8 中的 TS 路径
$baseUrl = dirname($m3u8Url) . '/';
$scriptUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];

$lines = explode("\n", $m3u8Content);
$output = [];
foreach ($lines as $line) {
    $line = rtrim($line);
    if (strpos($line, '#') === 0 || trim($line) === '') {
        $output[] = $line;
        continue;
    }
    // 匹配 .ts 文件（可能带查询参数）
    if (preg_match('/^([^?#]+\.ts)(\?.*)?$/', $line, $matches)) {
        $tsFile = $matches[1];
        $query = isset($matches[2]) ? $matches[2] : '';
        $fullTsUrl = $baseUrl . $tsFile . $query;
        $output[] = $scriptUrl . '?ts=' . urlencode($fullTsUrl);
    } else {
        $output[] = $line;
    }
}

header('Content-Type: application/vnd.apple.mpegurl');
header('Cache-Control: public, max-age=30');
echo implode("\n", $output);

function fetchWithHeaders($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Mobile Safari/537.36',
            'Accept: */*',
            'Origin: https://live.jstv.com',
            'X-Requested-With: mark.via',
            'Referer: https://live.jstv.com/',
            'Accept-Language: zh-CN,zh;q=0.9',
        ]
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($httpCode === 200) ? $response : false;
}
?>