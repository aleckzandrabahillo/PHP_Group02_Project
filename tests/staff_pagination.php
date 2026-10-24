<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/app/Support/helpers.php';
$checks=[];
$verify=static function(string $name,bool $ok)use(&$checks):void{$checks[]=['test'=>$name,'status'=>$ok?'PASS':'FAIL'];};
$url=static fn(int $n):string=>'/admin/users?'.http_build_query(['q'=>'care & "safe"','status'=>'active','role'=>'catalog_manager','page'=>$n]);
foreach([[1,1],[-1,0],[4,-2]] as [$page,$pages])$verify('No unnecessary controls for empty/single page '.$page.'/'.$pages,pagination_nav($page,$pages,$url)==='');
foreach([[1,20],[6,20],[20,20],[-100,20],[100,20],[500,1000000]] as [$page,$pages]){
 $actual=max(1,min($page,$pages));$html=pagination_nav($page,$pages,$url);$prefix=$page.'/'.$pages.' ';
 $verify($prefix.'one bounded current page',substr_count($html,'aria-current="page"')===1&&str_contains($html,'aria-current="page">'.$actual.'</span>'));
 $verify($prefix.'previous bound',str_contains($html,'rel="prev"')===($actual>1));$verify($prefix.'next bound',str_contains($html,'rel="next"')===($actual<$pages));
 preg_match_all('/href="([^"]+)"/',$html,$links);$safe=true;
 foreach($links[1] as $href){parse_str((string)parse_url(html_entity_decode($href,ENT_QUOTES,'UTF-8'),PHP_URL_QUERY),$query);$safe=$safe&&($query['q']??null)==='care & "safe"'&&($query['status']??null)==='active'&&($query['role']??null)==='catalog_manager'&&isset($query['page'])&&(int)$query['page']>=1&&(int)$query['page']<=$pages;}
 $verify($prefix.'filter values preserved and every destination bounded',$safe);$verify($prefix.'large totals do not create unbounded markup',count($links[1])<=8);
}
$html=pagination_nav(2,4,static fn(int $n):string=>'/admin/users?q=" onclick="bad&page='.$n,'" onmouseover="bad');
$verify('Pager label escaped',str_contains($html,'aria-label="&quot; onmouseover=&quot;bad"'));
$verify('Pager destinations escaped',!str_contains($html,'href="/admin/users?q=" onclick='));
$verify('Window is clamped',strlen(pagination_nav(500,1000000,$url,'Pages',PHP_INT_MAX))<5000);
$verify('Status preserves readable label and encodes hostile text',str_contains(staff_status('<img src=x onerror=bad>'),'&lt;img')&&!str_contains(staff_status('<img src=x onerror=bad>'),'<img'));
$verify('Unknown icon name cannot inject markup',!str_contains(staff_icon('" onload="bad'),'onload'));
$verify('Icons beside labels are decorative',str_contains(staff_icon('orders'),'aria-hidden="true"'));
$failed=array_values(array_filter($checks,static fn($c)=>$c['status']==='FAIL'));$options=getopt('',['output:']);if(isset($options['output']))file_put_contents($options['output'],json_encode(['checks'=>$checks],JSON_PRETTY_PRINT));echo json_encode(['suite'=>'staff_pagination','pass'=>count($checks)-count($failed),'fail'=>count($failed),'failed'=>$failed]),PHP_EOL;exit($failed?1:0);
