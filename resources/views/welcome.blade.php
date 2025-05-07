<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f0f2f5;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .header {
            background-color: #2c3e50;
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .container {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .admin-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 2rem;
            max-width: 500px;
            width: 100%;
            text-align: center;
        }
        
        .admin-logo {
            width: 100px;
            height: 100px;
            background-color: #3498db;
            border-radius: 50%;
            margin: 0 auto 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .admin-logo i {
            font-size: 48px;
            color: white;
        }
        
        h1 {
            color: #2c3e50;
            margin-bottom: 1rem;
        }
        
        p {
            color: #7f8c8d;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .btn-login {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-login:hover {
            background-color: #2980b9;
        }
        
        .footer {
            background-color: #2c3e50;
            color: #bdc3c7;
            text-align: center;
            padding: 1rem;
            margin-top: auto;
        }
    </style>
</head>
<body>
    <header class="header">
        <h2>Admin Simawar</h2>
    </header>
    
    <div class="container">
        <div class="admin-card">
            <div class="admin-logo">
                <i>👤</i>
            </div>
            <h1>Admin Rumah Jahit Mawar</h1>
            <p>Selamat datang di area administrasi. Silakan login untuk mengakses dashboard admin dan mengelola sistem Anda.</p>
            <a href="/admin" class="btn-login">Masuk Sebagai Admin</a>
        </div>
    </div>
    
    <footer class="footer">
        <p>&copy; Rumah Jahit Mawar.since 2015.</p>
    </footer>
</body>
</html>