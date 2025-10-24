<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if (isset($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/config/Auth.php';
require_once __DIR__ . '/models/ReviewModel.php';

$db = (new Database())->connect();
$auth = new Auth($db);

$auth->requireApiKey();

$reviewModel = new ReviewModel($db);

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$scriptName = $_SERVER['SCRIPT_NAME']; 
$basePath = rtrim(dirname($scriptName), '/\\');
if ($basePath !== '' && str_starts_with($uri, $basePath)) {
    $uri = substr($uri, strlen($basePath));
}
$uri = trim($uri, '/');
$parts = $uri === '' ? [] : explode('/', $uri);

if (count($parts) < 2 || $parts[0] !== 'api' || $parts[1] !== 'reviews') {
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found. Use /api/reviews or /api/reviews/{id}']);
    exit;
}

$id = null;
if (isset($parts[2])) {
    if (ctype_digit($parts[2])) {
        $id = (int)$parts[2];
        if ($id <= 0) $id = null;
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'ID must be a positive integer.']);
        exit;
    }
}

$rawInput = file_get_contents('php://input');
$input = null;
if ($rawInput !== false && $rawInput !== '') {
    $decoded = json_decode($rawInput, true);
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Malformed JSON: ' . json_last_error_msg()]);
        exit;
    }
    $input = $decoded;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            if ($id !== null) {
                $data = $reviewModel->getOne($id);
                echo json_encode($data);
            } else {
                $data = $reviewModel->getAll();
                echo json_encode($data);
            }
            break;

        case 'POST':
            if ($input === null) {
                http_response_code(400);
                echo json_encode(['error' => 'JSON body required.']);
                exit;
            }
            $res = $reviewModel->create($input);
            echo json_encode($res);
            break;

        case 'PUT':
        case 'PATCH':
            if ($id === null) {
                http_response_code(400);
                echo json_encode(['error' => 'ID required in URL for update.']);
                exit;
            }
            if ($input === null) {
                http_response_code(400);
                echo json_encode(['error' => 'JSON body required.']);
                exit;
            }
            $res = $reviewModel->update($id, $input);
            echo json_encode($res);
            break;

        case 'DELETE':
            if ($id === null) {
                http_response_code(400);
                echo json_encode(['error' => 'ID required in URL for deletion.']);
                exit;
            }
            $res = $reviewModel->delete($id);
            echo json_encode($res);
            break;

        default:
            http_response_code(405);
            header('Allow: GET, POST, PUT, PATCH, DELETE, OPTIONS');
            echo json_encode(['error' => 'Method not allowed.']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error.']);
}
