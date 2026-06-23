<?php
namespace App\Repositories;

use PDO;

final class AuditLog
{
    public function __construct(private PDO $pdo) {}

    public function record(
        string $action,
        ?int   $actorId  = null,
        ?string $target  = null,
        ?string $ip      = null,
        ?string $detail  = null
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_log (action, actor_id, target, ip_address, detail)
             VALUES (:action, :actor_id, :target, :ip, :detail)'
        );
        $stmt->execute([
            ':action'   => $action,
            ':actor_id' => $actorId,
            ':target'   => $target,
            ':ip'       => $ip,
            ':detail'   => $detail,
        ]);
    }
}