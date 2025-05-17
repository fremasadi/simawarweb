<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Landing Page - Rumah Jahit Mawar</title>
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

    .section {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 2rem;
      text-align: center;
    }

    .welcome-title {
      font-size: 2.5rem;
      color: #2c3e50;
      margin-bottom: 2rem;
    }

    .gallery {
      display: flex;
      gap: 1.5rem;
      justify-content: center;
      flex-wrap: wrap;
      margin-bottom: 0; /* <== diubah dari 3rem */
    }

    .gallery img {
      width: 300px;
      height: 200px;
      object-fit: cover;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .container {
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 0; /* <== hapus padding */
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
    }

    .btn-login:hover {
      background-color: #2980b9;
    }

    .main-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    }

    .footer {
      background-color: #2c3e50;
      color: #bdc3c7;
      text-align: center;
      padding: 1rem;
      margin-top: 2rem;
    }
  </style>
</head>
<body>
    <header class="header">
      <h2>Admin Simawar</h2>
    </header>
  
    <!-- Mulai konten utama -->
    <main class="main-content">
      <!-- Bagian Selamat Datang & Galeri -->
      <section class="section">
        <h1 class="welcome-title">Selamat Datang di Rumah Jahit Mawar</h1>
        <div class="gallery">
          <img src="assets/foto1.jpg" alt="Kegiatan 1">
          <img src="assets/foto2.jpg" alt="Kegiatan 2">
          <img src="assets/foto4.jpg" alt="Kegiatan 3">
        </div>
      </section>
  
      <!-- Admin Card -->
      <div class="container">
        <div class="admin-card">
          <h1>Admin Rumah Jahit Mawar</h1>
          <p>Selamat datang di area administrasi. Silakan login untuk mengakses dashboard admin dan mengelola sistem Anda.</p>
          <a href="/admin" class="btn-login">Masuk Sebagai Admin</a>
        </div>
      </div>
    </main>
    <!-- Akhir konten utama -->
  
    <footer class="footer">
      <p>&copy; 2025 Rumah Jahit Mawar.</p>
    </footer>
  </body>
  
</html>
