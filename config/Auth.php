<?php
declare(strict_types=1);

class Auth {
    private PDO $conn;
    private string $headerName = 'X-API-Key';

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    private function getRequestHeaders(): array {
        if (function_exists('getallheaders')) {
            $h = getallheaders();
            $res = [];
            foreach ($h as $k => $v) {
                $res[$k] = $v;
            }
            return $res;
        }
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                $name = str_replace('_', '-', substr($name, 5));
                $headers[$name] = $value;
            }
        }
        return $headers;
    }

    public function requireApiKey(): void {
        $headers = $this->getRequestHeaders();

        $possible = [
            $this->headerName,
            'HTTP_' . strtoupper(str_replace('-', '_', $this->headerName)),
            'X-Api-Key',
            'x-api-key'
        ];

        $provided = null;
        foreach ($possible as $p) {
            if (isset($headers[$p])) {
                $provided = $headers[$p];
                break;
            }
        }

        if ($provided === null && isset($_SERVER['HTTP_X_API_KEY'])) {
            $provided = $_SERVER['HTTP_X_API_KEY'];
        }

        if (empty($provided)) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'API key required in header "X-API-Key".']);
            exit;
        }

        try {
            $stmt = $this->conn->prepare('SELECT api_key FROM api_keys WHERE is_active = 1');
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        } catch (PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Auth check failed.']);
            exit;
        }

        foreach ($rows as $hash) {
            if (password_verify($provided, $hash)) {
                return;
            }
        }

        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Invalid or inactive API key.']);
        exit;
    }
}
