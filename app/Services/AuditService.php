<?php

declare(strict_types=1);
namespace App\Services;

use PDO;

final class AuditService
{
    public static function record(PDO $pdo, ?int $actor, string $action, string $type, ?int $target, array $details=[]): void
    {
        if(!$pdo->inTransaction())throw new \LogicException('Audit requires the business transaction.');
        if(!preg_match('/^[a-z][a-z0-9_.]{0,79}$/D',$action)||!preg_match('/^[a-z][a-z0-9_]{0,59}$/D',$type))throw new \InvalidArgumentException('Invalid audit event.');
        $json=json_encode($details,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
        if(strlen($json)>1000)throw new \InvalidArgumentException('Audit details exceed the storage limit.');
        $pdo->prepare('INSERT INTO audit_logs(actor_id,action,target_type,target_id,details) VALUES (?,?,?,?,?)')->execute([$actor,$action,$type,$target,$json]);
    }
}
