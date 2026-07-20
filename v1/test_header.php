<?php

header("Content-Type: application/json");
echo json_encode(getallheaders(), JSON_PRETTY_PRINT);

// Load Konfigurasi & Validator HMAC
include_once(dirname(__FILE__) . "/../config.php");
include_once(dirname(__FILE__) . "/../hmac_validator.php");

$conn = getDBConnection();

validateHMACRequest($conn);

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents("php://input"), true);
$action = isset($input['action']) ? $input['action'] : null;
$group_name = isset($input['group_name']) ? $input['group_name'] : null;
$device_profile = isset($input['device_profile']) ? $input['device_profile'] : null;
$time_limit = isset($input['time_limit']) ? floatval($input['time_limit']) : 0;
$quota_download = isset($input['quota_download']) ? floatval($input['quota_download']) : 0;
$quota_upload = isset($input['quota_upload']) ? floatval($input['quota_upload']) : 0;

echo json_encode([
    "status" => "success",
    "message" => "API is working",
    "method" => $method,
    "action" => $action,
    "group_name" => $group_name,
    "device_profile" => $device_profile,
    "time_limit" => $time_limit,
    "quota_download" => $quota_download,
    "quota_upload" => $quota_upload
], JSON_PRETTY_PRINT);
