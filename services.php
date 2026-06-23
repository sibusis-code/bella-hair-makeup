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
  <meta name="description" content="Bella Hair & Makeup — Full price list for braids, cornrows, wigs, ponytails, makeup, hair care & more." />
  <title>Services & Pricing — Bella Hair | Makeup</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet" />
  <link rel="canonical" href="https://bellahairandmakeup.co.za/services.php" />
  <link rel="icon" href="images/logo.jpeg" type="image/jpeg" />
  <link rel="stylesheet" href="css/style.css" />
  <?php echo ga4Snippet(); ?>
</head>
<body>

  <!-- ======= NAVIGATION ======= -->
  <header class="site-header scrolled" id="header">
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
        <li><a href="index.php">Home</a></li>
        <li><a href="about.php">About</a></li>
        <li><a href="services.php" class="page-active">Services & Pricing</a></li>
        <li><a href="policy.php">Policy</a></li>
        <li><a href="book.php" class="nav-cta">Book Now</a></li>
      </ul>
    </nav>
  </header>

  <!-- ======= PAGE HEADER ======= -->
  <section class="page-hero section-dark" style="padding:8rem 0 4rem;text-align:center;">
    <p class="section-eyebrow light">What We Offer</p>
    <h1 class="section-title light">Services &amp; <em>Pricing</em></h1>
    <p style="color:#aaa;font-size:0.95rem;max-width:640px;margin:0 auto 2rem;">All prices are in ZAR and are set per style and length below. We offer mobile services, including group bookings for weddings, celebrations, film productions and corporate shoots. WhatsApp for a quote.</p>
    <a href="book.php" class="btn btn-gold">Book an Appointment</a>
  </section>

  <!-- ======= PRICING NOTE ======= -->
  <div style="max-width:900px;margin:2rem auto 0;padding:0 1rem;">
    <div style="background:linear-gradient(135deg, #fff9e6 0%, #fff3cd 100%);border-left:4px solid #b07a2d;padding:1.2rem 1.5rem;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
      <div style="display:flex;align-items:start;gap:1rem;">
        <span style="font-size:1.5rem;line-height:1;">💡</span>
        <div>
          <strong style="color:#7a5920;font-size:1rem;display:block;margin-bottom:0.5rem;">Pricing Information</strong>
          <p style="margin:0;color:#5a4515;font-size:0.9rem;line-height:1.6;">
            The rates below are our current set prices per style and length. Choose your exact style, length and any extras in our
            <a href="book.php" style="color:#b07a2d;font-weight:600;text-decoration:underline;">online booking system</a>
            to see your total and pay a 50% deposit to confirm.
          </p>
        </div>
      </div>
    </div>
  </div>

  <!-- ======= MAIN CONTENT ======= -->
  <main class="pricelist-page">
    <div class="container" style="padding-top:3rem;">

      <!-- Category Pills Nav -->
      <nav class="pricelist-category-nav" aria-label="Jump to category">
        <a href="#braids"    class="cat-pill">Braids</a>
        <a href="#cornrows"  class="cat-pill">Cornrows &amp; Locs</a>
        <a href="#wigs"      class="cat-pill">Wigs</a>
        <a href="#ponytail"  class="cat-pill">Ponytail</a>
        <a href="#makeup"    class="cat-pill">Make-Up</a>
        <a href="#haircare"  class="cat-pill">Hair Care</a>
        <a href="#sewin"     class="cat-pill">Sewin</a>
      </nav>

      <!-- ================================================ -->
      <!-- BRAIDS -->
      <!-- ================================================ -->
      <section class="pricelist-section" id="braids">
        <div class="pl-divider"><span>Braids</span></div>

        <!-- Extras banner -->
        <div class="extras-banner">
          <div class="extra-item"><strong>+R200</strong> Small (S) Size</div>
          <div class="extra-item"><strong>+R200</strong> Beads Extra</div>
          <div class="extra-item"><strong>+R50</strong>  Curling Ends</div>
          <div class="extra-item"><strong>+R250</strong> French Curl Ends</div>
          <div class="extra-item"><strong>+R100</strong> Hairpiece Colour Blend</div>
        </div>

        <div class="pricelist-grid">

          <div class="price-group">
            <div class="price-group-header">Knotless Braids</div>
            <div class="price-group-body">
              <div class="price-row"><span class="price-item-name">Bra / Shoulder Length</span><span class="price-amount">R650</span></div>
              <div class="price-row"><span class="price-item-name">Waist Length</span><span class="price-amount">R750</span></div>
              <div class="price-row"><span class="price-item-name">Bum Length</span><span class="price-amount">R950</span></div>
            </div>
          </div>

          <div class="price-group">
            <div class="price-group-header">Normal Braids</div>
            <div class="price-group-body">
              <div class="price-row"><span class="price-item-name">Bra / Shoulder Length</span><span class="price-amount">R550</span></div>
              <div class="price-row"><span class="price-item-name">Waist Length</span><span class="price-amount">R650</span></div>
              <div class="price-row"><span class="price-item-name">Bum Length</span><span class="price-amount">R850</span></div>
            </div>
          </div>

          <div class="price-group">
            <div class="price-group-header">Koroba Braids</div>
            <div class="price-group-body">
              <div class="price-row"><span class="price-item-name">Koroba Knotless Braids</span><span class="price-amount">R850</span></div>
              <div class="price-row"><span class="price-item-name">Koroba Normal Braids</span><span class="price-amount">R750</span></div>
              <div class="price-row"><span class="price-item-name">Koroba Tribal Braids</span><span class="price-amount">R650</span></div>
            </div>
          </div>

          <div class="price-group">
            <div class="price-group-header">Goddess Knotless Braids</div>
            <div class="price-group-body">
              <div class="price-row"><span class="price-item-name">Bra / Shoulder Length</span><span class="price-amount">R750</span></div>
              <div class="price-row"><span class="price-item-name">Waist Length</span><span class="price-amount">R850</span></div>
              <div class="price-row"><span class="price-item-name">Bum Length</span><span class="price-amount">R1 050</span></div>
            </div>
          </div>

          <div class="price-group">
            <div class="price-group-header">Goddess Normal Braids</div>
            <div class="price-group-body">
              <div class="price-row"><span class="price-item-name">Bra / Shoulder Length</span><span class="price-amount">R650</span></div>
              <div class="price-row"><span class="price-item-name">Waist Length</span><span class="price-amount">R750</span></div>
              <div class="price-row"><span class="price-item-name">Bum Length</span><span class="price-amount">R950</span></div>
            </div>
          </div>

          <div class="price-group">
            <div class="price-group-header">Knotless Boho with French Curls</div>
            <div class="price-group-body">
              <div class="price-row"><span class="price-item-name">Bra Length</span><span class="price-amount">R950</span></div>
              <div class="price-row"><span class="price-item-name">Waist Length</span><span class="price-amount">R1 200</span></div>
              <div class="price-row"><span class="price-item-name">Bum Length</span><span class="price-amount">R1 400</span></div>
            </div>
          </div>

          <div class="price-group">
            <div class="price-group-header">French Curls</div>
            <div class="price-group-body">
              <div class="price-row"><span class="price-item-name">Bra / Shoulder Length</span><span class="price-amount">R950</span></div>
              <div class="price-row"><span class="price-item-name">Waist Length</span><span class="price-amount">R1 200</span></div>
              <div class="price-row"><span class="price-item-name">Bum Length</span><span class="price-amount">R1 400</span></div>
            </div>
          </div>

          <div class="price-group">
            <div class="price-group-header">Boho French Curls</div>
            <div class="price-group-body">
              <div class="price-row"><span class="price-item-name">Shoulder Length</span><span class="price-amount">R950</span></div>
              <div class="price-row"><span class="price-item-name">Waist Length</span><span class="price-amount">R1 300</span></div>
            </div>
          </div>

          <div class="price-group">
            <div class="price-group-header">Tribal French Curl</div>
            <div class="price-group-body">
              <div class="price-row"><span class="price-item-name">Tribal French Curl</span><span class="price-amount">R950</span></div>
            </div>
          </div>

          <div class="price-group">
            <div class="price-group-header">Tribal Braids</div>
            <div class="price-group-body">
              <div class="price-row"><span class="price-item-name">Bra Length</span><span class="price-amount">R450</span></div>
              <div class="price-row"><span class="price-item-name">Waist Length</span><span class="price-amount">R550</span></div>
              <div class="price-row"><span class="price-item-name">Bum Length</span><span class="price-amount">R750</span></div>
            </div>
          </div>

          <div class="price-group">
            <div class="price-group-header">Boho Tribal Braids</div>
            <div class="price-group-body">
              <div class="price-row"><span class="price-item-name">Bra / Shoulder Length</span><span class="price-amount">R650</span></div>
              <div class="price-row"><span class="price-item-name">Waist Length</span><span class="price-amount">R750</span></div>
              <div class="price-row"><span class="price-item-name">Bum Length</span><span class="price-amount">R850</span></div>
            </div>
          </div>

          <div class="price-group">
            <div class="price-group-header">Kinky Twist</div>
            <div class="price-group-body">
              <div class="price-row"><span class="price-item-name">Bra / Shoulder Length</span><span class="price-amount">R650</span></div>
              <div class="price-row"><span class="price-item-name">Waist Length</span><span class="price-amount">R800</span></div>
            </div>
          </div>

          <div class="price-group">
            <div class="price-group-header">Jumbo Knotless</div>
            <div class="price-group-body">
              <div class="price-row"><span class="price-item-name">Bra Length</span><span class="price-amount">R950</span></div>
              <div class="price-row"><span class="price-item-name">Waist Length</span><span class="price-amount">R1 200</span></div>
              <div class="price-row"><span class="price-item-name">Bum Length</span><span class="price-amount">R1 500</span></div>
            </div>
          </div>

          <div class="price-group">
            <div class="price-group-header">Jumbo Normal</div>
            <div class="price-group-body">
              <div class="price-row"><span class="price-item-name">Bra Length</span><span class="price-amount">R850</span></div>
              <div class="price-row"><span class="price-item-name">Waist Length</span><span class="price-amount">R1 050</span></div>
              <div class="price-row"><span class="price-item-name">Bum Length</span><span class="price-amount">R1 150</span></div>
            </div>
          </div>

          <div class="price-group">
            <div class="price-group-header">Lemonade Braids</div>
            <div class="price-group-body">
              <div class="price-row"><span class="price-item-name">Shoulder Length</span><span class="price-amount">R750</span></div>
              <div class="price-row"><span class="price-item-name">Bra Length</span><span class="price-amount">R850</span></div>
            </div>
          </div>

          <div class="price-group">
            <div class="price-group-header">Jayda Wayda Sewin</div>
            <div class="price-group-body">
              <div class="price-row"><span class="price-item-name">Bra Length</span><span class="price-amount">R550</span></div>
              <div class="price-row"><span class="price-item-name">Waist Length</span><span class="price-amount">R650</span></div>
            </div>
          </div>

        </div><!-- /pricelist-grid (braids) -->
      </section>

      <!-- ================================================ -->
      <!-- CORNROWS & LOCS -->
      <!-- ================================================ -->
      <section class="pricelist-section" id="cornrows">
        <div class="pl-divider"><span>Cornrows &amp; Locs</span></div>
        <div class="pricelist-grid">

          <div class="price-group">
            <div class="price-group-header">Straightback Cornrows</div>
            <div class="price-group-body">
              <div class="price-row"><span class="price-item-name">Bra / Shoulder Length</span><span class="price-amount">R400</span></div>
              <div class="price-row"><span class="price-item-name">Waist Length</span><span class="price-amount">R450</span></div>
              <div class="price-row"><span class="price-item-name">Bum Length</span><span class="price-amount">R500</span></div>
            </div>
          </div>

          <div class="price-group">
            <div class="price-group-header">Stitch Cornrows</div>
            <div class="price-group-body">
              <div class="price-row"><span class="price-item-name">Bra / Shoulder Length</span><span class="price-amount">R450</span></div>
              <div class="price-row"><span class="price-item-name">Waist Length</span><span class="price-amount">R500</span></div>
              <div class="price-row"><span class="price-item-name">Bum Length</span><span class="price-amount">R550</span></div>
            </div>
          </div>

          <div class="price-group">
            <div class="price-group-header">Straight Up Cornrows</div>
            <div class="price-group-body">
              <div class="price-row"><span class="price-item-name">Waist Length</span><span class="price-amount">R650</span></div>
              <div class="price-row"><span class="price-item-name">Bum Length</span><span class="price-amount">R750</span></div>
            </div>
          </div>

          <div class="price-group">
            <div class="price-group-header">Cornrows (Wig Lines)</div>
            <div class="price-group-body">
              <div class="price-row"><span class="price-item-name">Wig Lines (8–10 lines)</span><span class="price-amount">R250</span></div>
              <div class="price-row"><span class="price-item-name">Freehand (12+ lines)</span><span class="price-amount">R350</span></div>
            </div>
          </div>

          <div class="price-group">
            <div class="price-group-header">Invisible Locs</div>
            <div class="price-group-body">
              <div class="price-row"><span class="price-item-name">Shoulder Length</span><span class="price-amount">R550</span></div>
              <div class="price-row"><span class="price-item-name">Waist Length</span><span class="price-amount">R750</span></div>
            </div>
          </div>

          <div class="price-group">
            <div class="price-group-header">Locs (Other)</div>
            <div class="price-group-body">
              <div class="price-row"><span class="price-item-name">Butterfly Locs</span><span class="price-amount">R1 150</span></div>
              <div class="price-row"><span class="price-item-name">River Locs</span><span class="price-amount">R1 200</span></div>
              <div class="price-row"><span class="price-item-name">Nana Locs</span><span class="price-amount">R1 400</span></div>
            </div>
          </div>

        </div>
      </section>

      <!-- ================================================ -->
      <!-- WIGS -->
      <!-- ================================================ -->
      <section class="pricelist-section" id="wigs">
        <div class="pl-divider"><span>Wigs</span></div>
        <div class="pricelist-grid">

          <div class="price-group">
            <div class="price-group-header">Wig Services</div>
            <div class="price-group-body">
              <div class="price-row"><span class="price-item-name">Basic Wig Installation</span><span class="price-amount">R500</span></div>
              <div class="price-row"><span class="price-item-name">Basic Wig Installation + Wig Lines</span><span class="price-amount">R750</span></div>
              <div class="price-row"><span class="price-item-name">360 Wig Installation</span><span class="price-amount">R800</span></div>
            </div>
          </div>

          <div class="price-group">
            <div class="price-group-header">Wig Style</div>
            <div class="price-group-body">
              <div class="price-row"><span class="price-item-name">Ponytail</span><span class="price-amount">R150</span></div>
              <div class="price-row"><span class="price-item-name">Half Up Ponytail</span><span class="price-amount">R150</span></div>
              <div class="price-row"><span class="price-item-name">Half Up Ponytail with Lines</span><span class="price-amount">R200</span></div>
              <div class="price-row"><span class="price-item-name">Half Up Ponytail with Curls</span><span class="price-amount">R250</span></div>
              <div class="price-row"><span class="price-item-name">Full Curls</span><span class="price-amount">R350</span></div>
              <div class="price-row"><span class="price-item-name">Bridal Style</span><span class="price-amount">R350</span></div>
            </div>
          </div>

          <div class="price-group">
            <div class="price-group-header">Wig Labour</div>
            <div class="price-group-body">
              <div class="price-row"><span class="price-item-name">Wig Making</span><span class="price-amount">R600</span></div>
              <div class="price-row"><span class="price-item-name">Lace Wash</span><span class="price-amount">R50</span></div>
              <div class="price-row"><span class="price-item-name">Lace Removal</span><span class="price-amount">R100</span></div>
              <div class="price-row"><span class="price-item-name">Wig Customisation</span><span class="price-amount">R250</span></div>
              <div class="price-row"><span class="price-item-name">Wig Treatment</span><span class="price-amount">R350</span></div>
            </div>
          </div>

        </div>
      </section>

      <!-- ================================================ -->
      <!-- PONYTAIL / FRONTAL PONYTAIL -->
      <!-- ================================================ -->
      <section class="pricelist-section" id="ponytail">
        <div class="pl-divider"><span>Ponytail</span></div>
        <div class="pricelist-grid">

          <div class="price-group">
            <div class="price-group-header">Ponytail</div>
            <div class="price-group-body">
              <div class="price-row"><span class="price-item-name">Curly</span><span class="price-amount">R500</span></div>
              <div class="price-row"><span class="price-item-name">Straight</span><span class="price-amount">R450</span></div>
              <div class="price-row"><span class="price-item-name">Half Up Sewin Ponytail</span><span class="price-amount">R650</span></div>
              <div class="price-row"><span class="price-item-name">Afro Twist</span><span class="price-amount">R550</span></div>
            </div>
          </div>

          <div class="price-group">
            <div class="price-group-header">Frontal Ponytail (Bella Closure + Bundles)</div>
            <div class="price-group-body">
              <div class="price-row"><span class="price-item-name">HD Frontal Closure + 24" Brazilian Bundles</span><span class="price-amount">R3 950</span></div>
              <div class="price-row"><span class="price-item-name">Swiss Frontal Closure + 24" Brazilian Bundles</span><span class="price-amount">R2 800</span></div>
              <div class="price-row"><span class="price-item-name">Swiss Frontal Closure + Synthetic Bundles</span><span class="price-amount">R1 350</span></div>
              <div class="price-row"><span class="price-item-name">With Your Closure + Bundles</span><span class="price-amount">R650</span></div>
            </div>
          </div>

        </div>
      </section>

      <!-- ================================================ -->
      <!-- MAKE-UP -->
      <!-- ================================================ -->
      <section class="pricelist-section" id="makeup">
        <div class="pl-divider"><span>Make-Up</span></div>
        <div class="pricelist-grid">

          <div class="price-group">
            <div class="price-group-header">Make-Up</div>
            <div class="price-group-body">
              <div class="price-row"><span class="price-item-name">Full / Soft Glam (includes lashes)</span><span class="price-amount">R750</span></div>
              <div class="price-row"><span class="price-item-name">Eyebrow Shaping</span><span class="price-amount">R100</span></div>
            </div>
          </div>

          <div class="price-group">
            <div class="price-group-header">Individual Lesson (Studio)</div>
            <div class="price-group-body">
              <div class="price-row"><span class="price-item-name">Daily Application | 2hrs (includes full set of brushes)</span><span class="price-amount">R1 400</span></div>
              <div class="price-row"><span class="price-item-name">Eyebrow Shaping included</span><span class="price-amount">—</span></div>
            </div>
          </div>

          <div class="price-group">
            <div class="price-group-header">Individual Lesson (Min 4 ppl)</div>
            <div class="price-group-body">
              <div class="price-row"><span class="price-item-name">Daily Application | 2hrs (includes full set of brushes)</span><span class="price-amount">R1 250</span></div>
            </div>
          </div>

        </div>

        <div style="margin-top:1.5rem;text-align:center;">
          <a href="https://wa.me/27712345678?text=Hi%20Bella%2C%20I%27d%20like%20to%20enquire%20about%20Makeup%20services" target="_blank" rel="noopener" class="btn btn-gold">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="16" height="16" style="margin-right:0.35rem"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            WhatsApp for Makeup Enquiries
          </a>
        </div>
      </section>

      <!-- ================================================ -->
      <!-- HAIR CARE -->
      <!-- ================================================ -->
      <section class="pricelist-section" id="haircare">
        <div class="pl-divider"><span>Hair Care</span></div>
        <div class="pricelist-grid">

          <div class="price-group">
            <div class="price-group-header">Hair Care</div>
            <div class="price-group-body">
              <div class="price-row"><span class="price-item-name">Mizani Silk-Press</span><span class="price-amount">R550</span></div>
            </div>
          </div>

          <div class="price-group">
            <div class="price-group-header">Wash</div>
            <div class="price-group-body">
              <div class="price-row"><span class="price-item-name">Natural Hair</span><span class="price-amount">R200</span></div>
              <div class="price-row"><span class="price-item-name">Relaxed Hair</span><span class="price-amount">R150</span></div>
              <div class="price-row"><span class="price-item-name">Detangle</span><span class="price-amount">R100</span></div>
              <div class="price-row"><span class="price-item-name">Undo Braids Normal</span><span class="price-amount">R150</span></div>
              <div class="price-row"><span class="price-item-name">Undo Braids Small</span><span class="price-amount">R200</span></div>
              <div class="price-row"><span class="price-item-name">Undo Cornrows</span><span class="price-amount">R50</span></div>
            </div>
          </div>

          <div class="price-group">
            <div class="price-group-header">Deep Conditioning</div>
            <div class="price-group-body">
              <div class="price-row"><span class="price-item-name">Hair Moisture Mayonnaise</span><span class="price-amount">R150</span></div>
            </div>
          </div>

          <div class="price-group">
            <div class="price-group-header">Relaxer</div>
            <div class="price-group-body">
              <div class="price-row"><span class="price-item-name">Pure Royal</span><span class="price-amount">R350</span></div>
              <div class="price-row"><span class="price-item-name">Mizani Moisture Treatment</span><span class="price-amount">R350</span></div>
              <div class="price-row"><span class="price-item-name">Mizani Strength</span><span class="price-amount">R400</span></div>
              <div class="price-row"><span class="price-item-name">Design Essential Anti-Itchy</span><span class="price-amount">R450</span></div>
              <div class="price-row"><span class="price-item-name">Design Essential Moisture</span><span class="price-amount">R350</span></div>
              <div class="price-row"><span class="price-item-name">Native Child</span><span class="price-amount">R350</span></div>
              <div class="price-row"><span class="price-item-name">Dark n Lovely Moisture</span><span class="price-amount">R250</span></div>
            </div>
          </div>

        </div>
      </section>

      <!-- ================================================ -->
      <!-- SEWIN -->
      <!-- ================================================ -->
      <section class="pricelist-section" id="sewin">
        <div class="pl-divider"><span>Sewin</span></div>
        <div class="pricelist-grid">

          <div class="price-group">
            <div class="price-group-header">Sewin</div>
            <div class="price-group-body">
              <div class="price-row"><span class="price-item-name">Weave Sewin</span><span class="price-amount">R650</span></div>
              <div class="price-row"><span class="price-item-name">Weave Sewin Brazilian</span><span class="price-amount">R1 800</span></div>
            </div>
          </div>

        </div>
      </section>

      <!-- CTA -->
      <div style="text-align:center;padding:3rem 0 5rem;">
        <p style="font-family:'Cormorant Garamond',serif;font-size:1.3rem;font-style:italic;color:#777;margin-bottom:1.5rem;">Ready to book? A non-refundable 50% deposit is charged on confirmation and deducted from your final bill.</p>
        <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;">
          <a href="book.php" class="btn btn-gold">Book an Appointment</a>
          <a href="<?php echo $whatsappLink; ?>" target="_blank" rel="noopener" class="btn btn-outline-gold">WhatsApp: <?php echo $phoneWhatsapp; ?></a>
        </div>
        <p style="margin-top:1.25rem;font-size:0.78rem;color:#aaa;">Landline: <?php echo $phoneLandline; ?> &nbsp;·&nbsp; WhatsApp (text only): <?php echo $phoneWhatsapp; ?></p>
      </div>

    </div><!-- /container -->
  </main>

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
      <!--<p class="footer-dev">Website developed by <a href="https://www.mplai.co.za" target="_blank" rel="noopener">MPL-->
      <!--    AI</a> In Partnership with <span style="color: var(--gold-dark); font-weight: 600;">CALEBVERSE</span></p>-->
      <!--    </a>-->
      <!--  </div>-->
      <!--</div>-->
    </div>
  </footer>

  <div class="nav-backdrop" id="navBackdrop"></div>
  <script src="js/main.js"></script>

</body>
</html>
