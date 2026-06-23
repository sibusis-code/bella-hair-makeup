<?php http_response_code(404); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex" />
  <title>Page Not Found — Bella Hair | Makeup</title>
  <link rel="icon" href="/images/logo.jpeg" type="image/jpeg" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Montserrat:wght@300;400;500;600&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="/css/style.css" />
  <style>
    .e404{min-height:70vh;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:8rem 1.5rem 4rem;}
    .e404 h1{font-size:4rem;margin:0;color:var(--gold,#C9A96E);}
    .e404 p{max-width:520px;color:#666;}
    .e404 .actions{display:flex;gap:1rem;flex-wrap:wrap;justify-content:center;margin-top:1.5rem;}
  </style>
</head>
<body>
  <header class="site-header" id="header">
    <nav class="nav-container">
      <a href="/index.php" class="nav-logo">
        <div class="nav-logo-img"><img src="/images/logo.jpeg" alt="Bella Hair Makeup logo" /></div>
        <div><span class="logo-brand">Bella</span><span class="logo-sub">Hair | Make up</span></div>
      </a>
      <ul class="nav-links">
        <li><a href="/index.php">Home</a></li>
        <li><a href="/services.php">Services &amp; Pricing</a></li>
        <li><a href="/book.php" class="nav-cta">Book Now</a></li>
      </ul>
    </nav>
  </header>

  <main class="e404">
    <h1>404</h1>
    <h2 class="section-title">Page <em>not found</em></h2>
    <p>The page you’re looking for has moved or no longer exists. Let’s get you back on track.</p>
    <div class="actions">
      <a href="/index.php" class="btn btn-gold">Back to Home</a>
      <a href="/book.php" class="btn btn-outline-gold">Book an Appointment</a>
      <a href="https://wa.me/27712345678" target="_blank" rel="noopener" class="btn btn-outline-gold">WhatsApp Us</a>
    </div>
  </main>

  <footer class="site-footer">
    <div class="footer-bottom"><p>&copy; 2026 Bella Hair | Makeup. All rights reserved.</p></div>
  </footer>
</body>
</html>
