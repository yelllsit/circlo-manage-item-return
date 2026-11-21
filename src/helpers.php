<?php
declare(strict_types=1);
final class HttpError extends RuntimeException
{
    public function __construct(public readonly int $status, string $message) {parent::__construct($message);}
}
function h(mixed $value): string {return htmlspecialchars(is_scalar($value)?(string)$value:'',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function get(string $key,string $default=''): string {return isset($_GET[$key])&&is_string($_GET[$key])?$_GET[$key]:$default;}
function post(string $key): string {return isset($_POST[$key])&&is_string($_POST[$key])?$_POST[$key]:'';}
function url(string $page,array $parameters=[]): string {return '?'.http_build_query(['page'=>$page]+$parameters);}
function go(string $page,array $parameters=[]): never {header('Location: '.url($page,$parameters),true,303);exit;}
function token(string $action): void {echo '<input type="hidden" name="csrf" value="'.h($_SESSION['csrf']).'"><input type="hidden" name="action" value="'.h($action).'">';}
function hidden(string $name,mixed $value): void {echo '<input type="hidden" name="'.h($name).'" value="'.h($value).'">';}
function flash(string $text): void {$_SESSION['flash']=$text;}
function dateLabel(?string $date,bool $time=false): string {return $date?date($time?'d M Y, H:i':'d M Y',strtotime($date)).($time?' WIB':''):'—';}
function money(int $amount): string {return $amount===0?'Gratis':'Rp'.number_format($amount,0,',','.');}
function reference(int $id): string {return 'TRX-'.str_pad((string)$id,4,'0',STR_PAD_LEFT);}
function statusLabel(string $status): string {return ['active'=>'Sedang dipinjam','awaiting'=>'Menunggu konfirmasi','completed'=>'Selesai','confirmed'=>'Dikonfirmasi','available'=>'Tersedia','booked'=>'Sedang dipinjam'][$status]??$status;}
function badge(string $status): string {return '<span class="badge badge-'.h($status).'">'.h(statusLabel($status)).'</span>';}
function state(array $transaction): string {return $transaction['status']==='completed'?'completed':($transaction['return_status']==='awaiting'?'awaiting':'active');}
function late(array $transaction): bool {return $transaction['status']==='active' && !$transaction['return_id'] && date('Y-m-d')>$transaction['end_date'];}
function imagePath(array $transaction): string {return '/assets/'.basename($transaction['image']);}
function initials(string $name): string {return strtoupper(substr($name,0,1));}
function icon(string $name): string {
    $paths=[
        'leaf'=>'<path d="M20 3C7 3 2 8 5 15s15 6 15-12ZM4 21 15 10"/>',
        'box'=>'<path d="m3 7 9-4 9 4-9 4-9-4v10l9 4 9-4V7M12 11v10M7 5l10 4"/>',
        'swap'=>'<path d="M3 7h17l-4-4M21 17H4l4 4M20 7l-4 4M4 17l4-4"/>',
        'check'=>'<path d="m5 12 4 4L20 5"/>',
        'clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 6v6l4 3"/>',
        'arrow'=>'<path d="M4 12h16m-6-6 6 6-6 6"/>',
        'logout'=>'<path d="M9 4H3v16h6M8 12h13m-5-5 5 5-5 5"/>',
        'alert'=>'<path d="m12 3 10 18H2L12 3ZM12 9v5M12 17h.01"/>',
        'user'=>'<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
    ];
    return '<svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name]??$paths['box']).'</svg>';
}
