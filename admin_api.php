<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
header('Content-Type: application/json');

// Path to password file
$PASS_FILE = __DIR__ . '/admin_pass.txt';

// Load password from file
function get_admin_pass($file) {
    return file_exists($file) ? trim(file_get_contents($file)) : 'admin123';
}

// Save password to file
function set_admin_pass($file, $pass) {
    file_put_contents($file, $pass);
}

$ADMIN_USER = 'admin';
$ADMIN_PASS = get_admin_pass($PASS_FILE);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Check if user is authenticated for protected actions
function is_authenticated() {
    return isset($_SESSION['admin']) && $_SESSION['admin'] === true;
}

// Login handler
if ($action === 'login') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if ($username === $ADMIN_USER && $password === $ADMIN_PASS) {
        $_SESSION['admin'] = true;
        echo json_encode(["success"=>true]);
    } else {
        http_response_code(401);
        echo json_encode(["success"=>false, "message"=>"Invalid credentials"]);
    }
    exit;
}

// Logout handler
if ($action === 'logout') {
    session_destroy();
    echo json_encode(["success"=>true]);
    exit;
}

// Change password handler
if ($action === 'change_password') {
    if (!is_authenticated()) {
        http_response_code(403);
        echo json_encode(["success"=>false, "message"=>"Unauthorized"]);
        exit;
    }
    
    $old = $_POST['oldPassword'] ?? '';
    $new = $_POST['newPassword'] ?? '';
    if ($old === $ADMIN_PASS) {
        set_admin_pass($PASS_FILE, $new);
        echo json_encode(["success"=>true]);
    } else {
        http_response_code(400);
        echo json_encode(["success"=>false, "message"=>"Old password incorrect"]);
    }
    exit;
}

// Upload handler
if ($action === 'upload') {
    if (!is_authenticated()) {
        http_response_code(403);
        echo json_encode(["success"=>false, "message"=>"Unauthorized"]);
        exit;
    }

    $type = $_POST['type'] ?? '';
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $googleLink = $_POST['googleLink'] ?? '';
    $file = $_FILES['file'] ?? null;

    // Validate inputs
    if (empty($type) || empty($title) || empty($description)) {
        http_response_code(400);
        echo json_encode(["success"=>false, "message"=>"Missing required fields"]);
        exit;
    }

    // Determine target folder
    $folder = '';
    if ($type === 'Forms') $folder = 'assets/resources/Forms/';
    elseif ($type === 'Financial Reports') $folder = 'assets/resources/Financial Reports/';
    elseif ($type === 'Publications') $folder = 'assets/resources/Publications/';
    elseif ($type === 'Land Plots') $folder = 'assets/resources/Land Plots/';
    else {
        http_response_code(400);
        echo json_encode(["success"=>false, "message"=>"Invalid resource type"]);
        exit;
    }

    // Create folder if it doesn't exist
    if (!file_exists($folder)) {
        mkdir($folder, 0755, true);
    }

    // Handle file upload
    if ($file && is_uploaded_file($file['tmp_name']) && $file['error'] === UPLOAD_ERR_OK) {
        $filename = basename($file['name']);
        $target = $folder . $filename;

        if (move_uploaded_file($file['tmp_name'], $target)) {
            // Update resources.json
            $json_path = __DIR__ . '/assets/resources/resources.json';
            $resources = file_exists($json_path) ? json_decode(file_get_contents($json_path), true) : [
                "forms" => [],
                "financial" => [],
                "publications" => [],
                "landPlots" => []
            ];

            $newItem = [
                "title" => $title,
                "description" => $description
            ];

            if ($type === 'Land Plots') {
                $newItem["image"] = $target;
                $newItem["googleLink"] = $googleLink;
            } else {
                $newItem["url"] = $target;
            }

            $resources[strtolower(str_replace(' ', '', $type))][] = $newItem;
            file_put_contents($json_path, json_encode($resources, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            echo json_encode([
                "success" => true,
                "message" => "File uploaded and resources.json updated",
                "filename" => $filename
            ]);
        } else {
            http_response_code(500);
            echo json_encode(["success"=>false, "message"=>"Failed to save file"]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["success"=>false, "message"=>"No file uploaded or upload error"]);
    }
    exit;
}

// Delete handler
if ($action === 'delete') {
    if (!is_authenticated()) {
        http_response_code(403);
        echo json_encode(["success"=>false, "message"=>"Unauthorized"]);
        exit;
    }

    $type = $_POST['type'] ?? '';
    $title = $_POST['title'] ?? '';
    $filePath = $_POST['filePath'] ?? '';

    if (empty($type) || empty($title) || empty($filePath)) {
        http_response_code(400);
        echo json_encode(["success"=>false, "message"=>"Missing parameters"]);
        exit;
    }

    // Load resources.json
    $json_path = __DIR__ . '/assets/resources/resources.json';
    if (!file_exists($json_path)) {
        http_response_code(404);
        echo json_encode(["success"=>false, "message"=>"Resources file not found"]);
        exit;
    }

    $resources = json_decode(file_get_contents($json_path), true);
    $updated = false;

    // Find and remove the item
    if (isset($resources[$type])) {
        foreach ($resources[$type] as $index => $item) {
            if ($item['title'] === $title && (isset($item['url']) && $item['url'] === $filePath || 
                isset($item['image']) && $item['image'] === $filePath)) {
                
                // Delete the physical file
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                
                // Remove from array
                array_splice($resources[$type], $index, 1);
                $updated = true;
                break;
            }
        }
    }

    if ($updated) {
        // Save updated JSON
        file_put_contents($json_path, json_encode($resources, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo json_encode(["success"=>true]);
    } else {
        http_response_code(404);
        echo json_encode(["success"=>false, "message"=>"Resource not found"]);
    }
    exit;
}

// Default response for invalid actions
http_response_code(403);
echo json_encode(["success"=>false, "message"=>"Unauthorized or invalid action"]);