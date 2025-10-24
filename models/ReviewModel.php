<?php
declare(strict_types=1);

class ReviewModel {
    private PDO $conn;
    private string $table = 'reviews';

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    public function getAll(): array {
        $stmt = $this->conn->query("SELECT id, product_id, user_name, rating, comment, created_at, updated_at FROM {$this->table} ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }

    public function getOne(int $id): array {
        $stmt = $this->conn->prepare("SELECT id, product_id, user_name, rating, comment, created_at, updated_at FROM {$this->table} WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Review not found.']);
            exit;
        }
        return $row;
    }

    public function create(array $data): array {
        $valid = $this->validateInput($data, $isUpdate = false);

        $stmt = $this->conn->prepare("
            INSERT INTO {$this->table} (product_id, user_name, rating, comment)
            VALUES (:product_id, :user_name, :rating, :comment)
        ");
        $stmt->execute([
            ':product_id' => $valid['product_id'],
            ':user_name' => $valid['user_name'],
            ':rating' => $valid['rating'],
            ':comment' => $valid['comment']
        ]);
        $id = (int)$this->conn->lastInsertId();
        http_response_code(201);
        return ['message' => 'Review created.', 'id' => $id];
    }

    public function update(int $id, array $data): array {
        $this->getOne($id);

        $valid = $this->validateInput($data, $isUpdate = true);

        $fields = [];
        $params = [':id' => $id];

        if (array_key_exists('product_id', $valid)) {
            $fields[] = 'product_id = :product_id';
            $params[':product_id'] = $valid['product_id'];
        }
        if (array_key_exists('user_name', $valid)) {
            $fields[] = 'user_name = :user_name';
            $params[':user_name'] = $valid['user_name'];
        }
        if (array_key_exists('rating', $valid)) {
            $fields[] = 'rating = :rating';
            $params[':rating'] = $valid['rating'];
        }
        if (array_key_exists('comment', $valid)) {
            $fields[] = 'comment = :comment';
            $params[':comment'] = $valid['comment'];
        }

        if (empty($fields)) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'No valid fields provided for update.']);
            exit;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return ['message' => 'Review updated.'];
    }

    public function delete(int $id): array {
        $this->getOne($id);
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return ['message' => 'Review deleted.'];
    }

    private function validateInput(array $data, bool $isUpdate): array {
        $result = [];

        if (!$isUpdate || array_key_exists('product_id', $data)) {
            if (!isset($data['product_id']) || !is_numeric($data['product_id']) || (int)$data['product_id'] <= 0) {
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Invalid product_id.']);
                exit;
            }
            $result['product_id'] = (int)$data['product_id'];
        }

        if (!$isUpdate || array_key_exists('user_name', $data)) {
            if (!isset($data['user_name']) || !is_string($data['user_name'])) {
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Invalid user_name.']);
                exit;
            }
            $name = trim($data['user_name']);
            if ($name === '' || mb_strlen($name) > 255) {
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'user_name must be 1..255 characters.']);
                exit;
            }
            $name = preg_replace('/\p{C}+/u', '', $name);
            $result['user_name'] = $name;
        }

        if (!$isUpdate || array_key_exists('rating', $data)) {
            if (!isset($data['rating']) || !is_numeric($data['rating'])) {
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Invalid rating.']);
                exit;
            }
            $rating = (int)$data['rating'];
            if ($rating < 1 || $rating > 5) {
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Rating must be integer 1..5.']);
                exit;
            }
            $result['rating'] = $rating;
        }

        if (array_key_exists('comment', $data)) {
            if ($data['comment'] === null) {
                $result['comment'] = null;
            } elseif (!is_string($data['comment'])) {
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Invalid comment.']);
                exit;
            } else {
                $c = trim($data['comment']);
                if (mb_strlen($c) > 2000) {
                    http_response_code(400);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['error' => 'Comment too long (max 2000).']);
                    exit;
                }
                $c = preg_replace('/\p{C}+/u', '', $c);
                $result['comment'] = $c;
            }
        } elseif (!$isUpdate) {
            $result['comment'] = null;
        }

        return $result;
    }
}
