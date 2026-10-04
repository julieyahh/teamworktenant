@extends('layouts.app')

@section('title', 'Welcome')

@section('content')

{{-- ===================== HERO SECTION ===================== --}}
<section class="pr-hero">
    <div class="pr-hero__bg-grid"></div>
    <div class="pr-hero__orb pr-hero__orb--1"></div>
    <div class="pr-hero__orb pr-hero__orb--2"></div>

    <div class="container pr-hero__inner">
        <div class="pr-hero__eyebrow">
            <span class="pr-eyebrow-dot"></span>
            <span>Est. 2015 · Cagayan de Oro</span>
        </div>
        <h1 class="pr-hero__title">
            Find Your<br>
            <em class="pr-hero__title-accent">Perfect Home</em>
        </h1>
        <p class="pr-hero__subtitle">
            Premium rental living for students &amp; professionals.
            <span class="pr-vacancy-pill">
                <span class="pr-vacancy-dot"></span>
                <strong>{{ $vacantCount }}</strong> rooms available now
            </span>
        </p>
        <div class="pr-hero__cta-row">
            <a href="#units" class="pr-btn pr-btn--primary">Browse Rooms</a>
            <a href="#contact" class="pr-btn pr-btn--ghost">Contact Us</a>
        </div>
    </div>

    <div class="pr-hero__scroll-hint">
        <span>Scroll</span>
        <div class="pr-scroll-line"></div>
    </div>
</section>

{{-- ===================== STATS BAR ===================== --}}
<section class="pr-stats">
    <div class="container">
        <div class="pr-stats__grid">
            <div class="pr-stat">
                <div class="pr-stat__number">9+</div>
                <div class="pr-stat__label">Years of Excellence</div>
            </div>
            <div class="pr-stat pr-stat--divider"></div>
            <div class="pr-stat">
                <div class="pr-stat__number">{{ $vacantCount }}</div>
                <div class="pr-stat__label">Vacant Rooms</div>
            </div>
            <div class="pr-stat pr-stat--divider"></div>
            <div class="pr-stat">
                <div class="pr-stat__number">100%</div>
                <div class="pr-stat__label">Verified Tenants</div>
            </div>
            <div class="pr-stat pr-stat--divider"></div>
            <div class="pr-stat">
                <div class="pr-stat__number">24/7</div>
                <div class="pr-stat__label">Support</div>
            </div>
        </div>
    </div>
</section>

{{-- ===================== UNITS SECTION ===================== --}}
<section class="pr-units" id="units">
    <div class="container">
        <div class="pr-section-header">
            <div class="pr-section-header__tag">Available Units</div>
            <h2 class="pr-section-header__title">Choose Your Space</h2>
            <p class="pr-section-header__desc">Each room is carefully maintained to give you the comfort and security you deserve.</p>
        </div>

        <div class="pr-units__grid">
            @foreach($units->where('status', 'vacant') as $unit)
                @php
                    $pendingLeaseCount = $unit->leases->where('lea_status', 'pending')->count();
                    $pendingApplicationCount = $unit->tenantApplications
                        ->filter(fn($app) => optional($app->user)->status === 'pending')
                        ->count();
                    $pendingApplicants = $pendingLeaseCount + $pendingApplicationCount;
                    $isFull = $pendingApplicants >= $unit->application_limit;
                @endphp

                <div class="pr-unit-card {{ $isFull ? 'pr-unit-card--full' : '' }}"
                    @if(!$isFull)
                        data-bs-toggle="modal"
                        data-bs-target="#unitModal"
                    @endif
                    data-unit-id="{{ $unit->id }}"
                    data-room-no="{{ $unit->room_no }}"
                    data-type="{{ $unit->type }}"
                    data-capacity="{{ $unit->capacity }}"
                    data-no-occupants="{{ $unit->no_of_occupants }}"
                    data-price="{{ $unit->room_price ?? 0 }}"
                    data-image="{{ asset('images/units/' . strtolower(str_replace(' ', '-', $unit->type)) . '.jpg') }}"
                    data-status="{{ $isFull ? 'Full' : 'Vacant' }}"
                >
                    <div class="pr-unit-card__img-wrap">
                        <img src="{{ asset('images/units/' . strtolower(str_replace(' ', '-', $unit->type)) . '.jpg') }}"
                             alt="{{ $unit->type }}"
                             class="pr-unit-card__img">
                        <div class="pr-unit-card__img-overlay"></div>
                        @if($isFull)
                            <div class="pr-unit-badge pr-unit-badge--full">Full</div>
                        @else
                            <div class="pr-unit-badge pr-unit-badge--vacant">Vacant</div>
                        @endif
                    </div>

                    <div class="pr-unit-card__body">
                        <div class="pr-unit-card__top">
                            <div>
                                <h3 class="pr-unit-card__type">{{ $unit->type }}</h3>
                                <p class="pr-unit-card__room">Room {{ $unit->room_no }}</p>
                            </div>
                            <div class="pr-unit-card__price">
                                ₱{{ number_format($unit->room_price ?? 0) }}
                                <span>/mo</span>
                            </div>
                        </div>

                        <div class="pr-unit-card__meta">
                            <div class="pr-unit-meta-item">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                @if($unit->type === 'Bed-Spacer')
                                    {{ $unit->no_of_occupants }}/{{ $unit->capacity }} Occupied
                                @else
                                    {{ $unit->capacity > 1 ? $unit->capacity . ' Persons' : '1 Person' }}
                                @endif
                            </div>
                            <div class="pr-unit-meta-item">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                {{ $pendingApplicants }}/{{ $unit->application_limit }} Pending
                            </div>
                        </div>

                        @if(!$isFull)
                            <div class="pr-unit-card__cta">
                                Apply Now
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                            </div>
                        @else
                            <div class="pr-unit-card__cta pr-unit-card__cta--disabled">Applications Closed</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ===================== ABOUT SECTION ===================== --}}
<section class="pr-about" id="about">
    <div class="container">
        <div class="pr-about__inner">
            <div class="pr-about__visual">
                <div class="pr-about__card-1">
                    <div class="pr-about__card-icon">🏠</div>
                    <div class="pr-about__card-text">Safe &amp; Secure Living</div>
                </div>
                <div class="pr-about__card-2">
                    <div class="pr-about__card-icon">⚡</div>
                    <div class="pr-about__card-text">Fast Application Process</div>
                </div>
                <div class="pr-about__badge">
                    <div class="pr-about__badge-year">2015</div>
                    <div class="pr-about__badge-label">Founded</div>
                </div>
            </div>

            <div class="pr-about__content">
                <div class="pr-section-header__tag">Our Story</div>
                <h2 class="pr-about__title">Built for People,<br>Not Just Profit</h2>
                <p class="pr-about__text">
                    Our apartment complex started in <strong>2015</strong> with the goal of providing safe,
                    affordable, and modern living spaces for students and working professionals.
                </p>
                <p class="pr-about__text">
                    With well-maintained facilities and accessible locations, we've built a community
                    where convenience meets comfort. Every detail is thoughtfully designed so you can
                    focus on what matters most.
                </p>
                <div class="pr-about__pillars">
                    <div class="pr-pillar">
                        <div class="pr-pillar__icon">🛡️</div>
                        <div class="pr-pillar__label">Safety First</div>
                    </div>
                    <div class="pr-pillar">
                        <div class="pr-pillar__icon">💰</div>
                        <div class="pr-pillar__label">Affordable</div>
                    </div>
                    <div class="pr-pillar">
                        <div class="pr-pillar__icon">🌟</div>
                        <div class="pr-pillar__label">Modern Living</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ===================== LEASE TERMS SECTION ===================== --}}
<section class="pr-lease-terms" id="terms-info">
    <div class="container">
        <div class="pr-section-header">
            <div class="pr-section-header__tag">Before You Sign</div>
            <h2 class="pr-section-header__title">Lease Terms &amp; Conditions</h2>
            <p class="pr-section-header__desc">Key things to know before applying for a unit.</p>
        </div>

        <div class="pr-terms-grid">

            <div class="pr-term-item">
                <div class="pr-term-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                </div>
                <div class="pr-term-text">
                    <strong>Lease Duration</strong>
                    1-year fixed term, renewable upon Lessor approval with at least 60 days' written notice from the Lessee.
                </div>
            </div>

            <div class="pr-term-item">
                <div class="pr-term-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                </div>
                <div class="pr-term-text">
                    <strong>Rent Payment</strong>
                    Monthly rent must be paid in advance on or before the due date.
                </div>
            </div>

            <div class="pr-term-item">
                <div class="pr-term-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </div>
                <div class="pr-term-text">
                    <strong>Security Deposit</strong>
                    Required upon signing — non-interest bearing and refundable after lease expiration, subject to deductions for damages or unpaid obligations.
                </div>
            </div>

            <div class="pr-term-item">
                <div class="pr-term-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                </div>
                <div class="pr-term-text">
                    <strong>Taxes &amp; Charges</strong>
                    All government taxes and charges related to the lease shall be paid by the Lessee.
                </div>
            </div>

            <div class="pr-term-item">
                <div class="pr-term-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                </div>
                <div class="pr-term-text">
                    <strong>Permitted Use</strong>
                    Premises shall be used for business/commercial purposes only. Illegal or unauthorized activities are strictly prohibited.
                </div>
            </div>

            <div class="pr-term-item">
                <div class="pr-term-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                </div>
                <div class="pr-term-text">
                    <strong>Cleanliness &amp; Safety</strong>
                    The Lessee must maintain cleanliness, safety, and proper condition of the premises at all times.
                </div>
            </div>

            <div class="pr-term-item">
                <div class="pr-term-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                </div>
                <div class="pr-term-text">
                    <strong>Repairs &amp; Alterations</strong>
                    Damage by the Lessee must be repaired at their expense. Major alterations require written approval from the Lessor.
                </div>
            </div>

            <div class="pr-term-item">
                <div class="pr-term-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </div>
                <div class="pr-term-text">
                    <strong>Inspection Rights</strong>
                    The Lessor has the right to enter and inspect the premises at reasonable times.
                </div>
            </div>

            <div class="pr-term-item">
                <div class="pr-term-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                </div>
                <div class="pr-term-text">
                    <strong>Utilities</strong>
                    All utility bills (water, electricity, etc.) shall be paid solely by the Lessee.
                </div>
            </div>

            <div class="pr-term-item">
                <div class="pr-term-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg>
                </div>
                <div class="pr-term-text">
                    <strong>Compliance &amp; Permits</strong>
                    Lessee must comply with all laws and ordinances, and secure all necessary permits and licenses.
                </div>
            </div>

            <div class="pr-term-item">
                <div class="pr-term-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <div class="pr-term-text">
                    <strong>Third-Party Liability</strong>
                    The Lessee is liable for any injury or damage to third parties occurring within the premises.
                </div>
            </div>

            <div class="pr-term-item">
                <div class="pr-term-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                </div>
                <div class="pr-term-text">
                    <strong>No Subleasing</strong>
                    Subleasing, assignment, or transfer of rights is not permitted without written consent from the Lessor.
                </div>
            </div>

            <div class="pr-term-item">
                <div class="pr-term-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                </div>
                <div class="pr-term-text">
                    <strong>Move-Out</strong>
                    Upon termination or expiration, the Lessee must vacate and return the premises in good condition.
                </div>
            </div>

            <div class="pr-term-item">
                <div class="pr-term-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                </div>
                <div class="pr-term-text">
                    <strong>Renewal Rate</strong>
                    Monthly rent shall increase by 5% upon each renewal of the lease.
                </div>
            </div>

            <div class="pr-term-item pr-term-item--wide">
                <div class="pr-term-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                </div>
                <div class="pr-term-text">
                    <strong>Breach &amp; Termination</strong>
                    Any legal action due to contract breach will require the Lessee to pay penalties, legal fees, and damages. The Lessor reserves the right to terminate the lease for any violations of this agreement.
                </div>
            </div>

        </div>
    </div>
</section>

{{-- ===================== CONTACT SECTION ===================== --}}
<section class="pr-contact" id="contact">
    <div class="container">
        <div class="pr-section-header">
            <div class="pr-section-header__tag">Get in Touch</div>
            <h2 class="pr-section-header__title">Contact Us</h2>
        </div>

        <div class="pr-contact__grid">
            <a class="pr-contact-card" href="https://maps.google.com" target="_blank">
                <div class="pr-contact-card__icon">📍</div>
                <div class="pr-contact-card__label">Location</div>
                <div class="pr-contact-card__value">123 Apartment Street,<br>Cagayan de Oro, PH</div>
            </a>
            <a class="pr-contact-card" href="tel:+639123456789">
                <div class="pr-contact-card__icon">📞</div>
                <div class="pr-contact-card__label">Phone</div>
                <div class="pr-contact-card__value">+63 912 345 6789</div>
            </a>
            <a class="pr-contact-card" href="mailto:info@apartmentportal.com">
                <div class="pr-contact-card__icon">✉️</div>
                <div class="pr-contact-card__label">Email</div>
                <div class="pr-contact-card__value">info@apartmentportal.com</div>
            </a>
        </div>
    </div>
</section>

{{-- ===================== MAP SECTION ===================== --}}
<section class="pr-map">
    <div class="container">
        <div class="pr-section-header">
            <div class="pr-section-header__tag">Location</div>
            <h2 class="pr-section-header__title">Find Us Here</h2>
        </div>
        <div class="pr-map__embed">
            <iframe
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d829.5969257428177!2d124.65405466128895!3d8.475028046399832!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x32fff2ce448339c1%3A0x639be33dade6bf70!2sR.A.%20VITORILLO%20LAW%20OFFICE%20AND%20ASSOCIATES!5e0!3m2!1sen!2sph!4v1761789546488!5m2!1sen!2sph"
                style="border:0;" allowfullscreen="" loading="lazy">
            </iframe>
        </div>
    </div>
</section>

{{-- ===================== UNIT MODAL ===================== --}}
<div class="modal fade" id="unitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content pr-modal">

            <div class="pr-modal__header">
                <div>
                    <div class="pr-modal__eyebrow">Unit Details</div>
                    <h5 class="pr-modal__title" id="unitModalTitle">Loading…</h5>
                </div>
                <button type="button" class="pr-modal__close" data-bs-dismiss="modal" aria-label="Close">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            <div class="modal-body p-0">
                <div class="pr-modal__body">

                    {{-- LEFT PANEL --}}
                    <div class="pr-modal__left">
                        <div class="pr-modal__img-wrap">
                            <img id="unitModalImg" src="" alt="Unit"
                                 class="pr-modal__img"
                                 style="cursor:zoom-in;"
                                 data-bs-toggle="modal" data-bs-target="#unitImageModal">
                            <div class="pr-modal__img-shine"></div>
                        </div>

                        {{-- ===================== CONTRACT INFO BLOCK ===================== --}}
                        <div class="pr-contract-info">
                            <div class="pr-contract-info__body">
                                <div class="pr-contract-item">
                                    <div class="pr-contract-item__icon">📋</div>
                                    <div>
                                        <div class="pr-contract-item__label">Contract Duration</div>
                                        <div class="pr-contract-item__value">1 Year Fixed Term</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        {{-- ===================== END CONTRACT INFO BLOCK ===================== --}}

                        <div class="pr-modal__info-grid">
                            <div class="pr-modal__info-item">
                                <div class="pr-modal__info-label">Type</div>
                                <div class="pr-modal__info-value" id="unitModalTypeText">—</div>
                            </div>
                            <div class="pr-modal__info-item">
                                <div class="pr-modal__info-label">Size</div>
                                <div class="pr-modal__info-value" id="unitModalSize">—</div>
                            </div>
                            <div class="pr-modal__info-item">
                                <div class="pr-modal__info-label">Capacity</div>
                                <div class="pr-modal__info-value" id="unitModalCapacity">—</div>
                            </div>
                            <div class="pr-modal__info-item">
                                <div class="pr-modal__info-label">Price / mo</div>
                                <div class="pr-modal__info-value">₱<span id="unitModalPrice">—</span></div>
                            </div>
                            <div class="pr-modal__info-item pr-modal__info-item--full">
                                <div class="pr-modal__info-label">Status</div>
                                <div class="pr-modal__info-value">
                                    <span class="badge" id="unitModalStatus">—</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- RIGHT PANEL --}}
                    <div class="pr-modal__right">
                        <div class="pr-modal__form-header">
                            <h5 class="pr-modal__form-title">
                                @auth
                                    Unit Application
                                @else
                                    Tenant Registration & Application
                                @endauth
                            </h5>
                            <p class="pr-modal__form-subtitle">
                                @auth
                                    Select your preferred room and move-in date to apply for this unit.
                                @else
                                    Fill in your details to apply for this unit.
                                @endauth
                            </p>
                        </div>

                        @if($errors->any())
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Please fix the following errors:</strong>
                                <ul class="mb-0 mt-2">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        @endif

                        @if(session('error'))
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                {{ session('error') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        @endif

                        {{-- ===================== FORM: DIFFERENT ACTION & FIELDS BASED ON AUTH ===================== --}}
                        @auth
                        <form method="POST" action="{{ route('tenant.leases.store') }}" enctype="multipart/form-data">
                        @else
                        <form method="POST" action="{{ route('register.submit') }}" enctype="multipart/form-data">
                        @endauth
                            @csrf

                            {{-- ACCOUNT DETAILS: Guests only --}}
                            @guest
                            <div class="pr-form-section">
                                <div class="pr-form-section__label">Account Details</div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="pr-label">First Name</label>
                                        <input type="text" name="first_name"
                                            class="pr-input @error('first_name') is-invalid @enderror"
                                            placeholder="Juan"
                                            value="{{ old('first_name') }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="pr-label">Last Name</label>
                                        <input type="text" name="last_name"
                                            class="pr-input @error('last_name') is-invalid @enderror"
                                            placeholder="Dela Cruz"
                                            value="{{ old('last_name') }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="pr-label">Email</label>
                                        <input type="email" name="email" id="email"
                                            class="pr-input @error('email') is-invalid @enderror"
                                            placeholder="you@example.com"
                                            value="{{ old('email') }}">
                                        <div class="invalid-feedback" id="email-error"></div>
                                        <small class="text-muted" id="email-check-status"></small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="pr-label">Contact Number</label>
                                        <input type="text" name="contact"
                                            class="pr-input @error('contact') is-invalid @enderror"
                                            placeholder="09XXXXXXXXX"
                                            value="{{ old('contact') }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="pr-label">Password</label>
                                        <div class="position-relative">
                                            <input type="password" name="password" id="password"
                                                class="pr-input @error('password') is-invalid @enderror">
                                            <button type="button" class="pr-eye-toggle" id="togglePassword">
                                                <i class="bi bi-eye" id="passwordEyeIcon"></i>
                                            </button>
                                        </div>
                                        @error('password')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <div class="password-strength" id="passwordStrength"></div>
                                        <small class="text-muted" id="passwordHint"></small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="pr-label">Confirm Password</label>
                                        <div class="position-relative">
                                            <input type="password" name="password_confirmation"
                                                id="password_confirmation"
                                                class="pr-input @error('password_confirmation') is-invalid @enderror">
                                            <button type="button" class="pr-eye-toggle" id="togglePasswordConfirmation">
                                                <i class="bi bi-eye" id="passwordConfirmationEyeIcon"></i>
                                            </button>
                                        </div>
                                        <small class="text-muted" id="passwordMatch"></small>
                                    </div>
                                </div>
                            </div>
                            @endguest

                            {{-- PERSONAL INFORMATION: Guests only --}}
                            @guest
                            <div class="pr-form-section">
                                <div class="pr-form-section__label">Personal Information</div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="pr-label">Birthdate</label>
                                        <input type="date" name="birthdate"
                                            class="pr-input @error('birthdate') is-invalid @enderror"
                                            value="{{ old('birthdate') }}" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="pr-label">Current Address</label>
                                        <input type="text" name="current_address"
                                            class="pr-input @error('current_address') is-invalid @enderror"
                                            placeholder="Street, City"
                                            value="{{ old('current_address') }}" required>
                                    </div>
                                </div>
                            </div>
                            @endguest

                            {{-- ROOM SELECTION: All users --}}
                            <div class="pr-form-section">
                                <div class="pr-form-section__label">Room Selection</div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="pr-label">Preferred Unit Type</label>
                                        <input type="text" name="unit_type" id="unitModalType"
                                            class="pr-input @error('unit_type') is-invalid @enderror"
                                            value="{{ old('unit_type') }}" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="pr-label">Select Room</label>
                                        <select id="unit_id" name="unit_id" class="pr-select" style="pointer-events:none;background:#f8f9fa;">
                                            <option value="">Select a room</option>
                                            @foreach($availableUnits as $unit)
                                                @php
                                                    $takenBeds = ($unit->taken_beds ?? collect())->values();
                                                    if ($unit->type === 'Bed-Spacer') {
                                                        $displayCount = max($unit->no_of_occupants ?? 0, $takenBeds->count());
                                                        $occupancyInfo = " ({$displayCount}/{$unit->capacity})";
                                                        $isFullyBooked = $takenBeds->count() >= $unit->capacity;
                                                    } else {
                                                        $occupancyInfo = "";
                                                        $isFullyBooked = $unit->status === 'occupied';
                                                    }
                                                @endphp
                                                @if($unit->status === 'vacant' || ($unit->type === 'Bed-Spacer' && !$isFullyBooked))
                                                    <option value="{{ $unit->id }}"
                                                            data-type="{{ $unit->type }}"
                                                            data-capacity="{{ $unit->capacity }}"
                                                            data-taken-beds='@json($takenBeds)'
                                                            {{ old('unit_id') == $unit->id ? 'selected' : '' }}>
                                                        {{ $unit->room_no }} ({{ $unit->type }}{{ $occupancyInfo }})
                                                        @if($isFullyBooked) - Occupied @endif
                                                    </option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-12 d-none" id="bedSelectionGroup">
                                        <label class="pr-label">Select Bed</label>
                                        <select name="bed_number" id="bed_number"
                                            class="pr-select @error('bed_number') is-invalid @enderror">
                                            <option value="">Select a bed</option>
                                        </select>
                                        @error('bed_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            {{-- APPLICATION DETAILS: Move-in date for all, extra fields for guests --}}
                            <div class="pr-form-section">
                                <div class="pr-form-section__label">Application Details</div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="pr-label">Preferred Move-in Date</label>
                                        <input type="date"
                                            name="{{ auth()->check() ? 'start_date' : 'move_in_date' }}"
                                            id="move_in_date"
                                            class="pr-input @error('move_in_date') is-invalid @enderror @error('start_date') is-invalid @enderror"
                                            value="{{ old(auth()->check() ? 'start_date' : 'move_in_date') }}"
                                            min="{{ now()->toDateString() }}"
                                            max="{{ now()->addWeeks(2)->toDateString() }}"
                                            required>
                                    </div>
                                    {{-- Contract end date display (shown when date is selected) --}}
                                    <div class="col-md-6" id="contractEndGroup" style="display:none;">
                                        <label class="pr-label">Contract End Date</label>
                                        <div class="pr-contract-end-display">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                            <span id="contractEndDate">—</span>
                                        </div>
                                        <small class="text-muted" style="font-size:.75rem;">This is based on a one-year contract term and is subject to change depending on the approval date.</small>
                                    </div>

                                    {{-- Guests-only extra fields --}}
                                    @guest
                                    <div class="col-md-6">
                                        <label class="pr-label">Reason for Renting</label>
                                        <input type="text" name="reason"
                                            class="pr-input @error('reason') is-invalid @enderror"
                                            placeholder="For work, school, etc."
                                            value="{{ old('reason') }}" required>
                                    </div>
                                    @endguest
                                </div>
                            </div>

                            {{-- EMPLOYMENT INFO: Guests only --}}
                            @guest
                            <div class="pr-form-section">
                                <div class="pr-form-section__label">Employment Info</div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="pr-label">Employment Status</label>
                                        <select name="employment_status"
                                            class="pr-select @error('employment_status') is-invalid @enderror"
                                            id="employment_status" required>
                                            <option value="">Select</option>
                                            <option value="Employed" {{ old('employment_status') == 'Employed' ? 'selected' : '' }}>Employed</option>
                                            <option value="Unemployed" {{ old('employment_status') == 'Unemployed' ? 'selected' : '' }}>Unemployed</option>
                                            <option value="Student" {{ old('employment_status') == 'Student' ? 'selected' : '' }}>Student</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="pr-label">Employer or School</label>
                                        <input type="text" name="employer_school"
                                            class="pr-input @error('employer_school') is-invalid @enderror"
                                            value="{{ old('employer_school') }}" required>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="pr-label">Source of Income</label>
                                        <input type="text" name="source_of_income" id="source_of_income"
                                            class="pr-input @error('source_of_income') is-invalid @enderror"
                                            value="{{ old('source_of_income') }}" required>
                                    </div>
                                    <div class="col-md-6 d-none" id="monthlyIncomeGroup">
                                        <label class="pr-label">Monthly Income / Wages</label>
                                        <input type="number" name="monthly_income" id="monthly_income"
                                            class="pr-input" placeholder="Enter monthly income" min=0>
                                    </div>
                                </div>
                            </div>
                            @endguest

                            {{-- EMERGENCY CONTACT: Guests only --}}
                            @guest
                            <div class="pr-form-section">
                                <div class="pr-form-section__label">Emergency Contact</div>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="pr-label">Full Name</label>
                                        <input type="text" name="emergency_name"
                                            class="pr-input @error('emergency_name') is-invalid @enderror"
                                            value="{{ old('emergency_name') }}" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="pr-label">Phone Number</label>
                                        <input type="tel" name="emergency_number"
                                            class="pr-input @error('emergency_number') is-invalid @enderror"
                                            required maxlength="15" minlength="10"
                                            pattern="^[0-9]{10,15}$" inputmode="numeric"
                                            value="{{ old('emergency_number') }}"
                                            oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="pr-label">Relationship</label>
                                        <input type="text" name="emergency_relationship"
                                            class="pr-input @error('emergency_relationship') is-invalid @enderror"
                                            value="{{ old('emergency_relationship') }}" required>
                                    </div>
                                </div>
                            </div>
                            @endguest

                            {{-- DOCUMENTS: Guests only --}}
                            @guest
                            <div class="pr-form-section">
                                <div class="pr-form-section__label">Documents</div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="pr-label">Valid ID (FRONT)</label>
                                        {{-- v19: the camera opens to capture the ID — see partials/id-camera.
                                             "required" is removed from this input by the partial and enforced
                                             by the JS validation below + the server. --}}
                                        <input type="file" name="valid_id" id="valid_id"
                                            class="pr-input @error('valid_id') is-invalid @enderror"
                                            accept="image/*" capture="environment"
                                            data-id-camera
                                            data-id-side="front"
                                            required>
                                        @error('valid_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="pr-label">Valid ID (BACK)</label>
                                        <input type="file" name="id_picture" id="id_picture"
                                            class="pr-input @error('id_picture') is-invalid @enderror"
                                            accept="image/*" capture="environment"
                                            data-id-camera
                                            data-id-side="back"
                                            required>
                                        @error('id_picture')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            @endguest

                            {{-- TERMS: Guests only --}}
                            @guest
                            <div class="pr-form-section pr-form-section--terms">
                                <label class="pr-terms-check">
                                    <input class="@error('terms') is-invalid @enderror"
                                        type="checkbox" name="terms" id="terms"
                                        {{ old('terms') ? 'checked' : '' }} required>
                                    <span>
                                        I have read and agree to the
                                        <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal"
                                           class="pr-terms-link">Terms and Conditions</a>.
                                    </span>
                                </label>
                                @error('terms')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- v18: RA 10173 consent. Also enforced server-side (RegisterController). --}}
                            <div class="pr-form-section pr-form-section--terms">
                                <label class="pr-terms-check dpn-consent">
                                    <input class="@error('data_privacy') is-invalid @enderror"
                                        type="checkbox" name="data_privacy" id="data_privacy" value="1"
                                        {{ old('data_privacy') ? 'checked' : '' }} required>
                                    <span>
                                        I have read the
                                        <a href="#" data-privacy-open class="pr-terms-link">Data Privacy Notice</a>
                                        and I consent to Pinikitan Rental collecting and processing my personal
                                        information, including my ID photo, for my application and tenancy.
                                        <small>Data Privacy Act of 2012 (RA 10173)</small>
                                    </span>
                                </label>
                                @error('data_privacy')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            @endguest

                            <div class="pr-form-submit">
                                @auth
                                    @if(($unpaidDeposit ?? 0) > 0)
                                        {{-- Unpaid deposit blocks any new unit application. --}}
                                        <div class="alert alert-warning" role="alert" style="margin-bottom:0;">
                                            <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Unpaid deposit</strong>
                                            <div class="mt-1">
                                                You have an outstanding deposit of
                                                <strong>₱{{ number_format($unpaidDeposit, 2) }}</strong>.
                                                Please settle it before applying for another unit or room.
                                            </div>
                                            <a href="{{ route('tenant.payments') }}" class="pr-btn pr-btn--primary mt-3">
                                                Go to Payments
                                            </a>
                                        </div>
                                    @elseif($hasPendingApplication ?? false)
                                        <div class="alert alert-info" role="alert" style="margin-bottom:0;">
                                            <strong><i class="bi bi-hourglass-split me-2"></i>Application pending</strong>
                                            <div class="mt-1">
                                                You already have an application awaiting manager approval.
                                                You can submit a new one once it has been reviewed.
                                            </div>
                                            <a href="{{ route('tenant.leases') }}" class="pr-btn pr-btn--primary mt-3">
                                                View My Leases
                                            </a>
                                        </div>
                                    @else
                                        <button type="submit" class="pr-btn pr-btn--primary pr-btn--full">
                                            Submit Application
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                                        </button>
                                    @endif
                                @else
                                    <button type="submit" class="pr-btn pr-btn--primary pr-btn--full">
                                        Submit Application
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                                    </button>
                                @endauth
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ===================== TERMS MODAL ===================== --}}
<div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content pr-modal">
            <div class="pr-modal__header">
                <div>
                    <div class="pr-modal__eyebrow">Legal</div>
                    <h5 class="pr-modal__title" id="termsModalLabel">Terms and Conditions</h5>
                </div>
                <button type="button" class="pr-modal__close" data-bs-dismiss="modal" aria-label="Close">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="modal-body p-3" style="height:80vh;">
                <iframe src="{{ asset('storage/assets/tenant_agreement.pdf') }}" width="100%" height="100%" style="border:none; border-radius:8px;"></iframe>
            </div>
            <div class="modal-footer border-0 pb-4">
                <button type="button" class="pr-btn pr-btn--ghost" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- ===================== ZOOM IMAGE MODAL ===================== --}}
<div class="modal fade" id="unitImageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow-lg bg-transparent">
            <div class="modal-body p-0">
                <img id="unitImageZoom" src="" alt="Unit Image" class="img-fluid rounded-4 w-100">
            </div>
        </div>
    </div>
</div>

{{-- valid-ID camera capture --}}
@include('partials.id-camera')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
    /* ===================== DESIGN TOKENS ===================== */
    :root {
        --pr-navy:      #0c1a3a;
        --pr-navy-mid:  #132044;
        --pr-gold:      #c9a84c;
        --pr-gold-lt:   #e2c580;
        --pr-cream:     #faf8f3;
        --pr-white:     #ffffff;
        --pr-gray-50:   #f8f9fa;
        --pr-gray-100:  #f0f0f0;
        --pr-gray-300:  #d0d0d0;
        --pr-gray-500:  #7a7a7a;
        --pr-gray-800:  #222222;
        --pr-success:   #22c55e;
        --pr-danger:    #ef4444;

        --pr-font-display: 'Playfair Display', Georgia, serif;
        --pr-font-body:    'DM Sans', system-ui, sans-serif;

        --pr-radius-sm:  6px;
        --pr-radius-md:  12px;
        --pr-radius-lg:  20px;
        --pr-radius-xl:  28px;

        --pr-shadow-card:  0 4px 24px rgba(12,26,58,.10);
        --pr-shadow-hover: 0 16px 48px rgba(12,26,58,.18);
        --pr-shadow-modal: 0 32px 80px rgba(12,26,58,.28);

        --pr-transition: 0.3s cubic-bezier(.4,0,.2,1);
    }

    *, *::before, *::after { box-sizing: border-box; }

    body {
        font-family: var(--pr-font-body);
        color: var(--pr-gray-800);
        background: var(--pr-cream);
        line-height: 1.65;
        overflow-x: hidden;
    }

    /* ===================== HERO ===================== */
    .pr-hero {
        position: relative;
        min-height: 100svh;
        background: var(--pr-navy);
        display: flex;
        align-items: center;
        overflow: hidden;
    }

    .pr-hero__bg-grid {
        position: absolute; inset: 0;
        background-image:
            linear-gradient(rgba(201,168,76,.06) 1px, transparent 1px),
            linear-gradient(90deg, rgba(201,168,76,.06) 1px, transparent 1px);
        background-size: 60px 60px;
    }

    .pr-hero__orb {
        position: absolute;
        border-radius: 50%;
        filter: blur(80px);
        pointer-events: none;
    }
    .pr-hero__orb--1 {
        width: 520px; height: 520px;
        background: radial-gradient(circle, rgba(201,168,76,.18) 0%, transparent 70%);
        top: -120px; right: -100px;
    }
    .pr-hero__orb--2 {
        width: 380px; height: 380px;
        background: radial-gradient(circle, rgba(19,32,68,.9) 0%, transparent 70%);
        bottom: 0; left: -80px;
    }

    .pr-hero__inner {
        position: relative;
        z-index: 2;
        padding-top: 120px;
        padding-bottom: 80px;
    }

    .pr-hero__eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        color: var(--pr-gold);
        font-size: .8rem;
        font-weight: 500;
        letter-spacing: .12em;
        text-transform: uppercase;
        margin-bottom: 28px;
        opacity: 0;
        animation: fadeUp .7s .2s forwards;
    }
    .pr-eyebrow-dot {
        width: 8px; height: 8px;
        border-radius: 50%;
        background: var(--pr-gold);
        display: inline-block;
        animation: pulse 2s infinite;
    }
    @keyframes pulse {
        0%,100% { opacity: 1; transform: scale(1); }
        50%      { opacity: .5; transform: scale(.7); }
    }

    .pr-hero__title {
        font-family: var(--pr-font-display);
        font-size: clamp(3rem, 7vw, 6rem);
        font-weight: 700;
        color: var(--pr-white);
        line-height: 1.08;
        margin-bottom: 28px;
        opacity: 0;
        animation: fadeUp .7s .35s forwards;
    }
    .pr-hero__title-accent {
        font-style: italic;
        color: var(--pr-gold);
    }

    .pr-hero__subtitle {
        font-size: 1.15rem;
        color: rgba(255,255,255,.7);
        max-width: 520px;
        margin-bottom: 44px;
        opacity: 0;
        animation: fadeUp .7s .5s forwards;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 14px;
    }

    .pr-vacancy-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(201,168,76,.15);
        border: 1px solid rgba(201,168,76,.3);
        color: var(--pr-gold-lt);
        border-radius: 999px;
        padding: 4px 14px;
        font-size: .85rem;
        font-weight: 500;
        white-space: nowrap;
    }
    .pr-vacancy-dot {
        width: 6px; height: 6px;
        background: var(--pr-success);
        border-radius: 50%;
        animation: pulse 2s infinite;
    }

    .pr-hero__cta-row {
        display: flex;
        gap: 16px;
        flex-wrap: wrap;
        opacity: 0;
        animation: fadeUp .7s .65s forwards;
    }

    .pr-hero__scroll-hint {
        position: absolute;
        bottom: 36px; left: 50%;
        transform: translateX(-50%);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        color: rgba(255,255,255,.4);
        font-size: .7rem;
        letter-spacing: .1em;
        text-transform: uppercase;
        z-index: 2;
    }
    .pr-scroll-line {
        width: 1px; height: 48px;
        background: linear-gradient(to bottom, rgba(201,168,76,.6), transparent);
        animation: scrollPulse 2s infinite;
    }
    @keyframes scrollPulse {
        0%,100% { opacity: .4; transform: scaleY(1); }
        50%      { opacity: 1;  transform: scaleY(.6); }
    }

    /* ===================== BUTTONS ===================== */
    .pr-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 14px 32px;
        border-radius: 999px;
        font-family: var(--pr-font-body);
        font-weight: 600;
        font-size: .95rem;
        text-decoration: none;
        transition: var(--pr-transition);
        cursor: pointer;
        border: none;
    }
    .pr-btn--primary {
        background: linear-gradient(135deg, var(--pr-gold), var(--pr-gold-lt));
        color: var(--pr-navy);
    }
    .pr-btn--primary:hover {
        color: var(--pr-navy);
        transform: translateY(-3px);
        box-shadow: 0 12px 32px rgba(201,168,76,.35);
    }
    .pr-btn--ghost {
        background: transparent;
        color: rgba(255,255,255,.75);
        border: 1px solid rgba(255,255,255,.25);
    }
    .pr-btn--ghost:hover {
        color: var(--pr-white);
        border-color: rgba(255,255,255,.6);
        background: rgba(255,255,255,.06);
    }
    .pr-btn--full { width: 100%; justify-content: center; }

    /* ===================== STATS ===================== */
    .pr-stats {
        background: var(--pr-white);
        border-bottom: 1px solid var(--pr-gray-100);
        padding: 0;
    }
    .pr-stats__grid {
        display: flex;
        align-items: center;
        justify-content: center;
        flex-wrap: wrap;
        gap: 0;
    }
    .pr-stat {
        padding: 28px 40px;
        text-align: center;
        flex: 1;
        min-width: 140px;
    }
    .pr-stat--divider {
        width: 1px;
        height: 40px;
        background: var(--pr-gray-100);
        padding: 0;
        flex: none;
    }
    .pr-stat__number {
        font-family: var(--pr-font-display);
        font-size: 2rem;
        font-weight: 700;
        color: var(--pr-navy);
        line-height: 1;
        margin-bottom: 4px;
    }
    .pr-stat__label {
        font-size: .8rem;
        color: var(--pr-gray-500);
        text-transform: uppercase;
        letter-spacing: .08em;
        font-weight: 500;
    }

    /* ===================== SECTION HEADER ===================== */
    .pr-section-header {
        text-align: center;
        margin-bottom: 56px;
    }
    .pr-section-header__tag {
        display: inline-block;
        color: var(--pr-gold);
        font-size: .75rem;
        font-weight: 600;
        letter-spacing: .15em;
        text-transform: uppercase;
        margin-bottom: 14px;
    }
    .pr-section-header__title {
        font-family: var(--pr-font-display);
        font-size: clamp(2rem, 4vw, 2.8rem);
        font-weight: 700;
        color: var(--pr-navy);
        margin-bottom: 14px;
        line-height: 1.2;
    }
    .pr-section-header__desc {
        color: var(--pr-gray-500);
        max-width: 520px;
        margin: 0 auto;
    }

    /* ===================== UNITS ===================== */
    .pr-units {
        padding: 100px 0;
        background: var(--pr-cream);
    }
    .pr-units__grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 28px;
    }

    .pr-unit-card {
        background: var(--pr-white);
        border-radius: var(--pr-radius-xl);
        overflow: hidden;
        box-shadow: var(--pr-shadow-card);
        transition: var(--pr-transition);
        cursor: pointer;
        border: 1px solid rgba(12,26,58,.05);
    }
    .pr-unit-card:hover:not(.pr-unit-card--full) {
        transform: translateY(-8px);
        box-shadow: var(--pr-shadow-hover);
    }
    .pr-unit-card--full {
        opacity: .55;
        cursor: not-allowed;
    }

    .pr-unit-card__img-wrap {
        position: relative;
        height: 210px;
        overflow: hidden;
    }
    .pr-unit-card__img {
        width: 100%; height: 100%;
        object-fit: cover;
        transition: transform .5s ease;
    }
    .pr-unit-card:hover:not(.pr-unit-card--full) .pr-unit-card__img {
        transform: scale(1.05);
    }
    .pr-unit-card__img-overlay {
        position: absolute; inset: 0;
        background: linear-gradient(to top, rgba(12,26,58,.5) 0%, transparent 60%);
    }

    .pr-unit-badge {
        position: absolute;
        top: 14px; right: 14px;
        padding: 5px 13px;
        border-radius: 999px;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
    }
    .pr-unit-badge--vacant { background: var(--pr-success); color: #fff; }
    .pr-unit-badge--full   { background: var(--pr-danger);  color: #fff; }

    .pr-unit-card__body { padding: 22px 24px; }
    .pr-unit-card__top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 16px;
    }
    .pr-unit-card__type {
        font-family: var(--pr-font-display);
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--pr-navy);
        margin: 0 0 4px;
    }
    .pr-unit-card__room {
        font-size: .82rem;
        color: var(--pr-gray-500);
        margin: 0;
    }
    .pr-unit-card__price {
        font-family: var(--pr-font-display);
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--pr-gold);
        white-space: nowrap;
        text-align: right;
    }
    .pr-unit-card__price span { font-size: .7rem; color: var(--pr-gray-500); font-family: var(--pr-font-body); }

    .pr-unit-card__meta {
        display: flex;
        gap: 16px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }
    .pr-unit-meta-item {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: .8rem;
        color: var(--pr-gray-500);
    }
    .pr-unit-meta-item svg { opacity: .6; }

    .pr-unit-card__cta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 18px;
        background: linear-gradient(135deg, var(--pr-navy), var(--pr-navy-mid));
        color: var(--pr-gold-lt);
        border-radius: var(--pr-radius-md);
        font-weight: 600;
        font-size: .9rem;
        transition: var(--pr-transition);
    }
    .pr-unit-card:hover:not(.pr-unit-card--full) .pr-unit-card__cta {
        background: linear-gradient(135deg, var(--pr-gold), var(--pr-gold-lt));
        color: var(--pr-navy);
    }
    .pr-unit-card__cta--disabled {
        background: var(--pr-gray-100);
        color: var(--pr-gray-500);
        cursor: not-allowed;
        justify-content: center;
    }

    /* ===================== ABOUT ===================== */
    .pr-about {
        padding: 100px 0;
        background: var(--pr-white);
        overflow: hidden;
    }
    .pr-about__inner {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 80px;
        align-items: center;
    }

    .pr-about__visual {
        position: relative;
        height: 380px;
    }
    .pr-about__card-1,
    .pr-about__card-2 {
        position: absolute;
        background: var(--pr-white);
        border-radius: var(--pr-radius-lg);
        padding: 22px 28px;
        box-shadow: var(--pr-shadow-hover);
        display: flex;
        align-items: center;
        gap: 16px;
        font-weight: 600;
        color: var(--pr-navy);
        border: 1px solid var(--pr-gray-100);
        animation: float 5s ease-in-out infinite;
    }
    .pr-about__card-1 { top: 40px; left: 0; }
    .pr-about__card-2 { bottom: 60px; right: 0; animation-delay: 2.5s; }
    .pr-about__card-icon { font-size: 1.6rem; }
    .pr-about__card-text { font-size: .9rem; }

    .pr-about__badge {
        position: absolute;
        top: 50%; left: 50%;
        transform: translate(-50%, -50%);
        background: linear-gradient(135deg, var(--pr-navy), var(--pr-navy-mid));
        border-radius: 50%;
        width: 130px; height: 130px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        box-shadow: 0 12px 48px rgba(12,26,58,.3);
    }
    .pr-about__badge-year {
        font-family: var(--pr-font-display);
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--pr-gold);
        line-height: 1;
    }
    .pr-about__badge-label {
        font-size: .68rem;
        color: rgba(255,255,255,.6);
        text-transform: uppercase;
        letter-spacing: .1em;
    }

    @keyframes float {
        0%,100% { transform: translateY(0); }
        50%      { transform: translateY(-10px); }
    }

    .pr-about__title {
        font-family: var(--pr-font-display);
        font-size: clamp(1.8rem, 3.5vw, 2.6rem);
        font-weight: 700;
        color: var(--pr-navy);
        line-height: 1.2;
        margin-bottom: 20px;
    }
    .pr-about__text {
        color: var(--pr-gray-500);
        margin-bottom: 16px;
        line-height: 1.8;
    }
    .pr-about__pillars {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
        margin-top: 32px;
    }
    .pr-pillar {
        display: flex;
        align-items: center;
        gap: 10px;
        background: var(--pr-cream);
        border-radius: var(--pr-radius-md);
        padding: 12px 18px;
        font-size: .85rem;
        font-weight: 600;
        color: var(--pr-navy);
        border: 1px solid var(--pr-gray-100);
    }
    .pr-pillar__icon { font-size: 1.1rem; }

    /* ===================== LEASE TERMS ===================== */
    .pr-lease-terms {
        padding: 100px 0;
        background: linear-gradient(180deg, var(--pr-navy) 0%, var(--pr-navy-mid) 100%);
    }
    .pr-lease-terms .pr-section-header__title { color: var(--pr-white); }

    .pr-terms-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        max-width: 960px;
        margin: 0 auto;
    }
    .pr-term-item {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        background: rgba(255,255,255,.04);
        border: 1px solid rgba(201,168,76,.15);
        border-radius: var(--pr-radius-lg);
        padding: 18px 20px;
        transition: var(--pr-transition);
    }
    .pr-term-item:hover {
        background: rgba(201,168,76,.07);
        border-color: rgba(201,168,76,.35);
        transform: translateY(-2px);
    }
    .pr-term-item--wide { grid-column: span 2; }

    .pr-term-icon {
        flex-shrink: 0;
        width: 36px; height: 36px;
        border-radius: 10px;
        background: rgba(201,168,76,.12);
        display: flex; align-items: center; justify-content: center;
    }
    .pr-term-icon svg { width: 16px; height: 16px; stroke: var(--pr-gold); }

    .pr-term-text {
        font-size: .875rem;
        color: rgba(255,255,255,.72);
        line-height: 1.65;
    }
    .pr-term-text strong {
        display: block;
        font-size: .72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .09em;
        color: var(--pr-gold-lt);
        margin-bottom: 3px;
    }

    @media (max-width: 767px) {
        .pr-terms-grid { grid-template-columns: 1fr; }
        .pr-term-item--wide { grid-column: span 1; }
        .pr-lease-terms { padding: 60px 0; }
    }

    /* ===================== CONTACT ===================== */
    .pr-contact {
        padding: 100px 0;
        background: var(--pr-navy);
    }
    .pr-contact .pr-section-header__title { color: var(--pr-white); }

    .pr-contact__grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 24px;
    }
    .pr-contact-card {
        background: rgba(255,255,255,.05);
        border: 1px solid rgba(201,168,76,.2);
        border-radius: var(--pr-radius-lg);
        padding: 36px 24px;
        text-align: center;
        color: var(--pr-white);
        text-decoration: none;
        transition: var(--pr-transition);
        display: block;
    }
    .pr-contact-card:hover {
        background: rgba(201,168,76,.1);
        border-color: rgba(201,168,76,.5);
        transform: translateY(-6px);
        color: var(--pr-white);
    }
    .pr-contact-card__icon { font-size: 2rem; margin-bottom: 14px; }
    .pr-contact-card__label {
        font-size: .72rem;
        letter-spacing: .12em;
        text-transform: uppercase;
        color: var(--pr-gold);
        font-weight: 600;
        margin-bottom: 10px;
    }
    .pr-contact-card__value {
        font-size: .92rem;
        color: rgba(255,255,255,.75);
        line-height: 1.6;
    }

    /* ===================== MAP ===================== */
    .pr-map {
        padding: 100px 0;
        background: var(--pr-cream);
    }
    .pr-map__embed {
        border-radius: var(--pr-radius-xl);
        overflow: hidden;
        box-shadow: var(--pr-shadow-hover);
        aspect-ratio: 16/7;
    }
    .pr-map__embed iframe { width: 100%; height: 100%; display: block; }

    /* ===================== MODAL ===================== */
    .pr-modal {
        border: 0;
        border-radius: var(--pr-radius-xl) !important;
        overflow: hidden;
        box-shadow: var(--pr-shadow-modal);
    }
    .pr-modal__header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        padding: 24px 32px;
        background: linear-gradient(135deg, var(--pr-navy), var(--pr-navy-mid));
        border-bottom: 1px solid rgba(201,168,76,.2);
    }
    .pr-modal__eyebrow {
        font-size: .7rem;
        font-weight: 600;
        letter-spacing: .15em;
        text-transform: uppercase;
        color: var(--pr-gold);
        margin-bottom: 4px;
    }
    .pr-modal__title {
        font-family: var(--pr-font-display);
        font-size: 1.4rem;
        color: var(--pr-white);
        margin: 0;
    }
    .pr-modal__close {
        background: rgba(255,255,255,.1);
        border: none;
        border-radius: 50%;
        width: 38px; height: 38px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: rgba(255,255,255,.7);
        cursor: pointer;
        transition: var(--pr-transition);
        flex-shrink: 0;
    }
    .pr-modal__close:hover {
        background: rgba(255,255,255,.2);
        color: var(--pr-white);
    }

    .pr-modal__body {
        display: grid;
        grid-template-columns: 340px 1fr;
    }

    .pr-modal__left {
        background: var(--pr-cream);
        border-right: 1px solid var(--pr-gray-100);
        padding: 28px;
    }
    .pr-modal__img-wrap {
        position: relative;
        border-radius: var(--pr-radius-lg);
        overflow: hidden;
        margin-bottom: 20px;
    }
    .pr-modal__img {
        width: 100%; height: 220px;
        object-fit: cover;
        display: block;
    }
    .pr-modal__img-shine {
        position: absolute; inset: 0;
        background: linear-gradient(135deg, rgba(255,255,255,.15) 0%, transparent 60%);
        pointer-events: none;
    }

    /* ===================== CONTRACT INFO BLOCK ===================== */
    .pr-contract-info {
        background: linear-gradient(135deg, var(--pr-navy) 0%, var(--pr-navy-mid) 100%);
        border-radius: var(--pr-radius-md);
        overflow: hidden;
        margin-bottom: 20px;
        border: 1px solid rgba(201,168,76,.2);
    }
    .pr-contract-info__header {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 11px 16px;
        background: rgba(201,168,76,.12);
        border-bottom: 1px solid rgba(201,168,76,.2);
        color: var(--pr-gold);
        font-size: .75rem;
        font-weight: 700;
        letter-spacing: .1em;
        text-transform: uppercase;
    }
    .pr-contract-info__body {
        padding: 14px 16px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .pr-contract-item {
        display: flex;
        align-items: flex-start;
        gap: 10px;
    }
    .pr-contract-item__icon {
        font-size: 1rem;
        line-height: 1.3;
        flex-shrink: 0;
    }
    .pr-contract-item__label {
        font-size: .68rem;
        color: rgba(255,255,255,.5);
        text-transform: uppercase;
        letter-spacing: .08em;
        font-weight: 600;
        margin-bottom: 1px;
    }
    .pr-contract-item__value {
        font-size: .82rem;
        color: rgba(255,255,255,.88);
        font-weight: 500;
        line-height: 1.3;
    }

    /* ===================== CONTRACT END DATE DISPLAY ===================== */
    .pr-contract-end-display {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 11px 16px;
        background: linear-gradient(135deg, rgba(201,168,76,.08), rgba(201,168,76,.04));
        border: 1.5px solid rgba(201,168,76,.35);
        border-radius: var(--pr-radius-md);
        color: var(--pr-navy);
        font-weight: 700;
        font-size: .9rem;
    }
    .pr-contract-end-display svg {
        color: var(--pr-gold);
        flex-shrink: 0;
    }

    .pr-modal__info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }
    .pr-modal__info-item {
        background: var(--pr-white);
        border-radius: var(--pr-radius-md);
        padding: 12px 14px;
        border: 1px solid var(--pr-gray-100);
    }
    .pr-modal__info-item--full { grid-column: span 2; }
    .pr-modal__info-label {
        font-size: .68rem;
        text-transform: uppercase;
        letter-spacing: .1em;
        color: var(--pr-gray-500);
        font-weight: 600;
        margin-bottom: 4px;
    }
    .pr-modal__info-value {
        font-weight: 600;
        color: var(--pr-navy);
        font-size: .9rem;
    }

    .pr-modal__right {
        padding: 32px;
        overflow-y: auto;
        max-height: 80vh;
    }
    .pr-modal__form-header { margin-bottom: 28px; }
    .pr-modal__form-title {
        font-family: var(--pr-font-display);
        font-size: 1.3rem;
        color: var(--pr-navy);
        margin-bottom: 4px;
    }
    .pr-modal__form-subtitle {
        font-size: .85rem;
        color: var(--pr-gray-500);
        margin: 0;
    }

    /* ===================== FORM STYLES ===================== */
    .pr-form-section {
        margin-bottom: 28px;
        padding-bottom: 28px;
        border-bottom: 1px solid var(--pr-gray-100);
    }
    .pr-form-section:last-child { border-bottom: 0; }
    .pr-form-section__label {
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .12em;
        text-transform: uppercase;
        color: var(--pr-gold);
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .pr-form-section__label::after {
        content: '';
        flex: 1;
        height: 1px;
        background: linear-gradient(to right, rgba(201,168,76,.3), transparent);
    }

    .pr-label {
        display: block;
        font-size: .82rem;
        font-weight: 600;
        color: var(--pr-navy);
        margin-bottom: 6px;
    }
    .pr-input,
    .pr-select {
        width: 100%;
        padding: 11px 16px;
        border: 1.5px solid var(--pr-gray-300);
        border-radius: var(--pr-radius-md);
        font-family: var(--pr-font-body);
        font-size: .9rem;
        color: var(--pr-gray-800);
        background: var(--pr-white);
        transition: border-color .2s, box-shadow .2s;
        outline: none;
    }
    .pr-input:focus,
    .pr-select:focus {
        border-color: var(--pr-navy);
        box-shadow: 0 0 0 3px rgba(12,26,58,.08);
    }
    .pr-input.is-invalid,
    .pr-select.is-invalid { border-color: var(--pr-danger); }
    .pr-input.is-valid,
    .pr-select.is-valid { border-color: var(--pr-success); }

    input[type="file"].pr-input { padding: 8px 12px; }

    .pr-eye-toggle {
        position: absolute;
        right: 10px; top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: var(--pr-gray-500);
        cursor: pointer;
        padding: 4px;
        z-index: 5;
        line-height: 1;
        transition: color .2s;
    }
    .pr-eye-toggle:hover { color: var(--pr-navy); }

    .pr-form-section--terms {
        display: flex;
        flex-direction: column;
        gap: 8px;
        border-bottom: 0;
        margin-bottom: 0;
        padding-bottom: 0;
    }
    .pr-terms-check {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        cursor: pointer;
        font-size: .9rem;
        color: var(--pr-gray-500);
        line-height: 1.5;
    }
    .pr-terms-check input[type="checkbox"] {
        width: 18px; height: 18px;
        margin-top: 2px;
        accent-color: var(--pr-navy);
        cursor: pointer;
        flex-shrink: 0;
    }
    .pr-terms-link {
        color: var(--pr-gold);
        font-weight: 600;
        text-decoration: underline;
        text-underline-offset: 3px;
    }
    .pr-terms-link:hover { color: var(--pr-navy); }

    .pr-form-submit { margin-top: 24px; }
    .pr-form-submit .pr-btn--primary {
        background: linear-gradient(135deg, var(--pr-navy), var(--pr-navy-mid));
        color: var(--pr-white);
    }
    .pr-form-submit .pr-btn--primary:hover {
        background: linear-gradient(135deg, var(--pr-gold), var(--pr-gold-lt));
        color: var(--pr-navy);
        box-shadow: 0 8px 24px rgba(201,168,76,.3);
    }

    /* Password strength */
    .password-strength {
        height: 3px;
        margin-top: 6px;
        border-radius: 2px;
        transition: all .3s;
    }
    .password-strength.weak   { background: linear-gradient(to right, var(--pr-danger) 33%, var(--pr-gray-100) 33%); }
    .password-strength.medium { background: linear-gradient(to right, #f59e0b 66%, var(--pr-gray-100) 66%); }
    .password-strength.strong { background: var(--pr-success); }

    /* Bootstrap overrides */
    .invalid-feedback { font-size: .78rem; color: var(--pr-danger); display: block; margin-top: 4px; }

    /* ===================== ANIMATIONS ===================== */
    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(24px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    /* ===================== RESPONSIVE ===================== */
    @media (max-width: 991px) {
        .pr-about__inner { grid-template-columns: 1fr; gap: 50px; }
        .pr-about__visual { height: 280px; }
        .pr-modal__body { grid-template-columns: 1fr; }
        .pr-modal__left { border-right: none; border-bottom: 1px solid var(--pr-gray-100); }
        .pr-modal__right { max-height: none; }
        .pr-contact__grid { grid-template-columns: 1fr; }
    }

    @media (max-width: 767px) {
        .pr-hero__title { font-size: 2.6rem; }
        .pr-stats__grid { gap: 0; }
        .pr-stat { padding: 20px; }
        .pr-stat--divider { display: none; }
        .pr-units { padding: 60px 0; }
        .pr-units__grid { grid-template-columns: 1fr; }
        .pr-about, .pr-contact, .pr-map { padding: 60px 0; }
        .pr-about__visual { height: 220px; }
        .pr-modal__right { padding: 20px; }
        .pr-modal__left { padding: 20px; }
        .pr-modal__info-grid { grid-template-columns: 1fr; }
        .pr-modal__info-item--full { grid-column: span 1; }
        .pr-hero__cta-row { flex-direction: column; align-items: flex-start; }
        .pr-contact__grid { grid-template-columns: 1fr; max-width: 360px; margin: 0 auto; }
        .pr-about__card-1 { top: 10px; left: 0; padding: 14px 18px; }
        .pr-about__card-2 { bottom: 10px; right: 0; padding: 14px 18px; }
    }

    @media (max-width: 480px) {
        .pr-modal__info-grid { grid-template-columns: 1fr 1fr; }
        .pr-modal__info-item--full { grid-column: span 2; }
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const termsModal = document.getElementById('termsModal');
        const unitModal  = document.getElementById('unitModal');

        window.isTermsModalOpening = false;

        termsModal.addEventListener('show.bs.modal', function () {
            window.isTermsModalOpening = true;
            const modal = bootstrap.Modal.getInstance(unitModal);
            if (modal) modal.hide();
        });

        termsModal.addEventListener('hidden.bs.modal', function () {
            const modal = new bootstrap.Modal(unitModal);
            modal.show();
            setTimeout(() => { window.isTermsModalOpening = false; }, 100);
        });

        @if($errors->any() || session('error'))
            const unitModalInstance = new bootstrap.Modal(unitModal);
            unitModalInstance.show();
        @endif
    });

    // ==================== UNIT MODAL POPULATE ====================
    document.addEventListener('DOMContentLoaded', function() {
        const unitModalEl = document.getElementById('unitModal');
        const zoomModalEl = document.getElementById('unitImageModal');
        const zoomImg     = document.getElementById('unitImageZoom');
        const zoomModal   = new bootstrap.Modal(zoomModalEl);

        const unitInfo = {
            "Studio":    { size: "25 m²", capacity: "2 persons" },
            "1 Bedroom": { size: "35 m²", capacity: "2–3 persons" },
            "2-Bedroom": { size: "45 m²", capacity: "4 persons" }
        };

        unitModalEl.addEventListener('show.bs.modal', event => {
            const card = event.relatedTarget;
            if (!card) return;

            const type        = card.dataset.type;
            const image       = card.dataset.image;
            const price       = card.dataset.price;
            const status      = card.dataset.status;
            const unitId      = card.dataset.unitId;
            const capacity    = card.dataset.capacity;
            const noOccupants = card.dataset.noOccupants;

            document.getElementById('unitModalType').value = type;

            const modalImg = document.getElementById('unitModalImg');
            modalImg.src   = image;

            document.getElementById('unitModalTitle').textContent    = `${type} Unit`;
            document.getElementById('unitModalTypeText').textContent = `${type} Unit`;

            const info = unitInfo[type] || { size: "N/A", capacity: "N/A" };
            document.getElementById('unitModalSize').textContent     = info.size;
            document.getElementById('unitModalCapacity').textContent =
                type === 'Bed-Spacer' ? `${noOccupants || 0}/${capacity}` : capacity;

            document.getElementById('unitModalPrice').textContent = Number(price).toFixed(2);

            const statusBadge = document.getElementById('unitModalStatus');
            statusBadge.textContent = status;
            statusBadge.className   = 'badge ' + (status.toLowerCase() === 'vacant' ? 'bg-success' : 'bg-danger');

            modalImg.onclick = function() { zoomImg.src = modalImg.src; zoomModal.show(); };

            const select = document.getElementById('unit_id');
            [...select.options].forEach(opt => {
                if (!opt.value) return;
                opt.hidden = opt.dataset.type !== type;
            });

            setTimeout(() => {
                select.value = unitId;
                select.dispatchEvent(new Event('change', { bubbles: true }));
            }, 0);
        });
    });

    // ==================== CONTRACT END DATE CALCULATION ====================
    document.addEventListener('DOMContentLoaded', function() {
        const moveInInput      = document.getElementById('move_in_date');
        const contractEndGroup = document.getElementById('contractEndGroup');
        const contractEndDate  = document.getElementById('contractEndDate');

        if (moveInInput) {
            moveInInput.addEventListener('change', function() {
                const val = this.value;
                if (!val) {
                    contractEndGroup.style.display = 'none';
                    contractEndDate.textContent = '—';
                    return;
                }
                // Add exactly 1 year then subtract 1 day (last day of contract)
                const start = new Date(val);
                const end   = new Date(start);
                end.setFullYear(end.getFullYear() + 1);
                end.setDate(end.getDate() - 1);

                const options = { year: 'numeric', month: 'long', day: 'numeric' };
                contractEndDate.textContent = end.toLocaleDateString('en-PH', options);
                contractEndGroup.style.display = 'block';
            });

            // If old value is present on page reload (e.g. validation error)
            if (moveInInput.value) {
                moveInInput.dispatchEvent(new Event('change'));
            }
        }
    });

    // ==================== EMPLOYMENT LOGIC ====================
    document.addEventListener('DOMContentLoaded', function () {
        const employmentSelect   = document.getElementById('employment_status');
        const incomeInput        = document.getElementById('source_of_income');
        const monthlyIncomeGroup = document.getElementById('monthlyIncomeGroup');

        if (!employmentSelect) return;

        function handleEmploymentChange() {
            const value = employmentSelect.value;
            if (value === 'Student') {
                incomeInput.value = 'Allowance';
                incomeInput.setAttribute('readonly', 'readonly');   // v19: setAttribute needs a value
                monthlyIncomeGroup.classList.add('d-none');
            } else if (value === 'Employed') {
                incomeInput.value = '';
                incomeInput.removeAttribute('readonly');
                monthlyIncomeGroup.classList.remove('d-none');
            } else {
                incomeInput.value = '';
                incomeInput.removeAttribute('readonly');
                monthlyIncomeGroup.classList.add('d-none');
            }
        }

        employmentSelect.addEventListener('change', handleEmploymentChange);
        handleEmploymentChange();
    });

    // ==================== BED SELECTION ====================
    document.addEventListener('DOMContentLoaded', function() {
        const unitSelect        = document.getElementById('unit_id');
        const bedSelectionGroup = document.getElementById('bedSelectionGroup');
        const bedSelect         = document.getElementById('bed_number');

        function populateBeds() {
            const selectedOption = unitSelect.selectedOptions[0];
            if (!selectedOption || selectedOption.dataset.type !== 'Bed-Spacer') {
                bedSelectionGroup.classList.add('d-none');
                bedSelect.required = false;
                bedSelect.innerHTML = '<option value="">Select a bed</option>';
                return;
            }
            bedSelectionGroup.classList.remove('d-none');
            bedSelect.required = true;

            const capacity  = parseInt(selectedOption.dataset.capacity || '0', 10);
            const takenBeds = JSON.parse(selectedOption.dataset.takenBeds || '[]').map(Number);
            const upperCount = Math.floor(capacity / 2);

            let html = '<option value="">Select a bed</option>';
            for (let i = 1; i <= upperCount; i++) {
                if (!takenBeds.includes(i)) html += `<option value="${i}">Bed ${i} - Upper</option>`;
            }
            for (let i = upperCount + 1; i <= capacity; i++) {
                if (!takenBeds.includes(i)) html += `<option value="${i}">Bed ${i} - Lower</option>`;
            }
            bedSelect.innerHTML = html;
        }

        if (unitSelect) {
            unitSelect.addEventListener('change', populateBeds);
            if (unitSelect.value) {
                populateBeds();
                const oldBed = "{{ old('bed_number') }}";
                if (oldBed) bedSelect.value = oldBed;
            }
        }
    });

    // ==================== FORM VALIDATION ====================
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('#unitModal form');
        if (!form) return;

        const isLoggedIn = {{ auth()->check() ? 'true' : 'false' }};

        const validationRules = {
            first_name:               { validate: v => v.trim().length >= 2, message: 'First name must be at least 2 characters' },
            last_name:                { validate: v => v.trim().length >= 2, message: 'Last name must be at least 2 characters' },
            email: {
                validate: (v, f) => {
                    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) return false;
                    const avail = f.querySelector('[name="email"]')?.getAttribute('data-email-available');
                    return avail === null || avail === 'true';
                },
                message: 'Please enter a valid email address'
            },
            contact:                  { validate: v => { const c=v.replace(/[^0-9]/g,''); return c.length>=10&&c.length<=15; }, message: 'Contact number must be 10-15 digits' },
            password: {
                validate: v => v.length>=8 && /[a-z]/.test(v) && /[A-Z]/.test(v) && /\d/.test(v) && /[\W_]/.test(v),
                message: 'Password must be at least 8 characters with uppercase, lowercase, number, and special character'
            },
            password_confirmation:    { validate: (v,f) => { const p=f.querySelector('[name="password"]').value; return v===p&&v.length>0; }, message: 'Passwords do not match' },
            birthdate: {
                validate: v => {
                    if (!v) return false;
                    const b=new Date(v), t=new Date();
                    let age=t.getFullYear()-b.getFullYear();
                    const m=t.getMonth()-b.getMonth();
                    if (m<0||(m===0&&t.getDate()<b.getDate())) age--;
                    return age>=18&&age<=100;
                },
                message: 'You must be at least 18 years old'
            },
            current_address:          { validate: v => v.trim().length >= 5, message: 'Please enter a complete address (at least 5 characters)' },
            unit_id:                  { validate: v => v !== '', message: 'Please select a room' },
            bed_number: {
                validate: (v,f) => {
                    const sel = f.querySelector('[name="unit_id"]');
                    const opt = sel?.options[sel.selectedIndex];
                    return (opt?.dataset.type === 'Bed-Spacer') ? v !== '' : true;
                },
                message: 'Please select a bed for Bed-Spacer units'
            },
            move_in_date: {
                validate: v => { if(!v) return false; const d=new Date(v),t=new Date(); t.setHours(0,0,0,0); return d>=t; },
                message: 'Move-in date must be today or in the future'
            },
            reason:                   { validate: v => v.trim().length >= 3, message: 'Please provide a reason (at least 3 characters)' },
            employment_status:        { validate: v => v !== '', message: 'Please select your employment status' },
            employer_school:          { validate: v => v.trim().length >= 2, message: 'Please enter your employer or school name' },
            source_of_income:         { validate: v => v.trim().length >= 2, message: 'Please specify your source of income' },
            emergency_name:           { validate: v => v.trim().length >= 2, message: 'Please enter emergency contact name' },
            emergency_number:         { validate: v => /^[0-9]{10,15}$/.test(v), message: 'Emergency number must be 10-15 digits' },
            emergency_relationship:   { validate: v => v.trim().length >= 2, message: 'Please specify the relationship' },
            valid_id: {
                validate: (v,f) => {
                    const inp=f.querySelector('[name="valid_id"]');
                    if (!inp.files||!inp.files.length) return false;
                    const file=inp.files[0];
                    const ext=file.name.split('.').pop().toLowerCase();
                    return ['jpg','jpeg','png','pdf'].includes(ext) && file.size<=5*1024*1024;
                },
                message: 'Please capture a clear photo of the FRONT of your valid ID (max 5MB)'
            },
            id_picture: {
                validate: (v,f) => {
                    const inp=f.querySelector('[name="id_picture"]');
                    if (!inp.files||!inp.files.length) return false;
                    const file=inp.files[0];
                    const ext=file.name.split('.').pop().toLowerCase();
                    // v19: limit raised to 5MB to match what the camera can produce
                    // (make sure the server rule for id_picture is max:5120 too).
                    return ['jpg','jpeg','png','gif','webp'].includes(ext) && file.size<=5*1024*1024;
                },
                message: 'Please capture a clear photo of the BACK of your valid ID (max 5MB)'
            },
            terms: { validate: (v,f) => f.querySelector('[name="terms"]').checked, message: 'You must agree to the Terms and Conditions' },
            data_privacy: { validate: (v,f) => f.querySelector('[name="data_privacy"]').checked, message: 'Please read the Data Privacy Notice and give your consent' }
        };

        // When logged in, only validate the fields that are visible
        const loggedInOnlyFields = ['unit_id', 'bed_number', 'move_in_date'];

        function clearError(field) {
            field.classList.remove('is-invalid');
            const parent = field.type==='checkbox' ? field.closest('.pr-terms-check, .form-check') : field.parentNode;
            parent?.querySelector('.invalid-feedback')?.remove();
        }
        function showError(field, message) {
            clearError(field);
            field.classList.add('is-invalid');
            field.classList.remove('is-valid');
            if (field.name==='password'||field.name==='password_confirmation') return;
            const err = document.createElement('div');
            err.className = 'invalid-feedback';
            err.textContent = message;
            if (field.type==='checkbox') {
                field.closest('.pr-terms-check, .form-check')?.appendChild(err);
            } else if (field.hasAttribute('data-id-camera')) {
                // v19: put the message directly under the visible camera box
                // (the real file input is hidden, so its error would be invisible).
                const box = field.nextElementSibling;   // .idcam
                (box || field).insertAdjacentElement('afterend', err);
            } else {
                field.parentNode.appendChild(err);
            }
        }
        function showSuccess(field) {
            clearError(field);
            field.classList.add('is-valid');
        }
        function validateField(field) {
            const rule = validationRules[field.name];
            if (!rule) return true;
            // Skip guest-only fields when logged in
            if (isLoggedIn && !loggedInOnlyFields.includes(field.name)) return true;
            const isValid = rule.validate(field.value, form);
            isValid ? showSuccess(field) : showError(field, rule.message);
            return isValid;
        }

        const formFields = form.querySelectorAll('input, select');
        formFields.forEach(field => {
            // v19: the camera inputs are hidden and have no meaningful blur/input
            // events. They are validated on 'change' (after a capture) and on submit.
            if (field.hasAttribute('data-id-camera')) {
                field.addEventListener('change', function() { validateField(this); });
                return;
            }
            if (field.name==='password'||field.name==='password_confirmation') {
                field.addEventListener('blur', function() { validateField(this); });
                return;
            }
            field.addEventListener('blur', function() { validateField(this); });
            field.addEventListener('input', function() { if (this.classList.contains('is-invalid')) validateField(this); });
            field.addEventListener('change', function() { validateField(this); });
        });

        // Password strength (guests only)
        const passwordField    = form.querySelector('[name="password"]');
        const confirmField     = form.querySelector('[name="password_confirmation"]');
        const passwordStrength = document.getElementById('passwordStrength');
        const passwordHint     = document.getElementById('passwordHint');
        const passwordMatch    = document.getElementById('passwordMatch');

        if (passwordField) {
            passwordField.addEventListener('input', function() {
                const p = this.value;
                let s = 0;
                if (p.length>=8) s++;
                if (/[a-z]/.test(p)&&/[A-Z]/.test(p)) s++;
                if (/\d/.test(p)) s++;
                if (/[^a-zA-Z\d]/.test(p)) s++;

                passwordStrength.className = 'password-strength';
                if (!p.length) {
                    passwordHint.textContent = 'Must have uppercase, lowercase, number & special char';
                    passwordHint.className = 'text-muted';
                } else if (s<=1) {
                    passwordStrength.classList.add('weak');
                    passwordHint.textContent = 'Weak — Add uppercase, numbers, or symbols';
                    passwordHint.className = 'text-danger';
                } else if (s<=2) {
                    passwordStrength.classList.add('medium');
                    passwordHint.textContent = 'Medium — Add more variety';
                    passwordHint.className = 'text-warning';
                } else {
                    passwordStrength.classList.add('strong');
                    passwordHint.textContent = 'Strong password!';
                    passwordHint.className = 'text-success';
                }
                if (confirmField.value) updatePasswordMatch();
            });
            passwordHint.textContent = 'Must have uppercase, lowercase, number & special char';
            passwordHint.className = 'text-muted';
        }

        function updatePasswordMatch() {
            if (!confirmField || !confirmField.value.length) { if(passwordMatch) passwordMatch.textContent=''; return; }
            if (passwordField.value===confirmField.value) {
                passwordMatch.textContent = '✓ Passwords match';
                passwordMatch.className = 'text-success';
            } else {
                passwordMatch.textContent = '✗ Passwords do not match';
                passwordMatch.className = 'text-danger';
            }
        }
        if (confirmField) confirmField.addEventListener('input', updatePasswordMatch);

        // Password toggle
        function setupToggle(btnId, inputId, iconId) {
            const btn = document.getElementById(btnId);
            const inp = document.getElementById(inputId);
            const ico = document.getElementById(iconId);
            if (!btn||!inp) return;
            btn.addEventListener('click', function() {
                const t = inp.type==='password' ? 'text' : 'password';
                inp.type = t;
                ico.classList.toggle('bi-eye', t==='password');
                ico.classList.toggle('bi-eye-slash', t==='text');
            });
        }
        setupToggle('togglePassword','password','passwordEyeIcon');
        setupToggle('togglePasswordConfirmation','password_confirmation','passwordConfirmationEyeIcon');

        // Submit
        form.addEventListener('submit', function(e) {
            let valid = true, first = null, invalid = [];
            formFields.forEach(field => {
                if (field.name && validationRules[field.name]) {
                    if (isLoggedIn && !loggedInOnlyFields.includes(field.name)) return;
                    if (!validateField(field)) { invalid.push(field.name); if (!first) first=field; valid=false; }
                }
            });
            if (!valid) {
                e.preventDefault();
                if (first) {
                    // v19: for the camera inputs scroll to the visible box (the real
                    // input is a hidden 1px element), and don't try to focus it.
                    const isCam  = first.hasAttribute('data-id-camera');
                    const target = isCam ? (first.nextElementSibling || first) : first;
                    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    if (!isCam) first.focus({ preventScroll: true });
                }
            }
        });

        // Reset on modal close
        document.getElementById('unitModal').addEventListener('hidden.bs.modal', function() {
            if (window.isTermsModalOpening) return;
            if (!form.querySelector('.is-invalid')) {
                form.reset();   // the camera partial listens for 'reset' and clears its preview
                formFields.forEach(f => { f.classList.remove('is-valid','is-invalid'); clearError(f); });
                // Reset contract end date
                const contractEndGroup = document.getElementById('contractEndGroup');
                const contractEndDate  = document.getElementById('contractEndDate');
                if (contractEndGroup) contractEndGroup.style.display = 'none';
                if (contractEndDate)  contractEndDate.textContent = '—';
            }
        });
    });

    // ==================== EMAIL CHECK ====================
    document.addEventListener('DOMContentLoaded', function() {
        const emailInput  = document.getElementById('email');
        const emailError  = document.getElementById('email-error');
        const emailStatus = document.getElementById('email-check-status');
        let emailCheckTimeout;

        if (!emailInput) return;

        emailInput.addEventListener('blur', function() { checkEmail(this.value); });
        emailInput.addEventListener('input', function() {
            clearTimeout(emailCheckTimeout);
            emailStatus.textContent = '';
            emailError.textContent  = '';
            emailInput.classList.remove('is-invalid','is-valid');
            emailInput.removeAttribute('data-email-available');
            if (this.value && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.value)) {
                emailCheckTimeout = setTimeout(() => checkEmail(this.value), 500);
            }
        });

        function checkEmail(email) {
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return;
            emailStatus.textContent = 'Checking…';
            emailStatus.className = 'text-muted';
            fetch('{{ route("register.check-email") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                                || document.querySelector('input[name="_token"]')?.value
                },
                body: JSON.stringify({ email })
            })
            .then(r => r.json())
            .then(data => {
                if (data.available) {
                    emailInput.classList.replace('is-invalid','is-valid') || emailInput.classList.add('is-valid');
                    emailInput.setAttribute('data-email-available','true');
                    emailStatus.textContent = '✓ Email is available';
                    emailStatus.className   = 'text-success';
                    emailError.textContent  = '';
                } else {
                    emailInput.classList.replace('is-valid','is-invalid') || emailInput.classList.add('is-invalid');
                    emailInput.setAttribute('data-email-available','false');
                    emailError.textContent  = data.message;
                    emailStatus.textContent = '';
                }
            })
            .catch(() => { emailStatus.textContent = ''; });
        }
    });

    // v19: the old "FILE PREVIEW" script was removed. Both ID inputs (front and
    // back) are now handled by partials/id-camera, which renders its own preview.
</script>
@endpush

@endsection