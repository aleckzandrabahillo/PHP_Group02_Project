<?php
declare(strict_types=1);

// Safe classroom evidence: synthetic data in a disposable in-memory database only.
// Deliberately do not load bootstrap.php, .env, sessions, mail or provider clients.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/app/Core/Database.php';
require_once dirname(__DIR__).'/app/Core/Env.php';
require_once dirname(__DIR__).'/app/Services/SecurityPolicy.php';
require_once dirname(__DIR__).'/app/Models/User.php';
require_once dirname(__DIR__).'/app/Core/Session.php';
require_once dirname(__DIR__).'/app/Core/Csrf.php';
require_once dirname(__DIR__).'/app/Core/Validator.php';
require_once dirname(__DIR__).'/app/Services/PasswordService.php';
require_once dirname(__DIR__).'/app/Support/helpers.php';

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Validator;
use App\Models\User;
use App\Services\PasswordService;
use App\Services\SecurityPolicy;

$checks = [];
$check = static function(string $name, bool $ok) use (&$checks): void {
    $checks[] = ['test'=>$name, 'status'=>$ok?'PASS':'FAIL'];
};
try {
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $property = new ReflectionProperty(Database::class, 'pdo');
    $property->setValue(null, $pdo);
    $pdo->exec('CREATE TABLE security_settings(setting_key TEXT PRIMARY KEY,setting_value TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE users(id INTEGER PRIMARY KEY,username TEXT,email TEXT)');
    $pdo->exec("INSERT INTO users VALUES(1,'synthetic_customer','customer@example.invalid')");
    foreach (SecurityPolicy::RULES as $key=>[,,$default]) {
        $pdo->prepare('INSERT INTO security_settings VALUES(?,?)')->execute([$key,(string)$default]);
    }
    $defaults = SecurityPolicy::values();
    $check('Default failed-sign-in threshold remains three', $defaults['max_login_attempts']===3 && SecurityPolicy::RULES['max_login_attempts'][2]===3);
    foreach ([1,2,3,4,5,10,100,255] as $value) {
        $input = ['max_login_attempts'=>$value]+$defaults;
        $check('Administrator threshold accepts '.$value, SecurityPolicy::validate($input)['max_login_attempts']===$value);
        $pdo->prepare('UPDATE security_settings SET setting_value=? WHERE setting_key=?')->execute([(string)$value,'max_login_attempts']);
        $check('Saved threshold is honored at '.$value, SecurityPolicy::values()['max_login_attempts']===$value);
    }
    foreach ([0,-1,256,PHP_INT_MAX,1.5,'1.5','invalid',['5'],true,null] as $value) {
        $denied = false;
        try { SecurityPolicy::validate(['max_login_attempts'=>$value]+$defaults); } catch (InvalidArgumentException) { $denied=true; }
        $check('Malformed or unsupported threshold rejected', $denied);
    }
    $pdo->prepare('UPDATE security_settings SET setting_value=? WHERE setting_key=?')->execute(['3','max_login_attempts']);
    $user = new User();
    $check('Normal bound-parameter lookup finds synthetic account', ($user->findByIdentifier('synthetic_customer')['id']??null)===1);
    $check('SQL-like identifier is treated as data', $user->findByIdentifier("' OR 1=1 --")===null);
    $check('Output helper encodes script markup and quotes', e('<script>"&</script>')==='&lt;script&gt;&quot;&amp;&lt;/script&gt;');
    $_SESSION=[];
    $token = Csrf::token();
    $check('Matching form token accepted', Csrf::validate($token));
    foreach ([null,'wrong',[$token],123] as $bad) $check('Missing or malformed form token rejected', !Csrf::validate($bad));
    $_SESSION['_csrf']=bin2hex(random_bytes(32));
    $check('Token from a different session rejected', !Csrf::validate($token));
    $password='Synthetic!'.bin2hex(random_bytes(12)).'aA1';
    $hash=password_hash($password,PASSWORD_DEFAULT);
    $check('Password stored as a one-way hash', $hash!==$password && password_verify($password,$hash));
    $check('Wrong password rejected', !password_verify($password.'wrong',$hash));
    $check('Password policy accepts valid synthetic password', Validator::passwordError($password)===null);
    foreach (['short','abcdefghijklmnop','ABCDEFGHIJKLMNO1!','Abcdefghijklmnop!','Abcdefghijklm123'] as $bad) {
        $check('Weak password rejected', Validator::passwordError($bad)!==null);
    }
    $check('Customer cannot authenticate while inactive', !User::canAuthenticate(['role'=>'customer','status'=>'inactive']));
    $check('Persistent lock remains locked even after its review time', User::isLocked(['lock_requires_admin'=>1,'locked_until'=>'2000-01-01 00:00:00']));
    $check('Unknown role is rejected', !User::canAuthenticate(['role'=>'invented','status'=>'active']));
    $_ENV['PASSWORD_EXPIRY_DAYS']='90';
    $check('Password is valid before its 90-day deadline', !PasswordService::expired(['password_changed_at'=>gmdate('c',time()-89*86400)]));
    $check('Password expires after its 90-day deadline', PasswordService::expired(['password_changed_at'=>gmdate('c',time()-91*86400)]));
    $changed=gmdate('c',time());
    $check('Password deadline is exactly 90 days from its change', PasswordService::expiresAt(['password_changed_at'=>$changed])===strtotime($changed)+90*86400);
    $failed=count(array_filter($checks,static fn(array $row):bool=>$row['status']==='FAIL'));
    echo json_encode(['database'=>'SQLite :memory: — synthetic unit evidence, not MySQL concurrency acceptance','private_configuration_loaded'=>false,'external_services_contacted'=>false,'checks'=>$checks,'passed'=>count($checks)-$failed,'failed'=>$failed],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),PHP_EOL;
    exit($failed?1:0);
} catch (Throwable $e) {
    fwrite(STDERR,'Evidence checks stopped: '.get_class($e).' at line '.$e->getLine().'; private details suppressed.'.PHP_EOL);
    exit(1);
}
