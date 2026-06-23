/* =====================================================
   Bella Hair | Makeup — Main JavaScript
   Handles: sticky nav, mobile menu, form validation,
            date restrictions, form submission
   ===================================================== */

(function () {
  'use strict';

  /* ---- STICKY HEADER ---- */
  const header = document.getElementById('header');
  function onScroll() {
    header.classList.toggle('scrolled', window.scrollY > 60);
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  /* ---- MOBILE NAV ---- */
  const navToggle  = document.getElementById('navToggle');
  const navLinks   = document.getElementById('navLinks');
  const navBackdrop = document.getElementById('navBackdrop');

  function lockBodyScroll() {
    const scrollbarComp = window.innerWidth - document.documentElement.clientWidth;
    document.body.style.setProperty('--scrollbar-comp', scrollbarComp > 0 ? scrollbarComp + 'px' : '0px');
    document.body.classList.add('nav-open');
  }

  function unlockBodyScroll() {
    document.body.classList.remove('nav-open');
    document.body.style.removeProperty('--scrollbar-comp');
  }

  function openNav() {
    navLinks.classList.add('open');
    navToggle.classList.add('open');
    navToggle.setAttribute('aria-expanded', 'true');
    lockBodyScroll();
    if (navBackdrop) navBackdrop.classList.add('open');
  }

  function closeNav() {
    navLinks.classList.remove('open');
    navToggle.classList.remove('open');
    navToggle.setAttribute('aria-expanded', 'false');
    unlockBodyScroll();
    if (navBackdrop) navBackdrop.classList.remove('open');
  }

  if (navToggle && navLinks) {
    navToggle.addEventListener('click', function () {
      navLinks.classList.contains('open') ? closeNav() : openNav();
    });
  }

  if (navBackdrop) {
    navBackdrop.addEventListener('click', closeNav);
  }

  // Close menu when a nav link is clicked
  if (navLinks) {
    navLinks.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', closeNav);
    });
  }

  // Close on outside click
  document.addEventListener('click', function (e) {
    if (!navLinks || !navToggle || !navBackdrop) {
      return;
    }
    if (navLinks.classList.contains('open') &&
        !navLinks.contains(e.target) &&
        !navToggle.contains(e.target) &&
        !navBackdrop.contains(e.target)) {
      closeNav();
    }
  });

  window.addEventListener('resize', function () {
    if (window.innerWidth > 768 && navLinks && navLinks.classList.contains('open')) {
      closeNav();
    }
  });

  /* ---- DATE: restrict to today onwards ---- */
  const dateInput = document.getElementById('preferredDate');
  if (dateInput) {
    const today = new Date();
    // Minimum = today (same-day bookings allowed if time rules pass)
    dateInput.min = today.toISOString().split('T')[0];

    // Max = 90 days out
    const maxDate = new Date(today);
    maxDate.setDate(today.getDate() + 90);
    dateInput.max = maxDate.toISOString().split('T')[0];
  }

  /* ---- DYNAMIC SERVICE FIELDS ---- */
  var serviceSelectEl   = document.getElementById('service');
  var subTypeRow        = document.getElementById('subTypeRow');
  var subTypeSelectEl   = document.getElementById('subType');
  var subTypeLabelEl    = document.getElementById('subTypeLabel');
  var lengthGroup       = document.getElementById('lengthGroup');
  var timeSelectEl      = document.getElementById('preferredTime');
  var svcInfoBanner     = document.getElementById('serviceInfoBanner');
  var svcInfoText       = document.getElementById('serviceInfoText');
  var dbBookingCatalog  = window.Bella_BOOKING_CATALOG || null;
  var businessInfo      = window.Bella_BUSINESS_INFO || {};
  var whatsappUrl       = businessInfo.whatsappUrl || 'https://wa.me/27712345678';
  var phoneWhatsapp     = businessInfo.phoneWhatsapp || '071 234 5678';
  var phoneCall         = businessInfo.phoneCall || phoneWhatsapp;
  var phoneCallHref     = String(phoneCall).replace(/[^0-9+]/g, '') || '+27712345678';
  var hoursMidrand      = businessInfo.hoursMidrand || 'Mon-Sat: 5am-6pm | Sun: Closed';
  var hoursCopperleaf   = businessInfo.hoursCopperleaf || 'Tue-Sat: 5am-6pm | Sun: Closed';
  var addressMidrand    = businessInfo.addressMidrand || '12 Demo Street, Sandton';
  var addressCopperleaf = businessInfo.addressCopperleaf || 'Copperleaf Golf & Country Estate (Appointment only)';

  var SERVICE_CONFIG = {
    'braids': {
      label: 'Type of Braids',
      options: ['Knotless Braids', 'Box Braids', 'Feed-in Braids', 'Goddess Braids', 'Faux Locs'],
      showLength: true,
      info: '<strong>Duration:</strong> 3–4 hours &nbsp;·&nbsp; <strong>Team:</strong> 1 client / 2 Braiders &nbsp;·&nbsp; <strong>Slots:</strong> 7:30am · 11:30am · 2:30pm',
      slots: [
        { value: '07:30', label: '7:30 AM' },
        { value: '11:30', label: '11:30 AM' },
        { value: '14:30', label: '2:30 PM' }
      ]
    },
    'cornrows': {
      label: 'Cornrow Style',
      options: ['Classic Cornrows', 'Feed-in Cornrows', 'Curved / Pattern Cornrows', 'Braided Updo Cornrows'],
      showLength: false,
      info: '<strong>Duration:</strong> 2–3 hours &nbsp;·&nbsp; <strong>Team:</strong> 2 Stylists',
      slots: null
    },
    'ponytail': {
      label: 'Ponytail Style',
      options: ['Straight Ponytail', 'Curly Ponytail', 'Afro Kinky Ponytail'],
      showLength: false,
      info: null,
      slots: null
    },
    'hair-colour': {
      label: 'Hair Colour Service',
      options: ['Full Colour', 'Partial Highlights', 'Root Touch-up'],
      showLength: false,
      info: '<strong>Duration:</strong> 1–2 hours',
      slots: null
    },
    'other-styling': {
      label: 'Styling Type',
      options: ['Blow Dry', 'Set & Curl', 'Roller Set', 'Twist-out'],
      showLength: false,
      info: null,
      slots: null
    },
    'wig-installation': {
      label: 'Wig Type',
      options: ['Full Lace Wig', 'Frontal Wig (13×4)', '360 Lace Wig', 'Closure Wig (4×4)', 'Closure Wig (5×5)', 'Super Double Drawn Wig'],
      showLength: false,
      info: 'Pricing varies by wig type — will be confirmed on booking',
      slots: null
    },
    'makeup': {
      label: 'Makeup Type',
      options: ['Bridal Makeup', 'Events & Functions', 'Editorial Makeup', 'Everyday Glam', 'Graduation Makeup'],
      showLength: false,
      info: 'Makeup bookings are handled directly through our online booking form.',
      slots: null
    },
    'mobile': {
      label: 'Mobile Service Type',
      options: ['Mobile Braids', 'Mobile Makeup'],
      showLength: false,
      info: '<strong>Travel fee:</strong> Additional R200',
      slots: null
    },
    'other': {
      label: 'Other Service',
      options: ['Consultation', 'Other'],
      showLength: false,
      info: null,
      slots: null
    }
  };

  var DEFAULT_SLOTS = [
    { value: '', label: 'Select time…' },
    { value: '08:00', label: '08:00 AM' },
    { value: '09:00', label: '09:00 AM' },
    { value: '10:00', label: '10:00 AM' },
    { value: '11:00', label: '11:00 AM' },
    { value: '12:00', label: '12:00 PM' },
    { value: '13:00', label: '01:00 PM' },
    { value: '14:00', label: '02:00 PM' },
    { value: '15:00', label: '03:00 PM' },
    { value: '16:00', label: '04:00 PM' },
    { value: '17:00', label: '05:00 PM' },
    { value: 'before-hours', label: 'Before Hours (extra R200)' },
    { value: 'after-hours', label: 'After Hours (extra R200)' }
  ];

  var STYLIST_LABELS = {};
  var SERVICE_LOCATION_STYLISTS = {};

  if (dbBookingCatalog) {
    if (dbBookingCatalog.serviceConfig && typeof dbBookingCatalog.serviceConfig === 'object') {
      SERVICE_CONFIG = {};
      Object.keys(dbBookingCatalog.serviceConfig).forEach(function (serviceKey) {
        var src = dbBookingCatalog.serviceConfig[serviceKey] || {};
        SERVICE_CONFIG[serviceKey] = {
          label: src.subTypeLabel || 'Style',
          subtypes: Array.isArray(src.subtypes) ? src.subtypes : [],
          options: Array.isArray(src.subtypes)
            ? src.subtypes.map(function (item) { return item && item.label ? item.label : ''; }).filter(Boolean)
            : [],
          showLength: !!src.showLength,
          info: src.info || null,
          slots: Array.isArray(src.slots) && src.slots.length > 0 ? src.slots : null
        };
      });
    }

    if (Array.isArray(dbBookingCatalog.defaultSlots) && dbBookingCatalog.defaultSlots.length > 0) {
      DEFAULT_SLOTS = [{ value: '', label: 'Select time…' }].concat(dbBookingCatalog.defaultSlots);
    }

    if (dbBookingCatalog.stylists && typeof dbBookingCatalog.stylists === 'object') {
      STYLIST_LABELS = dbBookingCatalog.stylists;
    }

    if (dbBookingCatalog.serviceLocationStylists && typeof dbBookingCatalog.serviceLocationStylists === 'object') {
      SERVICE_LOCATION_STYLISTS = dbBookingCatalog.serviceLocationStylists;
    }
  }

  function setTimeSlots(slots) {
    if (!timeSelectEl) return;
    var list = slots
      ? [{ value: '', label: 'Select time…' }].concat(slots)
      : DEFAULT_SLOTS;
    timeSelectEl.innerHTML = '';
    list.forEach(function (s) {
      var opt = document.createElement('option');
      opt.value = s.value;
      opt.textContent = s.label;
      timeSelectEl.appendChild(opt);
    });
  }

  function updateServiceFields(val) {
    var config = SERVICE_CONFIG[val];
    if (!config) {
      if (subTypeRow) subTypeRow.hidden = true;
      if (svcInfoBanner) svcInfoBanner.hidden = true;
      setTimeSlots(null);
      return;
    }
    // Info banner
    if (config.info) {
      if (svcInfoText) svcInfoText.innerHTML = config.info;
      if (svcInfoBanner) svcInfoBanner.hidden = false;
    } else {
      if (svcInfoBanner) svcInfoBanner.hidden = true;
    }
    // Sub-type label & options
    if (subTypeLabelEl) subTypeLabelEl.textContent = config.label + ' *';
    if (subTypeSelectEl) {
      subTypeSelectEl.innerHTML = '<option value="">Select…</option>';
      if (Array.isArray(config.subtypes) && config.subtypes.length > 0) {
        config.subtypes.forEach(function (item) {
          if (!item || !item.label) return;
          var el = document.createElement('option');
          el.value = item.key || item.label.toLowerCase().replace(/[^a-z0-9]+/g, '-');
          el.textContent = item.label;
          subTypeSelectEl.appendChild(el);
        });
      } else {
        config.options.forEach(function (optText) {
          var el = document.createElement('option');
          el.value = optText.toLowerCase().replace(/[^a-z0-9]+/g, '-');
          el.textContent = optText;
          subTypeSelectEl.appendChild(el);
        });
      }
    }
    if (subTypeRow) subTypeRow.hidden = false;
    if (lengthGroup) lengthGroup.hidden = !config.showLength;
    // Time slots
    setTimeSlots(config.slots || null);
  }

  function updateStylistOptions() {
    var stylistSel = document.getElementById('stylist');
    var locSel     = document.getElementById('location');
    if (!stylistSel || !serviceSelectEl) return;

    var svc = serviceSelectEl.value;
    var loc = locSel ? locSel.value : '';
    var serviceMap = SERVICE_LOCATION_STYLISTS[svc] || {};
    var names = [];
    if (loc && Array.isArray(serviceMap[loc]) && serviceMap[loc].length > 0) {
      names = serviceMap[loc].slice();
    } else if (Array.isArray(serviceMap.all) && serviceMap.all.length > 0) {
      names = serviceMap.all.slice();
    } else {
      names = ['caro', 'emma', 'patience', 'lincy', 'charity', 'itumeleng', 'pamela', 'marlyn', 'ibongiwe'];
    }

    // Save current selection to restore if still available
    var prev = stylistSel.value;

    // Clear and rebuild select safely
    stylistSel.innerHTML = '';

    // Add placeholder
    var placeholderOpt = document.createElement('option');
    placeholderOpt.value = '';
    placeholderOpt.textContent = svc ? 'Select a stylist\u2026' : 'Choose service & location first';
    stylistSel.appendChild(placeholderOpt);

    // Add stylist options
    names.forEach(function (name) {
      var opt = document.createElement('option');
      var normalized = String(name || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
      opt.value = normalized;
      opt.textContent = STYLIST_LABELS[normalized] || STYLIST_LABELS[name] || name;
      stylistSel.appendChild(opt);
    });

    // Try to restore previous selection if it still exists
    if (prev && Array.from(stylistSel.options).some(function (o) { return o.value === prev; })) {
      stylistSel.value = prev;
    } else {
      stylistSel.value = '';
    }
  }

  if (serviceSelectEl) {
    serviceSelectEl.addEventListener('change', function () {
      updateServiceFields(this.value);
      updateStylistOptions();
      clearError('subType');
      clearError('hairLength');
      clearError('stylist');
    });

    // Update stylist list when location changes
    var locationSelectEl = document.getElementById('location');
    if (locationSelectEl) {
      locationSelectEl.addEventListener('change', function () {
        updateStylistOptions();
        clearError('stylist');
      });
    }

    // Initialise stylist options on page load
    updateStylistOptions();

    // Restore previously submitted values on server-rendered booking form (on validation error).
    if (window.Bella_SERVER_BOOKING_DEFAULTS && Object.keys(window.Bella_SERVER_BOOKING_DEFAULTS).length > 0) {
      // Restore text fields
      var textFields = ['firstName', 'lastName', 'phone', 'email', 'notes'];
      textFields.forEach(function (id) {
        if (window.Bella_SERVER_BOOKING_DEFAULTS[id]) {
          var el = document.getElementById(id);
          if (el) el.value = window.Bella_SERVER_BOOKING_DEFAULTS[id];
        }
      });

      // Restore service (triggers field updates)
      if (window.Bella_SERVER_BOOKING_DEFAULTS.service) {
        serviceSelectEl.value = window.Bella_SERVER_BOOKING_DEFAULTS.service;
        updateServiceFields(serviceSelectEl.value);
        updateStylistOptions();
      }

      // Restore location
      if (window.Bella_SERVER_BOOKING_DEFAULTS.location) {
        var locEl = document.getElementById('location');
        if (locEl) {
          locEl.value = window.Bella_SERVER_BOOKING_DEFAULTS.location;
          updateStylistOptions();
        }
      }

      // Restore sub-type
      if (subTypeSelectEl && window.Bella_SERVER_BOOKING_DEFAULTS.subType) {
        subTypeSelectEl.value = window.Bella_SERVER_BOOKING_DEFAULTS.subType;
      }

      // Restore hair length
      if (window.Bella_SERVER_BOOKING_DEFAULTS.hairLength) {
        var hairLengthEl = document.getElementById('hairLength');
        if (hairLengthEl) hairLengthEl.value = window.Bella_SERVER_BOOKING_DEFAULTS.hairLength;
      }

      // Restore date & time
      if (window.Bella_SERVER_BOOKING_DEFAULTS.preferredDate) {
        var dateEl = document.getElementById('preferredDate');
        if (dateEl) dateEl.value = window.Bella_SERVER_BOOKING_DEFAULTS.preferredDate;
      }
      if (window.Bella_SERVER_BOOKING_DEFAULTS.preferredTime) {
        if (timeSelectEl) timeSelectEl.value = window.Bella_SERVER_BOOKING_DEFAULTS.preferredTime;
      }

      // Restore stylist
      if (window.Bella_SERVER_BOOKING_DEFAULTS.stylist) {
        var stylistEl = document.getElementById('stylist');
        if (stylistEl) stylistEl.value = window.Bella_SERVER_BOOKING_DEFAULTS.stylist;
      }

      // Restore deposit checkbox
      if (window.Bella_SERVER_BOOKING_DEFAULTS.depositAgree === true || window.Bella_SERVER_BOOKING_DEFAULTS.depositAgree === '1') {
        var depositEl = document.getElementById('depositAgree');
        if (depositEl) depositEl.checked = true;
      }
    }
  }

  /* ---- FORM VALIDATION & SUBMISSION ---- */
  const form       = document.getElementById('bookingForm');
  const submitBtn  = document.getElementById('submitBtn');
  const formSuccess = document.getElementById('formSuccess');

  if (form) {

  var isServerSubmit = form.getAttribute('data-server-submit') === '1';

  function showError(fieldId, message) {
    const input = document.getElementById(fieldId);
    const error = document.getElementById(fieldId + 'Error');
    if (input) input.classList.add('error');
    if (error) error.textContent = message;
  }

  function clearError(fieldId) {
    const input = document.getElementById(fieldId);
    const error = document.getElementById(fieldId + 'Error');
    if (input) input.classList.remove('error');
    if (error) error.textContent = '';
  }

  function clearAllErrors() {
    form.querySelectorAll('.error').forEach(function (el) {
      el.classList.remove('error');
    });
    form.querySelectorAll('.field-error').forEach(function (el) {
      el.textContent = '';
    });
  }

  function validatePhone(value) {
    // South African phone: 10 digits, optionally starting with +27
    const cleaned = value.replace(/[\s\-()]/g, '');
    return /^(\+27|0)[6-8][0-9]{8}$/.test(cleaned);
  }

  function validateEmail(value) {
    return !value || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
  }

  function validateForm() {
    clearAllErrors();
    let valid = true;

    const firstName    = document.getElementById('firstName').value.trim();
    const lastName     = document.getElementById('lastName').value.trim();
    const phone        = document.getElementById('phone').value.trim();
    const email        = document.getElementById('email').value.trim();
    const service      = document.getElementById('service').value;
    const location     = document.getElementById('location').value;
    const prefDate     = document.getElementById('preferredDate').value;
    const prefTime     = document.getElementById('preferredTime').value;
    const depositAgree = document.getElementById('depositAgree').checked;

    if (!firstName) { showError('firstName', 'Please enter your first name.'); valid = false; }
    if (!lastName)  { showError('lastName',  'Please enter your last name.'); valid = false; }

    if (!phone) {
      showError('phone', 'Please enter a contact number.'); valid = false;
    } else if (!validatePhone(phone)) {
      showError('phone', 'Please enter a valid South African phone number.'); valid = false;
    }

    if (email && !validateEmail(email)) {
      showError('email', 'Please enter a valid email address.'); valid = false;
    }

    if (!service)  { showError('service',  'Please select a service.'); valid = false; }

    var subTypeRowEl = document.getElementById('subTypeRow');
    if (subTypeRowEl && !subTypeRowEl.hidden) {
      var subTypeVal = document.getElementById('subType') ? document.getElementById('subType').value : '';
      if (!subTypeVal) { showError('subType', 'Please select a style/type.'); valid = false; }
      var lengthGrpEl = document.getElementById('lengthGroup');
      if (lengthGrpEl && !lengthGrpEl.hidden) {
        var hairLengthVal = document.getElementById('hairLength') ? document.getElementById('hairLength').value : '';
        if (!hairLengthVal) { showError('hairLength', 'Please select a braid length.'); valid = false; }
      }
    }

    if (!location) { showError('location', 'Please select a location.'); valid = false; }
    if (!prefDate) { showError('preferredDate', 'Please choose a preferred date.'); valid = false; }
    if (!prefTime) { showError('preferredTime', 'Please choose a preferred time.'); valid = false; }

    const stylist = document.getElementById('stylist') ? document.getElementById('stylist').value : '';
    if (!stylist) { showError('stylist', 'Please select a preferred stylist.'); valid = false; }

    if (!depositAgree) {
      showError('depositAgree', 'You must agree to the deposit policy to proceed.'); valid = false;
    }

    return valid;
  }

  // Display server-side validation errors on page load
  function displayServerErrors() {
    if (window.Bella_SERVER_ERRORS && Array.isArray(window.Bella_SERVER_ERRORS)) {
      window.Bella_SERVER_ERRORS.forEach(function (error) {
        if (error.field && error.message) {
          showError(error.field, error.message);
        }
      });
      // Scroll to first error
      var firstError = form.querySelector('.error');
      if (firstError) {
        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    }
  }

  // Live clear errors on change
  ['firstName','lastName','phone','email','service','location','subType','hairLength','stylist','preferredDate','preferredTime','depositAgree'].forEach(function (id) {
    const el = document.getElementById(id);
    if (el) {
      el.addEventListener('change', function () { clearError(id); });
      el.addEventListener('input', function () { clearError(id); });
    }
  });

  // Legacy non-server forms now submit to booking.php (no WhatsApp booking flow).
  if (!isServerSubmit) {
    form.setAttribute('method', 'post');
    form.setAttribute('action', 'booking.php');
  }

  // Always validate client-side before submission (catches errors inline before server round-trip)
  form.addEventListener('submit', function (e) {
    if (!validateForm()) {
      e.preventDefault();
      var firstError = form.querySelector('.error');
      if (firstError) {
        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        firstError.focus();
      }
    }
  });

  // Display any server-side validation errors from previous submission
  displayServerErrors();
  }

  /* ---- SMOOTH ACTIVE NAV HIGHLIGHTING ---- */
  const sections  = document.querySelectorAll('section[id]');
  const navAnchors = document.querySelectorAll('.nav-links a[href^="#"]');

  const observer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        navAnchors.forEach(function (a) {
          a.classList.toggle('active', a.getAttribute('href') === '#' + entry.target.id);
        });
      }
    });
  }, { rootMargin: '-40% 0px -55% 0px' });

  sections.forEach(function (s) { observer.observe(s); });

  /* ---- SCROLL REVEAL ---- */
  var revealTargets = document.querySelectorAll(
    '.service-card, .policy-card, .location-card, .price-group, ' +
    '.booking-steps li, .stat, .hours-card, .gallery-item, ' +
    '.section-header, .about-text, .extras-banner, .contact-chip, ' +
    '.booking-info, .booking-form-wrap'
  );

  revealTargets.forEach(function (el, i) {
    el.classList.add('reveal');
    var mod = i % 4;
    if (mod === 1) el.classList.add('reveal-d1');
    else if (mod === 2) el.classList.add('reveal-d2');
    else if (mod === 3) el.classList.add('reveal-d3');
  });

  var revealObserver = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        entry.target.classList.add('revealed');
        revealObserver.unobserve(entry.target);
      }
    });
  }, { threshold: 0.08, rootMargin: '0px 0px -30px 0px' });

  revealTargets.forEach(function (el) { revealObserver.observe(el); });

  /* ---- FAQ CHATBOT ---- */
  function initFaqChatbot() {
    if (document.getElementById('faqChatbot')) return;

    var chatbot = document.createElement('div');
    chatbot.className = 'chatbot-widget';
    chatbot.id = 'faqChatbot';
    chatbot.innerHTML = [
      '<div class="chatbot-panel" id="chatbotPanel">',
      '  <div class="chatbot-header">',
      '    <div class="chatbot-title">',
      '      <span class="chatbot-title-badge">',
      '        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>',
      '      </span>',
      '      <div><strong>Bella Assistant</strong><span>FAQs, bookings and quick guidance</span></div>',
      '    </div>',
      '    <button type="button" class="chatbot-close" id="chatbotClose" aria-label="Close chatbot">&times;</button>',
      '  </div>',
      '  <div class="chatbot-body">',
      '    <div class="chatbot-messages" id="chatbotMessages"></div>',
      '    <div class="chatbot-chip-row" id="chatbotChips"></div>',
      '  </div>',
      '  <form class="chatbot-input-row" id="chatbotForm">',
      '    <input class="chatbot-input" id="chatbotInput" type="text" placeholder="Ask about prices, hours, locations or bookings" />',
      '    <button class="chatbot-send" type="submit">Send</button>',
      '  </form>',
      '</div>',
      '<div class="chatbot-hint" id="chatbotHint">Ask Bella Assistant</div>',
      '<button type="button" class="chatbot-toggle" id="chatbotToggle" aria-label="Open Bella assistant">',
      '  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/><path d="M8 9h8"/><path d="M8 13h5"/></svg>',
      '</button>'
    ].join('');

    document.body.appendChild(chatbot);

    var panel = document.getElementById('chatbotPanel');
    var toggle = document.getElementById('chatbotToggle');
    var closeBtn = document.getElementById('chatbotClose');
    var hint = document.getElementById('chatbotHint');
    var formEl = document.getElementById('chatbotForm');
    var inputEl = document.getElementById('chatbotInput');
    var messagesEl = document.getElementById('chatbotMessages');
    var chipsEl = document.getElementById('chatbotChips');

    function escapeHtml(value) {
      return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    function scrollMessagesToBottom() {
      window.requestAnimationFrame(function () {
        messagesEl.scrollTop = messagesEl.scrollHeight;
      });
    }

    function openBookingFlow() {
      var bookingSection = document.getElementById('booking');
      if (bookingSection) {
        bookingSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        panel.classList.remove('open');
        if (hint) hint.hidden = false;
        return;
      }
      window.location.href = 'booking.php';
    }

    function addMessage(role, content) {
      var message = document.createElement('div');
      message.className = 'chatbot-message ' + role;
      message.innerHTML = role === 'user' ? escapeHtml(content) : content;
      messagesEl.appendChild(message);
      scrollMessagesToBottom();
    }

    function renderChips(items) {
      chipsEl.innerHTML = '';
      items.forEach(function (item) {
        var chip = document.createElement('button');
        chip.type = 'button';
        chip.className = 'chatbot-chip';
        chip.textContent = typeof item === 'string' ? item : item.label;
        chip.addEventListener('click', function () {
          if (typeof item === 'object' && item.action === 'book') {
            addMessage('user', item.label);
            addMessage('bot', 'Taking you to the fastest booking route now.');
            openBookingFlow();
            return;
          }
          if (typeof item === 'object' && item.action === 'whatsapp') {
            addMessage('user', item.label);
            addMessage('bot', 'Opening WhatsApp so you can chat directly with Bella.');
            if (hint) hint.hidden = false;
            window.open(whatsappUrl, '_blank', 'noopener,noreferrer');
            renderChips(getDefaultChips());
            return;
          }
          handlePrompt(typeof item === 'string' ? item : (item.prompt || item.label));
        });
        chipsEl.appendChild(chip);
      });
    }

    function getDefaultChips() {
      return [
        { label: 'Hair services', prompt: 'Hair services' },
        { label: 'Makeup services', prompt: 'Makeup services' },
        { label: 'Book now', action: 'book' },
        { label: 'Pricing', prompt: 'Services pricing' }
      ];
    }

    function getReply(message) {
      var text = (message || '').toLowerCase();

      if (/book|appointment|reserve|schedule/.test(text)) {
        return {
          answer: 'Ready to book? I can take you straight to the booking form or you can message Bella directly on <a href="' + whatsappUrl + '" target="_blank" rel="noopener">WhatsApp</a>. A 50% non-refundable deposit confirms the slot.',
          chips: [
            { label: 'Open booking form', action: 'book' },
            { label: 'Booking policy', prompt: 'Booking policy' },
            { label: 'WhatsApp', action: 'whatsapp' }
          ]
        };
      }

      if (/price|cost|how much|quote|pricing/.test(text)) {
        return {
          answer: 'You can view the full <a href="services.php">services and pricing list</a>. Final pricing can vary based on length, texture and complexity, especially for braids, wigs and custom styling.',
          chips: [
            { label: 'Hair services', prompt: 'Hair services' },
            { label: 'Makeup services', prompt: 'Makeup services' },
            { label: 'Book now', action: 'book' }
          ]
        };
      }

      if (/hour|open|close|time/.test(text)) {
        return {
          answer: 'Studio hours are Midrand: ' + hoursMidrand + '. Copperleaf: ' + hoursCopperleaf + '. Before or after-hours appointments add R200.',
          chips: ['Locations', { label: 'Book now', action: 'book' }, 'Booking policy']
        };
      }

      if (/where|location|midrand|copperleaf|address/.test(text)) {
        return {
          answer: 'Bella serves clients in Midrand and Copperleaf. Midrand studio: ' + addressMidrand + '. Copperleaf studio: ' + addressCopperleaf + '.',
          chips: ['Opening hours', { label: 'Book now', action: 'book' }, { label: 'WhatsApp', action: 'whatsapp' }]
        };
      }

      if (/policy|deposit|refund|cancel/.test(text)) {
        return {
          answer: 'A 50% non-refundable deposit is required to secure every booking. The deposit is deducted from the final amount. You can read the full <a href="policy.php">booking policy here</a>.',
          chips: [{ label: 'Book now', action: 'book' }, 'Opening hours', { label: 'WhatsApp', action: 'whatsapp' }]
        };
      }

      if (/makeup|glam|bridal|graduation|editorial/.test(text)) {
        return {
          answer: 'Bella offers bridal makeup, events and functions glam, editorial makeup, everyday glam and graduation makeup. For exact availability or recommendations, it is best to <a href="' + whatsappUrl + '" target="_blank" rel="noopener">WhatsApp the studio</a>.',
          chips: ['Services pricing', { label: 'Book now', action: 'book' }, 'Locations']
        };
      }

      if (/braid|cornrow|wig|ponytail|hair/.test(text)) {
        return {
          answer: 'Hair services include braids, cornrows, ponytails, wig installations, hair colour and other styling. If you already know the style you want, go straight to <a href="booking.php">Book Now</a>.',
          chips: ['Services pricing', { label: 'Book now', action: 'book' }, 'Booking policy']
        };
      }

      if (/whatsapp|call|phone|contact/.test(text)) {
        return {
          answer: 'You can contact Bella on <a href="' + whatsappUrl + '" target="_blank" rel="noopener">WhatsApp ' + phoneWhatsapp + '</a> or call <a href="tel:' + phoneCallHref + '">' + phoneCall + '</a>.',
          chips: [{ label: 'Book now', action: 'book' }, 'Locations', 'Opening hours']
        };
      }

      return {
        answer: 'I can help with bookings, pricing, locations, hours, makeup, hair services and booking policy. If you want to secure a slot, head to <a href="booking.php">Book Now</a>.',
        chips: getDefaultChips()
      };
    }

    function handlePrompt(prompt) {
      addMessage('user', prompt);
      var reply = getReply(prompt);
      window.setTimeout(function () {
        addMessage('bot', reply.answer);
        renderChips(reply.chips);
      }, 180);
    }

    toggle.addEventListener('click', function () {
      panel.classList.toggle('open');
      if (hint) hint.hidden = panel.classList.contains('open');
      if (panel.classList.contains('open')) {
        inputEl.focus();
        scrollMessagesToBottom();
      }
    });

    closeBtn.addEventListener('click', function () {
      panel.classList.remove('open');
      if (hint) hint.hidden = false;
    });

    formEl.addEventListener('submit', function (e) {
      e.preventDefault();
      var value = inputEl.value.trim();
      if (!value) return;
      inputEl.value = '';
      handlePrompt(value);
    });

    addMessage('bot', 'Hi, I\'m the Bella assistant. Are you looking for hair or makeup today? I can also help with prices, booking policy, locations and the fastest way to book.');
    renderChips(getDefaultChips());
  }

  initFaqChatbot();

})();
