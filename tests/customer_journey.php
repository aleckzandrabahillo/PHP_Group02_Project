<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('AVELA_HAIR_TEST_LIBRARY', true);
require __DIR__.'/hair_profiles.php';

function journeyProductIds(string $html): array {
    preg_match_all('~class="product-card-main" href="[^"?]*\?id=([0-9]+)"~', $html, $matches);
    return array_map('intval', $matches[1]);
}
function journeyLink(string $html, string $label): string {
    preg_match('~<a\b[^>]*href="([^"]+)"[^>]*>\s*'.preg_quote($label,'~').'\s*</a>~', $html, $match);
    return html_entity_decode($match[1]??'', ENT_QUOTES, 'UTF-8');
}

try {
    $o=getopt('', ['expect-database:','expect-port:','expect-datadir:','base-url:','session-directory:','output:']);
    $pdo=App\Core\Database::connection();
    $id=$pdo->query('SELECT DATABASE() db,@@port port,@@datadir datadir')->fetch();
    $base=$o['base-url']??'';$url=parse_url($base);$sessions=realpath($o['session-directory']??'');
    $norm=static fn($p)=>strtolower(rtrim(str_replace('\\','/',$p),'/'));
    if(!preg_match('/^avela_.*test_[A-Za-z0-9_]+$/D',$id['db']) || ($o['expect-database']??'')!==$id['db']
        || (int)($o['expect-port']??0)!==(int)$id['port'] || in_array((int)$id['port'],[3306,13307],true)
        || $norm($o['expect-datadir']??'')!==$norm($id['datadir']) || env('DB_HOST')!=='127.0.0.1' || env('APP_ENV')!=='development'
        || !$sessions || str_starts_with($norm($sessions),$norm(dirname(__DIR__)).'/')
        || ($url['host']??'')!=='127.0.0.1' || in_array((int)($url['port']??80),[80,18080],true))throw new RuntimeException('Disposable-only guard');
    ini_set('session.save_path',$sessions);$checks=[];
    $productsBefore=$pdo->query('SELECT * FROM products ORDER BY id')->fetchAll();
    $ordersBefore=$pdo->query('SELECT * FROM orders ORDER BY id')->fetchAll();
    $users=new App\Models\User();$profiles=new App\Models\HairProfile();$clients=[];$fixtureIds=[];
    foreach(['journey_a','journey_b'] as $prefix){
        $name=$prefix.'_'.bin2hex(random_bytes(5));
        $uid=$users->createCustomer(['username'=>$name,'email'=>$name.'@example.invalid','full_name'=>'Synthetic Journey Customer','contact_no'=>'+639123456789','password_hash'=>password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT)]);
        $users->activate($uid);$fixtureIds[]=$uid;$clients[]=hairClient($uid);
    }
    [$a,$b]=$fixtureIds;[$client,$other]=$clients;
    verifyHair('No profile shows assessment action',str_contains(hairRequest('/recommendations',$client)['body'],'Create hair profile'));
    $form=hairForm($client);$form['treatments']=[];
    $invalid=hairRequest('/assessment',$client,array_replace($form,['hair_texture'=>'invalid']));
    verifyHair('Invalid assessment retains values and error summary',$invalid['code']===422&&str_contains($invalid['body'],'data-error-summary')&&str_contains($invalid['body'],'My scalp is oily'));
    $response=hairRequest('/assessment',$client,$form);
    verifyHair('Assessment redirects to current results',$response['code']===303&&(bool)array_filter($response['headers'],static fn($h)=>strtolower(trim($h))==='location: /recommendations'));
    $results=hairRequest('/recommendations',$client);
    foreach(['Your saved hair profile','Priorities:','From your description','Wavy','Oily roots / scalp','Dry lengths or ends','Matching details','Your suggested routine'] as $text)verifyHair('Results display saved evidence: '.$text,$results['code']===200&&str_contains($results['body'],$text));
    verifyHair('Results do not echo raw concern text',!str_contains($results['body'],$form['concern_text']));
    verifyHair('Other customer cannot view owner results',!str_contains(hairRequest('/recommendations?user_id='.$a,$other)['body'],'Your saved hair profile'));
    $plan=(new App\Services\RecommendationService())->forUser($a);
    verifyHair('Actual catalog produces available routine',count($plan['routine']['items'])>=2&&count($plan['ranked'])>=2);
    $post=['_token'=>$client['csrf'],'plan_fingerprint'=>$plan['fingerprint']];
    verifyHair('Routine save still enforces CSRF',hairRequest('/routine',$client,array_replace($post,['_token'=>'bad']))['code']===419);
    verifyHair('Routine saved through existing route',hairRequest('/routine',$client,$post)['code']===303);
    $routine=(new App\Models\Routine())->stateForUser($a);
    verifyHair('Saved routine explains current items',!$routine['stale']&&count($routine['items'])===count($plan['routine']['items'])&&str_contains(hairRequest('/routine',$client)['body'],'Add to bag'));
    $product=$plan['routine']['items'][0]['product'];$productId=(int)$product['id'];
    verifyHair('Product details show real match product',str_contains(hairRequest('/product?id='.$productId,$client)['body'],htmlspecialchars($product['name'],ENT_QUOTES,'UTF-8')));
    $add=hairRequest('/cart/add',$client,['_token'=>$client['csrf'],'product_id'=>$productId,'quantity'=>1,'return_to'=>'/routine']);
    verifyHair('Routine product can be added to bag',in_array($add['code'],[302,303],true)&&(new App\Models\Cart())->countForUser($a)===1);
    verifyHair('Bag drawer displays actual matched product',str_contains(hairRequest('/cart?panel=1',$client)['body'],htmlspecialchars($product['name'],ENT_QUOTES,'UTF-8')));
    verifyHair('Other customer bag remains empty',(new App\Models\Cart())->countForUser($b)===0);
    $drawer=hairRequest('/cart?panel=1',$client);
    verifyHair('Bag fragment contains owner items and working CSRF forms',$drawer['code']===200&&str_contains($drawer['body'],'data-bag-fragment')&&str_contains($drawer['body'],'data-cart-item')&&str_contains($drawer['body'],'name="_token"')&&str_contains($drawer['body'],'name="product_id"'));
    verifyHair('Bag fragment is private and not cacheable',(bool)array_filter($drawer['headers'],static fn($header)=>str_starts_with(strtolower($header),'cache-control:')&&str_contains(strtolower($header),'no-store')&&str_contains(strtolower($header),'private')));
    $otherDrawer=hairRequest('/cart?panel=1&user_id='.$a,$other);
    verifyHair('Bag fragment ignores foreign user selector',$otherDrawer['code']===200&&str_contains($otherDrawer['body'],'data-item-count="0"')&&!str_contains($otherDrawer['body'],'data-cart-item'));
    verifyHair('Bag fragment requires authentication',in_array(hairRequest('/cart?panel=1')['code'],[302,303],true));
    foreach(['image_admin','image_catalog_manager'] as $staffName){$staffUser=$users->findByIdentifier($staffName);verifyHair('Bag fragment rejects staff: '.$staffName,$staffUser&&hairRequest('/cart?panel=1',hairClient((int)$staffUser['id']))['code']===403);}
    $checkout=hairRequest('/checkout',$client);
    verifyHair('Checkout provides structured delivery and quoted shipping',$checkout['code']===200&&str_contains($checkout['body'],'name="postal_code"')&&str_contains($checkout['body'],'name="region"')&&str_contains($checkout['body'],'Cash on delivery')&&!str_contains($checkout['body'],'does not arrange delivery'));
    $fresh=hairForm($client);$fresh['treatments']=[];$fresh['hair_texture']='straight';$fresh['scalp_type']='normal_scalp';$fresh['concern_text']='curly hair with oily scalp';
    verifyHair('Changed assessment saves',hairRequest('/assessment',$client,$fresh)['code']===303);
    $conflict=hairRequest('/recommendations',$client)['body'];
    verifyHair('Conflicting interpretation is explained',str_contains($conflict,'Your description differs from a selected texture or scalp answer'));
    verifyHair('Structured answers retain precedence',$profiles->forUser($a)['hair_texture']==='straight'&&!array_filter($profiles->forUser($a)['tags'],static fn($tag)=>$tag['type']==='texture'&&$tag['code']==='curly'));
    verifyHair('Outdated saved routine is identified',(new App\Models\Routine())->stateForUser($a)['stale']&&str_contains(hairRequest('/routine',$client)['body'],'has changed since this routine was saved'));
    verifyHair('Old results cannot overwrite routine',hairRequest('/routine',$client,$post)['code']===409);
    $unknown=hairForm($other);foreach(['hair_texture','scalp_type','hair_condition'] as $field)$unknown[$field]='unsure';
    $unknown['concerns']=[];$unknown['treatments']=[];$unknown['concern_text']='';
    verifyHair('Not sure profile can be saved',hairRequest('/assessment',$other,$unknown)['code']===303);
    $none=hairRequest('/recommendations',$other)['body'];
    verifyHair('Incomplete characteristics are explained',str_contains($none,'Some details are still “Not sure”'));
    verifyHair('No match state offers catalog without invented products',str_contains($none,'No supported matches yet.')&&str_contains($none,'Browse all products')&&!str_contains($none,'class="matching-product"'));
    $sorts=['newest'=>'p.created_at DESC,p.id DESC','name_asc'=>'p.name ASC,p.id DESC','price_asc'=>'p.price ASC,p.name ASC,p.id DESC','price_desc'=>'p.price DESC,p.name ASC,p.id DESC'];
    foreach($sorts as $sort=>$order){
        $expected=array_map('intval',$pdo->query('SELECT p.id FROM products p JOIN categories c ON c.id=p.category_id WHERE p.status="active" AND c.status="active" ORDER BY '.$order)->fetchAll(PDO::FETCH_COLUMN));
        $page=hairRequest('/shop?sort='.$sort);
        $seen=journeyProductIds($page['body']);$allPagesOk=$page['code']===200;
        for($number=2;$number<=max(1,(int)ceil(count($expected)/12));$number++){
            $next=hairRequest('/shop?sort='.$sort.'&page='.$number);$allPagesOk=$allPagesOk&&$next['code']===200;
            $seen=array_merge($seen,journeyProductIds($next['body']));
        }
        verifyHair('Database-backed catalog ordering across all actual pages: '.$sort,$allPagesOk&&$seen===$expected&&count(journeyProductIds($page['body']))===min(12,count($expected)));
        verifyHair('Filter/search form retains sort: '.$sort,str_contains($page['body'],'name="sort" value="'.$sort.'"'));
    }
    $default=journeyProductIds(hairRequest('/shop')['body']);
    foreach(['sort=not_allowed','sort%5B%5D=price_desc','sort=price_desc%3BDROP+TABLE+products'] as $query)verifyHair('Invalid sort safely defaults: '.$query,journeyProductIds(hairRequest('/shop?'.$query)['body'])===$default);
    $filtered=hairRequest('/shop?sort=price_desc&q=shampoo')['body'];
    $categoryId=(int)$pdo->query("SELECT id FROM categories WHERE name='Shampoo'")->fetchColumn();
    verifyHair('Filter drawer preserves category controls, search and sort',str_contains($filtered,'name="category[]" value="'.$categoryId.'"')&&str_contains($filtered,'name="q" value="shampoo"')&&str_contains($filtered,'name="sort" value="price_desc"'));
    $category='/shop?'.http_build_query(['sort'=>'price_desc','q'=>'shampoo','category'=>[$categoryId]]);
    verifyHair('Applied category and search retain database ordering',journeyProductIds(hairRequest($category)['body'])===array_map('intval',array_column((new App\Models\Product())->catalog('shampoo',['categories'=>[$categoryId]],'price_desc'),'id')));
    $empty=hairRequest('/shop?sort=price_asc&q=not-a-product-'.bin2hex(random_bytes(4)));
    verifyHair('Empty sorted search remains valid',$empty['code']===200&&journeyProductIds($empty['body'])===[]&&str_contains($empty['body'],'name="sort" value="price_asc"'));
    $suggestions=json_decode(hairRequest('/search/suggest?q=shampoo&sort=price_desc')['body'],true);
    parse_str(parse_url($suggestions['allResultsUrl']??'',PHP_URL_QUERY)??'', $suggestionParams);
    verifyHair('Explicit catalog suggestion API remains compatible with selected sort',($suggestionParams['sort']??'')==='price_desc'&&($suggestionParams['q']??'')==='shampoo');
    $home=hairRequest('/')['body'];
    foreach(['home'=>$home,'filtered shop'=>$filtered] as $label=>$html){
        preg_match('~<form[^>]*class="nav-search-field"[^>]*>(.*?)</form>~s',$html,$headerForm);
        verifyHair('Header search stays general on '.$label,preg_match('~<form[^>]*action="/search"[^>]*class="nav-search-field"~',$html)&&!str_contains($headerForm[1]??'','type="hidden"')&&!str_contains($html,'data-catalog-filters='));
        verifyHair('Header search has a real no-script destination on '.$label,preg_match('~<a[^>]*href="/search"[^>]*data-nav-open="search"~',$html)===1);
    }
    $general=json_decode(hairRequest('/search/suggest?q=shampoo')['body'],true);
    verifyHair('General suggestions use full search and genuine product prices',str_starts_with($general['allResultsUrl']??'','/search?')&&count($general['products'])>0&&str_starts_with($general['products'][0]['priceLabel']??'','₱'));
    verifyHair('Homepage category strip removed',!str_contains($home,'class="category-tabs"'));
    verifyHair('Homepage requested verbose copy removed',!str_contains($home,'Selected from our demonstration collection.')&&!str_contains($home,'in English or Taglish'));
    verifyHair('Homepage hero and real product links preserved',str_contains($home,'editorial-hero')&&count(journeyProductIds($home))===4);
    verifyHair('Products and inventory preserved',$productsBefore===$pdo->query('SELECT * FROM products ORDER BY id')->fetchAll());
    verifyHair('No orders created',$ordersBefore===$pdo->query('SELECT * FROM orders ORDER BY id')->fetchAll());
    $result=['checks'=>$checks,'pass'=>count(array_filter($checks,static fn($r)=>$r['status']==='PASS')),'fail'=>count(array_filter($checks,static fn($r)=>$r['status']==='FAIL'))];
    if(isset($o['output']))file_put_contents($o['output'],json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    ob_end_flush();exit($result['fail']?1:0);
}catch(Throwable $e){fwrite(STDERR,'Customer journey tests stopped: '.get_class($e).' at line '.$e->getLine().'; inspect protected test diagnostics. No private values printed.'.PHP_EOL);exit(1);}
