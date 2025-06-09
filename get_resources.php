<?php
header('Content-Type: application/json');
$resources_json = __DIR__ . '/assets/resources/resources.json';
if (!file_exists($resources_json)) {
    echo json_encode(["forms"=>[],"financial"=>[],"publications"=>[],"landPlots"=>[]]);
    exit;
}
echo file_get_contents($resources_json);
