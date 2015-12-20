<?php
$tool_user_name = 'convert';

include_once ( 'shared/common.php' );
error_reporting( E_ALL & ~ E_NOTICE ); // Don't clutter the directory with unhelpful stuff

$prot = getProtocol();
$url = $prot . "://tools.wmflabs.org/$tool_user_name/";

if ( array_key_exists( 'HTTP_ORIGIN', $_SERVER ) ) {
	$origin = $_SERVER['HTTP_ORIGIN'];
}

// Response Headers
header( 'Content-type: application/json; charset=utf-8' );
header( 'Cache-Control: private, s-maxage=0, max-age=0, must-revalidate' );
header( 'x-content-type-options: nosniff' );
header( 'X-Frame-Options: SAMEORIGIN' );
header( 'X-API-VERSION: 0.0.0.0' );

if ( isset( $origin ) ) {
	// Check protocol
	$protOrigin = parse_url( $origin, PHP_URL_SCHEME );
	if ( $protOrigin != $prot ) {
		header( 'HTTP/1.0 403 Forbidden' );
		if ( 'https' == $protOrigin ) {
			echo '{"error":"Please use this service over https."}';
		} else {
			echo '{"error":"Please use this service over http."}';
		}
		exit();
	}
	
	// Do we serve content to this origin?
	if ( matchOrigin( $origin ) ) {
		header( 'Access-Control-Allow-Origin: ' . $origin );
		header( 'Access-Control-Allow-Methods: GET' );
	} else {
		header( 'HTTP/1.0 403 Forbidden' );
		echo '{"error":"Accessing this tool from the origin you are attempting to connect from is not allowed."}';
		exit();
	}
}

$version = shell_exec( 'rsvg-convert -v' );

header( 'X-Generator: ' . $version );

if ( isset( $_GET['version'] ) ) {
	$versionEncoded = json_encode( array( 
		'version' => $version 
	) );
	header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
	header( 'Content-type: application/json; charset=utf-8' );
	header( 'Expires: 0' );
	header( 'Content-Length: ' . strlen( $versionEncoded ) );
	echo $versionEncoded;
	die();
}

if ( ! array_key_exists( 'file', $_FILES ) ) {
	header( "Location: $url" );
	die();
}
$uploadName = $_FILES['file']['tmp_name'];
$fileName = $uploadName . '.svg';
$targetName = $fileName . '.png';

if ( $_FILES['file']['size'] > 1000000 ) {
	unlink( $uploadName );
	header( "Location: $url#tooBig" );
	die();
}

if ( ! move_uploaded_file( $uploadName, $fileName ) ) {
	unlink( $uploadName );
	header( "Location: $url#cantmove" );
	echo ( 'cant move uploaded file' );
	die();
}

exec( 
	'rsvg-convert -o ' . escapeshellarg( $targetName ) . ' ' .
		 escapeshellarg( $fileName ) );
unlink( $fileName );

$handle = fopen( $targetName, 'r' );

if ( $handle === false ) {
	header( "Location: $url#conversionError" );
	echo ( 'error converting the file' );
	die();
}

$filesize = filesize( $targetName );
if ( $filesize > 9000000 ) {
	fclose( $handle );
	unlink( $targetName );
	header( "Location: $url#outputTooHuge" );
	echo ( 'output unexpectedly huge' );
	die();
}

header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
header( 'Content-type: image/png' );
header( 
	'Content-Disposition: attachment; filename="' .
		 addslashes( $_FILES['file']['name'] ) . '.png"' );
header( 'Expires: 0' );
header( 'Content-Length: ' . $filesize );

readfile( $targetName );
fclose( $handle );
unlink( $targetName );

die();

