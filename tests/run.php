<?php
declare(strict_types=1);
$root=dirname(__DIR__);
if(!is_file($root.'/src/ReturnService.php')){fwrite(STDERR,"FAIL: Manage Item Return service is not implemented.\n");exit(1);}
require $root.'/src/Database.php';require $root.'/src/ReturnService.php';
function check(bool $condition,string $message='Assertion failed'):void{if(!$condition)throw new RuntimeException($message);}
function rejects(callable $fn):void{try{$fn();}catch(DomainException){return;}throw new RuntimeException('Expected operation to be rejected');}
function fixture():ReturnService{return new ReturnService(Database::open(':memory:'));}
$tests=[
 'borrower inserts return without completing transaction'=>function(){
    $s=fixture();$id=$s->markReturned(1,1);check($id>0);$t=$s->transactionDetails(1,1);
    check($t['return_status']==='awaiting');check($t['status']==='active');check($t['availability']==='booked');
 },
 'owner confirms return and atomically completes transaction'=>function(){
    $s=fixture();$s->markReturned(1,1);$s->confirmReturn(2,1);$t=$s->transactionDetails(1,1);
    check($t['return_status']==='confirmed');check($t['status']==='completed');check($t['availability']==='available');check($t['confirmed_at']!==null);
 },
 'owner cannot submit on behalf of borrower'=>function(){rejects(fn()=>fixture()->markReturned(2,1));},
 'borrower cannot confirm their own return'=>function(){
    $s=fixture();$s->markReturned(1,1);rejects(fn()=>$s->confirmReturn(1,1));check($s->transactionDetails(1,1)['status']==='active');
 },
 'unrelated student cannot read or mutate transaction'=>function(){
    $s=fixture();rejects(fn()=>$s->transactionDetails(3,1));rejects(fn()=>$s->markReturned(3,1));rejects(fn()=>$s->confirmReturn(3,1));
 },
 'owner must wait for borrower return submission'=>function(){
    $s=fixture();rejects(fn()=>$s->confirmReturn(2,1));check($s->transactionDetails(2,1)['status']==='active');
 },
 'duplicate submission cannot create second item return'=>function(){
    $s=fixture();$s->markReturned(1,1);rejects(fn()=>$s->markReturned(1,1));
    check((int)$s->db->query('SELECT COUNT(*) FROM item_returns WHERE transaction_id=1')->fetchColumn()===1);
 },
 'duplicate confirmation is rejected'=>function(){
    $s=fixture();$s->markReturned(1,1);$s->confirmReturn(2,1);rejects(fn()=>$s->confirmReturn(2,1));
 },
 'overdue return is allowed and recorded as late'=>function(){
    $s=fixture();$s->markReturned(1,2);$t=$s->transactionDetails(1,2);check($t['is_late']===1);check($t['return_date']===date('Y-m-d'));
 },
 'on-time return records correct server date'=>function(){
    $s=fixture();$s->markReturned(1,1);$t=$s->transactionDetails(1,1);check($t['is_late']===0);check($t['return_date']===date('Y-m-d'));
 },
 'completed transaction cannot be returned again'=>function(){rejects(fn()=>fixture()->markReturned(1,4));},
 'item remains booked while another transaction is active'=>function(){
    $s=fixture();$s->db->exec("INSERT INTO transactions(item_id,borrower_id,owner_id,start_date,end_date,total_price) VALUES(1,3,2,'".date('Y-m-d',strtotime('+4 days'))."','".date('Y-m-d',strtotime('+6 days'))."',0)");
    $s->markReturned(1,1);$s->confirmReturn(2,1);check($s->transactionDetails(1,1)['availability']==='booked');
 },
 'transaction list only contains the signed-in students records'=>function(){
    $s=fixture();$rows=$s->transactions(1,'active');check(count($rows)===3);
    foreach($rows as $r)check($r['borrower_id']===1||$r['owner_id']===1);
    check(count($s->transactions(3,'active'))===1);
 },
 'demo login checks password and unknown users'=>function(){
    $s=fixture();check($s->login('khayel@circlo.test','Circlo123!')['id']===1);
    rejects(fn()=>$s->login('khayel@circlo.test','wrong'));rejects(fn()=>$s->login('no@circlo.test','Circlo123!'));
 },
 'return state survives opening a new connection'=>function(){
    $path=tempnam(sys_get_temp_dir(),'circlo-return-');
    try{$s=new ReturnService(Database::open($path));$s->markReturned(1,1);unset($s);$s=new ReturnService(Database::open($path));check($s->transactionDetails(2,1)['return_status']==='awaiting');}
    finally{unlink($path);}
 }
];
$failed=0;foreach($tests as $name=>$test){try{$test();echo "PASS $name\n";}catch(Throwable $e){$failed++;echo "FAIL $name: {$e->getMessage()}\n";}}
echo count($tests)." tests, $failed failures\n";exit($failed?1:0);
