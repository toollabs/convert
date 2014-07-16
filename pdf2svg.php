<?php
$tool_user_name = 'convert';

include_once ( 'shared/common.php' ) ;
error_reporting( E_ALL & ~E_NOTICE ); # Don't clutter the directory with unhelpful stuff

$host = getProtocol() . "://tools.wmflabs.org/$tool_user_name/";

if (!array_key_exists('file', $_FILES)) {
  header("Location: $host");
  die();
}
$uploadName = $_FILES['file']['tmp_name'];
$fileName = $uploadName . '.pdf';
$targetName = $fileName . '.svg';

if ($_FILES['file']['size'] > 1000000) {
  unlink($uploadName);
  header("Location: $host#tooBig");
  die();
}

if (!move_uploaded_file($uploadName, $fileName)) {
  echo('cant move uploaded file');
  unlink($uploadName);
  header("Location: $host#cantmove");
  die();
}

exec('pdf2svg ' . escapeshellarg($fileName) . ' ' . escapeshellarg($targetName));
unlink($fileName);

$handle = fopen($targetName, 'r');

if ($handle === false) {
  echo('error converting the file');
  header("Location: $host#conversionError");
  die();
}

if (filesize($targetName) > 5000000) {
  fclose($handle);
  unlink($targetName);
  echo('output unexpectedly huge');
  header("Location: $host#outputTooHuge");
  die();
}

$content = file_get_contents($targetName);
fclose($handle);
unlink($targetName);

if (strlen($content) > 5000000) {
  echo('output unexpectedly huge 2');
  header("Location: $host#outputTooHuge2");
  die();
}

header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Content-type: image/svg+xml');
header('Content-Disposition: attachment; filename="' . addslashes($_FILES['file']['name']) . '.svg"');
echo $content;
die();
