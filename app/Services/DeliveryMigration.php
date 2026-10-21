<?php
declare(strict_types=1);
namespace App\Services;

use DomainException;
use PDO;

final class DeliveryMigration
{
    public const SQL = "CREATE TABLE order_delivery_details (
        order_id BIGINT UNSIGNED PRIMARY KEY,
        country ENUM('PH') NOT NULL,
        first_name VARCHAR(60) NOT NULL,
        last_name VARCHAR(60) NOT NULL,
        address_line VARCHAR(200) NOT NULL,
        barangay VARCHAR(100) NOT NULL,
        postal_code CHAR(4) NOT NULL,
        city VARCHAR(100) NOT NULL,
        region VARCHAR(16) NOT NULL,
        shipping_method ENUM('standard','priority') NOT NULL,
        shipping_fee DECIMAL(10,2) NOT NULL,
        CONSTRAINT fk_delivery_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    public function run(PDO $pdo,bool $apply): array
    {
        if($pdo->inTransaction())throw new DomainException('Run additive DDL outside business transactions.');
        $q=$pdo->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute(['orders']);
        if($q->fetchColumn()!=='InnoDB')throw new DomainException('Transactional order schema required.');
        $q->execute(['order_delivery_details']);$engine=$q->fetchColumn();$pending=$engine===false;
        if(!$pending){
            if($engine!=='InnoDB')throw new DomainException('Unexpected delivery table engine.');
            $expected=['order_id'=>'bigint','country'=>"enum('ph')",'first_name'=>'varchar(60)','last_name'=>'varchar(60)',
                'address_line'=>'varchar(200)','barangay'=>'varchar(100)','postal_code'=>'char(4)','city'=>'varchar(100)',
                'region'=>'varchar(16)','shipping_method'=>"enum('standard','priority')",'shipping_fee'=>'decimal(10,2)'];
            $fields=$pdo->query('SHOW COLUMNS FROM order_delivery_details')->fetchAll();
            if(array_column($fields,'Field')!==array_keys($expected))throw new DomainException('Unexpected delivery table fields.');
            foreach($fields as $field){$name=$field['Field'];$type=strtolower($field['Type']);
                if($field['Null']!=='NO'||($name==='order_id'?(!str_starts_with($type,'bigint')||!str_contains($type,'unsigned')||$field['Key']!=='PRI'):$type!==$expected[$name]))throw new DomainException('Unexpected delivery field definition.');}
            $ddl=$pdo->query('SHOW CREATE TABLE order_delivery_details')->fetch(PDO::FETCH_NUM)[1];
            if(!str_contains($ddl,'FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE'))throw new DomainException('Delivery foreign key differs.');
        }elseif($apply){$pdo->exec(self::SQL);$this->run($pdo,false);}
        return ['pending_additions'=>$pending?1:0,'applied'=>$pending&&$apply?1:0,'rows_updated'=>0];
    }
}
