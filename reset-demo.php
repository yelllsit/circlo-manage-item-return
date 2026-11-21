<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/src/Database.php';
$storage=getenv('CIRCLO_STORAGE') ?: __DIR__.'/storage';
$database=$storage.'/circlo.sqlite';
if(is_file($database)) {
    $backups=$storage.'/backups';
    if(!is_dir($backups))mkdir($backups,0700,true);
    $backup=$backups.'/circlo-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)).'.sqlite';
    if(!rename($database,$backup)){fwrite(STDERR,"Database tidak dapat dicadangkan. Hentikan server dan coba lagi.\n");exit(1);}
    echo "Database sebelumnya dicadangkan: $backup\n";
}
Database::open($database);
echo "Data demo baru siap. Nyalakan server dan masuk kembali.\n";
