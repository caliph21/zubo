<?php
error_reporting(0);
ini_set('display_errors', 0);

// 频道映射（短名 -> 真实Key）
$map = [
    'jsws'   => 'jswspro',
    'jsws4k' => 'jsws4kpro',
    'jscs'   => 'jscspro',
    'jszy'   => 'jszypro',
    'jsys'   => 'jsyspro',
    'jsxw'   => 'jsxwpro',
    'jsjy'   => 'jsjypro',
    'jsty'   => 'jsxxpro',
    'jsgj'   => 'jsgjpro',
    'ymkt'   => 'ymktpro',
];

$id = isset($_GET['id']) ? $_GET['id'] : '';
if (isset($map[$id])) {
    $channelKey = $map[$id];
} elseif (in_array($id, $map)) {
    $channelKey = $id;
} else {
    $channelKey = 'jswspro'; // 默认
}

// 生成签名（与抓包示例一致）
$txTime = dechex(time() + 180);
$txSecret = md5("HCPMPKxQNrKAyjzR67JG" . $channelKey . $txTime);
$m3u8Url = "https://litchi-play-encrypted-site.jstv.com/applive/{$channelKey}.m3u8?txSecret={$txSecret}&txTime={$txTime}";

// 处理 TS 代理请求
if (isset($_GET['ts'])) {
    $tsUrl = urldecode($_GET['ts']);
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

// 获取 M3U8 内容（完全模拟 curl 成功的请求头）
$m3u8Content = fetchWithHeaders($m3u8Url);
if (!$m3u8Content) {
    header('HTTP/1.1 502 Bad Gateway');
    echo 'Failed to fetch M3U8';
    exit;
}

// 重写 TS 路径
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

/**
 * 发起 HTTP 请求，完全模拟浏览器/curl 成功的头信息
 */
function fetchWithHeaders($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,   // 跟随重定向
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Mobile Safari/537.36',
            'Accept: */*',
            'Origin: https://live.jstv.com',
            'X-Requested-With: mark.via',
            'Referer: https://live.jstv.com/',
            'Accept-Language: zh-CN,zh;q=0.9,en-CN;q=0.8,en-US;q=0.7,en;q=0.6',
            'Accept-Encoding: gzip, deflate',  // 允许压缩
        ],
        CURLOPT_ENCODING => '',               // 自动处理压缩内容
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("fetchWithHeaders failed for $url - HTTP $httpCode, Error: $error");
        return false;
    }
    return $response;
}
?>