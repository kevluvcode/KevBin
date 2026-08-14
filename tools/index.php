<?php
require_once __DIR__ . '/../functions.php';

start_session();
page_header('Tools');
?>
<div class="container" style="max-width: 1050px;">
    <h1 class="h4 mb-1 reveal in-view">KevBin Tools</h1>
    <p class="text-secondary mb-4 reveal in-view">Multi-toolkit: OSINT research, code protection, dev utilities, text tools and generators. Unless noted, everything runs 100% in your browser — nothing you type is uploaded.</p>

    <h2 class="h6 mb-3 reveal in-view" style="border-bottom:1px solid var(--line);padding-bottom:.5rem;">🛠️ Code & Security</h2>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-4">

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🧙 Lua Obfuscator</h3>
                <p class="text-secondary small flex-grow-1">Multi-layer Lua protection: renames, string & number encoding, junk injection, an anti-hook / anti-env guard and a load()-based VM with 6 strength levels (Level 5 hides the VM itself as char codes; Level 6 adds dual keys, triple-split bytes and polymorphic junk).</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="lua-obfuscator/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🔐 Hash Generator</h3>
                <p class="text-secondary small flex-grow-1">MD5 / SHA-1 / SHA-256 / SHA-512 checksums plus a "what hash is this?" identifier.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="hash/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🎲 UID Generator</h3>
                <p class="text-secondary small flex-grow-1">UUID v4, hex tokens, base64 secret keys, strong passwords and memorable passphrases.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="uid/">Open</a>
            </div></div>
        </div>

    </div>

    <h2 class="h6 mb-3 reveal in-view" style="border-bottom:1px solid var(--line);padding-bottom:.5rem;">💻 Developer</h2>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-4">

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🧾 Dev Toolkit</h3>
                <p class="text-secondary small flex-grow-1">JSON formatter/validator, regex tester, number-base converter, URL parser, HTML entity escape/unescape and a live markdown preview.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="dev/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🔄 Encoders</h3>
                <p class="text-secondary small flex-grow-1">Base64, hex, URL, ROT13, binary, Morse code and leet speak encode/decode with live copy.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="encoders/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">📏 Unit Converter</h3>
                <p class="text-secondary small flex-grow-1">Length, mass, temperature, data size, time and speed — instant conversions with swap & copy.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="convert/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🎨 Color Converter</h3>
                <p class="text-secondary small flex-grow-1">Convert between HEX, RGB, HSL and CMYK with a color picker.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="color/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🌐 IP / Subnet Calc</h3>
                <p class="text-secondary small flex-grow-1">Network, broadcast, usable hosts, CIDR, masks and binary layout — with explanations.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="net/">Open</a>
            </div></div>
        </div>

    </div>

    <h2 class="h6 mb-3 reveal in-view" style="border-bottom:1px solid var(--line);padding-bottom:.5rem;">📝 Text</h2>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-4">

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">📊 Text Tools</h3>
                <p class="text-secondary small flex-grow-1">Case converter, text analyzer (words/read-time), lorem ipsum generator and a diff checker.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="text/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🎲 Random Generator</h3>
                <p class="text-secondary small flex-grow-1">Coin flips, dice, random numbers, strings, pick-one choices and 6/49 lottery picks using crypto-grade randomness.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="random/">Open</a>
            </div></div>
        </div>

    </div>

    <h2 class="h6 mb-3 reveal in-view" style="border-bottom:1px solid var(--line);padding-bottom:.5rem;">🧰 Handy Utilities</h2>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-4">

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🔑 Password Generator</h3>
                <p class="text-secondary small flex-grow-1">Crypto-grade random passwords with options, bulk generate and live entropy estimates.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="password/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🔲 QR Code Generator</h3>
                <p class="text-secondary small flex-grow-1">QR codes for URLs, Wi-Fi strings or any text with custom size, margin and colors.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="qr/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">⏱ Timestamp Converter</h3>
                <p class="text-secondary small flex-grow-1">Unix timestamps ↔ human dates, both directions, with handy presets.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="timestamp/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🕒 Cron Explainer</h3>
                <p class="text-secondary small flex-grow-1">Turn a cron expression into plain English plus the next times it will fire.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="crontab/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🔗 Slug Generator</h3>
                <p class="text-secondary small flex-grow-1">Clean URL slugs from any text — accents stripped, separators and length options.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="slug/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🔄 CSV ↔ JSON</h3>
                <p class="text-secondary small flex-grow-1">Convert CSV / TSV to JSON and back, with quote handling and delimiter options.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="csv/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🎨 CSS Gradient Generator</h3>
                <p class="text-secondary small flex-grow-1">Linear / radial gradients with a live preview and one-click CSS copy.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="gradient/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">👁 Contrast Checker</h3>
                <p class="text-secondary small flex-grow-1">WCAG contrast ratios and AA / AAA pass-fail for any two colors.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="contrast/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🔤 Character Inspector</h3>
                <p class="text-secondary small flex-grow-1">Code points, UTF-8 bytes and HTML entities for any character.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="charcode/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">📦 Hex Dump</h3>
                <p class="text-secondary small flex-grow-1">Classic hexdump of any text with offsets, hex and ASCII columns.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="hexdump/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">⏳ Date Duration</h3>
                <p class="text-secondary small flex-grow-1">Difference between two dates in every unit, or add a duration to a date.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="duration/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🖼 Image ↔ Base64</h3>
                <p class="text-secondary small flex-grow-1">Encode images to data URIs (with resize) or decode them back to files.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="imgbase64/">Open</a>
            </div></div>
        </div>

    </div>

    <h2 class="h6 mb-3 reveal in-view" style="border-bottom:1px solid var(--line);padding-bottom:.5rem;">🔍 OSINT & Research</h2>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-4">

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🔍 OSINT Toolkit</h3>
                <p class="text-secondary small flex-grow-1">Public-information lookups for usernames, emails, phones, domains and IPs plus a manual grab-bag.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="osint/">Open</a>
                <span class="text-secondary small mt-2">⚠️ For legal research & security testing only.</span>
            </div></div>
        </div>

    </div>
</div>
<?php page_footer(); ?>