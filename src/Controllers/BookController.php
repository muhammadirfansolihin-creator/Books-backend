<?php
namespace App\Controllers;

use App\Repositories\BookRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Validation\Validator;
use App\Repositories\AuditLog;

final class BookController
{
    public function __construct(private BookRepository $books, private AuditLog $audit) {}

    public function index(Request $r, Response $s): Response
    {
        $p = $r->getQueryParams();
        $rows = $this->books->all((string)($p['q'] ?? ''), (int)($p['limit'] ?? 0));
        return $this->json($s, ['count' => count($rows), 'data' => $rows]);
    }

    public function show(Request $r, Response $s, array $a): Response
    {
        $book = $this->books->find((int)$a['id']);
        return $book ? $this->json($s, $book) 
                : $this->json($s, ['error' => 'not found'], 404);
    }

    public function create(Request $r, Response $s): Response
    {
        $body = (array)($r->getParsedBody() ?? []);

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

        $auth = (array)$r->getAttribute('auth', []);
        $userId = (int)($auth['sub'] ?? 0);
        $id = $this->books->create($body, $userId);
        $ip = (string)($r->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
        $this->audit->record('book.create', $userId, (string)$id, $ip, 'Created book: ' . $body['title']);
        
    
        return $this->json($s, ['message' => 'Book created', 'data' => $this->books->find($id)], 201)->withHeader('Location', '/api/books/' . $id);
           
    }

    public function update(Request $r, Response $s, array $a): Response{
        $id = (int)($a['id']);
        $book = $this->books->find($id);
        if (!$book) {
            return $this->json($s, ['error' => 'not found'], 404);
        }

        $auth = (array)$r->getAttribute('auth', []);
        $isOwner = (int)($book['created_by'] ?? 0) === (int)($auth['sub'] ?? 0);
        $isAdmin = ($auth['role'] ?? 'member') === 'admin';

        if (!$isOwner && !$isAdmin) {
            $ip = (string)($r->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
            $this->audit->record('book.update.forbidden', (int)($auth['sub'] ?? 0), (string)$id, $ip, 'Forbidden update attempt on book ID: ' . $id);
            return $this->json($s, ['error' => 'Forbidden'], 403);
        }

        $body = (array)($r->getParsedBody() ?? []);
        $errors = (new Validator())
            ->field('title',  Validator::nonEmptyString(200), 'title must be 1-200 chars')
            ->field('author', Validator::nonEmptyString(150), 'author must be 1-150 chars')
            ->field('year',   Validator::intRange(1000, (int)date('Y')), 'year must be 1000..now')
            ->field('genre',  Validator::nonEmptyString(80),  'genre must be ≤ 80 chars')
            ->validate($body, true);
        if ($errors) {
            return $this->json($s, ['errors' => $errors], 400);
        }

        $this->books->update($id, $body);
        $ip = (string)($r->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
        $this->audit->record('book.update', (int)($auth['sub'] ?? 0), (string)$id, $ip, 'Updated book ID: ' . $id);
        return $this->json($s, ['message' => 'Book updated', 'data' => $this->books->find($id)]);
    }

    public function delete(Request $r, Response $s, array $a): Response
    {
        // Fixed: changed $req to $r and $res to $s to match the method arguments
        $auth = (array) $r->getAttribute('auth', []);
        if (($auth['role'] ?? 'member') !== 'admin') {
            return $this->json($s, ['error' => 'Admins only'], 403);
        }

        $id = (int)($a['id']);
        $deleted = $this->books->delete($id);
        if (!$deleted)
            return $this->json($s, ['error' => 'not found or already deleted'], 404);

        $ip = (string)($r->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
        $this->audit->record('book.delete', (int)($auth['sub'] ?? 0), (string)$id, $ip, 'Deleted book ID: ' . $id);

        return $this->json($s, ['message' => 'Book deleted']);
    }

    private function json(Response $r, $data, int $status = 200): Response {
    $r->getBody()->write(json_encode(
        $data,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ));
    
    return $r->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus($status);
    }
}