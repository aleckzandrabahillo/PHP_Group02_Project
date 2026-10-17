<?php

declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use InvalidArgumentException;

final class AdminLogReader
{
    public function read(string $kind, array $input): array
    {
        if(!in_array($kind,['auth','audit'],true))throw new InvalidArgumentException('Choose a supported log.');
        $filters=[];
        foreach(['q','event','result','from','to','sort'] as $key){
            $value=$input[$key]??'';
            if(!is_string($value)||!mb_check_encoding($value,'UTF-8')||mb_strlen($value)>100)throw new InvalidArgumentException('Use valid text within 100 characters for filters.');
            $filters[$key]=trim($value);
        }
        $filters['sort']=$filters['sort']?:'newest';
        if(!in_array($filters['sort'],['newest','oldest','actor','event'],true)||!in_array($filters['result'],['','success','failure'],true))throw new InvalidArgumentException('Choose a valid sort or result.');
        if($kind==='audit')$filters['result']='';
        foreach(['from','to'] as $key){
            if($filters[$key]==='')continue;
            $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$filters[$key]);
            if(!$date||$date->format('Y-m-d')!==$filters[$key])throw new InvalidArgumentException('Use a valid calendar date.');
        }
        if($filters['from']!==''&&$filters['to']!==''&&$filters['from']>$filters['to'])throw new InvalidArgumentException('The start date must precede the end date.');
        $table=$kind==='auth'?'auth_logs':'audit_logs';$account=$kind==='auth'?'user_id':'actor_id';$event=$kind==='auth'?'event':'action';
        $where=[];$params=[];
        if($filters['q']!==''){
            $q='%'.strtr($filters['q'],['!'=>'!!','%'=>'!%','_'=>'!_']).'%';
            $where[]="(COALESCE(u.username,'') LIKE ? ESCAPE '!' OR l.{$event} LIKE ? ESCAPE '!'".($kind==='audit'?" OR l.target_type LIKE ? ESCAPE '!'":'').")";
            $params[]=$q;$params[]=$q;if($kind==='audit')$params[]=$q;
        }
        if($filters['event']!==''){$where[]='l.'.$event.'=?';$params[]=$filters['event'];}
        if($kind==='auth'&&$filters['result']!==''){$where[]='l.result=?';$params[]=$filters['result'];}
        if($filters['from']!==''){$where[]='l.created_at>=?';$params[]=$filters['from'].' 00:00:00';}
        if($filters['to']!==''){$where[]='l.created_at<?';$params[]=(new \DateTimeImmutable($filters['to']))->modify('+1 day')->format('Y-m-d').' 00:00:00';}
        $sql=' FROM '.$table.' l LEFT JOIN users u ON u.id=l.'.$account.($where?' WHERE '.implode(' AND ',$where):'');
        $pdo=Database::connection();$count=$pdo->prepare('SELECT COUNT(*)'.$sql);$count->execute($params);$total=(int)$count->fetchColumn();
        $pages=max(1,(int)ceil($total/20));$raw=$input['page']??1;
        $page=(is_string($raw)||is_int($raw))?filter_var($raw,FILTER_VALIDATE_INT):false;
        $page=max(1,min($pages,1000000,$page===false?1:$page));
        $sort=match($filters['sort']){'oldest'=>'l.created_at ASC,l.id ASC','actor'=>"COALESCE(u.username,'') ASC,l.created_at DESC,l.id DESC",'event'=>'l.'.$event.' ASC,l.created_at DESC,l.id DESC',default=>'l.created_at DESC,l.id DESC'};
        $fields=$kind==='auth'?'l.id,l.event,l.result,l.ip_address,l.created_at':'l.id,l.action,l.target_type,l.target_id,l.details,l.created_at';
        $query=$pdo->prepare('SELECT '.$fields.',u.username AS actor_name'.$sql.' ORDER BY '.$sort.' LIMIT 20 OFFSET '.(($page-1)*20));$query->execute($params);
        $events=$pdo->query('SELECT DISTINCT '.$event.' FROM '.$table.' ORDER BY '.$event.' LIMIT 100')->fetchAll(\PDO::FETCH_COLUMN);
        return ['records'=>$query->fetchAll(),'filters'=>$filters,'events'=>$events,'total'=>$total,'page'=>$page,'pages'=>$pages,'kind'=>$kind,'error'=>null];
    }
}
