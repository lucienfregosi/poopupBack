<?php
function getDB() {
	$dbhost="54.218.31.103";
	$dbuser="root";
	$dbpass="root";
	$dbname="poopup";
	$dbConnection = new PDO("mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass);

	$dbConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	return $dbConnection;
}
?>