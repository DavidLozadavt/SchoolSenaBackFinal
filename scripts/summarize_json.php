<?php
$raw = stream_get_contents(STDIN);
$j = json_decode($raw, true);
if (!$j) { echo "Invalid JSON\n"; exit(1); }
foreach ($j['results'] ?? [] as $r) {
    $d = $r['detail'];
    if (is_array($d)) {
        $d = json_encode($d, JSON_UNESCAPED_UNICODE);
        if (strlen($d) > 300) $d = substr($d, 0, 300) . '...';
    } else {
        $d = substr((string)$d, 0, 300);
    }
    echo $r['step'] . ' | ' . $r['status'] . ' | ' . $d . PHP_EOL;
}
echo 'SUMMARY: ' . json_encode($j['summary'] ?? []) . PHP_EOL;
