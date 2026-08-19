<?php
require_once __DIR__ . '/../functions.php';

start_session();
page_header('Tools');
?>
<div class="container" style="max-width: 1250px;">
    <h1 class="h4 mb-1 reveal in-view">KevBin Tools</h1>
    <p class="text-secondary mb-4 reveal in-view">Multi-toolkit: OSINT research, code protection, dev utilities, text tools and generators. Unless noted, everything runs 100% in your browser — nothing you type is uploaded.</p>

    <div class="tools-layout">
        <aside class="tools-side">
            <div class="tools-side-inner">
                <div class="mb-3">
                    <input id="js-search" class="form-control" type="text" placeholder="🔍 Search tools...">
                </div>
                <nav id="js-tabs" class="tools-nav">
                    <button class="tools-cat active" data-cat="all"><span class="tc-ic">✦</span><span class="tc-name">All tools</span><span class="tc-count" data-count="all"></span></button>
                    <button class="tools-cat" data-cat="security"><span class="tc-ic">🛠</span><span class="tc-name">Code &amp; Security</span><span class="tc-count" data-count="security"></span></button>
                    <button class="tools-cat" data-cat="developer"><span class="tc-ic">💻</span><span class="tc-name">Developer</span><span class="tc-count" data-count="developer"></span></button>
                    <button class="tools-cat" data-cat="text"><span class="tc-ic">📝</span><span class="tc-name">Text</span><span class="tc-count" data-count="text"></span></button>
                    <button class="tools-cat" data-cat="utilities"><span class="tc-ic">🧰</span><span class="tc-name">Utilities</span><span class="tc-count" data-count="utilities"></span></button>
                    <button class="tools-cat" data-cat="offensive"><span class="tc-ic">🎯</span><span class="tc-name">Offensive &amp; Recon</span><span class="tc-count" data-count="offensive"></span></button>
                    <button class="tools-cat" data-cat="osint"><span class="tc-ic">🔍</span><span class="tc-name">OSINT &amp; Research</span><span class="tc-count" data-count="osint"></span></button>
                    <button class="tools-cat" data-cat="network"><span class="tc-ic">🧪</span><span class="tc-name">Network &amp; Web</span><span class="tc-count" data-count="network"></span></button>
                    <button class="tools-cat" data-cat="data"><span class="tc-ic">🏷</span><span class="tc-name">Encoding &amp; Data</span><span class="tc-count" data-count="data"></span></button>
                </nav>
            </div>
        </aside>
        <main class="tools-main" id="js-main">

    <div data-cat-sec="security" class="tool-sec">
    <h2 class="h6 mb-3 reveal in-view" style="border-bottom:1px solid var(--line);padding-bottom:.5rem;">🛠️ Code & Security</h2>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-4">

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🔐 Hash Generator</h3>
                <p class="text-secondary small flex-grow-1">MD5 / SHA-1 / SHA-2 (224–512) / SHA-3 / CRC32 / HMAC plus a "what hash is this?" identifier — and file checksums.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="hash/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🔏 JWT Decoder</h3>
                <p class="text-secondary small flex-grow-1">Decode any JWT header, payload & signature, verify HS256/384/512 against a secret, and read expiry claims.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="jwt-decoder/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🔎 Regex Tester</h3>
                <p class="text-secondary small flex-grow-1">Test live regular expressions against text with match highlighting, capture-group details and a flag reference.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="regex-tester/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🕶 HTML &amp; JS Obfuscator</h3>
                <p class="text-secondary small flex-grow-1">Hardened in-browser obfuscator with 6 strength levels — minify, string/number encoding, XOR decoder pool, safe variable renaming, VM wrapper, junk & anti-debug.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="js-obfuscator/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🧙 Lua Obfuscator</h3>
                <p class="text-secondary small flex-grow-1">Multi-layer Lua protection: renames, string & number encoding, junk injection, an anti-hook / anti-env guard and a load()-based VM with 6 strength levels (Level 5 hides the VM itself as char codes; Level 6 adds dual keys, triple-split bytes and polymorphic junk).</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="lua-obfuscator/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🧪 Lua Sandbox</h3>
                <p class="text-secondary small flex-grow-1">Run untrusted Lua scripts safely in your browser via Fengari — with a built-in timer jail, memory cap and protected <code>os</code>/<code>io</code>/<code>require</code>s so scripts can't freeze the tab or touch the filesystem.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="lua-sandbox/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🎲 UID Generator</h3>
                <p class="text-secondary small flex-grow-1">UUID v4, hex tokens, base64 secret keys, strong passwords and memorable passphrases.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="uid/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🏛 Classic Ciphers</h3>
                <p class="text-secondary small flex-grow-1">Vigenère, Beaufort, Atbash, Affine, Caesar and Rail Fence — encrypt/decrypt with keys. All in your browser.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="ciphers/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">📜 CVE Lookup</h3>
                <p class="text-secondary small flex-grow-1">Look up a specific CVE by ID (CVSS score, references, affected products) or keyword-search the NVD for vendors &amp; products.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="cve-lookup/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🎭 Email Reputation</h3>
                <p class="text-secondary small flex-grow-1">Check if an email address is linked to breaches, spam, disposable services or malicious activity via emailrep.io.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="email-reputation/">Open</a>
            </div></div>
        </div>

    </div>
    </div>

    <div data-cat-sec="developer" class="tool-sec">
    <h2 class="h6 mb-3 reveal in-view" style="border-bottom:1px solid var(--line);padding-bottom:.5rem;">💻 Developer</h2>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-4">

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🧮 JSON Formatter</h3>
                <p class="text-secondary small flex-grow-1">Pretty-print minified JSON with clean indentation, minify it back and validate with instant syntax errors.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="json-formatter/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🔢 Base64 Decoder</h3>
                <p class="text-secondary small flex-grow-1">Encode and decode Base64 both directions with UTF-8 support — perfect for data URIs, tokens and payloads.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="base64-decoder/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🌐 Subnet Calculator</h3>
                <p class="text-secondary small flex-grow-1">Network, broadcast, usable hosts, CIDR, masks and binary layout — with explanations.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="subnet-calculator/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">📦 Hex Dump</h3>
                <p class="text-secondary small flex-grow-1">Classic hexdump of any text with offsets, hex and ASCII columns.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="hex-dump/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🕒 Cron Parser</h3>
                <p class="text-secondary small flex-grow-1">Turn any cron expression into plain English plus the next times it will fire.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="cron-parser/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🎨 Color Converter</h3>
                <p class="text-secondary small flex-grow-1">Convert between HEX, RGB, HSL and CMYK with a color picker and WCAG contrast hints.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="color-converter/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🧮 Developer Toolkit</h3>
                <p class="text-secondary small flex-grow-1">Regex tester, JSON formatter, timestamp converter, base64, URL encoding, character inspector and more.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="dev/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🐚 Lua Runner</h3>
                <p class="text-secondary small flex-grow-1">Run Lua 100% in your browser with Fengari — console output and line-numbered errors, nothing sent to the server.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="lua/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🎯 Hash Cracker</h3>
                <p class="text-secondary small flex-grow-1">Recover weak MD5 / SHA / NTLM / CRC32 inputs — common-password rules, dates, digits and keyboard-walk attacks. Multiple hashes at once, time-limited on the server.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="crack/">Open</a>
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
                <h3 class="h6 mb-2">⚡ HTTP Request Builder</h3>
                <p class="text-secondary small flex-grow-1">Compose a request visually and generate ready-to-run curl, JavaScript fetch and Python requests code.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="reqbuild/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🪝 Discord Webhook Sender</h3>
                <p class="text-secondary small flex-grow-1">Send rich embeds and plain messages to any Discord webhook URL — colour, author, footer, fields, timestamps and images, routed through our proxy.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="webhook/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">⚡ Discord Webhook Spammer</h3>
                <p class="text-secondary small flex-grow-1">Send multiple messages to a webhook to test rate limits and flood behavior — sequential delivery with full response logging.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="webhook-spam/">Open</a>
                <span class="text-secondary small mt-2">⚠️ Educational use only — test your own webhooks.</span>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🗑 Discord Webhook Deleter</h3>
                <p class="text-secondary small flex-grow-1">Permanently delete a Discord webhook by URL — irreversible, sends DELETE to Discord's API via our proxy.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="webhook-delete/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🔗 Link Spoof / Obfuscator</h3>
                <p class="text-secondary small flex-grow-1">Disguise URLs through percent encoding, Unicode homoglyphs, @ tricks, IP decimal and cascading through multiple shorteners (TinyURL, is.gd, v.gd, da.gd, Shrtr, zip1.io, KevBin).</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="link-spoof/">Open</a>
                <span class="text-secondary small mt-2">⚠️ For phishing awareness &amp; security education.</span>
            </div></div>
        </div>

    </div>
    </div>

    <div data-cat-sec="text" class="tool-sec">
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
                <h3 class="h6 mb-2">🎨 ASCII Art Generator</h3>
                <p class="text-secondary small flex-grow-1">328 classic FIGlet fonts (Standard, Slant, Banner, Doom and more) rendered on the server — or convert any image into block ASCII art in your browser.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="asciiart/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🎲 Random Generator</h3>
                <p class="text-secondary small flex-grow-1">Coin flips, dice, random numbers, strings, pick-one choices and 6/49 lottery picks using crypto-grade randomness.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="random/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🔤 Unicode Inspector</h3>
                <p class="text-secondary small flex-grow-1">Break any text down into codepoints — hex/dec/BMP/supplementary, scripts and char names, with a live char-skimmer for quick lookup.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="unicode/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">⇄ Diff Checker</h3>
                <p class="text-secondary small flex-grow-1">Line-by-line diff between two texts with add/remove highlights, stats and copyable unified-style output.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="diff/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🏛 Roman Numerals</h3>
                <p class="text-secondary small flex-grow-1">Convert decimal numbers to Roman numerals or parse Roman numerals back to Arabic — with validity checking.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="roman/">Open</a>
            </div></div>
        </div>

    </div>
    </div>

    <div data-cat-sec="utilities" class="tool-sec">
    <h2 class="h6 mb-3 reveal in-view" style="border-bottom:1px solid var(--line);padding-bottom:.5rem;">🧰 Handy Utilities</h2>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-4">

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">⛏ Bitcoin Address Lookup</h3>
                <p class="text-secondary small flex-grow-1">Balance, total received/sent and transaction count for any Bitcoin address via the blockchain.info public API.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="btc-lookup/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🔑 Password Generator</h3>
                <p class="text-secondary small flex-grow-1">Crypto-grade random passwords with options, bulk generate and live entropy estimates.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="password/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">📚 Wordlist Generator</h3>
                <p class="text-secondary small flex-grow-1">Build password/wordlist candidates like real cracking tools: 1337 speak, case changes, keyboard walks, date patterns, separators and multi-word combos from an extra word pool. See how a dictionary attack would hit your own passwords.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="wordlist/">Open</a>
                <span class="text-secondary small mt-2">⚠️ For testing your own accounts & education.</span>
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
                <h3 class="h6 mb-2">🛠️ HTTP Headers Inspector</h3>
                <p class="text-secondary small flex-grow-1">View request & response headers for any public URL, with redirect hop tracing and TLS certificate details.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="headers/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🔢 Numeral Converter</h3>
                <p class="text-secondary small flex-grow-1">Live binary / octal / decimal / hexadecimal conversion as you type.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="numerals/">Open</a>
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

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">📸 Photo Metadata</h3>
                <p class="text-secondary small flex-grow-1">Extract EXIF, IPTC, XMP and ICC data from photos — camera model, lens, GPS coordinates, date taken, shutter speed, aperture and more.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="photo-meta/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🧹 Photo Metadata Stripper</h3>
                <p class="text-secondary small flex-grow-1">Strip GPS coordinates, camera info, timestamps and every hidden EXIF/XMP block before sharing photos — 100% in your browser, nothing uploaded.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="metadata-strip/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🪪 Browser Fingerprint Test</h3>
                <p class="text-secondary small flex-grow-1">Compute the same device fingerprint tracking scripts see — canvas, WebGL, fonts, audio, timezone, screen & hardware — no data ever leaves this tab.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="browser-fingerprint/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🎯 Percentage Calculator</h3>
                <p class="text-secondary small flex-grow-1">Find what percent X is of Y, add/subtract percentages, and show the change from A to B with step-by-step working.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="percentage/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🎂 Age Calculator</h3>
                <p class="text-secondary small flex-grow-1">Exact age in years/months/days between any two dates, plus the next birthday countdown and weekday milestones.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="age-calc/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🗺 XPath Tester</h3>
                <p class="text-secondary small flex-grow-1">Run XPath 1.0 queries against sample HTML with a live result count, node snapshots and namespace-aware evaluation.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="xpath/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🖱 Sensitivity Converter</h3>
                <p class="text-secondary small flex-grow-1">Convert mouse sensitivity between games by matching the 360&deg;-turn distance, or target a specific cm/360 and eDPI — editable yaw values including approximate Roblox / Minecraft mappings. All in-browser.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="sens-converter/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🎮 Free Games</h3>
                <p class="text-secondary small flex-grow-1">A curated lineup of open-licensed / free-to-play HTML5 games at their official homes — sports "bros" series, arcade classics, puzzles and tycoons. Nothing hosted here, everything opens in a new tab.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="games/">Open</a>
            </div></div>
        </div>

    </div>
    </div>

    <div data-cat-sec="offensive" class="tool-sec">
    <h2 class="h6 mb-3 reveal in-view" style="border-bottom:1px solid var(--line);padding-bottom:.5rem;">🎯 Offensive Security &amp; Recon</h2>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-4">

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🔓 TCP Port Scanner</h3>
                <p class="text-secondary small flex-grow-1">Lightweight TCP connect() probe of common ports on any public host you own — flagged open/closed/filtered. Rate-limited, single connection per port.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="portscan/">Open</a>
                <span class="text-secondary small mt-2">⚠️ Only scan hosts you own or have permission to test.</span>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">📡 Subdomain Finder</h3>
                <p class="text-secondary small flex-grow-1">Passive subdomain enumeration via Certificate Transparency logs (crt.sh) — every TLS cert ever issued for a domain leaks its subdomains. No contact with the target.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="subenum/">Open</a>
                <span class="text-secondary small mt-2">⚠️ Passive OSINT — legal use only.</span>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🔄 Reverse IP Lookup</h3>
                <p class="text-secondary small flex-grow-1">Which domains point at this IP? Passive public-DNS queries (HackerTarget) — inventory shared hosting you manage before a pen-test.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="revip/">Open</a>
                <span class="text-secondary small mt-2">⚠️ Legal use only.</span>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🌍 ASN / BGP Lookup</h3>
                <p class="text-secondary small flex-grow-1">Find the autonomous system (network operator) behind an IP or AS number via RIPEStat + ipwho.is — map who really operates a network.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="asnintel/">Open</a>
                <span class="text-secondary small mt-2">⚠️ Legal use only.</span>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🧅 Tor Exit Node Checker</h3>
                <p class="text-secondary small flex-grow-1">Check if an IP is currently a Tor exit node against the live Tor Project exit lists plus the dnstor.im DNS blacklist.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="tor-check/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🗂 MAC Vendor Lookup</h3>
                <p class="text-secondary small flex-grow-1">Map a MAC address's OUI to its manufacturer — identify the mystery device on your network (is that a Raspberry Pi or an intruder?).</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="macfind/">Open</a>
            </div></div>
        </div>

    </div>
    </div>

    <div data-cat-sec="osint" class="tool-sec">
    <h2 class="h6 mb-3 reveal in-view" style="border-bottom:1px solid var(--line);padding-bottom:.5rem;">🔍 OSINT &amp; Research</h2>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-4">

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🔍 OSINT Toolkit</h3>
                <p class="text-secondary small flex-grow-1">Live lookups (IP, WHOIS, certs, email) plus a searchable directory of all 1,166 OSINT Framework tools — badges, pricing and live status included.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="osint/">Open</a>
                <span class="text-secondary small mt-2">⚠️ For legal research & security testing only.</span>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">📼 Wayback Machine</h3>
                <p class="text-secondary small flex-grow-1">Find archived snapshots of any page in the Internet Archive — latest capture date and full snapshot history with direct links.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="wayback/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🌐 Web Search</h3>
                <p class="text-secondary small flex-grow-1">Metasearch — queries 6 independent engines at once (DuckDuckGo, Mojeek, Wikipedia, GitHub, DDG Answers, SearXNG), fetches them in parallel from this server, dedupes and combines all results. No tracking, no account.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="websearch/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🌑 Dark Web Search</h3>
                <p class="text-secondary small flex-grow-1">Gateway to the Tor network's search engines (Torch, DuckDuckGo Onion, Ahmia) — your query goes straight from your Tor Browser to the onion index.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="darkweb/">Open</a>
                <span class="text-secondary small mt-2">⚠️ Requires Tor Browser — .onion links don't open in normal browsers.</span>
            </div></div>
        </div>

    </div>
    </div>

    <div data-cat-sec="network" class="tool-sec">
    <h2 class="h6 mb-3 reveal in-view" style="border-bottom:1px solid var(--line);padding-bottom:.5rem;">🧪 Network &amp; Web Analysis</h2>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-4">

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🛰 SSL Certificate Analyzer</h3>
                <p class="text-secondary small flex-grow-1">Fetch and inspect any site's TLS certificate — SANs, issuer, key size, validity, chain hierarchy, HTTP/2 and HSTS.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="ssl-cert/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">📡 DNS over HTTPS</h3>
                <p class="text-secondary small flex-grow-1">Query DNS via encrypted DoH to Cloudflare, Google or Quad9 — A, AAAA, MX, TXT, CAA, DNSSEC — plus a local dig comparison.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="doh/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🏁 Traceroute Visualizer</h3>
                <p class="text-secondary small flex-grow-1">Trace the route packets take to any host with a visual hop map, latency per hop and packet-loss detection.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="traceroute/">Open</a>
                <span class="text-secondary small mt-2">⚠️ Legal use only.</span>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🚫 IP Blacklist Checker</h3>
                <p class="text-secondary small flex-grow-1">Check any IP against 25+ DNSBL spam databases — find out if it's flagged by Spamhaus, SpamCop, SORBS, Barracuda and more.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="ip-blacklist/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">📧 Email Header Analyzer</h3>
                <p class="text-secondary small flex-grow-1">Paste any email's raw headers to trace its routing path, decode SPF/DKIM/DMARC verdicts and spot malicious indicators.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="email-header/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">✅ Email Validator</h3>
                <p class="text-secondary small flex-grow-1">Bulk email validation — syntax, MX records, disposable-domain detection, free-provider and role-account flags with a 0–100 score.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="email-validate/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🌐 WHOIS Lookup</h3>
                <p class="text-secondary small flex-grow-1">Pull raw WHOIS for any domain or IP over port 43 — registrar, dates, name servers, status codes and DNSSEC — privacy-safe.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="whois/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🛰 HTTP Status Checker</h3>
                <p class="text-secondary small flex-grow-1">Batch-check up to 50 URLs' status codes, redirect chains, timings and server headers with sortable results and CSV export.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="http-status/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🌐 CORS Tester</h3>
                <p class="text-secondary small flex-grow-1">Send cross-origin requests with custom Origin and methods, then audit the CORS headers and security posture of any API.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="cors-tester/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🛡 CSP Analyzer</h3>
                <p class="text-secondary small flex-grow-1">Parse and score any Content-Security-Policy — spot unsafe-inline, wildcards and missing directives, then get a hardened example.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="csp-analyzer/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🛡 Security Headers Checker</h3>
                <p class="text-secondary small flex-grow-1">Grade any site's HTTP security headers (CSP, HSTS, XFO, COOP/CORP & more) against a 100-point baseline with copyable fix tips.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="security-headers/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🗂 RDAP Domain Lookup</h3>
                <p class="text-secondary small flex-grow-1">Registration dates, registrar, DNSSEC, nameservers and domain status codes straight from the registry RDAP servers.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="rdap-domain/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">📍 GeoIP Lookup</h3>
                <p class="text-secondary small flex-grow-1">Location, ISP, ASN and threat flags (proxy/VPN/Tor) for any IPv4/IPv6 address — or check your own public IP.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="geoip/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🪤 Honeypot Detector</h3>
                <p class="text-secondary small flex-grow-1">Scan a site for hidden form traps, WAF fingerprints, honeypot paths and tracking beacons (GA, Meta Pixel, etc.) with a 0–100 score.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="honeypot/">Open</a>
                <span class="text-secondary small mt-2">⚠️ Legal use only.</span>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">📦 Breach Checker</h3>
                <p class="text-secondary small flex-grow-1">Check if an email, username or password has appeared in known data breaches and password dumps — k-anonymity password check plus optional HIBP account lookup.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="breach-check/">Open</a>
                <span class="text-secondary small mt-2">🔒 Your own account data only.</span>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🦠 Stealer Checker</h3>
                <p class="text-secondary small flex-grow-1">Search a downloaded infostealer log (RedLine, Raccoon, Lumma...) in your browser — nothing is ever uploaded. Plus public stealer-log lookups by email / username / IP / domain via the Hudson Rock free index, log sniffing, credential extraction and HIBP password checks.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="stealer-check/">Open</a>
                <span class="text-secondary small mt-2">🔒 100% local log search.</span>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🧾 Receipt Generator</h3>
                <p class="text-secondary small flex-grow-1">Create a realistic Walmart-style grocery receipt with itemized lines, taxes, card payment and a scannable barcode — fully editable, downloadable as PNG.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="receipt/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🛰 Website Tech Stack Detector</h3>
                <p class="text-secondary small flex-grow-1">Wappalyzer-style scan of any site — frameworks, CMS, JS libraries, CDN, analytics pixels and server headers, with HTML signature matching.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="tech-stack/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🩻 Page Cloner</h3>
                <p class="text-secondary small flex-grow-1">See how easily a real site can be impersonated — download a byte-identical clone of any public page (base-href mirror) with a sandboxed preview. Form actions are untouched and no passwords are ever captured.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="page-clone/">Open</a>
                <span class="text-secondary small mt-2">⚠️ For phishing awareness &amp; authorized testing only — never host cloned login pages.</span>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🧨 Link Expander</h3>
                <p class="text-secondary small flex-grow-1">Follow any suspicious short URL hop-by-hop — reveals every redirect, the real final destination, servers and resolved IPs with loop protection.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="link-expander/">Open</a>
                <span class="text-secondary small mt-2">⚠️ For phishing awareness &amp; link analysis.</span>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">📍 WebRTC Leak Test</h3>
                <p class="text-secondary small flex-grow-1">Find out if your browser leaks your real IP through WebRTC/STUN — with per-IP cards, raw candidate output and fixes.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="webrtc/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🪟 Site Viewer</h3>
                <p class="text-secondary small flex-grow-1">Sandboxed browsing proxy — load any public page into a script-blocked iframe, with links rewritten to keep navigating inside the viewer. Private-IP ranges blocked.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="site-viewer/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🫳 Link Bypasser</h3>
                <p class="text-secondary small flex-grow-1">Walk the full chain the way a browser would — HTTP redirects, meta-refresh and JavaScript location "doors" — to reveal a link's true destination with every step explained. Educational.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="link-bypass/">Open</a>
                <span class="text-secondary small mt-2">⚠️ For link-wall &amp; phishing education — anti-bot walls stop here by design.</span>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">📈 Link Tracker</h3>
                <p class="text-secondary small flex-grow-1">Create a redirect link that logs every click — IP, approximate location &amp; ISP, browser, OS, device, screen, timezone and a fingerprint beacon — with a live analytics panel. All on KevBin's built-in tracker system.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="link-tracker/">Open</a>
                <span class="text-secondary small mt-2">⚠️ Only track links you own or are authorized to monitor.</span>
            </div></div>
        </div>

    </div>
    </div>

    <div data-cat-sec="data" class="tool-sec">
    <h2 class="h6 mb-3 reveal in-view" style="border-bottom:1px solid var(--line);padding-bottom:.5rem;">🏷 Encoding, Data &amp; Generators</h2>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-4">

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🔤 Base32 / Base58 / Base85</h3>
                <p class="text-secondary small flex-grow-1">Encode and decode 6 variants — RFC Base32/Hex, Bitcoin & Ripple Base58, Ascii85 and Z85 — with hex views.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="base-n/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🛰 File Type Detector</h3>
                <p class="text-secondary small flex-grow-1">Drag in any file (or paste hex) to identify its type by magic bytes — 200+ signatures across images, archives, executables and more.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="file-type/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🧩 YAML / TOML / JSON</h3>
                <p class="text-secondary small flex-grow-1">Convert between YAML, TOML and JSON both directions with live feedback and copy-ready output panels.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="yaml-toml/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">📝 SQL Formatter</h3>
                <p class="text-secondary small flex-grow-1">Beautify or minify any SQL query with keyword highlighting, proper indentation and copy-ready output.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="sql-formatter/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🎨 Markdown Live Preview</h3>
                <p class="text-secondary small flex-grow-1">Write Markdown on the left, watch it render on the right — full syntax support plus a formatting toolbar and copy buttons.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="markdown/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">⚒ CSS / JS Minifier</h3>
                <p class="text-secondary small flex-grow-1">Beautify or minify JavaScript and CSS with byte-saving stats, safe comment/string handling and copy-ready output.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="code-fmt/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🔐 JWT Generator</h3>
                <p class="text-secondary small flex-grow-1">Create, decode and verify JWTs with HS256/384/512 — preset payloads, live expiry badges and signature checking.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="jwt-gen/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🧮 Password Entropy</h3>
                <p class="text-secondary small flex-grow-1">Real-time Shannon entropy, crack-time estimates across 5 attack tiers, pattern detection and a crypto-random generator.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="entropy/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">⏰ Cron Job Builder</h3>
                <p class="text-secondary small flex-grow-1">Visually build cron expressions from dropdowns and presets — human-readable description, validation and next 10 run times.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="cron-builder/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🧾 Barcode Generator</h3>
                <p class="text-secondary small flex-grow-1">Generate Code128, Code39, EAN-13, UPC-A, ITF-14 and Codabar barcodes in-browser — download PNG or SVG.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="barcode/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🌐 QR &amp; Emoji Picker</h3>
                <p class="text-secondary small flex-grow-1">Browse 700+ emojis by category with Unicode/HTML/JS escape codes, skin tones and a recently-used tray.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="emoji/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🕵️ Steganography</h3>
                <p class="text-secondary small flex-grow-1">Hide secret messages in PNG images (LSB) with optional password XOR, or reveal messages hidden in images — educational.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="steganography/">Open</a>
                <span class="text-secondary small mt-2">⚠️ For education only.</span>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🍳 Recipe Generator</h3>
                <p class="text-secondary small flex-grow-1">15 recipe templates with editable ingredients, serving scaling, live preview, print-friendly output and Markdown/JSON export.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="recipe/">Open</a>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h3 class="h6 mb-2">🪝 Webhook Tester</h3>
                <p class="text-secondary small flex-grow-1">Get a unique URL that captures every incoming request — inspect headers, body and query params live for your own webhook debugging.</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="webhook-tester/">Open</a>
            </div></div>
        </div>

    </div>
    </div>
    </div>
</div>
<?php page_footer(); ?>
<style>
.tools-layout{display:flex;gap:1.5rem;align-items:flex-start;}
.tools-side{flex:0 0 250px;max-width:250px;position:sticky;top:70px;}
.tools-side-inner{background:var(--panel);border:1px solid var(--line);border-radius:18px;padding:.9rem;}
.tools-nav{display:flex;flex-direction:column;gap:2px;}
.tools-cat{display:flex;align-items:center;gap:.55rem;width:100%;padding:.5rem .7rem;border-radius:12px;border:1px solid transparent;background:transparent;color:var(--text);font-size:.9rem;font-weight:500;text-align:left;cursor:pointer;transition:background .2s ease,color .2s ease,transform .15s ease,border-color .2s ease;}
.tools-cat:hover{background:rgba(255,255,255,.06);transform:translateX(3px);}
.tools-cat.active{background:linear-gradient(135deg,rgba(88,101,242,.18),rgba(145,70,255,.14));border-color:rgba(88,101,242,.4);color:#fff;font-weight:600;}
.tools-cat.active .tc-ic{transform:scale(1.1);}
.tc-ic{width:20px;text-align:center;font-size:1rem;transition:transform .2s ease;}
.tc-name{flex:1;overflow-wrap:anywhere;}
.tc-count{background:rgba(255,255,255,.08);border-radius:99px;padding:0 8px;font-size:.72rem;color:var(--dim);min-width:26px;text-align:center;}
.tools-cat.active .tc-count{background:rgba(88,101,242,.35);color:#fff;}
.tools-main{flex:1;min-width:0;}
@keyframes toolIn{from{opacity:0;transform:translateY(14px) scale(.99);}to{opacity:1;transform:none;}}
.tool-sec{animation:toolIn .4s cubic-bezier(.22,1,.36,1);}
@media (max-width:767.98px){
    .tools-layout{flex-direction:column;}
    .tools-side{flex:none;max-width:none;width:100%;position:static;order:2;}
    .tools-side-inner{padding:.6rem;}
    .tools-nav{flex-direction:row;flex-wrap:wrap;gap:4px;}
    .tools-cat{width:auto;flex:1 1 auto;min-width:130px;}
    .tools-side{order:1;}
    .tools-main{order:2;}
}
</style>
<script>
(function(){
    var search=document.getElementById('js-search');
    var tabs=document.getElementById('js-tabs');
    var cards=document.querySelectorAll('.tool-sec');
    var currentCat='all';

    // category counts
    cards.forEach(function(sec){
        var n=sec.querySelectorAll('.row > .col').length;
        var cnt=document.querySelector('.tc-count[data-count="'+sec.dataset.catSec+'"]');
        var allCnt=document.querySelector('.tc-count[data-count="all"]');
        if(cnt) cnt.textContent=n;
        if(allCnt) allCnt.textContent=(parseInt(allCnt.textContent||'0',10)+n);
    });

    function apply(){
        var q=(search.value||'').trim().toLowerCase();
        var shown=0;
        cards.forEach(function(sec){
            var cardEls=sec.querySelectorAll('.row > .col');
            var head=sec.querySelector('h2');
            sec.style.display=(currentCat==='all'||sec.dataset.catSec===currentCat)?'':'none';
            if(sec.style.display!=='none'){
                cardEls.forEach(function(card){
                    var text=(card.textContent||'').toLowerCase();
                    card.style.display=(q===''||text.indexOf(q)!==-1)?'':'none';
                });
                var remaining=[].filter.call(cardEls,function(c){return c.style.display!=='none';});
                if(head) head.style.display=remaining.length?'':'none';
                if(remaining.length) shown+=remaining.length;
                // stagger reveal
                remaining.forEach(function(c, i){
                    if(!c.classList.contains('in-view')) c.classList.add('in-view');
                });
                if(head&&remaining.length) head.classList.add('in-view');
                // retrigger animation for smoothness
                sec.style.animation='none'; void sec.offsetWidth; sec.style.animation='';
            }
        });
        var empty=document.getElementById('js-empty');
        if(!empty){
            empty=document.createElement('div');
            empty.id='js-empty';
            empty.className='text-secondary small mt-4';
            empty.textContent='No tools match your search. Try a different keyword.';
            document.getElementById('js-main').appendChild(empty);
        }
        empty.style.display=shown?'none':'';
    }

    tabs.addEventListener('click',function(e){
        var btn=e.target.closest('.tools-cat');
        if(!btn) return;
        tabs.querySelectorAll('.tools-cat').forEach(function(b){b.classList.remove('active');});
        btn.classList.add('active');
        currentCat=btn.dataset.cat;
        apply();
        window.scrollTo({top:document.querySelector('.tools-layout').getBoundingClientRect().top+window.scrollY-80,behavior:'smooth'});
    });
    search.addEventListener('input',apply);
})();
</script>