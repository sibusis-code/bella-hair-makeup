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
  <meta name="description"
    content="About Bella Hair & Makeup — luxury beauty studio in Midrand & Copperleaf, Gauteng. Our story, team and locations." />
  <title>About — Bella Hair | Makeup</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Montserrat:wght@300;400;500;600&display=swap"
    rel="stylesheet" />
  <link rel="canonical" href="https://bellahairandmakeup.co.za/about.php" />
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
        <li><a href="about.php" class="page-active">About</a></li>
        <li><a href="services.php">Services &amp; Pricing</a></li>
        <li><a href="policy.php">Policy</a></li>
        <li><a href="book.php" class="nav-cta">Book Now</a></li>
      </ul>
    </nav>
  </header>

  <!-- ======= PAGE HEADER ======= -->
  <section class="page-hero section-dark" style="padding:8rem 0 4rem;text-align:center;">
    <p class="section-eyebrow light">The Studio</p>
    <h1 class="section-title light">About <em>Bella</em></h1>
  </section>

  <!-- ======= ABOUT ======= -->
  <section class="about section" id="about">
    <div class="container about-grid">
      <div class="about-image-wrap">
        <img src="images/make-up/m2.jpeg" alt="Bella Hair &amp; Makeup — glam artistry" loading="lazy" decoding="async" class="about-img" />
        <!--<div class="about-badge">-->
        <!--  <span class="badge-num">10.1K</span>-->
        <!--  <span class="badge-label">Instagram Followers</span>-->
        <!--</div>-->
      </div>
      <div class="about-text">
        <p class="section-eyebrow">Who We Are</p>
        <h2 class="section-title">Where <em>luxury meets</em> artistry</h2>
        <p>Bella Hair | Makeup operates a flagship studio in Midrand and a satellite branch within Copperleaf Estate, Centurion.</p>
        <p>The name Bella means &ldquo;God&rdquo; in Spanish, and our work is rooted in servitude, excellence, and personalised care for every client. With over 10,000 satisfied clients and 751 portfolio posts, our reputation is built on consistent results and attention to detail.</p>
        <ul style="list-style:none;padding:0;margin:1.25rem 0;display:flex;flex-direction:column;gap:0.6rem;">
          <li><strong>Midrand Studio:</strong> Walk-ins welcome and appointments available</li>
          <li><strong>Copperleaf Estate Studio:</strong> Strictly by appointment only</li>
          <li><strong>Mobile Services:</strong> Available for individuals for matric farewells, or simply a night out — and groups for weddings, celebrations, film productions, and corporate shoots</li>
          <li><strong>Early Appointments:</strong> Available from 5:00 AM by arrangement</li>
        </ul>
        <p>Whether it&rsquo;s a studio visit or an on-location team for your event, we bring the same standard of professional, personalised service to you.</p>
        <div class="about-stats">
          <div class="stat">
            <span class="stat-num">2</span>
            <span class="stat-label">Studio Locations</span>
          </div>
          <div class="stat">
            <span class="stat-num">10,000+</span>
            <span class="stat-label">Satisfied Clients</span>
          </div>
          <div class="stat">
            <span class="stat-num">751</span>
            <span class="stat-label">Portfolio Posts</span>
          </div>
        </div>
        <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-top:2rem;">
          <a href="book.php" class="btn btn-gold">Book an Appointment</a>
          <a href="services.php" class="btn btn-outline-gold">View Services &amp; Pricing</a>
        </div>
      </div>
    </div>
  </section>

  <!-- ======= OPERATING HOURS ======= -->
  <section class="hours section" id="hours">
    <div class="container hours-grid">
      <div class="hours-text">
        <p class="section-eyebrow">We're Open</p>
        <h2 class="section-title">Operating <em>Hours</em></h2>
        <p>We accommodate early appointments from <strong>5:00 AM</strong> by arrangement. Early appointments carry an
          additional <strong>R200 surcharge</strong>.</p>
        <a href="book.php" class="btn btn-gold" style="margin-top:2rem;">Book Your Slot</a>
      </div>
      <div class="hours-card">
        <div class="hours-row">
          <div class="hours-day-badge">Mon – Wed</div>
          <div class="hours-time">09:00 AM – 17:30 PM</div>
        </div>
        <div class="hours-divider"></div>
        <div class="hours-row">
          <div class="hours-day-badge">Thurs – Fri</div>
          <div class="hours-time">08:00 AM – 18:00 PM</div>
        </div>
        <div class="hours-divider"></div>
        <div class="hours-row">
          <div class="hours-day-badge">Saturday</div>
          <div class="hours-time">08:00 AM – 17:00 PM</div>
        </div>
        <div class="hours-divider"></div>
          <div class="hours-row">
          <div class="hours-day-badge">Sunday</div>
          <div class="hours-time">Midrand 11:00 AM – 16:00 PM | Copperleaf Closed</div>
        </div>
        <div class="hours-divider"></div>
       <div class="hours-row">
          <div class="hours-day-badge">Public Holidays</div>
          <div class="hours-time">08:00 AM – 14:00 PM</div>
        </div>
        <div class="hours-extra">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2">
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="8" x2="12" y2="12" />
            <line x1="12" y1="16" x2="12.01" y2="16" />
          </svg>
          Early appointments from 5:00 AM — extra <strong>R200</strong>
        </div>
      </div>
    </div>
  </section>

  <!-- ======= LOCATIONS ======= -->
  <section class="locations section section-dark" id="locations">
    <div class="container">
      <div class="section-header">
        <p class="section-eyebrow light">Find Us</p>
        <h2 class="section-title light">Our <em>Locations</em></h2>
      </div>
      <div class="locations-grid">
        <div class="location-card">
          <div class="location-tag">Walk-ins Welcome</div>
          <h3>Midrand Studio</h3>
          <p class="location-address">12 Demo Street, Sandton<br>Midrand, Gauteng</p>
          <ul class="location-details">
            <li>
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="2">
                <path
                  d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.1 11a19.79 19.79 0 01-3.07-8.67A2 2 0 012 .84h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 8.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z" />
              </svg>
              <a href="tel:<?php echo preg_replace('/[^0-9]/', '', $phoneLandline); ?>"><?php echo $phoneLandline; ?></a>
            </li>
            <li>
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="2">
                <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
              </svg>
              <a href="<?php echo $whatsappLink; ?>" target="_blank" rel="noopener">WhatsApp: <?php echo $phoneWhatsapp; ?></a>
            </li>
          </ul>
          <a href="https://maps.google.com/?q=12+Demo+Street+Sandton+Johannesburg" target="_blank" rel="noopener"
            class="btn btn-outline-gold">Get Directions</a>
        </div>
        <div class="location-card">
          <div class="location-tag appointment">Appointment Only</div>
          <h3>Copperleaf Studio</h3>
          <p class="location-address">Copperleaf Golf &amp; Country Estate<br>Centurion, Gauteng</p>
          <ul class="location-details">
            <li>
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                <line x1="16" y1="2" x2="16" y2="6" />
                <line x1="8" y1="2" x2="8" y2="6" />
                <line x1="3" y1="10" x2="21" y2="10" />
              </svg>
              Mon – Sat | Studio &amp; Mobile (Sun: Closed at Copperleaf)
            </li>
            <li>
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="2">
                <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
              </svg>
              <a href="https://wa.me/27712345678" target="_blank" rel="noopener">WhatsApp Enquiries</a>
            </li>
          </ul>
          <a href="book.php" class="btn btn-gold">Book Appointment</a>
        </div>
      </div>
    </div>
  </section>

  <!-- ======= SOCIAL MEDIA STRIP ======= -->
  <section class="instagram-strip">
    <div class="container instagram-inner">
      <p>Follow our work on social media</p>
      <div style="display:flex;gap:2rem;align-items:center;justify-content:center;flex-wrap:wrap;">
        <a href="https://www.instagram.com/bella_hair_and_makeup" target="_blank" rel="noopener" class="insta-handle">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2">
            <rect x="2" y="2" width="20" height="20" rx="5" ry="5" />
            <path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z" />
            <line x1="17.5" y1="6.5" x2="17.51" y2="6.5" />
          </svg>
          @bella_hair_and_makeup
          <!--<span class="insta-count">10.1K Followers</span>-->
        </a>
        <a href="https://www.tiktok.com/@bella_hair_and_makeup" target="_blank" rel="noopener"
          class="insta-handle">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="24" height="24" fill="currentColor">
            <path
              d="M33.5 6c0 4.142 3.358 7.5 7.5 7.5v5.25c-2.13.002-4.23-.417-6.2-1.24V33c0 6.075-4.925 11-11 11s-11-4.925-11-11 4.925-11 11-11c.254 0 .507.009.76.025V27.3A4.7 4.7 0 0 0 24 27c-3.032 0-5.5 2.468-5.5 5.5S20.968 38 24 38s5.5-2.468 5.5-5.5V6h4z" />
          </svg>
          @bella_hair_and_makeup
        </a>
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
    <div class="footer-bottom">
      <p>&copy; 2026 Bella Hair | Makeup. All rights reserved.</p>
    <!--  <p class="footer-dev">Website developed by <a href="https://www.mplai.co.za" target="_blank" rel="noopener">MPL-->
    <!--      AI</a> In Partnership with <span style="color: var(--gold-dark); font-weight: 600;">CALEBVERSE</span></p>-->
    <!--  </a>-->
    <!--</div>-->
    <!--</div>-->
    </div>
  </footer>

  <div class="nav-backdrop" id="navBackdrop"></div>
  <script src="js/main.js"></script>

</body>

</html>