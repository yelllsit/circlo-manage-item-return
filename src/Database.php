<?php
declare(strict_types=1);

final class Database
{
    public static function open(string $path): PDO
    {
        date_default_timezone_set('Asia/Jakarta');
        if ($path !== ':memory:' && !is_dir(dirname($path))) {
            mkdir(dirname($path), 0700, true);
        }
        $db = new PDO('sqlite:'.$path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $db->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000;');
        $db->exec(file_get_contents(dirname(__DIR__).'/database/schema.sql'));
        // Lock the first initialization so two browser sessions cannot both seed it.
        $db->exec('BEGIN IMMEDIATE');
        try {
            if (!(int)$db->query('SELECT COUNT(*) FROM students')->fetchColumn()) {
                self::seed($db);
            }
            $db->exec('COMMIT');
        } catch (Throwable $e) {
            $db->exec('ROLLBACK');
            throw $e;
        }
        return $db;
    }

    private static function seed(PDO $db): void
    {
        $password = password_hash('Circlo123!', PASSWORD_DEFAULT);
        $insert = $db->prepare('INSERT INTO students(name,student_id,email,password_hash) VALUES(?,?,?,?)');
        foreach ([
            ['Khayel Sitorus', '5026231222', 'khayel@circlo.test'],
            ['Nadia Putri', '5026241007', 'nadia@circlo.test'],
            ['Arya Pratama', '5026241051', 'arya@circlo.test'],
        ] as $student) {
            $insert->execute([...$student, $password]);
        }
        $insert = $db->prepare('INSERT INTO items(owner_id,name,description,condition,image,pickup_location,availability) VALUES(?,?,?,?,?,?,?)');
        foreach ([
            [2,'Canon EOS 700D','Kamera DSLR, lensa kit 18–55 mm, baterai, charger, dan tas kamera.','Baik','camera.svg','Perpustakaan ITS, pintu utama','booked'],
            [2,'Tenda camping 4 orang','Tenda dome, flysheet, alas, pasak, dan tas penyimpanan.','Seperti baru','tent.svg','Student Center ITS','booked'],
            [1,'Buku Clean Code','Buku Robert C. Martin, edisi bahasa Inggris.','Baik','book.svg','Teknik Informatika ITS','booked'],
            [2,'Toolkit serbaguna','Set obeng, tang, kunci, dan meteran untuk proyek kuliah.','Baik','toolkit.svg','Perpustakaan ITS','available'],
        ] as $item) {
            $insert->execute($item);
        }
        $day = fn(string $offset): string => date('Y-m-d', strtotime($offset));
        $now = date('Y-m-d H:i:s');
        $insert = $db->prepare('INSERT INTO transactions(item_id,borrower_id,owner_id,start_date,end_date,total_price,status,completed_at) VALUES(?,?,?,?,?,?,?,?)');
        foreach ([
            [1,1,2,$day('-2 days'),$day('+2 days'),175000,'active',null],
            [2,1,2,$day('-7 days'),$day('-1 day'),175000,'active',null],
            [3,3,1,$day('-5 days'),$day('+1 day'),0,'active',null],
            [4,1,2,$day('-9 days'),$day('-6 days'),0,'completed',$now],
        ] as $transaction) {
            $insert->execute($transaction);
        }
        $db->prepare("INSERT INTO item_returns(transaction_id,return_date,status,is_late,created_at,confirmed_at) VALUES(3,?,'awaiting',0,?,NULL),(4,?,'confirmed',0,?,?)")
            ->execute([$day('today'),$now,$day('-6 days'),$now,$now]);
    }
}
