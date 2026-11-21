<?php
declare(strict_types=1);

/** FR-10 business rules. The same methods can be called by another project's controllers. */
final class ReturnService
{
    public function __construct(public readonly PDO $db) {}

    private function one(string $sql, array $values): ?array
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($values);
        return $statement->fetch() ?: null;
    }

    public function student(int $id): array
    {
        $student = $this->one('SELECT id,name,student_id,email FROM students WHERE id=?', [$id]);
        if (!$student) throw new DomainException('Akun tidak ditemukan.');
        return $student;
    }

    public function login(string $email, string $password): array
    {
        $student = $this->one('SELECT * FROM students WHERE email=?', [trim(strtolower($email))]);
        if (!$student || !password_verify($password, $student['password_hash'])) {
            throw new DomainException('Email atau password salah. Pilih akun demo dan coba lagi.');
        }
        return $this->student((int)$student['id']);
    }

    private function query(): string
    {
        return 'SELECT t.*,i.name item_name,i.description,i.condition,i.image,i.pickup_location,i.availability,
            b.name borrower_name,o.name owner_name,r.id return_id,r.status return_status,r.return_date,
            r.is_late,r.created_at returned_at,r.confirmed_at
            FROM transactions t JOIN items i ON i.id=t.item_id
            JOIN students b ON b.id=t.borrower_id JOIN students o ON o.id=t.owner_id
            LEFT JOIN item_returns r ON r.transaction_id=t.id';
    }

    public function transactions(int $student, string $status): array
    {
        $this->student($student);
        if (!in_array($status, ['active','completed'], true)) throw new DomainException('Filter transaksi tidak valid.');
        $statement = $this->db->prepare($this->query().' WHERE (t.borrower_id=? OR t.owner_id=?) AND t.status=? ORDER BY t.id DESC');
        $statement->execute([$student,$student,$status]);
        return $statement->fetchAll();
    }

    public function transactionDetails(int $student, int $id): array
    {
        $this->student($student);
        $transaction = $this->one($this->query().' WHERE t.id=?', [$id]);
        if (!$transaction) throw new DomainException('Transaksi tidak ditemukan.');
        if ($transaction['borrower_id'] !== $student && $transaction['owner_id'] !== $student) {
            throw new DomainException('Kamu tidak memiliki akses ke transaksi ini.');
        }
        return $transaction;
    }

    /** Serialize status checks + writes to prevent duplicate or conflicting browser submissions. */
    private function atomic(callable $operation): mixed
    {
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $result = $operation();
            $this->db->exec('COMMIT');
            return $result;
        } catch (Throwable $error) {
            $this->db->exec('ROLLBACK');
            throw $error;
        }
    }

    /** Insert form: only the borrower may create an Awaiting Confirmation return record. */
    public function markReturned(int $student, int $transactionId): int
    {
        return $this->atomic(function () use ($student, $transactionId): int {
            $transaction = $this->transactionDetails($student, $transactionId);
            if ($transaction['borrower_id'] !== $student || $transaction['status'] !== 'active') {
                throw new DomainException('Hanya peminjam pada transaksi aktif yang dapat mengajukan pengembalian.');
            }
            if ($transaction['return_id']) {
                throw new DomainException('Pengembalian sudah diajukan. Tunggu konfirmasi pemilik.');
            }
            $today = date('Y-m-d');
            $statement = $this->db->prepare('INSERT INTO item_returns(transaction_id,return_date,is_late,created_at) VALUES(?,?,?,?)');
            $statement->execute([$transactionId,$today,(int)($today>$transaction['end_date']),date('Y-m-d H:i:s')]);
            // The transaction deliberately stays active until the owner confirms the physical return.
            return (int)$this->db->lastInsertId();
        });
    }

    /** Owner confirmation: all three updates either succeed together or roll back together. */
    public function confirmReturn(int $student, int $transactionId): void
    {
        $this->atomic(function () use ($student, $transactionId): void {
            $transaction = $this->transactionDetails($student, $transactionId);
            if ($transaction['owner_id'] !== $student || $transaction['status'] !== 'active' || $transaction['return_status'] !== 'awaiting') {
                throw new DomainException('Konfirmasi hanya dapat dilakukan pemilik setelah peminjam mengajukan pengembalian.');
            }
            $now = date('Y-m-d H:i:s');
            $this->db->prepare("UPDATE item_returns SET status='confirmed',confirmed_at=? WHERE transaction_id=?")->execute([$now,$transactionId]);
            $this->db->prepare("UPDATE transactions SET status='completed',completed_at=? WHERE id=?")->execute([$now,$transactionId]);
            if (!$this->one("SELECT id FROM transactions WHERE item_id=? AND status='active'", [$transaction['item_id']])) {
                $this->db->prepare("UPDATE items SET availability='available' WHERE id=?")->execute([$transaction['item_id']]);
            }
        });
    }
}
