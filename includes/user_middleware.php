<?php
// includes/user_middleware.php
// Middleware untuk memastikan isolasi data per pengguna.
// File ini berisi semua fungsi untuk query database yang aman.

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Pastikan user sudah login sebelum fungsi-fungsi ini bisa digunakan.
if (!isset($_SESSION['user_id'])) {
    // Jika ada yang mencoba mengakses file proses tanpa login, hentikan.
    // Seharusnya ini tidak terjadi karena ada auth_check.php, tapi ini sebagai pengaman tambahan.
    die("Akses ditolak. Anda harus login untuk melakukan operasi ini.");
}

/**
 * Menyisipkan data baru ke dalam tabel dengan user_id otomatis.
 * @param PDO $conn Objek koneksi database.
 * @param string $table Nama tabel.
 * @param array $data Data yang akan disisipkan dalam format [kolom => nilai].
 * @return bool True jika berhasil, false jika gagal.
 */
function insertWithUserId($conn, $table, $data) {
    // Tambahkan user_id dari session saat ini ke dalam data.
    $data['user_id'] = $_SESSION['user_id'];
    
    $columns = implode(', ', array_keys($data));
    $placeholders = ':' . implode(', :', array_keys($data));

    $query = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
    $stmt = $conn->prepare($query);

    foreach ($data as $key => &$value) {
        $stmt->bindParam(":{$key}", $value);
    }

    return $stmt->execute();
}

/**
 * Memperbarui data di tabel, hanya untuk data milik user yang login.
 * @param PDO $conn Objek koneksi database.
 * @param string $table Nama tabel.
 * @param array $data Data yang akan diperbarui dalam format [kolom => nilai].
 * @param string $where Kondisi WHERE tambahan (misal: 'id = :id').
 * @param array $whereParams Parameter untuk kondisi WHERE.
 * @return bool True jika berhasil, false jika gagal.
 */
function updateWithUserId($conn, $table, $data, $where, $whereParams = []) {
    $setParts = [];
    foreach ($data as $key => $value) {
        $setParts[] = "{$key} = :{$key}";
    }
    $setClause = implode(', ', $setParts);

    // Tambahkan filter user_id ke kondisi WHERE secara otomatis.
    $where .= " AND user_id = :user_id_session";
    $whereParams['user_id_session'] = $_SESSION['user_id'];

    $query = "UPDATE {$table} SET {$setClause} WHERE {$where}";
    $stmt = $conn->prepare($query);

    // Bind data untuk bagian SET
    foreach ($data as $key => &$value) {
        $stmt->bindParam(":{$key}", $value);
    }
    // Bind data untuk bagian WHERE
    foreach ($whereParams as $key => &$value) {
        $stmt->bindParam($key, $value);
    }

    $stmt->execute();
    return $stmt->rowCount() > 0; // Mengembalikan true jika ada baris yang terpengaruh
}

/**
 * Menghapus data dari tabel, hanya untuk data milik user yang login.
 * @param PDO $conn Objek koneksi database.
 * @param string $table Nama tabel.
 * @param string $where Kondisi WHERE (misal: 'id = :id').
 * @param array $whereParams Parameter untuk kondisi WHERE.
 * @return bool True jika berhasil, false jika gagal.
 */
function deleteWithUserId($conn, $table, $where, $whereParams = []) {
    // Tambahkan filter user_id ke kondisi WHERE secara otomatis.
    $where .= " AND user_id = :user_id_session";
    $whereParams['user_id_session'] = $_SESSION['user_id'];

    $query = "DELETE FROM {$table} WHERE {$where}";
    $stmt = $conn->prepare($query);
    
    foreach ($whereParams as $key => &$value) {
        $stmt->bindParam($key, $value);
    }

    return $stmt->execute();
}

/**
 * Mengambil data dari tabel, hanya untuk data milik user yang login.
 * @param PDO $conn Objek koneksi database.
 * @param string $table Nama tabel.
 * @param string $columns Kolom yang akan diambil.
 * @param string $where Kondisi WHERE tambahan.
 * @param array $whereParams Parameter untuk WHERE.
 * @param string $orderBy Pengurutan hasil.
 * @param string $limit Batasan hasil.
 * @return PDOStatement Objek statement yang bisa di-fetch.
 */
function selectWithUserId($conn, $table, $columns = '*', $where = '1=1', $whereParams = [], $orderBy = '', $limit = '') {
    // Tambahkan filter user_id ke kondisi WHERE secara otomatis.
    $where .= " AND user_id = :user_id_session";
    $whereParams['user_id_session'] = $_SESSION['user_id'];

    $query = "SELECT {$columns} FROM {$table} WHERE {$where}";
    
    if (!empty($orderBy)) {
        $query .= " ORDER BY {$orderBy}";
    }
    if (!empty($limit)) {
        $query .= " LIMIT {$limit}";
    }

    $stmt = $conn->prepare($query);
    
    foreach ($whereParams as $key => &$value) {
        $stmt->bindParam($key, $value);
    }
    
    $stmt->execute();
    return $stmt;
}

/**
 * Menghitung jumlah baris data, hanya untuk data milik user yang login.
 * @param PDO $conn Objek koneksi database.
 * @param string $table Nama tabel.
 * @param string $where Kondisi WHERE tambahan.
 * @param array $whereParams Parameter untuk WHERE.
 * @return int Jumlah baris.
 */
function countWithUserId($conn, $table, $where = '1=1', $whereParams = []) {
    // Tambahkan filter user_id ke kondisi WHERE secara otomatis.
    $where .= " AND user_id = :user_id_session";
    $whereParams['user_id_session'] = $_SESSION['user_id'];

    $query = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
    $stmt = $conn->prepare($query);
    
    foreach ($whereParams as $key => &$value) {
        $stmt->bindParam($key, $value);
    }
    
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}
?>
