<?php
declare(strict_types=1);
$requestPath=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);
if (PHP_SAPI==='cli-server' && str_starts_with((string)$requestPath,'/assets/')) {
    $real=realpath(__DIR__.$requestPath);
    if($real && str_starts_with($real,__DIR__.'/assets/') && is_file($real))return false;
}
require dirname(__DIR__).'/src/Database.php';
require dirname(__DIR__).'/src/ReturnService.php';
require dirname(__DIR__).'/src/helpers.php';

try {
    $storage=getenv('CIRCLO_STORAGE') ?: dirname(__DIR__).'/storage';
    foreach([$storage,$storage.'/sessions'] as $dir)if(!is_dir($dir))mkdir($dir,0700,true);
    session_save_path($storage.'/sessions');session_name('circlo_return');
    ini_set('session.use_strict_mode','1');
    session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off']);
    session_start();$_SESSION['csrf']??=bin2hex(random_bytes(32));
    header('X-Content-Type-Options: nosniff');header('Cache-Control: no-store');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; img-src 'self'; style-src 'self'; script-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    $service=new ReturnService(Database::open($storage.'/circlo.sqlite'));
    $user=isset($_SESSION['student_id'])?$service->student((int)$_SESSION['student_id']):null;
    $page=get('page','transactions');$error=null;$transaction=null;
    if(!$user && $page!=='login')go('login');
    if($user && $page==='login')go('transactions');
    $templates=['login'=>'login','transactions'=>'transactions','transaction'=>'detail','return'=>'return-form','confirmation'=>'confirmation'];
    try {
        if(!isset($templates[$page]))throw new HttpError(404,'Halaman tidak ditemukan.');
        if(in_array($page,['transaction','return','confirmation'],true)) {
            try{$transaction=$service->transactionDetails((int)$user['id'],(int)get('id'));}
            catch(DomainException $e){throw new HttpError(str_contains($e->getMessage(),'akses')?403:404,$e->getMessage());}
            if($page==='return' && $transaction['borrower_id']!==$user['id'])throw new HttpError(403,'Hanya peminjam yang dapat mengisi form pengembalian.');
            if($page==='confirmation' && $transaction['owner_id']!==$user['id'])throw new HttpError(403,'Hanya pemilik yang dapat mengonfirmasi pengembalian.');
        }
        if($_SERVER['REQUEST_METHOD']==='POST') {
            if(!hash_equals($_SESSION['csrf'],post('csrf')))throw new HttpError(403,'Sesi form tidak valid. Muat ulang halaman sebelum mencoba lagi.');
            try {
                $action=post('action');
                if($action==='login') {
                    $account=$service->login(post('email'),post('password'));session_regenerate_id(true);
                    $_SESSION['student_id']=$account['id'];$_SESSION['csrf']=bin2hex(random_bytes(32));go('transactions');
                }
                if(!$user)throw new HttpError(403,'Silakan masuk terlebih dahulu.');
                if($action==='logout'){$_SESSION=[];session_regenerate_id(true);go('login');}
                if(!in_array($action,['submit_return','confirm_return'],true))throw new DomainException('Tindakan tidak dikenali.');
                if(post('acknowledge')!=='1')throw new DomainException('Centang pernyataan konfirmasi sebelum melanjutkan.');
                $id=(int)post('transaction_id');
                if($action==='submit_return'){$service->markReturned((int)$user['id'],$id);flash('Pengembalian berhasil diajukan. Tunggu konfirmasi pemilik barang.');}
                else{$service->confirmReturn((int)$user['id'],$id);flash('Barang sudah dikonfirmasi kembali. Transaksi selesai.');}
                go('transaction',['id'=>$id]);
            } catch(DomainException $e) {http_response_code(422);$error=$e->getMessage();}
        }
        ob_start();require dirname(__DIR__).'/views/'.$templates[$page].'.php';$content=ob_get_clean();
    } catch(HttpError $e) {
        http_response_code($e->status);$title='Halaman tidak tersedia';
        $content='<section class="empty-state"><div class="empty-icon">'.icon('alert').'</div><h1>Halaman tidak tersedia</h1><p>'.h($e->getMessage()).'</p><a class="btn" href="'.h(url($user?'transactions':'login')).'">Kembali</a></section>';
    }
    require dirname(__DIR__).'/views/layout.php';
} catch(Throwable $e) {
    if(ob_get_level())ob_end_clean();error_log((string)$e);http_response_code(500);
    echo '<!doctype html><html lang="id"><meta charset="utf-8"><title>Circlo</title><h1>Circlo belum dapat dibuka</h1><p>Pastikan ekstensi pdo_sqlite tersedia dan folder storage dapat ditulis. Periksa log server untuk detail.</p></html>';
}
