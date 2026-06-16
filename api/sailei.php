<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);



$cacheTime = 600; 
$cacheFile = __DIR__.'/cache/sailei_data.json'; 
$userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36';
$requiredHeaders = [
    'User-Agent: ' . $userAgent,
    'Referer: https://sailei.dpdns.org/'
];


$channelId = isset($_GET['id']) ? trim($_GET['id']) : '';
$proxyUrl = isset($_GET['url']) ? trim($_GET['url']) : '';

$currentScriptUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[SCRIPT_NAME]";

if (!is_dir(__DIR__.'/cache')) {
    mkdir(__DIR__.'/cache', 0755, true);
}

function getOriginalChannelData() {
    global $cacheTime, $cacheFile, $userAgent;
    
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
        return file_get_contents($cacheFile);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://sailei.dpdns.org/api/m3u/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: */*',
        'accept-language: en-US,en;q=0.9,zh-CN;q=0.8,zh;q=0.7,zh-TW;q=0.6',
        'cache-control: no-cache',
        'pragma: no-cache',
        'priority: u=1, i',
        'referer: https://sailei.dpdns.org/',
        'sec-ch-ua: "Google Chrome";v="137", "Chromium";v="137", "Not/A)Brand";v="24"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'sec-fetch-dest: empty',
        'sec-fetch-mode: cors',
        'sec-fetch-site: same-origin',
        'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36',
    ]);
    curl_setopt($ch, CURLOPT_COOKIE, '_pk_id.1.8387=94566b0d111f84e5.1770874654.; _pk_ses.1.8387=1; userId=1770876131692');
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response !== false) {
        @file_put_contents($cacheFile, $response);
    }
    
    return $response;
}

function getStreamUrl($channelId) {
    $challengeApiUrl = 'https://sailei.dpdns.org/api/altcha';
    $challengeJson = file_get_contents($challengeApiUrl);
    
    if (!$challengeJson) {
        return null;
    }

    $data = json_decode($challengeJson, true);
    if (!isset($data['challenge']) || !isset($data['salt'])) {
        return null; 
    }

    $challenge = $data['challenge'];
    $salt = str_replace('&amp;', '&', $data['salt']); 
    $maxNumber = $data['maxnumber'] ?? 100000;
    $algorithm = $data['algorithm'] ?? 'SHA-256';
    $signature = $data['signature'] ?? '';

    $solvedNumber = null;
    $startTime = microtime(true);

    for ($i = 0; $i <= $maxNumber; $i++) {
        if (hash('sha256', $salt . $i) === $challenge) {
            $solvedNumber = $i;
            break;
        }
    }

    if ($solvedNumber === null) {
        return null; 
    }

    $took = (int)((microtime(true) - $startTime) * 1000);
    $payload = [
        "algorithm" => $algorithm,
        "challenge" => $challenge,
        "number"    => $solvedNumber,
        "salt"      => $salt,
        "signature" => $signature,
        "took"      => $took
    ];
    $token = base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    $targetUrl = "https://sailei.dpdns.org/api/checkout?channelId={$channelId}";
    
    $referer = "https://sailei.dpdns.org/play.html?channelId={$channelId}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $targetUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json',
        'accept-language: zh-CN,zh;q=0.9',
        'cache-control: no-cache',
        'referer: ' . $referer,
        'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36',
        'x-altcha-token: ' . $token, 
    ]);
    
    curl_setopt($ch, CURLOPT_COOKIE, 'userId=' . (time() * 1000)); 

    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        curl_close($ch);
        return null;
    }
    curl_close($ch);

    $result = json_decode($response, true);
    
    if (isset($result['success']) && $result['success'] === true && isset($result['streamUrl'])) {
        return $result['streamUrl'];
    }

    return null;
}

function generateM3uPlaylistFromJson($jsonContent) {
    global $currentScriptUrl;

    $data = json_decode($jsonContent, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        return "#EXTM3U\n#EXTINF:-1,Error - Invalid JSON data from source\n";
    }

    $output = "#EXTM3U\n";

    foreach ($data as $group) {
        $groupTitle = isset($group['title']) && $group['title'] !== '' ? $group['title'] : 'Unknown Group';
        $items      = $group['items'] ?? [];
        if (!is_array($items)) {
            continue;
        }

        if ($groupTitle === '请立即更新') {
            continue;
        }


        foreach ($items as $item) {
            $channelName = isset($item['name']) && $item['name'] !== '' ? $item['name'] : 'Unknown Channel';
            $channelId   = $item['channelId'] ?? null;
            $iconUrl     = $item['icon_url'] ?? '';

            if (empty($channelId)) {
                continue;
            }
            $safeGroupTitle = str_replace('"', '\"', $groupTitle);
            $safeIconUrl    = str_replace('"', '\"', $iconUrl);
            $safeName       = str_replace(',', ' ', $channelName); 

            $logoAttribute = $safeIconUrl !== '' ? ' tvg-logo="' . $safeIconUrl . '"' : '';

            $output .= '#EXTINF:-1 tvg-id="' . $safeName . '" tvg-name="' . $safeName . '" group-title="' . $safeGroupTitle . '"' . $logoAttribute . ',' . $safeName . "\n";
            $output .= $currentScriptUrl . '?id=' . rawurlencode($channelId) . "\n";
        }
    }

    return $output;
}

function proxyTsStream($url) {
    global $requiredHeaders;
    
    $qValue = '';
    if (preg_match('/q=(\d+)/', $url, $matches)) {
        $qValue = $matches[1];
    }
    
    $headers = $requiredHeaders;
    if (!empty($qValue)) {
        $headers[] = 'Cookie: userId=q=' . $qValue;
    }
    
    header('Content-Type: video/MP2T');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_REFERER => 'https://sailei.dpdns.org/',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_WRITEFUNCTION => function($ch, $data) {
            echo $data;
            return strlen($data);
        }
    ]);
    curl_exec($ch);
    curl_close($ch);
}


function proxyM3u8($url) {
    global $currentScriptUrl, $requiredHeaders;
    
    $qValue = '';
    if (preg_match('/q=(\d+)/', $url, $matches)) {
        $qValue = $matches[1];
    }
    
    $headers = $requiredHeaders;
    if (!empty($qValue)) {
        $headers[] = 'Cookie: userId=q=' . $qValue;
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_REFERER => 'https://sailei.dpdns.org/',
    ]);
    $content = curl_exec($ch);
    curl_close($ch);
    
    $lines = explode("\n", $content);
    $output = [];
    
    foreach ($lines as $line) {
        $trimmed = trim($line);
        
        if (empty($trimmed) || $line[0] === '#' || strpos($trimmed, '#EXT-X-KEY') === 0) {
            $output[] = $line;
            continue;
        }
        
        if (strpos($trimmed, 'http') === 0 || strpos($trimmed, '/') === 0) {
            $output[] = $currentScriptUrl . '?url=' . $trimmed;
        } else {
            $output[] = $line;
        }
    }
    
    header('Content-Type: application/vnd.apple.mpegurl');
    header('Cache-Control: public, max-age=30');
    echo implode("\n", $output);
}

if (!empty($proxyUrl)) {
    if (strpos($proxyUrl, '.ts') !== false) {
        proxyTsStream($proxyUrl);
    } else {
        proxyM3u8($proxyUrl);
    }
    exit;
} 
elseif (!empty($channelId)) {
    $jsonContent = getOriginalChannelData();
    $data = json_decode($jsonContent, true);
    
    $foundStreamUrl = null;
    if (is_array($data)) {
        foreach ($data as $group) {
            $items = $group['items'] ?? [];
            foreach ($items as $item) {
                if (isset($item['channelId']) && $item['channelId'] === $channelId) {
                    $foundStreamUrl = getStreamUrl($channelId);
                    break 2;
                }
            }
        }
    }

    if ($foundStreamUrl) {
        proxyM3u8($foundStreamUrl);
    } else {
        header('HTTP/1.1 404 Not Found');
        echo 'Channel with ID "' . htmlspecialchars($channelId) . '" not found or stream URL could not be retrieved.';
    }
    exit;
} 

else {
    $jsonContent = getOriginalChannelData();
    header('Content-Type: text/plain; charset=utf-8');
    echo generateM3uPlaylistFromJson($jsonContent);
    exit;
}