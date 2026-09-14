<?php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');

$allow = [
  'ntp.nict.jp',
  'ntp.jst.mfeed.ad.jp',
  'ntp.nict.jp',
  'time.google.com',
  'time.cloudflare.com',
  'time.nist.gov',
  'pool.ntp.org',
  'jp.pool.ntp.org',
];

$host = isset($_GET['host']) ? strtolower(trim($_GET['host'])) : 'ntp.nict.jp';
if (!in_array($host, $allow, true)) {
  $host = 'ntp.nict.jp';
}

function ntp_ms($host, $timeout = 2.0) {
  $sock = @fsockopen('udp://' . $host, 123, $errno, $errstr, $timeout);
  if (!$sock) return null;
  stream_set_timeout($sock, (int)$timeout, (int)(($timeout - (int)$timeout) * 1000000));
  // NTP client request, version 3
  $packet = "\x1b" . str_repeat("\0", 47);
  $sent = microtime(true);
  fwrite($sock, $packet);
  $recv = fread($sock, 48);
  $recvAt = microtime(true);
  fclose($sock);
  if ($recv === false || strlen($recv) < 48) return null;

  $int = unpack('N', substr($recv, 40, 4));
  $frac = unpack('N', substr($recv, 44, 4));
  if (!$int || !$frac) return null;

  $seconds = $int[1] - 2208988800; // NTP(1900) -> Unix(1970)
  $millis  = ($frac[1] / 4294967296) * 1000;
  $server  = $seconds * 1000 + $millis;
  $rtt     = ($recvAt - $sent) * 1000;
  return [
    'serverMs' => $server + ($rtt / 2),
    'rttMs'    => $rtt,
  ];
}

$ntp = ntp_ms($host);
$phpNow = microtime(true) * 1000;

echo json_encode([
  'ok'       => $ntp !== null,
  'host'     => $host,
  'ntpMs'    => $ntp ? $ntp['serverMs'] : null,
  'phpMs'    => $phpNow,
  'rttMs'    => $ntp ? $ntp['rttMs'] : null,
  'source'   => $ntp ? 'ntp' : 'php',
  // NTP失敗時はPHPサーバ時刻（通常はNTP同期済み）を使う
  'nowMs'    => $ntp ? $ntp['serverMs'] : $phpNow,
], JSON_UNESCAPED_UNICODE);
