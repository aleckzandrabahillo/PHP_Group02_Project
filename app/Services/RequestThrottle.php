<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Database;
use PDO;

final class RequestThrottle
{
    public static function allow(string $scope, string $identity, int $limit, int $seconds): bool
    {
        if(!in_array($scope,['login','activation','administrator_recovery'],true)||$limit<1||$limit>100||$seconds<1||$seconds>3600)throw new \InvalidArgumentException('Invalid request limit.');
        $hash=hash('sha256',$scope.':'.$identity);
        return Database::transaction(static function(PDO $pdo)use($hash,$limit,$seconds):bool{
            $reset=date('Y-m-d H:i:s',time()+$seconds);
            $pdo->prepare('INSERT IGNORE INTO security_rate_limits(bucket_hash,attempts,reset_at) VALUES (?,0,?)')->execute([$hash,$reset]);
            $q=$pdo->prepare('SELECT attempts,reset_at FROM security_rate_limits WHERE bucket_hash=? FOR UPDATE');$q->execute([$hash]);$row=$q->fetch();
            $expired=strtotime($row['reset_at'])<=time();$attempts=$expired?1:min(65535,(int)$row['attempts']+1);
            $pdo->prepare('UPDATE security_rate_limits SET attempts=?,reset_at=? WHERE bucket_hash=?')->execute([$attempts,$expired?$reset:$row['reset_at'],$hash]);
            // Bounded expiry cleanup; no raw IP, username or credential is stored.
            $pdo->exec('DELETE FROM security_rate_limits WHERE reset_at<DATE_SUB(NOW(),INTERVAL 1 DAY) LIMIT 100');
            return $attempts<=$limit;
        });
    }
}
