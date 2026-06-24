<?php
require_once __DIR__ . '/config.php';
$mysqli = tryGetDbConnection();
if ($mysqli instanceof mysqli) {
  $catalog = getBookingCatalog($mysqli);
  $businessInfo = getBusinessInfo($mysqli);
  $mysqli->close();
} else {
  $catalog = getDefaultBookingCatalog();
  $businessInfo = [];
}

$phoneWhatsapp = htmlspecialchars($businessInfo['phone_whatsapp'] ?? '071 234 5678', ENT_QUOTES, 'UTF-8');
$phoneLandline = htmlspecialchars($businessInfo['phone_landline'] ?? '010 500 7562', ENT_QUOTES, 'UTF-8');
$whatsappUrl = htmlspecialchars($businessInfo['whatsapp_url'] ?? 'https://wa.me/27712345678', ENT_QUOTES, 'UTF-8');
$whatsappNumber = preg_replace('/[^0-9]/', '', $phoneWhatsapp);
$whatsappLink = 'https://wa.me/27' . substr($whatsappNumber, -9);
$addressMidrand = htmlspecialchars($businessInfo['address_midrand'] ?? '12 Demo Street, Sandton', ENT_QUOTES, 'UTF-8');
$addressCopperleaf = htmlspecialchars($businessInfo['address_copperleaf'] ?? 'Copperleaf Golf Estate, Centurion', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="Bella Hair & Makeup Booking Policy — deposits, cancellations, rescheduling and rectifications." />
  <title>Booking Policy — Bella Hair | Makeup</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet" />
  <link rel="canonical" href="https://bellahairandmakeup.co.za/policy.php" />
  <link rel="icon" href="images/logo.svg" type="image/svg+xml" />
  <link rel="stylesheet" href="css/style.css" />
  <?php echo ga4Snippet(); ?>
</head>
<body>

  <!-- ======= NAVIGATION ======= -->
  <header class="site-header scrolled" id="header">
    <nav class="nav-container">
      <a href="index.php" class="nav-logo">
        <div class="nav-logo-img"><img src="images/logo.svg" alt="Bella Hair Makeup logo" /></div>
        <div>
          <span class="logo-brand">Bella</span>
          <span class="logo-sub">Hair | Make up</span>
        </div>
      </a>
      <button class="nav-toggle" id="navToggle" aria-label="Toggle menu">
        <span></span><span></span><span></span>
      </button>
      <ul class="nav-links" id="navLinks">
        <li><a href="index.php">Home</a></li>
        <li><a href="about.php">About</a></li>
        <li><a href="services.php">Services &amp; Pricing</a></li>
        <li><a href="policy.php" class="page-active">Policy</a></li>
        <li><a href="book.php" class="nav-cta">Book Now</a></li>
      </ul>
    </nav>
  </header>

  <!-- ======= PAGE HEADER ======= -->
  <section class="page-hero section-dark" style="padding:8rem 0 4rem;text-align:center;">
    <p class="section-eyebrow light">Please Read Before Booking</p>
    <h1 class="section-title light">Booking <em>Policy</em></h1>
    <p style="color:#aaa;font-size:0.95rem;max-width:560px;margin:0 auto;">At Bella, we value your appointments and would like to help you make the most suitable reservations with us.</p>
  </section>

  <!-- ======= POLICY ======= -->
  <section class="policy section section-dark" id="policy" style="padding-top:3rem;">
    <div class="container">
      <div class="policy-grid">

        <div class="policy-card">
          <div class="policy-card-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          </div>
          <h3>Booking Procedure</h3>
          <ul class="policy-list">
            <li>Finalise your booking directly on our website.</li>
            <li>A <strong>non-refundable 50% deposit</strong> is required to confirm your booking for all services.</li>
            <li>Your appointment is only secured once the deposit is received.</li>
          </ul>
        </div>

        <div class="policy-card">
          <div class="policy-card-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
          </div>
          <h3>Rescheduling</h3>
          <ul class="policy-list">
            <li>Clients may reschedule <strong>within 48 hours</strong> of their appointment date.</li>
            <li>Rescheduling requests received after this window may not be accommodated.</li>
          </ul>
        </div>

        <div class="policy-card">
          <div class="policy-card-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
          </div>
          <h3>No Refunds &amp; No-Shows</h3>
          <ul class="policy-list">
            <li><strong>No refunds</strong> will be made for missed appointments.</li>
            <li>A no-show is regarded as a missed appointment.</li>
            <li>Arriving <strong>more than 15 minutes late</strong> will result in your appointment being considered missed and cancelled.</li>
          </ul>
        </div>

        <div class="policy-card">
          <div class="policy-card-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          </div>
          <h3>Rectifications</h3>
          <ul class="policy-list">
            <li>Rectifications are done <strong>within 48 hours</strong> of your treatment date.</li>
            <li>Rectifications do <strong>not apply to makeup services</strong>.</li>
            <li>We will charge for any rectification performed due to poor home care of your hairstyle.</li>
          </ul>
        </div>

      </div>

      <!-- CTA -->
      <div style="text-align:center;padding:3rem 0 4rem;">
        <p style="font-family:'Cormorant Garamond',serif;font-size:1.2rem;font-style:italic;color:#aaa;margin-bottom:1.5rem;">Have questions about our policy? We're happy to help.</p>
        <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;">
          <a href="book.php" class="btn btn-gold">Book an Appointment</a>
          <a href="https://wa.me/27712345678?text=Hi%20Bella%2C%20I%20have%20a%20question%20about%20your%20booking%20policy" target="_blank" rel="noopener" class="btn btn-outline-gold">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="15" height="15" style="margin-right:0.35rem"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            Ask via WhatsApp
          </a>
        </div>
      </div>

    </div>
  </section>

  <!-- ======= FOOTER ======= -->
  <footer class="site-footer">
    <div class="container footer-grid">
      <div class="footer-brand">
        <span class="logo-brand">Bella</span>
        <span class="logo-sub">Hair | Make up</span>
        <p>Luxury hair and makeup studio serving Midrand &amp; Copperleaf, Gauteng.</p>
      </div>
      <div class="footer-links">
        <h4>Quick Links</h4>
        <ul>
          <li><a href="index.php">Home</a></li>
          <li><a href="about.php">About</a></li>
          <li><a href="services.php">Services &amp; Pricing</a></li>
          <li><a href="policy.php">Booking Policy</a></li>
          <li><a href="book.php">Book Now</a></li>
        </ul>
      </div>
      <div class="footer-contact">
        <h4>Contact</h4>
        <ul>
          <li><a href="tel:<?php echo preg_replace('/[^0-9]/', '', $phoneLandline); ?>"><?php echo $phoneLandline; ?></a></li>
          <li><a href="<?php echo $whatsappLink; ?>" target="_blank" rel="noopener">WhatsApp: <?php echo $phoneWhatsapp; ?></a></li>
          <li><?php echo $addressMidrand; ?>,<br>Midrand, Gauteng</li>
          <li><?php echo $addressCopperleaf; ?></li>
        </ul>
      </div>
      <div class="footer-hours">
        <h4>Hours</h4>
        <ul>
          <li><span>Mon – Wed</span><span>09:00 – 17:30</span></li>
          <li><span>Thu – Fri</span><span>08:00 – 18:00</span></li>
          <li><span>Saturday</span><span>08:00 – 17:00</span></li>
          <li><span>Public Holidays</span><span>08:00 – 14:00</span></li>
          <li><span>Sunday</span><span>Midrand 11:00 – 16:00 | Copperleaf Closed</span></li>
        </ul>
      </div>
    </div>
    <!--<div class="footer-bottom">-->
    <!--  <p>&copy; 2026 Bella Hair | Makeup. All rights reserved.</p>-->
    <!--  <p class="footer-dev">Website developed by <a href="https://www.mplai.co.za" target="_blank" rel="noopener">MPL-->
    <!--      AI</a> In Partnership with <span style="color: var(--gold-dark); font-weight: 600;">CALEBVERSE</span></p> -->
    <!--      </a>-->
    <!--    </div>-->
    <!--  </div>-->
    <!--</div>-->
  </footer>

  <div class="nav-backdrop" id="navBackdrop"></div>
  <script src="js/main.js"></script>

</body>
</html>
