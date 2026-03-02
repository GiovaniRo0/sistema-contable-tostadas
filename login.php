<?php
session_start();

require_once __DIR__ . '/Conexion.php';

$error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    if (!empty($email) && !empty($password)) {
        $database = new Conexion();
        $db = Conexion::ConexionBD();
        
        $query = "SELECT id_usuario, nombre, email, password, rol, activo 
                  FROM usuarios 
                  WHERE email = :email AND activo = true";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":email", $email);
        
        if ($stmt->execute()) {
            if ($stmt->rowCount() == 1) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $id_usuario = $row['id_usuario'];
                $nombre = $row['nombre'];
                $hashed_password = $row['password'];
                $rol = $row['rol'];
                
                if (password_verify($password, $hashed_password)) {
                    $_SESSION['loggedin'] = true;
                    $_SESSION['id_usuario'] = $id_usuario;
                    $_SESSION['nombre'] = $nombre;
                    $_SESSION['email'] = $email;
                    $_SESSION['rol'] = $rol;
                    
                    header("location: pag.php");
                    exit;
                } else {
                    $error = "Contraseña incorrecta.";
                }
            } else {
                $error = "No existe un usuario con ese email.";
            }
        } else {
            $error = "Error en la consulta a la base de datos.";
        }
    } else {
        $error = "Por favor, completa todos los campos.";
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema Tostadas</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            padding: 2rem;
            width: 100%;
            max-width: 400px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header h1 {
            color: #ff6b35;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .login-header p {
            color: #666;
            font-size: 1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: bold;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #ff6b35;
        }

        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .btn-login:hover {
            transform: translateY(-2px);
        }

        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 1rem;
            text-align: center;
            border-left: 4px solid #c62828;
        }

        .success-message {
            background: #e8f5e8;
            color: #2e7d32;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 1rem;
            text-align: center;
            border-left: 4px solid #2e7d32;
        }

        .demo-accounts {
            margin-top: 1.5rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            font-size: 0.9rem;
        }

        .demo-accounts h3 {
            color: #333;
            margin-bottom: 0.5rem;
        }

        .demo-accounts p {
            color: #666;
            margin-bottom: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🍴 Tostadas Jela</h1>
            <p>Sistema de Gestión - Iniciar Sesión</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['registered'])): ?>
            <div class="success-message">
                ✅ Usuario registrado exitosamente. Ya puedes iniciar sesión.
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required 
                       placeholder="tu.email@ejemplo.com"
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" required 
                       placeholder="Ingresa tu contraseña">
            </div>

            <button type="submit" name="login" class="btn-login">
                🚀 Iniciar Sesión
            </button>
        </form>


    </div>

    <script>
        document.getElementById('email').focus();

        document.getElementById('password').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.querySelector('form').submit();
            }
        });
    </script>
</body>
</html>