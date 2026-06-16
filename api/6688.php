<?php
$base = "http://110.42.52.180:6688/live/live.php?token=76AYudAHan&id=";

$channels = [
    "zb-cctv1" => "CCTV1综合", "zb-cctv2" => "CCTV2财经",
    "zb-cctv3" => "CCTV3综艺", "zb-cctv4" => "CCTV4中文国际",
    "zb-cctv5" => "CCTV5体育", "zb-cctv5p" => "CCTV5+体育赛事",
    "zb-cctv6" => "CCTV6电影", "zb-cctv7" => "CCTV7国防军事",
    "zb-cctv8" => "CCTV8电视剧", "zb-cctv9" => "CCTV9纪录",
    "zb-cctv10" => "CCTV10科教", "zb-cctv11" => "CCTV11戏曲",
    "zb-cctv12" => "CCTV12社会与法", "zb-cctv13" => "CCTV13新闻",
    "zb-cctv14" => "CCTV14少儿", "zb-cctv15" => "CCTV15音乐",
    "zb-cctv16" => "CCTV16奥林匹克", "zb-cctv17" => "CCTV17农业农村",
    "zb-zjws" => "浙江卫视", "zb-bjws" => "北京卫视",
    "zb-dfws" => "东方卫视", "zb-jsws" => "江苏卫视",
    "zb-hunws" => "湖南卫视", "zb-gdws" => "广东卫视",
    "zb-jxws" => "江西卫视", "zb-henws" => "河南卫视",
    "zb-hubws" => "湖北卫视", "zb-scws" => "四川卫视",
    "zb-lnws" => "辽宁卫视", "zb-ahws" => "安徽卫视",
    "zb-gxws" => "广西卫视", "zb-tjws" => "天津卫视",
    "zb-cqws" => "重庆卫视", "zb-dnws" => "东南卫视",
    "zb-szws" => "深圳卫视",
    "zb-chcymdy" => "CHC影迷电影", "zb-chcdzdy" => "CHC动作电影",
    "zb-chcjtyy" => "CHC家庭影院", "zb-24hlb" => "24小时轮播",
    "zb-cqjc" => "传奇剧场", "zb-rxjc" => "热血剧场",
    "zb-wxyy" => "往昔影院", "zb-hjjd" => "怀旧经典",
];

// ========== ?id= 直接播放 ==========
$id = isset($_GET['id']) ? $_GET['id'] : '';
if ($id !== '' && isset($channels[$id])) {
    header('Location: ' . $base . $id);
    exit;
}

// ========== 默认输出 TXT 格式 ==========
header('Content-Type: text/plain; charset=utf-8');

// 央视
echo "央视,#genre#\n";
foreach ($channels as $id => $name) {
    if (strpos($name, 'CCTV') !== false) echo "{$name},{$base}{$id}\n";
}

// 卫视
echo "\n卫视,#genre#\n";
foreach ($channels as $id => $name) {
    if (strpos($name, '卫视') !== false) echo "{$name},{$base}{$id}\n";
}

// CHC
echo "\nCHC电影,#genre#\n";
foreach ($channels as $id => $name) {
    if (strpos($name, 'CHC') !== false) echo "{$name},{$base}{$id}\n";
}

// 轮播
echo "\n轮播剧场,#genre#\n";
foreach ($channels as $id => $name) {
    if (strpos($name, 'CCTV') === false && strpos($name, '卫视') === false && strpos($name, 'CHC') === false) {
        echo "{$name},{$base}{$id}\n";
    }
}
?>