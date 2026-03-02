<?php
class Conexion {

    public static function ConexionBD(){
        $host = "localhost";
        $dbname = "DB_fin";
        $username = "postgres";
        $password = "1234";

        try {
            $conn = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $conn;

        } catch (PDOException $e) {
            $debugEnv = getenv('DB_DEBUG');
            $debug = ($debugEnv !== false && $debugEnv === '1') || (isset($_GET['debug_db']) && $_GET['debug_db'] === '1');
            if ($debug) {
                if (!headers_sent()) {
                    header('Content-Type: text/plain; charset=utf-8');
                }
                echo "Error de conexión (DEBUG): " . $e->getMessage() . "\n";
                echo "DSN: pgsql:host=$host;dbname=$dbname\n";
                echo "Usuario: $username\n";
                exit;
            }
            throw $e;
        }
    }

    public static function setUserId($conn, $user_id) {
        if ($user_id && is_numeric($user_id)) {
            $stmt = $conn->prepare("SET app.user_id = ?");
            $stmt->execute([$user_id]);
        }
    }
}
?>