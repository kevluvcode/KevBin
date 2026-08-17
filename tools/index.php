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

    </div>

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

    </div>

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
                <h3 class="h6 mb-2">🗂 MAC Vendor Lookup</h3>
                <p class="text-secondary small flex-grow-1">Map a MAC address's OUI to its manufacturer — identify the mystery device on your network (is that a Raspberry Pi or an intruder?).</p>
                <a class="btn btn-outline-light btn-sm mt-2" href="macfind/">Open</a>
            </div></div>
        </div>

    </div>

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
<?php page_footer(); ?>