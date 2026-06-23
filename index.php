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
$hoursMidrand = htmlspecialchars($businessInfo['hours_midrand'] ?? 'Mon-Wed: 09:00-17:30 | Thu-Fri: 08:00-18:00 | Sat: 08:00-17:00 | Sun: 11:00-16:00', ENT_QUOTES, 'UTF-8');
$hoursCopperleaf = htmlspecialchars($businessInfo['hours_copperleaf'] ?? 'Mon-Wed: 09:00-17:30 | Thu-Fri: 08:00-18:00 | Sat: 08:00-17:00 | Sun: Closed', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description"
    content="Bella Hair & Makeup — Luxury hair and makeup studio in Midrand & Copperleaf, Gauteng. Book your appointment online." />
  <title>Bella Hair | Makeup — Midrand & Copperleaf</title>
  <link rel="canonical" href="https://bellahairandmakeup.co.za/index.php" />
  <link rel="icon" href="images/logo.jpeg" type="image/jpeg" />
  <meta property="og:title" content="Bella Hair | Makeup — Midrand &amp; Copperleaf" />
  <meta property="og:description" content="Luxury hair and makeup studio in Midrand &amp; Copperleaf, Gauteng. Book your appointment online." />
  <meta property="og:type" content="website" />
  <meta property="og:url" content="https://bellahairandmakeup.co.za/index.php" />
  <meta property="og:image" content="https://bellahairandmakeup.co.za/images/logo.jpeg" />
  <meta name="twitter:card" content="summary_large_image" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Montserrat:wght@300;400;500;600&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="css/style.css" />
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "HairSalon",
    "name": "Bella Hair | Makeup",
    "description": "Luxury hair and makeup studio serving Midrand & Copperleaf, Gauteng.",
    "url": "https://bellahairandmakeup.co.za/",
    "image": "https://bellahairandmakeup.co.za/images/logo.jpeg",
    "telephone": "+27712345678",
    "priceRange": "RR",
    "address": {
      "@type": "PostalAddress",
      "streetAddress": "12 Demo Street, Sandton",
      "addressLocality": "Midrand",
      "addressRegion": "Gauteng",
      "addressCountry": "ZA"
    },
    "areaServed": ["Midrand", "Centurion", "Copperleaf", "Gauteng"],
    "sameAs": [
      "https://www.instagram.com/bella_hair_and_makeup",
      "https://www.tiktok.com/@bella_hair_and_makeup"
    ],
    "openingHoursSpecification": [
      { "@type": "OpeningHoursSpecification", "dayOfWeek": ["Monday","Tuesday","Wednesday"], "opens": "09:00", "closes": "17:30" },
      { "@type": "OpeningHoursSpecification", "dayOfWeek": ["Thursday","Friday"], "opens": "08:00", "closes": "18:00" },
      { "@type": "OpeningHoursSpecification", "dayOfWeek": "Saturday", "opens": "08:00", "closes": "17:00" },
      { "@type": "OpeningHoursSpecification", "dayOfWeek": "Sunday", "opens": "11:00", "closes": "16:00" }
    ]
  }
  </script>
  <?php echo ga4Snippet(); ?>
</head>

<body>

  <!-- ======= NAVIGATION ======= -->
  <header class="site-header" id="header">
    <nav class="nav-container">
      <a href="index.php" class="nav-logo">
        <div class="nav-logo-img"><img src="images/logo.jpeg" alt="Bella Hair Makeup logo" /></div>
        <div>
          <span class="logo-brand">Bella</span>
          <span class="logo-sub">Hair | Make up</span>
        </div>
      </a>
      <button class="nav-toggle" id="navToggle" aria-label="Toggle menu">
        <span></span><span></span><span></span>
      </button>
      <ul class="nav-links" id="navLinks">
        <li><a href="index.php" class="page-active">Home</a></li>
        <li><a href="about.php">About</a></li>
        <li><a href="services.php">Services &amp; Pricing</a></li>
        <li><a href="policy.php">Policy</a></li>
        <li><a href="book.php" class="nav-cta">Book Now</a></li>
      </ul>
    </nav>
  </header>

  <!-- ======= HERO ======= -->
  <section class="hero" id="home">
    <div class="hero-overlay"></div>
    <div class="hero-content">
      <p class="hero-eyebrow">Midrand · Copperleaf · Mobile</p>
      <h1 class="hero-title">
        <span class="hero-title-brand">Bella</span>
        <span class="hero-title-sub">Hair | Make up</span>
      </h1>
      <p class="hero-tagline">Where luxury meets artistry — every client, every time.</p>
      <div class="hero-actions">
        <a href="book.php" class="btn btn-gold">Book Your Appointment</a>
        <a href="services.php" class="btn btn-outline">Explore Services</a>
      </div>
    </div>
    <div class="hero-scroll-hint">
      <span>Scroll</span>
      <div class="scroll-line"></div>
    </div>
  </section>

  <!-- ======= DEPOSIT NOTICE BANNER ======= -->
  <div class="notice-banner">
    <div class="notice-inner">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="10" />
        <line x1="12" y1="8" x2="12" y2="12" />
        <line x1="12" y1="16" x2="12.01" y2="16" />
      </svg>
      <p><strong>Booking Policy:</strong> A <strong>non-refundable 50% deposit</strong> is required to secure every
        appointment. This is always calculated from your selected service price and deducted from your final bill.</p>
    </div>
  </div>

  <!-- ======= ABOUT ======= -->
  <section class="about section" id="about">
    <div class="container about-grid">
      <div class="about-image-wrap">
        <img src="images/hair/h4.jpeg" alt="Bella Hair studio — auburn knotless braids" loading="lazy" decoding="async" class="about-img" />
        <!-- <div class="about-badge">10.1K+ Clients Served</div> -->
      </div>
      <div class="about-text">
        <p class="section-eyebrow">Who We Are</p>
        <h2 class="section-title">Rooted in <em>Service</em></h2>
        <p>Bella Hair | Makeup has a flagship store in Midrand and a satellite branch within Copperleaf Estate in
          Centurion. We are rooted in the spirit of servitude and personalised service, and Bella means God in Spanish.
        </p>
        <p>With over 10,000 satisfied clients and 751 portfolio posts, our reputation speaks for itself. Alongside
          studio appointments, we place strong emphasis on mobile services, including group bookings for weddings,
          celebrations, film productions and corporate shoots.</p>
        <ul class="about-features">
          <li><span class="feature-dot"></span>Walk-ins welcome at Midrand and Appointments</li>
          <li><span class="feature-dot"></span>Strictly Appointment-only at Copperleaf</li>
          <li><span class="feature-dot"></span>Mobile services for individuals and groups for events such as weddings
            and other celebrations, as well as film and corporate shoots</li>
          <li><span class="feature-dot"></span>Early appointments from 5:00 AM by arrangement</li>
        </ul>
        <a href="book.php" class="btn btn-gold">Book Online</a>
      </div>
    </div>
  </section>

  <!-- ======= GALLERY STRIP ======= -->
  <section class="gallery-section">
    <div class="gallery-grid">
      <div class="gallery-item">
        <img src="images/hair/h4.jpeg" alt="Auburn knotless braids" loading="lazy" decoding="async" class="gallery-img"
          style="object-position: center 30%;" />
        <div class="gallery-item-overlay"></div>
        <!-- <span class="gallery-item-label">Knotless Braids</span> -->
      </div>
      <div class="gallery-item">
        <img src="images/hair/h9.jpeg" alt="Goddess locs ombré" loading="lazy" decoding="async" class="gallery-img"
          style="object-position: center 15%;" />
        <div class="gallery-item-overlay"></div>
        <!-- <span class="gallery-item-label">Goddess Locs</span> -->
      </div>
      <div class="gallery-item">
        <img src="images/make-up/m2.jpeg" alt="Glam artistry" loading="lazy" decoding="async" class="gallery-img"
          style="object-position: center 20%;" />
        <div class="gallery-item-overlay"></div>
        <!-- <span class="gallery-item-label">Glam Artistry</span> -->
      </div>
      <div class="gallery-item">
        <img src="images/hair/h3.jpeg" alt="Senegalese twists" loading="lazy" decoding="async" class="gallery-img"
          style="object-position: center 15%;" />
        <div class="gallery-item-overlay"></div>
        <!-- <span class="gallery-item-label">Senegalese Twists</span> -->
      </div>
      <div class="gallery-item">
        <img src="images/make-up/m4.jpeg" alt="Editorial makeup" loading="lazy" decoding="async" class="gallery-img"
          style="object-position: center 20%;" />
        <div class="gallery-item-overlay"></div>
        <!-- <span class="gallery-item-label">Editorial Glam</span> -->
      </div>
      <div class="gallery-item">
        <img src="images/make-up/m5.jpeg" alt="Cornrow updo" loading="lazy" decoding="async" class="gallery-img" style="object-position: center 25%;" />
        <div class="gallery-item-overlay"></div>
        <!-- <span class="gallery-item-label">Cornrow Updo</span> -->
      </div>
    </div>
  </section>

  <!-- ======= SERVICES ======= -->
  <section class="services section section-dark" id="services">
    <div class="container">
      <div class="section-header">
        <p class="section-eyebrow light">What We Offer</p>
        <h2 class="section-title light">Our Services</h2>
        <p class="section-intro light">Every service is performed with care, precision and the finest products. More
          services coming soon.</p>
      </div>
      <div class="services-grid">

        <div class="service-card">
          <!-- <div class="service-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 8c2 0 3-2 5-2s3 2 5 2 3-2 5-2"/><path d="M4 12c2 0 3-2 5-2s3 2 5 2 3-2 5-2"/><path d="M4 16c2 0 3-2 5-2s3 2 5 2 3-2 5-2"/></svg></div> -->
          <h3>Braids</h3>
          <div class="service-tags">
            <span>Knotless Braids</span><span>Normal Braids</span><span>French Curls</span>
            <span>Tribal Braids</span><span>Kinky Twist</span>
          </div>
          <ul class="service-meta">
            <li><span class="meta-label">Duration</span>3–4 hours per session</li>
            <li><span class="meta-label">Team</span>1 client · 2 Braiders</li>
            <li><span class="meta-label">Slots</span>7:30am · 11:30am · 2:30pm</li>
          </ul>
          <a href="book.php" class="service-link">Book →</a>
        </div>

        <div class="service-card">
          <!-- <div class="service-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 7h18"/><path d="M5 12h14"/><path d="M7 17h10"/></svg></div> -->
          <h3>Cornrows</h3>
          <div class="service-tags">
            <span>Classic</span><span>Feed-in</span><span>Curved / Pattern</span><span>Braided Updo</span>
          </div>
          <ul class="service-meta">
            <li><span class="meta-label">Duration</span>2–3 hours per session</li>
            <li><span class="meta-label">Team</span>2 Stylists</li>
          </ul>
          <a href="book.php" class="service-link">Book →</a>
        </div>

        <div class="service-card">
          <!-- <div class="service-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19h16"/><path d="M8 19V9l4-4 4 4v10"/></svg></div> -->
          <h3>Ponytail</h3>
          <div class="service-tags">
            <span>Straight Ponytail</span><span>Curly Ponytail</span><span>Afro Kinky Ponytail</span>
          </div>
          <a href="book.php" class="service-link">Book →</a>
        </div>

        <div class="service-card">
          <!-- <div class="service-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3l3 3-6 6-3-3 6-6z"/><path d="M14 8l7 7-3 3-7-7"/><path d="M3 21h6"/></svg></div> -->
          <h3>Wig Installation</h3>
          <div class="service-tags">
            <span>Full Lace</span><span>Frontal 13×4</span><span>360 Lace</span><span>Closure 4×4</span><span>Super
              Double Drawn</span>
          </div>
          <ul class="service-prices">
            <li><span>Full Lace Wig</span><span class="price-tag">Price on request</span></li>
            <li><span>Frontal (13×4)</span><span class="price-tag">Price on request</span></li>
            <li><span>360 Lace Wig</span><span class="price-tag">Price on request</span></li>
            <li><span>Closure (4×4)</span><span class="price-tag">Price on request</span></li>
            <li><span>Super Double Drawn Wig</span><span class="price-tag">Price on request</span></li>
          </ul>
          <a href="book.php" class="service-link">Book →</a>
        </div>

        <div class="service-card service-card--featured">
          <!-- <div class="service-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 20l4-10 4 10"/><path d="M4 20h16"/><circle cx="12" cy="6" r="3"/></svg></div> -->
          <h3>Makeup Artistry</h3>
          <div class="service-tags">
            <span>Bridal</span><span>Events &amp; Functions</span><span>Editorial</span>
            <span>Everyday Glam</span><span>Graduation</span>
          </div>
          <p class="service-note">Makeup bookings are handled directly through our online booking form.</p>
          <a href="book.php" class="btn btn-gold btn-sm">Book Makeup</a>
        </div>

        <div class="service-card">
          <!-- <div class="service-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 18l6-6 4 4 6-6"/><path d="M14 10h6v6"/></svg></div> -->
          <h3>Hair Colour</h3>
          <div class="service-tags">
            <span>Balayage</span><span>Highlights</span><span>Fashion Colour</span>
            <span>Root Touch-up</span><span>Full Colour</span>
          </div>
          <a href="book.php" class="service-link">Book →</a>
        </div>

      </div>
      <p class="services-note">For pricing &amp; enquiries: WhatsApp <?php echo $phoneWhatsapp; ?> · Landline <?php echo $phoneLandline; ?></p>
    </div>
  </section>

  <!-- ======= OPERATING HOURS ======= -->
  <section class="hours section" id="hours">
    <div class="container hours-grid">
      <div class="hours-text">
        <p class="section-eyebrow">We're Open</p>
        <h2 class="section-title">Operating Hours</h2>
        <p>We accommodate early appointments from <strong>5:00 AM</strong> by arrangement. Early appointments carry an
          additional <strong>R200 surcharge</strong>.</p>
        <a href="book.php" class="btn btn-gold" style="margin-top:2rem">Book Your Slot</a>
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
          <!-- <div class="hours-time">Closed (Copperleaf)</div> -->
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
        <h2 class="section-title light">Our Locations</h2>
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

  <!-- ======= BOOKING POLICY ======= -->
  <section class="policy section section-dark" id="policy">
    <div class="container">
      <div class="section-header">
        <p class="section-eyebrow light">Please Read Before Booking</p>
        <h2 class="section-title light">Booking Policy</h2>
        <p class="section-intro light">At Bella, we value your appointments and would like to help you make the most
          suitable reservations with us.</p>
      </div>

      <div class="policy-grid">

        <div class="policy-card">
          <div class="policy-card-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
              stroke-width="1.5">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
              <line x1="16" y1="2" x2="16" y2="6" />
              <line x1="8" y1="2" x2="8" y2="6" />
              <line x1="3" y1="10" x2="21" y2="10" />
            </svg>
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
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
              stroke-width="1.5">
              <path d="M23 4v6h-6" />
              <path d="M1 20v-6h6" />
              <path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15" />
            </svg>
          </div>
          <h3>Rescheduling</h3>
          <ul class="policy-list">
            <li>Clients may reschedule <strong>within 48 hours</strong> of their appointment date.</li>
            <li>Rescheduling requests received after this window may not be accommodated.</li>
          </ul>
        </div>

        <div class="policy-card">
          <div class="policy-card-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
              stroke-width="1.5">
              <circle cx="12" cy="12" r="10" />
              <line x1="4.93" y1="4.93" x2="19.07" y2="19.07" />
            </svg>
          </div>
          <h3>No Refunds &amp; No-Shows</h3>
          <ul class="policy-list">
            <li><strong>No refunds</strong> will be made for missed appointments.</li>
            <li>A no-show is regarded as a missed appointment.</li>
            <li>Arriving <strong>more than 15 minutes late</strong> will result in your appointment being considered
              missed and cancelled.</li>
          </ul>
        </div>

        <div class="policy-card">
          <div class="policy-card-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
              stroke-width="1.5">
              <path d="M12 20h9" />
              <path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z" />
            </svg>
          </div>
          <h3>Rectifications</h3>
          <ul class="policy-list">
            <li>Rectifications are done <strong>within 48 hours</strong> of your treatment date.</li>
            <li>Rectifications do <strong>not apply to makeup services</strong>.</li>
            <li>We will charge for any rectification performed due to poor home care of your hairstyle.</li>
          </ul>
        </div>

      </div>

      <div class="policy-footer">
        <p>Thank you for your understanding, cooperation and support.</p>
        <div class="policy-contact">
          <a href="https://wa.me/27712345678" target="_blank" rel="noopener" class="btn btn-gold">Enquiries: 073 266
            8348</a>
          <span class="policy-address">12 Demo Street, Sandton, Midrand</span>
        </div>
      </div>
    </div>
  </section>

  <!-- ======= BOOKING / LEAD FORM ======= -->
  <!--<section class="booking section" id="booking">-->
  <!--  <div class="container booking-grid">-->
  <!--    <div class="booking-info">-->
  <!--      <p class="section-eyebrow">Reserve Your Spot</p>-->
  <!--      <h2 class="section-title">Book an Appointment</h2>-->
  <!--      <p>Fill in your details and we'll confirm your booking within <strong>2 hours</strong> during operating hours. A-->
  <!--        <strong>non-refundable 50% deposit</strong> is required to secure your slot.</p>-->
  <!--      <ul class="booking-steps">-->
  <!--        <li>-->
  <!--          <div class="step-num">1</div>-->
  <!--          <div><strong>Submit your request</strong><br>Fill in the form with your preferred date and service.</div>-->
  <!--        </li>-->
  <!--        <li>-->
  <!--          <div class="step-num">2</div>-->
  <!--          <div><strong>Receive confirmation</strong><br>We'll confirm your booking on-site within 2 hours.</div>-->
  <!--        </li>-->
  <!--        <li>-->
  <!--          <div class="step-num">3</div>-->
  <!--          <div><strong>Pay your deposit</strong><br>Secure your slot with a non-refundable 50% deposit — deducted from-->
  <!--            your final bill.</div>-->
  <!--        </li>-->
  <!--        <li>-->
  <!--          <div class="step-num">4</div>-->
  <!--          <div><strong>You're all set!</strong><br>See you at the studio — come ready to glow.</div>-->
  <!--        </li>-->
  <!--      </ul>-->
  <!--      <div class="booking-contact-links">-->
  <!--        <a href="book.php" class="contact-chip whatsapp">-->
  <!--          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"-->
  <!--            stroke-width="2">-->
  <!--            <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />-->
  <!--          </svg>-->
  <!--          Book Online-->
  <!--        </a>-->
  <!--        <a href="tel:0105007562" class="contact-chip phone">-->
  <!--          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"-->
  <!--            stroke-width="2">-->
  <!--            <path-->
  <!--              d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.1 11a19.79 19.79 0 01-3.07-8.67A2 2 0 012 .84h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 8.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z" />-->
  <!--          </svg>-->
  <!--          010 500 7562-->
  <!--        </a>-->
  <!--      </div>-->
  <!--    </div>-->

  <!--    <div class="booking-form-wrap">-->
  <!--      <form class="booking-form" id="bookingForm" data-server-submit="1" method="post" action="booking.php"-->
  <!--        novalidate>-->
  <!--        <h3>Request a Booking</h3>-->

  <!--        <div class="form-row">-->
  <!--          <div class="form-group">-->
  <!--            <label for="firstName">First Name *</label>-->
  <!--            <input type="text" id="firstName" name="firstName" placeholder="Your first name" required />-->
  <!--            <span class="field-error" id="firstNameError"></span>-->
  <!--          </div>-->
  <!--          <div class="form-group">-->
  <!--            <label for="lastName">Last Name *</label>-->
  <!--            <input type="text" id="lastName" name="lastName" placeholder="Your last name" required />-->
  <!--            <span class="field-error" id="lastNameError"></span>-->
  <!--          </div>-->
  <!--        </div>-->

  <!--        <div class="form-group">-->
  <!--          <label for="phone">WhatsApp / Phone Number *</label>-->
  <!--          <input type="tel" id="phone" name="phone" placeholder="e.g. 071 234 5678" required />-->
  <!--          <span class="field-error" id="phoneError"></span>-->
  <!--        </div>-->

  <!--        <div class="form-group">-->
  <!--          <label for="email">Email Address</label>-->
  <!--          <input type="email" id="email" name="email" placeholder="Optional — for confirmation email" />-->
  <!--          <span class="field-error" id="emailError"></span>-->
  <!--        </div>-->

  <!--        <div class="form-row">-->
  <!--          <div class="form-group">-->
  <!--            <label for="service">Service Required *</label>-->
  <!--            <select id="service" name="service" required>-->
  <!--              <option value="">Select a service…</option>-->
  <!--              <optgroup label="Braiding Services">-->
  <!--                <option value="braids">Braids</option>-->
  <!--                <option value="cornrows">Cornrows</option>-->
  <!--              </optgroup>-->
  <!--              <optgroup label="Hair Styling">-->
  <!--                <option value="ponytail">Ponytail</option>-->
  <!--                <option value="wig-installation">Wig Installation</option>-->
  <!--                <option value="hair-colour">Hair Colour</option>-->
  <!--                <option value="other-styling">Other Hair Styling</option>-->
  <!--              </optgroup>-->
  <!--              <optgroup label="Makeup">-->
  <!--                <option value="makeup">Makeup Artistry</option>-->
  <!--              </optgroup>-->
  <!--              <optgroup label="Other">-->
  <!--                <option value="mobile">Mobile Service</option>-->
  <!--                <option value="other">Other (specify below)</option>-->
  <!--              </optgroup>-->
  <!--            </select>-->
  <!--            <span class="field-error" id="serviceError"></span>-->
  <!--          </div>-->
  <!--          <div class="form-group">-->
  <!--            <label for="location">Preferred Location *</label>-->
  <!--            <select id="location" name="location" required>-->
  <!--              <option value="">Select location…</option>-->
  <!--              <option value="midrand">Midrand Studio</option>-->
  <!--              <option value="copperleaf">Copperleaf Studio</option>-->
  <!--              <option value="mobile">Mobile (come to me)</option>-->
  <!--            </select>-->
  <!--            <span class="field-error" id="locationError"></span>-->
  <!--          </div>-->
  <!--        </div>-->

  <!--        <div class="form-row">-->
  <!--          <div class="form-group">-->
  <!--            <label for="stylist">Preferred Stylist *</label>-->
  <!--            <select id="stylist" name="stylist" required>-->
  <!--              <option value="">Select a stylist…</option>-->
  <!--              <option value="no-preference">No Preference</option>-->
  <!--              <option value="amara">Amara</option>-->
  <!--              <option value="zara">Zara</option>-->
  <!--              <option value="thandeka">Thandeka</option>-->
  <!--            </select>-->
  <!--            <span class="field-error" id="stylistError"></span>-->
  <!--          </div>-->
  <!--        </div>-->

          <!-- Dynamic: Service info banner -->
  <!--        <div class="service-info-banner" id="serviceInfoBanner" hidden>-->
  <!--          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"-->
  <!--            stroke-width="2">-->
  <!--            <circle cx="12" cy="12" r="10" />-->
  <!--            <line x1="12" y1="8" x2="12" y2="12" />-->
  <!--            <line x1="12" y1="16" x2="12.01" y2="16" />-->
  <!--          </svg>-->
  <!--          <p id="serviceInfoText"></p>-->
  <!--        </div>-->

          <!-- Dynamic: Sub-type + Length row -->
  <!--        <div class="form-row" id="subTypeRow" hidden>-->
  <!--          <div class="form-group">-->
  <!--            <label for="subType" id="subTypeLabel">Style *</label>-->
  <!--            <select id="subType" name="subType">-->
  <!--              <option value="">Select…</option>-->
  <!--            </select>-->
  <!--            <span class="field-error" id="subTypeError"></span>-->
  <!--          </div>-->
  <!--          <div class="form-group" id="lengthGroup" hidden>-->
  <!--            <label for="hairLength">Braid Length *</label>-->
  <!--            <select id="hairLength" name="hairLength">-->
  <!--              <option value="">Select length…</option>-->
  <!--              <option value="short">Short — up to shoulder</option>-->
  <!--              <option value="medium">Medium — armpit length</option>-->
  <!--              <option value="long">Long — waist length</option>-->
  <!--              <option value="extra-long">Extra Long — below waist</option>-->
  <!--            </select>-->
  <!--            <span class="field-error" id="hairLengthError"></span>-->
  <!--          </div>-->
  <!--        </div>-->

  <!--        <div class="form-row">-->
  <!--          <div class="form-group">-->
  <!--            <label for="preferredDate">Preferred Date *</label>-->
  <!--            <input type="date" id="preferredDate" name="preferredDate" required />-->
  <!--            <span class="field-error" id="preferredDateError"></span>-->
  <!--          </div>-->
  <!--          <div class="form-group">-->
  <!--            <label for="preferredTime">Preferred Time *</label>-->
  <!--            <select id="preferredTime" name="preferredTime" required>-->
  <!--              <option value="">Select time…</option>-->
  <!--              <option value="08:00">08:00 AM</option>-->
  <!--              <option value="09:00">09:00 AM</option>-->
  <!--              <option value="10:00">10:00 AM</option>-->
  <!--              <option value="11:00">11:00 AM</option>-->
  <!--              <option value="12:00">12:00 PM</option>-->
  <!--              <option value="13:00">01:00 PM</option>-->
  <!--              <option value="14:00">02:00 PM</option>-->
  <!--              <option value="15:00">03:00 PM</option>-->
  <!--              <option value="16:00">04:00 PM</option>-->
  <!--              <option value="17:00">05:00 PM</option>-->
  <!--              <option value="before-hours">Before Hours (extra R200)</option>-->
  <!--              <option value="after-hours">After Hours (extra R200)</option>-->
  <!--            </select>-->
  <!--            <span class="field-error" id="preferredTimeError"></span>-->
  <!--          </div>-->
  <!--        </div>-->

  <!--        <div class="form-group">-->
  <!--          <label for="notes">Additional Notes</label>-->
  <!--          <textarea id="notes" name="notes" rows="3"-->
  <!--            placeholder="Any special requests, references or details about your preferred style…"></textarea>-->
  <!--        </div>-->

  <!--        <div class="form-group form-checkbox">-->
  <!--          <label class="checkbox-label">-->
  <!--            <input type="checkbox" id="depositAgree" name="depositAgree" required />-->
  <!--            <span class="checkmark"></span>-->
  <!--            <span class="checkbox-copy">I have read and agree to the <a href="policy.html" class="policy-link">Booking-->
  <!--                Policy</a>. I understand a <strong>non-refundable 50% deposit</strong> is required to confirm my-->
  <!--              booking. *</span>-->
  <!--          </label>-->
  <!--          <span class="field-error" id="depositAgreeError"></span>-->
  <!--        </div>-->

  <!--        <div class="form-notice">-->
  <!--          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"-->
  <!--            stroke-width="2">-->
  <!--            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />-->
  <!--          </svg>-->
  <!--          Your details are kept confidential and used only for booking purposes.-->
  <!--        </div>-->

  <!--        <button type="submit" class="btn btn-gold btn-full" id="submitBtn">-->
  <!--          <span class="btn-text">Review Booking →</span>-->
  <!--          <span class="btn-loading" hidden>Processing…</span>-->
  <!--        </button>-->
  <!--      </form>-->

        <!-- Step 2: Review booking summary -->
  <!--      <div class="booking-summary" id="bookingSummary" hidden>-->
  <!--        <div class="summary-header">-->
  <!--          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"-->
  <!--            stroke-width="2">-->
  <!--            <path d="M9 11l3 3L22 4" />-->
  <!--            <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" />-->
  <!--          </svg>-->
  <!--          <div>-->
  <!--            <h4>Review Your Booking</h4>-->
  <!--            <p>Confirm all details are correct before we send your request to Bella.</p>-->
  <!--          </div>-->
  <!--        </div>-->
  <!--        <div class="summary-table" id="summaryDetails"></div>-->
  <!--        <div class="summary-notice">-->
  <!--          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"-->
  <!--            stroke-width="2">-->
  <!--            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />-->
  <!--          </svg>-->
  <!--          A non-refundable <strong>50% deposit</strong> is required to secure your slot. Payment details will be sent-->
  <!--          via WhatsApp after confirmation.-->
  <!--        </div>-->
  <!--        <div class="summary-actions">-->
  <!--          <button type="button" class="btn btn-outline-gold" id="editBookingBtn">← Edit Details</button>-->
  <!--          <button type="button" class="btn btn-gold" id="confirmBookingBtn">-->
  <!--            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="16" height="16">-->
  <!--              <path-->
  <!--                d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />-->
  <!--            </svg>-->
  <!--            Confirm &amp; Book-->
  <!--          </button>-->
  <!--        </div>-->
  <!--      </div>-->

        <!-- Step 3: Booking confirmed -->
  <!--      <div class="booking-confirmed" id="bookingConfirmed" hidden>-->
  <!--        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"-->
  <!--          stroke-width="2">-->
  <!--          <path d="M22 11.08V12a10 10 0 11-5.93-9.14" />-->
  <!--          <polyline points="22 4 12 14.01 9 11.01" />-->
  <!--        </svg>-->
  <!--        <div>-->
  <!--          <strong>Booking Sent!</strong>-->
  <!--          <p>Your request has been sent to Bella via WhatsApp. We'll confirm your appointment within 2 business hours-->
  <!--            and send deposit payment details.</p>-->
  <!--          <button type="button" class="btn btn-gold" id="newBookingBtn" style="margin-top:1rem;">Make Another-->
  <!--            Booking</button>-->
  <!--        </div>-->
  <!--      </div>-->
  <!--    </div>-->
  <!--  </div>-->
  <!--</section>-->

  <!-- ======= SOCIAL MEDIA STRIP ======= -->
  <section class="instagram-strip">
    <div class="container instagram-inner">
      <p>Follow our work on social media</p>
      <div style="display:flex;gap:2rem;align-items:center;justify-content:center;flex-wrap:wrap;">
        <a href="https://www.instagram.com/bella_hair_and_makeup" target="_blank" rel="noopener" class="insta-handle">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="2" y="2" width="20" height="20" rx="5" ry="5" />
            <path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z" />
            <line x1="17.5" y1="6.5" x2="17.51" y2="6.5" />
          </svg>
          @bella_hair_and_makeup
    
        </a>
        <a href="https://www.tiktok.com/@bella_hair_and_makeup" target="_blank" rel="noopener" class="insta-handle">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="24" height="24" fill="currentColor">
            <path d="M33.5 6c0 4.142 3.358 7.5 7.5 7.5v5.25c-2.13.002-4.23-.417-6.2-1.24V33c0 6.075-4.925 11-11 11s-11-4.925-11-11 4.925-11 11-11c.254 0 .507.009.76.025V27.3A4.7 4.7 0 0 0 24 27c-3.032 0-5.5 2.468-5.5 5.5S20.968 38 24 38s5.5-2.468 5.5-5.5V6h4z"/>
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
          <li><span>Sunday</span><span>Midrand 11:00 – 16:00 | Copperleaf Closed</span></li>
          <li><span>Public Holidays</span><span>08:00 – 14:00</span></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <p>&copy; 2026 Bella Hair | Makeup. All rights reserved.</p>
    <!--  <p class="footer-dev">Website developed by <a href="https://www.mplai.co.za" target="_blank" rel="noopener">MPL-->
    <!--      AI</a> In Partnership with <span style="color: var(--gold-dark); font-weight: 600;">CALEBVERSE</span></p>-->
    <!--</div>-->
  </footer>

  <div class="nav-backdrop" id="navBackdrop"></div>
  <script src="js/main.js"></script>

</body>

</html>