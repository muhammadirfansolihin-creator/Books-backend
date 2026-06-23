<?php
namespace App\Controllers;

use App\Repositories\BookRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

final class BookController
{
    public function __construct(private BookRepository $books, private PDO $pdo) {}

    public function index(Request $r, Response $s): Response
    {
        return $this->json($s, $this->books->all());
    }

    public function show(Request $r, Response $s, array $a): Response
    {
        $book = $this->books->find((int)$a['id']);
        return $book ? $this->json($s, $book) 
                : $this->json($s, ['error' => 'not found'], 404);
    }

    public function create(Request $r, Response $s): Response
    {
        $body = (array)($r->getParsedBody());
        $auth = (array)$r->getAttribute('auth', []);
        $userId = (int)($auth['sub'] ?? 0);

        $errors = (new Validator())
            ->required('title','author','year')
            ->field('title', Validator::nonEmptyString(200), 'title must be 1-200 chars')
            ->field('author', Validator::nonEmptyString(150), 'author must be 1-150 chars')
            ->field('year', Validator::intRange(1000, (int)date('Y')), 'year must be 1000..now')
            ->field('genre', Validator::nonEmptyString(80), 'genre must be ≤ 80 chars')
            ->validate($body);

        if ($errors) {
            return $this->json($s, ['errors' => $errors], 400);
        }

        $id = $this->books->create($body, $userId);
        $this->logEvent($userId, 'book.create', (string)$id, $r->getServerParams()['REMOTE_ADDR'] ?? '', "Created book: " . $body['title']);

        return $this->json($s, ['message' => 'Book created', 'id' => $id], 201);    
        }

    public function update(Request $r, Response $s, array $a): Response{
        $id = (int)($a['id']);
        $book = $this->books->find($id);
        if (!$book) {
            return $this->json($s, ['error' => 'not found'], 404);
        }

        $auth = (array)$r->getAttribute('auth', []);
        $userId = (int)($auth['sub'] ?? 0);
        $isOwner = (int)($book['created_by'] ?? 0) === (int)($auth['sub'] ?? 0);
        $isAdmin = ($auth['role'] ?? 'member') === 'admin';

        if (!$isOwner && !$isAdmin) {
            return $this->json($s, ['error' => 'Forbidden'], 403);
        }

        $body = (array)($r->getParsedBody());
        $this->books->update($id, array_merge($book, $body));
            $this->logEvent($userId, 'book.update', (string)$id, $r->getServerParams()['REMOTE_ADDR'] ?? '', "Updated book ID: " . $id);

            return $this->json($s, ['message' => 'Book updated']);
    }

    public function delete(Request $r, Response $s, array $a): Response
    {
        // Fixed: changed $req to $r and $res to $s to match the method arguments
        $auth = (array) $r->getAttribute('auth', []);
        if (($auth['role'] ?? 'member') !== 'admin') {
            return $this->json($s, ['error' => 'Admins only'], 403);
        }
        
        $id = (int)($a['id']);
        $book = $this->books->find($id);
        if (!$book) {
            return $this->json($s, ['error' => 'not found'], 404);
        }

        $this->books->delete($id);
        $this->logEvent($userId, 'book.delete', (string)$id, $r->getServerParams()['REMOTE_ADDR'] ?? '', "Deleted book ID: " . $id);
        return $this->json($s, ['message' => 'Book deleted', 'data' => $book]);
    }

    private function logEvent(?int $actorId, string $action, ?string $target, string $ip, string $detail): void {
        $stmt = $this->pdo->prepare('INSERT INTO audit_log (actor_id, action, target, ip_address, detail) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$actorId ?: null, $action, $target, $ip, $detail]);
    }
    
    private function validate(array $b, bool $requireAll): array
    {
        $errors = [];
        $rules = [
            'title'  => fn($v) => is_string($v) && trim($v) !== '',
            'author' => fn($v) => is_string($v) && trim($v) !== '',
            'year'   => fn($v) => is_numeric($v) && (int)$v >= 1000 && (int)$v <= (int)date('Y'),
        ];

        foreach ($rules as $f => $check) {
            if ($requireAll && !array_key_exists($f, $b)) {
                $errors[$f] = "$f is required";
                continue;
            }
            if (array_key_exists($f, $b) && !$check($b[$f])) {
                $errors[$f] = "$f is invalid";
            }
        }
        return $errors;
    }

    private function json(Response $r, $data, int $status = 200): Response {
    $r->getBody()->write(json_encode(
        $data,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ));
    
    return $r->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus($status);
}
}