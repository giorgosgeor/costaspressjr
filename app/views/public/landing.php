<!DOCTYPE html>
<html lang="<?= htmlspecialchars(I18n::locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Costaspressjr - Custom T-Shirts & Apparel</title>
    <meta name="description" content="Design your own custom t-shirts and apparel, or choose from hundreds of pre-made designs. Premium quality, fast delivery.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Special+Elite&family=IBM+Plex+Mono:wght@400;500;600;700&family=Anton&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'IBM Plex Mono', 'Courier Prime', monospace;
            background: #15130E;
            background-image: radial-gradient(circle, rgba(241,236,222,0.07) 1px, transparent 1.5px);
            background-size: 14px 14px;
            min-height: 100vh;
            color: #F1ECDE;
            overflow-x: hidden;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Bebas Neue', 'Anton', 'Impact', sans-serif;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        /* ── Navigation ── */
        .navbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            padding: 1rem 3rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 100;
            background: rgba(0,0,0,0.25);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }

        .logo {
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        .logo img {
            height: 56px;
            width: auto;
            object-fit: contain;
        }

        .nav-buttons { display: flex; gap: 0.75rem; }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.65rem 1.4rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            font-family: inherit;
            text-decoration: none;
            transition: all 0.25s;
            cursor: pointer;
            border: none;
        }

        .btn-outline {
            background: transparent;
            border: 1.5px solid rgba(206, 186, 207, 0.4);
            color: #CEBACF;
        }
        .btn-outline:hover {
            background: rgba(206, 186, 207, 0.1);
            border-color: rgba(206, 186, 207, 0.6);
        }

        .btn-primary {
            background: linear-gradient(135deg, #15130E 0%, #E63946 100%);
            color: #fff;
            box-shadow: 0 4px 16px rgba(125,128,218,0.35);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(125,128,218,0.45);
        }

        .btn-lg { padding: 1rem 2.25rem; font-size: 1rem; border-radius: 12px; }

        /* ── Hero ── */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 7rem 2rem 4rem;
            position: relative;
        }

        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="g" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.04)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23g)"/></svg>');
            pointer-events: none;
        }

        /* Decorative blobs */
        .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.12;
            pointer-events: none;
        }
        .blob-1 { width: 500px; height: 500px; background: #15130E; top: -100px; left: -150px; }
        .blob-2 { width: 400px; height: 400px; background: #E63946; bottom: 0; right: -100px; }
        .blob-3 { width: 300px; height: 300px; background: #CEBACF; top: 40%; left: 50%; transform: translate(-50%, -50%); opacity: 0.08; }

        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 760px;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            background: rgba(125,128,218,0.15);
            border: 1px solid rgba(125,128,218,0.3);
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #E63946;
            margin-bottom: 2rem;
            letter-spacing: 0.3px;
        }

        .hero h1 {
            font-size: clamp(2.4rem, 6vw, 4.2rem);
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 1.5rem;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #fff 0%, #CEBACF 50%, #E63946 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero p {
            font-size: 1.1rem;
            color: rgba(255,255,255,0.6);
            line-height: 1.85;
            margin-bottom: 2.75rem;
            max-width: 560px;
            margin-left: auto;
            margin-right: auto;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* ── Features ── */
        .features {
            padding: 6rem 2rem;
            background: rgba(0,0,0,0.15);
        }

        .container { max-width: 1200px; margin: 0 auto; }

        .section-tag {
            text-align: center;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            color: #15130E;
            margin-bottom: 0.75rem;
        }

        .section-title {
            text-align: center;
            font-size: clamp(1.8rem, 3vw, 2.4rem);
            font-weight: 800;
            margin-bottom: 0.75rem;
            letter-spacing: -0.3px;
        }

        .section-subtitle {
            text-align: center;
            color: rgba(255,255,255,0.5);
            font-size: 1rem;
            margin-bottom: 3.5rem;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .feature-card {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 18px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s;
        }

        .feature-card:hover {
            background: rgba(125,128,218,0.1);
            border-color: rgba(125,128,218,0.25);
            transform: translateY(-4px);
        }

        .feature-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, rgba(125,128,218,0.2), rgba(176,163,212,0.2));
            border: 1px solid rgba(125,128,218,0.25);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            margin: 0 auto 1.25rem;
        }

        .feature-card h3 {
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #fff;
        }

        .feature-card p {
            color: rgba(255,255,255,0.5);
            line-height: 1.7;
            font-size: 0.9rem;
        }

        /* ── CTA ── */
        .cta {
            padding: 6rem 2rem;
            text-align: center;
        }

        .cta-box {
            background: linear-gradient(135deg, rgba(125,128,218,0.15) 0%, rgba(176,163,212,0.12) 50%, rgba(198,175,177,0.1) 100%);
            border: 1px solid rgba(125,128,218,0.25);
            border-radius: 24px;
            padding: 4rem;
            max-width: 800px;
            margin: 0 auto;
            position: relative;
            overflow: hidden;
        }

        .cta-box::before {
            content: '';
            position: absolute;
            top: -50%; left: 50%;
            transform: translateX(-50%);
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(125,128,218,0.12) 0%, transparent 70%);
            pointer-events: none;
        }

        .cta h2 {
            font-size: clamp(1.8rem, 3vw, 2.5rem);
            font-weight: 800;
            margin-bottom: 1rem;
            letter-spacing: -0.3px;
        }

        .cta p {
            color: rgba(255,255,255,0.6);
            font-size: 1rem;
            margin-bottom: 2rem;
        }

        /* ── Footer ── */
        .footer {
            padding: 2rem;
            text-align: center;
            border-top: 1px solid rgba(255,255,255,0.06);
        }

        .footer p { color: rgba(255,255,255,0.3); font-size: 0.85rem; }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            .navbar { padding: 0.85rem 1.25rem; }
            .logo img { height: 44px; }
            .hero-buttons { flex-direction: column; align-items: center; }
            .cta-box { padding: 2.5rem 1.5rem; }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="/" class="logo">
            <img src="/images/logo.png" alt="Costaspressjr">
        </a>
        <div class="nav-buttons">
            <a href="/login" class="btn btn-outline">Login</a>
            <a href="/register" class="btn btn-primary">Get Started</a>
        </div>
    </nav>

    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="blob blob-3"></div>

    <section class="hero">
        <div class="hero-content">
            <span class="hero-badge">✦ Premium Custom Apparel</span>
            <h1>Express Your Style,<br>Wear Your Story</h1>
            <p>Create unique custom t-shirts with our easy design tools or choose from hundreds of pre-made designs. Quality fabrics, bold prints, delivered to your door.</p>
            <div class="hero-buttons">
                <a href="/register" class="btn btn-primary btn-lg">Get Started Free</a>
                <a href="/login" class="btn btn-outline btn-lg">I Have an Account</a>
            </div>
        </div>
    </section>

    <section class="features">
        <div class="container">
            <p class="section-tag">Why choose us</p>
            <h2 class="section-title">Everything You Need</h2>
            <p class="section-subtitle">Quality tools, premium fabrics, and fast delivery — all in one place</p>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">🎨</div>
                    <h3>Easy Design Tools</h3>
                    <p>Our intuitive editor makes it easy to create your perfect t-shirt in minutes.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🖼️</div>
                    <h3>Pre-made Designs</h3>
                    <p>Browse hundreds of professionally designed graphics ready to print.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">✨</div>
                    <h3>Premium Quality</h3>
                    <p>We use only the finest fabrics and printing techniques for lasting comfort.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🚀</div>
                    <h3>Fast Delivery</h3>
                    <p>Quick turnaround times and reliable shipping to get your designs fast.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">💬</div>
                    <h3>Expert Support</h3>
                    <p>Our friendly team is here to help you every step of the way.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🛡️</div>
                    <h3>Satisfaction Guaranteed</h3>
                    <p>Not happy with your order? We'll make it right, no questions asked.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="cta">
        <div class="cta-box">
            <h2>Ready to Create?</h2>
            <p>Join thousands of happy customers who've found their perfect custom apparel.</p>
            <a href="/register" class="btn btn-primary btn-lg">Create Your Free Account</a>
        </div>
    </section>

    <footer class="footer">
        <p>&copy; <?= date('Y') ?> Costaspressjr. All rights reserved.</p>
    </footer>
</body>
</html>
