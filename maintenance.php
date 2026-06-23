<?php
// Self-contained maintenance page. Included by config.php's enforceMaintenanceMode()
// (which already sent the 503 + Retry-After). Must NOT load config.php or touch the
// database — it has to render even when the DB or mysqli driver is unavailable.
if (!headers_sent()) {
    http_response_code(503);
    header('Retry-After: 3600');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex" />
  <title>We'll be right back | Bella Hair &amp; Makeup</title>
  <link rel="icon" href="/images/logo.jpeg" type="image/jpeg" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=Montserrat:wght@300;400;500;600&display=swap"
    rel="stylesheet" />
  <style>
    :root{--gold:#C9A96E;--gold-light:#E8D5A8;}
    *{box-sizing:border-box;}
    html,body{margin:0;height:100%;}
    body{
      font-family:'Montserrat',system-ui,sans-serif;background:#0a0a0a;color:#eee;
      display:flex;align-items:center;justify-content:center;min-height:100vh;padding:2rem 1.25rem;text-align:center;
    }
    .m-wrap{max-width:560px;}
    .m-logo{width:96px;height:96px;border-radius:50%;object-fit:cover;margin:0 auto 1.75rem;display:block;
      border:2px solid var(--gold);}
    .m-eyebrow{letter-spacing:.28em;text-transform:uppercase;font-size:.72rem;color:var(--gold);margin:0 0 .75rem;}
    h1{font-family:'Cormorant Garamond',serif;font-weight:600;font-size:2.6rem;line-height:1.1;margin:0 0 1rem;color:#fff;}
    h1 em{font-style:italic;color:var(--gold);}
    p{color:#b9b9b9;font-size:1rem;line-height:1.6;margin:0 auto 1.5rem;max-width:440px;}
    .m-actions{display:flex;gap:.75rem;flex-wrap:wrap;justify-content:center;}
    .m-btn{display:inline-flex;align-items:center;gap:.5rem;text-decoration:none;font-weight:600;font-size:.95rem;
      padding:.8rem 1.4rem;border-radius:999px;}
    .m-btn.wa{background:#25d366;color:#062e16;}
    .m-btn.ig{border:1.5px solid var(--gold);color:var(--gold);}
    .m-foot{margin-top:2.5rem;color:#777;font-size:.8rem;}
    .m-foot a{color:var(--gold);text-decoration:none;}
  </style>
</head>
<body>
  <div class="m-wrap">
    <img src="/images/logo.jpeg" alt="Bella Hair &amp; Makeup" class="m-logo"
         onerror="this.style.display='none'" />
    <p class="m-eyebrow">Bella Hair &amp; Makeup</p>
    <h1>We're getting <em>glam-ready</em></h1>
    <p>
      Our online booking is briefly offline while we put the finishing touches in place.
      We'll be back very soon — in the meantime, reach us on WhatsApp and we'll book you in.
    </p>
    <div class="m-actions">
      <a class="m-btn wa" href="https://wa.me/27712345678" target="_blank" rel="noopener">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
          <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347M12.05 21.785h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884"/>
        </svg>
        WhatsApp 071 234 5678
      </a>
      <a class="m-btn ig" href="https://www.instagram.com/bella_hair_and_makeup" target="_blank" rel="noopener">Instagram</a>
    </div>
    <p class="m-foot">Midrand · Copperleaf · Mobile &nbsp;·&nbsp; <a href="tel:0105007562">010 500 7562</a></p>
  </div>
</body>
</html>
