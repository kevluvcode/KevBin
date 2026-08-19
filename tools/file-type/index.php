<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$GLOBALS['_meta'] = [
    'description' => 'Free online file type detector using magic bytes. Identify any file format by reading the first bytes of a file or by pasting hex manually. Supports over 150 file types including images, documents, archives, audio, video, executables and more.',
    'keywords' => 'file type detector, magic bytes, file identifier, file signature, MIME type, hex header, file format',
];
page_header('File Type Detector — Magic Bytes');
?>
<style>
.drop-zone{border:2px dashed var(--line,#555);border-radius:12px;padding:3rem 1rem;text-align:center;transition:background .2s,border-color .2s;cursor:pointer;position:relative;}
.drop-zone.dragover{background:rgba(13,110,253,.12);border-color:#0d6efd;}
.drop-zone input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;}
.hex-dump{font-family:'JetBrains Mono',monospace;font-size:.75rem;line-height:1.6;background:rgba(0,0,0,.25);border-radius:8px;padding:.75rem 1rem;overflow-x:auto;white-space:pre;border:1px solid var(--line,#444);}
.hex-dump .offset{color:#888;}
.hex-dump .hex-byte{color:#79c0ff;}
.hex-dump .hex-space{color:#555;}
.hex-dump .ascii-printable{color:#a5d6a7;}
.hex-dump .ascii-dot{color:#666;}
.result-row{display:flex;gap:.5rem;align-items:baseline;padding:.35rem 0;border-bottom:1px solid rgba(255,255,255,.06);}
.result-row:last-child{border-bottom:none;}
.result-label{min-width:140px;color:#888;font-size:.82rem;flex-shrink:0;}
.result-value{font-size:.85rem;word-break:break-all;}
.badge-type{font-size:.95rem;padding:.4em .7em;}
.magic-bytes-display{font-family:'JetBrains Mono',monospace;font-size:.85rem;background:rgba(0,0,0,.3);border-radius:6px;padding:.5rem .75rem;display:inline-block;letter-spacing:.5px;border:1px solid var(--line,#444);}
.confidence-exact{color:#2ea043;}
.confidence-primary{color:#58a6ff;}
.confidence-heuristic{color:#d29922;}
</style>
<div class="container" style="max-width:920px;">
    <h1 class="h4 mb-1 reveal in-view">File Type Detector (Magic Bytes)</h1>
    <p class="text-secondary mb-4 reveal in-view">Identify any file by its magic bytes. Drag &amp; drop a file, pick one with the button, or paste raw hex. The first 32 bytes are matched against a database of over 150 known file signatures — all processing happens in your browser.</p>

    <div class="alert alert-secondary reveal in-view">
        <strong>What are magic bytes?</strong> Nearly every file format starts with a fixed sequence of bytes that identifies it — a "magic number." For example, PNG files always begin with <code>89 50 4E 47</code> (‰PNG). This tool reads the first 32 bytes and compares them against a known database to tell you the file type, MIME type and suggested extension.
    </div>

    <div class="card reveal in-view">
        <div class="card-body">
            <div id="drop-zone" class="drop-zone mb-3">
                <input type="file" id="ft-file" onchange="handleFile(this.files)">
                <div class="mb-2" style="font-size:2rem;opacity:.6;">&#128194;</div>
                <div class="text-secondary mb-1">Drag &amp; drop a file here</div>
                <div class="text-secondary small">or click to browse</div>
            </div>

            <div class="d-flex align-items-center gap-2 mb-3">
                <hr class="flex-grow-1" style="border-color:var(--line,#444);">
                <span class="text-secondary small text-nowrap">or paste hex bytes</span>
                <hr class="flex-grow-1" style="border-color:var(--line,#444);">
            </div>

            <div class="mb-2">
                <input id="ft-hex-in" class="form-control mb-2" style="font-family:'JetBrains Mono',monospace;font-size:.85rem;" placeholder="e.g. 89 50 4E 47 0D 0A 1A 0A or 89504E470D0A1A0A" oninput="detectFromHex()">
                <div class="d-flex gap-2">
                    <button class="btn btn-primary btn-sm" onclick="detectFromHex()">Detect</button>
                    <button class="btn btn-outline-light btn-sm" onclick="clearAll()">Clear</button>
                </div>
            </div>
        </div>
    </div>

    <div id="ft-results" class="card mt-3" style="display:none;">
        <div class="card-body">
            <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
                <span id="ft-badge" class="badge bg-primary badge-type"></span>
                <span id="ft-mime" class="text-secondary small"></span>
            </div>
            <div id="ft-file-info"></div>
            <div id="ft-magic-hex" class="mt-3"></div>
            <div id="ft-hex-dump-label" class="text-secondary small mb-1 mt-3">Hex dump of header:</div>
            <div id="ft-hex-dump" class="hex-dump"></div>
        </div>
    </div>

    <div class="card mt-4 reveal in-view">
        <div class="card-body">
            <h2 class="h6 mb-2">Supported formats</h2>
            <p class="text-secondary small mb-2">This tool recognizes signatures for 150+ file types across these categories:</p>
            <div class="d-flex flex-wrap gap-1" id="ft-category-tags"></div>
        </div>
    </div>
</div>

<script>
(function(){
var DB=[
{sig:"89504E470D0A1A0A",ext:"png",mime:"image/png",name:"PNG Image",cat:"Images",conf:"exact",offset:0},
{sig:"FFD8FF",ext:"jpg",mime:"image/jpeg",name:"JPEG Image",cat:"Images",conf:"exact",offset:0},
{sig:"FFD8FFDB",ext:"jpg",mime:"image/jpeg",name:"JPEG Image (DQT)",cat:"Images",conf:"exact",offset:0},
{sig:"FFD8FFEE",ext:"jpg",mime:"image/jpeg",name:"JPEG Image (JFIF)",cat:"Images",conf:"exact",offset:0},
{sig:"FFD8FFE0",ext:"jpg",mime:"image/jpeg",name:"JPEG Image (JFIF)",cat:"Images",conf:"exact",offset:0},
{sig:"FFD8FFE1",ext:"jpg",mime:"image/jpeg",name:"JPEG Image (Exif)",cat:"Images",conf:"exact",offset:0},
{sig:"474946383761",ext:"gif",mime:"image/gif",name:"GIF Image (87a)",cat:"Images",conf:"exact",offset:0},
{sig:"474946383961",ext:"gif",mime:"image/gif",name:"GIF Image (89a)",cat:"Images",conf:"exact",offset:0},
{sig:"424D",ext:"bmp",mime:"image/bmp",name:"Bitmap Image",cat:"Images",conf:"exact",offset:0},
{sig:"49492A00",ext:"tif",mime:"image/tiff",name:"TIFF Image (little-endian)",cat:"Images",conf:"exact",offset:0},
{sig:"4D4D002A",ext:"tif",mime:"image/tiff",name:"TIFF Image (big-endian)",cat:"Images",conf:"exact",offset:0},
{sig:"52494646",ext:"webp",mime:"image/webp",name:"WEBP Image",cat:"Images",conf:"check",offset:0,
 check:function(b){return b.length>=12&&String.fromCharCode(b[8],b[9],b[10],b[11])==="WEBP"}},
{sig:"52494646",ext:"avi",mime:"video/avi",name:"AVI Video",cat:"Video",conf:"check",offset:0,
 check:function(b){return b.length>=12&&(String.fromCharCode(b[8],b[9],b[10],b[11])==="AVI ")}},
{sig:"52494646",ext:"wav",mime:"audio/wav",name:"WAV Audio",cat:"Audio",conf:"check",offset:0,
 check:function(b){return b.length>=12&&(String.fromCharCode(b[8],b[9],b[10],b[11])==="WAVE")}},
{sig:"00000100",ext:"ico",mime:"image/x-icon",name:"ICO Icon",cat:"Images",conf:"exact",offset:0},
{sig:"00000200",ext:"cur",mime:"image/x-cursor",name:"Windows Cursor",cat:"Images",conf:"exact",offset:0},
{sig:"38425053",ext:"psd",mime:"image/vnd.adobe.photoshop",name:"Photoshop PSD",cat:"Images",conf:"exact",offset:0},
{sig:"3C737667",ext:"svg",mime:"image/svg+xml",name:"SVG Image",cat:"Images",conf:"exact",offset:0},
{sig:"3C3F786D6C",ext:"svg",mime:"image/svg+xml",name:"SVG Image (XML decl)",cat:"Images",conf:"check",offset:0,
 check:function(b){var s=shortStr(b);return s.indexOf("<svg")!==-1}},
{sig:"6674797068656963",ext:"heic",mime:"image/heic",name:"HEIC Image",cat:"Images",conf:"exact",offset:4},
{sig:"667479706D696631",ext:"heif",mime:"image/heif",name:"HEIF Image",cat:"Images",conf:"exact",offset:4},
{sig:"6674797061766966",ext:"avif",mime:"image/avif",name:"AVIF Image",cat:"Images",conf:"exact",offset:4},
{sig:"667479706A707820",ext:"jpx",mime:"image/jpx",name:"JPEG 2000",cat:"Images",conf:"exact",offset:4},
{sig:"0000000C6A584C20",ext:"jxl",mime:"image/jxl",name:"JPEG XL",cat:"Images",conf:"exact",offset:0},
{sig:"667479706D696372",ext:"heic",mime:"image/heic",name:"HEIC Sequence",cat:"Images",conf:"exact",offset:4},
{sig:"FFD0FFD1",ext:"jpg",mime:"image/jpeg",name:"JPEG Raw",cat:"Images",conf:"exact",offset:0},
{sig:"25504446",ext:"pdf",mime:"application/pdf",name:"PDF Document",cat:"Documents",conf:"exact",offset:0},
{sig:"504B0304",ext:"zip",mime:"application/zip",name:"ZIP Archive",cat:"Archives",conf:"check",offset:0,
 check:function(b){return !isOOXML(b)}},
{sig:"504B0304",ext:"docx",mime:"application/vnd.openxmlformats-officedocument.wordprocessingml.document",name:"Word Document (OOXML)",cat:"Documents",conf:"check",offset:0,
 check:function(b){return isOOXML(b)&&hasEntry(b,"word/")}},
{sig:"504B0304",ext:"xlsx",mime:"application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",name:"Excel Spreadsheet (OOXML)",cat:"Documents",conf:"check",offset:0,
 check:function(b){return isOOXML(b)&&hasEntry(b,"xl/")}},
{sig:"504B0304",ext:"pptx",mime:"application/vnd.openxmlformats-officedocument.presentationml.presentation",name:"PowerPoint (OOXML)",cat:"Documents",conf:"check",offset:0,
 check:function(b){return isOOXML(b)&&hasEntry(b,"ppt/")}},
{sig:"504B0304",ext:"odt",mime:"application/vnd.oasis.opendocument.text",name:"OpenDocument Text",cat:"Documents",conf:"check",offset:0,
 check:function(b){return isOOXML(b)&&hasEntry(b,"content.xml")}},
{sig:"504B0304",ext:"ods",mime:"application/vnd.oasis.opendocument.spreadsheet",name:"OpenDocument Spreadsheet",cat:"Documents",conf:"check",offset:0,
 check:function(b){return isOOXML(b)&&hasEntry(b,"content.xml")&&hasEntry(b,"meta.xml")&&hasEntry(b,"styles.xml")}},
{sig:"504B0304",ext:"epub",mime:"application/epub+zip",name:"EPUB eBook",cat:"Documents",conf:"check",offset:0,
 check:function(b){return isOOXML(b)&&hasEntry(b,"mimetype")}},
{sig:"504B0304",ext:"apk",mime:"application/vnd.android.package-archive",name:"Android APK",cat:"Archives",conf:"check",offset:0,
 check:function(b){return isOOXML(b)&&hasEntry(b,"AndroidManifest.xml")}},
{sig:"504B0304",ext:"jar",mime:"application/java-archive",name:"Java Archive",cat:"Archives",conf:"check",offset:0,
 check:function(b){return isOOXML(b)&&hasEntry(b,"META-INF/")}},
{sig:"504B0304",ext:"war",mime:"application/java-archive",name:"Java Web Archive",cat:"Archives",conf:"check",offset:0,
 check:function(b){return isOOXML(b)&&hasEntry(b,"WEB-INF/")}},
{sig:"504B0304",ext:"xar",mime:"application/x-xar",name:"XAR Archive",cat:"Archives",conf:"check",offset:0,
 check:function(b){return isOOXML(b)&&hasEntry(b,"META/")}},
{sig:"D0CF11E0A1B11AE1",ext:"doc",mime:"application/msword",name:"Word Document (OLE)",cat:"Documents",conf:"exact",offset:0},
{sig:"D0CF11E0A1B11AE1",ext:"xls",mime:"application/vnd.ms-excel",name:"Excel Spreadsheet (OLE)",cat:"Documents",conf:"check",offset:0,
 check:function(b){var s=shortStr(b);return s.indexOf("Workbook")!==-1||s.indexOf("VBA")!==-1}},
{sig:"D0CF11E0A1B11AE1",ext:"ppt",mime:"application/vnd.ms-powerpoint",name:"PowerPoint (OLE)",cat:"Documents",conf:"check",offset:0,
 check:function(b){var s=shortStr(b);return s.indexOf("PowerPoint")!==-1}},
{sig:"7B5C72746631",ext:"rtf",mime:"text/rtf",name:"Rich Text Format",cat:"Documents",conf:"exact",offset:0},
{sig:"4F676753",ext:"ogg",mime:"application/ogg",name:"OGG Container",cat:"Audio",conf:"exact",offset:0},
{sig:"4F676753",ext:"ogv",mime:"video/ogg",name:"OGG Video",cat:"Video",conf:"check",offset:0,
 check:function(b){var s=shortStr(b);return s.indexOf("Theora")!==-1}},
{sig:"4F676753",ext:"opus",mime:"audio/opus",name:"Opus Audio (in OGG)",cat:"Audio",conf:"check",offset:0,
 check:function(b){var s=shortStr(b);return s.indexOf("OpusHead")!==-1}},
{sig:"4F676753",ext:"oga",mime:"audio/ogg",name:"OGG Audio",cat:"Audio",conf:"check",offset:0,
 check:function(b){return true}},
{sig:"1F8B",ext:"gz",mime:"application/gzip",name:"Gzip Archive",cat:"Archives",conf:"exact",offset:0},
{sig:"526172211A07",ext:"rar",mime:"application/vnd.rar",name:"RAR Archive",cat:"Archives",conf:"exact",offset:0},
{sig:"526172211A070100",ext:"rar5",mime:"application/vnd.rar",name:"RAR5 Archive",cat:"Archives",conf:"exact",offset:0},
{sig:"377AAFCF",ext:"7z",mime:"application/x-7z-compressed",name:"7-Zip Archive",cat:"Archives",conf:"exact",offset:0},
{sig:"425A68",ext:"bz2",mime:"application/x-bzip2",name:"Bzip2 Archive",cat:"Archives",conf:"exact",offset:0},
{sig:"FD377A585A00",ext:"xz",mime:"application/x-xz",name:"XZ Archive",cat:"Archives",conf:"exact",offset:0},
{sig:"28B52FFD",ext:"zst",mime:"application/zstd",name:"Zstandard Compressed",cat:"Archives",conf:"exact",offset:0},
{sig:"04224D18",ext:"lz4",mime:"application/x-lz4",name:"LZ4 Frame",cat:"Archives",conf:"exact",offset:0},
{sig:"4C5A4950",ext:"lz",mime:"application/x-lzip",name:"LZIP Archive",cat:"Archives",conf:"exact",offset:0},
{sig:"4D534346",ext:"cab",mime:"application/vnd.ms-cab-compressed",name:"Cabinet Archive",cat:"Archives",conf:"exact",offset:0},
{sig:"53514C69746520666F726D6174203300",ext:"db",mime:"application/x-sqlite3",name:"SQLite Database",cat:"Database",conf:"exact",offset:0},
{sig:"000100005374616E64617264204A6574",ext:"mdb",mime:"application/x-msaccess",name:"MS Access Database",cat:"Database",conf:"exact",offset:0},
{sig:"49534344",ext:"adb",mime:"application/x-adb",name:"Android Debug Bridge",cat:"Database",conf:"exact",offset:0},
{sig:"2D2D2D2D2D424547494E20525341",ext:"pem",mime:"application/x-pem-file",name:"RSA Private Key (PEM)",cat:"Crypto",conf:"exact",offset:0},
{sig:"2D2D2D2D2D424547494E205055424C4943204B4559",ext:"pub",mime:"application/x-pem-file",name:"Public Key (PEM)",cat:"Crypto",conf:"exact",offset:0},
{sig:"2D2D2D2D2D4F70656E535348",ext:"pub",mime:"text/plain",name:"OpenSSH Public Key",cat:"Crypto",conf:"exact",offset:0},
{sig:"2D2D2D2D2D484541444552",ext:"pub",mime:"text/plain",name:"PGP Public Key Block",cat:"Crypto",conf:"exact",offset:0},
{sig:"64383A616E6E6F756E6365",ext:"torrent",mime:"application/x-bittorrent",name:"BitTorrent File",cat:"Misc",conf:"exact",offset:0},
{sig:"D4C3B2A1",ext:"pcap",mime:"application/vnd.tcpdump.pcap",name:"PCAP Capture (little-endian)",cat:"Misc",conf:"exact",offset:0},
{sig:"A1B2C3D4",ext:"pcap",mime:"application/vnd.tcpdump.pcap",name:"PCAP Capture (big-endian)",cat:"Misc",conf:"exact",offset:0},
{sig:"0A0D0D0A",ext:"pcapng",mime:"application/vnd.tcpdump.pcap",name:"PCAPNG Capture",cat:"Misc",conf:"exact",offset:0},
{sig:"1A45DFA3",ext:"mkv",mime:"video/x-matroska",name:"Matroska Video (MKV)",cat:"Video",conf:"exact",offset:0},
{sig:"1A45DFA3",ext:"webm",mime:"video/webm",name:"WebM Video",cat:"Video",conf:"check",offset:0,
 check:function(b){var s=shortStr(b);return s.indexOf("webm")!==-1}},
{sig:"66747970",ext:"mp4",mime:"video/mp4",name:"MPEG-4 Video",cat:"Video",conf:"check",offset:0,
 check:function(b){var s=shortStr(b,4);return s.indexOf("mp41")!==-1||s.indexOf("mp42")!==-1||s.indexOf("isom")!==-1||s.indexOf("avc1")!==-1||s.indexOf("iso2")!==-1||s.indexOf("M4V ")!==-1||s.indexOf("msdh")!==-1||s.indexOf("msix")!==-1}},
{sig:"6674797071742020",ext:"mov",mime:"video/quicktime",name:"QuickTime Movie",cat:"Video",conf:"exact",offset:0},
{sig:"667479704D344120",ext:"m4a",mime:"audio/mp4",name:"MPEG-4 Audio (M4A)",cat:"Audio",conf:"exact",offset:0},
{sig:"667479704D345620",ext:"m4v",mime:"video/x-m4v",name:"MPEG-4 Video (M4V)",cat:"Video",conf:"exact",offset:0},
{sig:"6674797066717420",ext:"qcp",mime:"audio/qcelp",name:"Qualcomm PureVoice",cat:"Audio",conf:"exact",offset:0},
{sig:"66747970434D4643",ext:"cmfc",mime:"audio/AC4",name:"AC-4 Audio",cat:"Audio",conf:"exact",offset:0},
{sig:"6674797069736F6D",ext:"mp4",mime:"audio/mp4",name:"MP4 Audio (ISOM)",cat:"Audio",conf:"exact",offset:0},
{sig:"464C56",ext:"flv",mime:"video/x-flv",name:"Flash Video",cat:"Video",conf:"exact",offset:0},
{sig:"47",ext:"ts",mime:"video/mp2t",name:"MPEG Transport Stream",cat:"Video",conf:"check",offset:0,
 check:function(b){return b.length>3&&(b[1]===0x47||b[187]===0x47)}},
{sig:"494433",ext:"mp3",mime:"audio/mpeg",name:"MP3 Audio (ID3v2)",cat:"Audio",conf:"exact",offset:0},
{sig:"FFFB",ext:"mp3",mime:"audio/mpeg",name:"MP3 Audio (MPEG1 Layer 3)",cat:"Audio",conf:"exact",offset:0},
{sig:"FFF3",ext:"mp3",mime:"audio/mpeg",name:"MP3 Audio (MPEG2 Layer 3)",cat:"Audio",conf:"exact",offset:0},
{sig:"FFF2",ext:"mp3",mime:"audio/mpeg",name:"MP3 Audio (MPEG2 Layer 3)",cat:"Audio",conf:"exact",offset:0},
{sig:"FFF1",ext:"mp3",mime:"audio/mpeg",name:"MP3 Audio (MPEG2 Layer 3)",cat:"Audio",conf:"exact",offset:0},
{sig:"FFFA",ext:"mp3",mime:"audio/mpeg",name:"MP3 Audio (MPEG2 Layer 3)",cat:"Audio",conf:"exact",offset:0},
{sig:"664C6143",ext:"flac",mime:"audio/flac",name:"FLAC Audio",cat:"Audio",conf:"exact",offset:0},
{sig:"4D344120",ext:"m4a",mime:"audio/mp4",name:"M4A Audio",cat:"Audio",conf:"check",offset:0,
 check:function(b){var s=shortStr(b);return s.indexOf("ftyp")!==-1||b[0]===0x66}},
{sig:"FFF1",ext:"aac",mime:"audio/aac",name:"AAC Audio (ADTS)",cat:"Audio",conf:"exact",offset:0},
{sig:"FFF9",ext:"aac",mime:"audio/aac",name:"AAC Audio (ADTS)",cat:"Audio",conf:"exact",offset:0},
{sig:"3026B2758E66CF11",ext:"wma",mime:"video/x-ms-wmv",name:"Windows Media (ASF)",cat:"Audio",conf:"exact",offset:0},
{sig:"2E7261FD",ext:"ram",mime:"application/x-pn-realaudio",name:"RealAudio",cat:"Audio",conf:"exact",offset:0},
{sig:"2E524D46",ext:"rm",mime:"application/vnd.rn-realmedia",name:"RealMedia",cat:"Video",conf:"exact",offset:0},
{sig:"67496646",ext:"gif",mime:"image/gif",name:"GIF Image",cat:"Images",conf:"check",offset:0,
 check:function(b){return b.length>=6&&b[4]===0x38}},
{sig:"4D5A",ext:"exe",mime:"application/x-dosexec",name:"Windows Executable",cat:"Executables",conf:"check",offset:0,
 check:function(b){return b.length>=64}},
{sig:"4D5A",ext:"dll",mime:"application/x-dosexec",name:"Windows DLL",cat:"Executables",conf:"check",offset:0,
 check:function(b){var s=shortStr(b,0x40);var off=((b[0x3C]||0)|((b[0x3D]||0)<<8)|((b[0x3E]||0)<<16)|((b[0x3F]||0)<<24));return off>0&&off+4<b.length}},
{sig:"7F454C46",ext:"elf",mime:"application/x-elf",name:"ELF Executable",cat:"Executables",conf:"exact",offset:0},
{sig:"7F454C46",ext:"",mime:"application/x-elf",name:"ELF Object",cat:"Executables",conf:"check",offset:0,
 check:function(b){return b.length>=5&&b[4]===1}},
{sig:"FEEDFACE",ext:"dylib",mime:"application/x-mach-binary",name:"Mach-O 32-bit",cat:"Executables",conf:"exact",offset:0},
{sig:"FEEDFACF",ext:"dylib",mime:"application/x-mach-binary",name:"Mach-O 64-bit",cat:"Executables",conf:"exact",offset:0},
{sig:"CEFAEDFE",ext:"dylib",mime:"application/x-mach-binary",name:"Mach-O 32-bit (reverse)",cat:"Executables",conf:"exact",offset:0},
{sig:"CFFAEDFE",ext:"dylib",mime:"application/x-mach-binary",name:"Mach-O 64-bit (reverse)",cat:"Executables",conf:"exact",offset:0},
{sig:"CAFEBABE",ext:"class",mime:"application/java-vm",name:"Java Class File",cat:"Executables",conf:"exact",offset:0},
{sig:"CAFEBAABE",ext:"class",mime:"application/java-vm",name:"Java Module (JDK9+)",cat:"Executables",conf:"exact",offset:0},
{sig:"425A6839314159265359",ext:"bz2",mime:"application/x-bzip2",name:"Bzip2 (block header)",cat:"Archives",conf:"exact",offset:0},
{sig:"894C5A4F0D0A1A0A",ext:"lzo",mime:"application/x-lzo",name:"LZO Compressed",cat:"Archives",conf:"exact",offset:0},
{sig:"213C617263683E0A",ext:"deb",mime:"application/x-debian-package",name:"Debian Package",cat:"Archives",conf:"exact",offset:0},
{sig:"EDABEEDB",ext:"rpm",mime:"application/x-rpm",name:"RPM Package",cat:"Archives",conf:"exact",offset:0},
{sig:"4F54544F",ext:"ttf",mime:"font/ttf",name:"TrueType Font",cat:"Misc",conf:"exact",offset:0},
{sig:"00010000",ext:"ttf",mime:"font/ttf",name:"TrueType Font (alt)",cat:"Misc",conf:"exact",offset:0},
{sig:"4C50",ext:"ttc",mime:"font/collection",name:"TrueType Collection",cat:"Misc",conf:"check",offset:0,
 check:function(b){var s=shortStr(b);return s.indexOf("LP")!==-1}},
{sig:"774F4646",ext:"woff",mime:"font/woff",name:"WOFF Font",cat:"Misc",conf:"exact",offset:0},
{sig:"774F4632",ext:"woff2",mime:"font/woff2",name:"WOFF2 Font",cat:"Misc",conf:"exact",offset:0},
{sig:"4F54544F",ext:"otf",mime:"font/otf",name:"OpenType Font (CFF)",cat:"Misc",conf:"check",offset:0,
 check:function(b){return b.length>=4}},
{sig:"664C614300000022",ext:"flac",mime:"audio/flac",name:"FLAC Stream Marker",cat:"Audio",conf:"exact",offset:0},
{sig:"52494646",ext:"ani",mime:"image/vnd.microsoft.icon",name:"Animated Cursor (RIFF)",cat:"Images",conf:"check",offset:0,
 check:function(b){return b.length>=12&&(String.fromCharCode(b[8],b[9],b[10],b[11])==="ACON")}},
{sig:"52494646",ext:"cdt",mime:"application/x-cdr",name:"CorelDRAW (RIFF-based)",cat:"Misc",conf:"check",offset:0,
 check:function(b){return b.length>=12&&(String.fromCharCode(b[8],b[9],b[10],b[11])==="CDR8"||String.fromCharCode(b[8],b[9],b[10],b[11])==="CDR9")}},
{sig:"52494646",ext:"webp",mime:"image/webp",name:"WEBP Image",cat:"Images",conf:"check",offset:0,
 check:function(b){return b.length>=12&&(String.fromCharCode(b[8],b[9],b[10],b[11])==="WEBP")}},
{sig:"52494646",ext:"ivf",mime:"video/x-ivf",name:"IVF Video Container",cat:"Video",conf:"check",offset:0,
 check:function(b){return b.length>=12&&(String.fromCharCode(b[8],b[9],b[10],b[11])==="IVF ") }},
{sig:"0000002066747970",ext:"mp4",mime:"video/mp4",name:"MP4 (32-bit size)",cat:"Video",conf:"exact",offset:0},
{sig:"69736F6D",ext:"mp4",mime:"video/mp4",name:"MP4 (ISOM)",cat:"Video",conf:"check",offset:4,
 check:function(b){var s=shortStr(b,0);return s.indexOf("ftyp")!==-1}},
{sig:"4D344120",ext:"m4a",mime:"audio/mp4",name:"Apple M4A Audio",cat:"Audio",conf:"exact",offset:4},
{sig:"64617368",ext:"mp4",mime:"video/mp4",name:"MP4 (DASH)",cat:"Video",conf:"check",offset:4,
 check:function(b){return shortStr(b).indexOf("ftyp")!==-1}},
{sig:"6D6F6F76",ext:"mp4",mime:"video/mp4",name:"MP4 (MOOV)",cat:"Video",conf:"check",offset:4,
 check:function(b){return shortStr(b).indexOf("ftyp")!==-1}},
{sig:"73747970",ext:"mp4",mime:"video/mp4",name:"MP4 (STYP)",cat:"Video",conf:"check",offset:4,
 check:function(b){return shortStr(b).indexOf("ftyp")!==-1}},
{sig:"6D646174",ext:"mp4",mime:"video/mp4",name:"MP4 (MDAT)",cat:"Video",conf:"check",offset:4,
 check:function(b){return shortStr(b).indexOf("ftyp")!==-1}},
{sig:"D4C3B2A1",ext:"pcap",mime:"application/vnd.tcpdump.pcap",name:"Libpcap Capture",cat:"Misc",conf:"exact",offset:0},
{sig:"0D0A",ext:"smtp",mime:"message/rfc822",name:"SMTP Email",cat:"Misc",conf:"check",offset:0,
 check:function(b){var s=shortStr(b);return s.indexOf("From:")!==-1||s.indexOf("Subject:")!==-1}},
{sig:"46726F6D20",ext:"eml",mime:"message/rfc822",name:"Email Message (From:)",cat:"Misc",conf:"exact",offset:0},
{sig:"53746F7261676544422056657273696F6E",ext:"kdbx",mime:"application/x-keepass",name:"KeePass Database",cat:"Database",conf:"exact",offset:0},
{sig:"370AB001",ext:"lzh",mime:"application/x-lzh",name:"LZH Archive",cat:"Archives",conf:"exact",offset:0},
{sig:"52454745",ext:"reg",mime:"text/x-windows-registry",name:"Windows Registry File",cat:"Misc",conf:"exact",offset:0},
{sig:"5B57696E646F77735265676973747279",ext:"reg",mime:"text/x-windows-registry",name:"Windows Registry (text)",cat:"Misc",conf:"exact",offset:0},
{sig:"FFFE",ext:"txt",mime:"text/plain",name:"UTF-16 LE BOM",cat:"Misc",conf:"exact",offset:0},
{sig:"FEFF",ext:"txt",mime:"text/plain",name:"UTF-16 BE BOM",cat:"Misc",conf:"exact",offset:0},
{sig:"EFBBBF",ext:"txt",mime:"text/plain",name:"UTF-8 BOM",cat:"Misc",conf:"exact",offset:0},
{sig:"23212F",ext:"sh",mime:"text/x-shellscript",name:"Shell Script",cat:"Code",conf:"exact",offset:0},
{sig:"23212F7573722F62696E2F7065726C",ext:"pl",mime:"text/x-perl",name:"Perl Script",cat:"Code",conf:"exact",offset:0},
{sig:"23212F7573722F62696E2F656E7620707974686F6E",ext:"py",mime:"text/x-python",name:"Python Script",cat:"Code",conf:"exact",offset:0},
{sig:"23212F7573722F62696E2062617368",ext:"bash",mime:"text/x-shellscript",name:"Bash Script",cat:"Code",conf:"exact",offset:0},
{sig:"23212F7573722F62696E207A7368",ext:"zsh",mime:"text/x-shellscript",name:"Zsh Script",cat:"Code",conf:"exact",offset:0},
{sig:"3C3F706870",ext:"php",mime:"text/x-php",name:"PHP Script",cat:"Code",conf:"exact",offset:0},
{sig:"3C21444F435459504520",ext:"html",mime:"text/html",name:"HTML Document",cat:"Code",conf:"exact",offset:0},
{sig:"3C68746D6C",ext:"html",mime:"text/html",name:"HTML Document (short)",cat:"Code",conf:"exact",offset:0},
{sig:"3C48544D4C",ext:"html",mime:"text/html",name:"HTML Document (caps)",cat:"Code",conf:"exact",offset:0},
{sig:"3C3F786D6C",ext:"xml",mime:"text/xml",name:"XML Document",cat:"Code",conf:"exact",offset:0},
{sig:"3C726F6F74",ext:"xml",mime:"text/xml",name:"XML Root Element",cat:"Code",conf:"check",offset:0,
 check:function(b){var s=shortStr(b);return s.indexOf("</")!==-1||s.indexOf("/>")!==-1}},
{sig:"3C212D2D",ext:"html",mime:"text/html",name:"HTML Comment Start",cat:"Code",conf:"exact",offset:0},
{sig:"3C6831",ext:"html",mime:"text/html",name:"HTML (h1 tag)",cat:"Code",conf:"exact",offset:0},
{sig:"2F2A",ext:"css",mime:"text/css",name:"CSS Stylesheet",cat:"Code",conf:"exact",offset:0},
{sig:"2F2F",ext:"js",mime:"text/javascript",name:"JavaScript (// comment)",cat:"Code",conf:"heuristic",offset:0},
{sig:"23696E636C756465",ext:"js",mime:"text/javascript",name:"JavaScript (#include)",cat:"Code",conf:"heuristic",offset:0},
{sig:"303030303030303020",ext:"pcap",mime:"application/vnd.tcpdump.pcap",name:"PCAP (ASCII header)",cat:"Misc",conf:"heuristic",offset:0},
{sig:"D3ADB33F",ext:"qif",mime:"application/x-qw",name:"Quicken Interchange",cat:"Misc",conf:"exact",offset:0},
{sig:"5F302E3130",ext:"nk",mime:"text/x-python",name:"Python Bytecode (0.10)",cat:"Code",conf:"exact",offset:0},
{sig:"E3000000",ext:"pyc",mime:"application/x-python-bytecode",name:"Python 3 Bytecode",cat:"Code",conf:"exact",offset:0},
{sig:"42534843",ext:"pbz2",mime:"application/x-bzip2",name:"Bzip2 (BSh)",cat:"Archives",conf:"exact",offset:0},
{sig:"C1FFC0D9",ext:"snappy",mime:"application/x-snappy",name:"Snappy Framed",cat:"Archives",conf:"exact",offset:0},
{sig:"5A4D",ext:"exe",mime:"application/x-dosexec",name:"DOS Executable (ZM)",cat:"Executables",conf:"exact",offset:0},
{sig:"4B44",ext:"db",mime:"application/x-sqlite3",name:"SQLite (KD variant)",cat:"Database",conf:"heuristic",offset:0},
{sig:"4D494C45",ext:"log",mime:"text/plain",name:"Mileage Log",cat:"Misc",conf:"exact",offset:0},
{sig:"4D5A900003000000",ext:"exe",mime:"application/x-dosexec",name:"PE Executable (DOS stub)",cat:"Executables",conf:"exact",offset:0},
{sig:"3026B2758E66CF11A1D20000",ext:"asf",mime:"video/x-ms-asf",name:"Advanced Streaming Format",cat:"Video",conf:"exact",offset:0},
{sig:"D0CF11E0A1B11AE1",ext:"msi",mime:"application/x-msi",name:"Windows Installer (MSI)",cat:"Archives",conf:"check",offset:0,
 check:function(b){var s=shortStr(b);return s.indexOf("MsiPackage")!==-1||s.indexOf("Installer")!==-1}},
{sig:"526172211A070100",ext:"rar",mime:"application/vnd.rar",name:"RAR v5 Archive",cat:"Archives",conf:"exact",offset:0},
{sig:"20434420617564696F20646174612063",ext:"cda",mime:"audio/x-cda",name:"CD Audio Track",cat:"Audio",conf:"exact",offset:0},
{sig:"49454E444145424243",ext:"png",mime:"image/png",name:"PNG Image (IEND chunk first)",cat:"Images",conf:"heuristic",offset:0},
{sig:"800000000100000000000000",ext:"emf",mime:"image/x-emf",name:"Windows Enhanced Metafile",cat:"Images",conf:"exact",offset:0},
{sig:"D7CDC69A",ext:"wmf",mime:"image/x-wmf",name:"Windows Metafile",cat:"Images",conf:"exact",offset:0},
{sig:"41433130",ext:"dwg",mime:"image/vnd.dwg",name:"AutoCAD Drawing",cat:"Images",conf:"exact",offset:0},
{sig:"425047",ext:"bpg",mime:"image/bpg",name:"Better Portable Graphics",cat:"Images",conf:"exact",offset:0},
{sig:"4D4D0042",ext:"cr2",mime:"image/x-canon-cr2",name:"Canon CR2 RAW",cat:"Images",conf:"exact",offset:0},
{sig:"49492A00100000004352",ext:"cr2",mime:"image/x-canon-cr2",name:"Canon CR2 RAW (v2)",cat:"Images",conf:"exact",offset:0},
{sig:"49492A0008000000",ext:"nef",mime:"image/x-nikon-nef",name:"Nikon NEF RAW",cat:"Images",conf:"check",offset:0,
 check:function(b){return b.length>=10}},
{sig:"4F524947494E414C20434D52",ext:"cr3",mime:"image/x-canon-cr3",name:"Canon CR3 RAW",cat:"Images",conf:"exact",offset:0},
{sig:"3A4249544D4150",ext:"bpt",mime:"image/x-bitmap",name:"BitMap Texture",cat:"Images",conf:"exact",offset:0},
{sig:"45584946",ext:"exr",mime:"image/x-exr",name:"OpenEXR Image",cat:"Images",conf:"exact",offset:4},
{sig:"664F726D61742076",ext:"exr",mime:"image/x-exr",name:"OpenEXR (header)",cat:"Images",conf:"exact",offset:0},
{sig:"524153544D",ext:"blend",mime:"application/x-blender",name:"Blender File",cat:"Misc",conf:"exact",offset:0},
{sig:"23212056657273696F6E",ext:"qif",mime:"text/x-qif",name:"Quicken/QuickBooks Data",cat:"Misc",conf:"exact",offset:0},
{sig:"656E747279",ext:"qr",mime:"application/x-qr",name:"QR Data Entry",cat:"Misc",conf:"heuristic",offset:0},
{sig:"4D494F303030",ext:"db",mime:"application/x-miob",name:"MIO Format",cat:"Database",conf:"exact",offset:0},
{sig:"5F2031302E30",ext:"dbf",mime:"application/x-dbf",name:"dBASE II Table",cat:"Database",conf:"exact",offset:0},
{sig:"0005",ext:"dbf",mime:"application/x-dbf",name:"dBASE III Table",cat:"Database",conf:"check",offset:0,
 check:function(b){return b.length>=32}},
{sig:"030108",ext:"dbf",mime:"application/x-dbf",name:"dBASE IV Table",cat:"Database",conf:"exact",offset:0},
{sig:"2000000001",ext:"dbf",mime:"application/x-dbf",name:"FoxPro Table",cat:"Database",conf:"exact",offset:0},
{sig:"536166654469736B20496D616765",ext:"dmg",mime:"application/x-apple-diskimage",name:"Apple Disk Image",cat:"Archives",conf:"exact",offset:0},
{sig:"6B6F6C79206469736B20696D616765",ext:"udif",mime:"application/x-apple-diskimage",name:"Apple UDIF Image",cat:"Archives",conf:"exact",offset:0},
{sig:"7801",ext:"zlib",mime:"application/x-zlib",name:"ZLIB Compressed",cat:"Archives",conf:"exact",offset:0},
{sig:"789C",ext:"zlib",mime:"application/x-zlib",name:"ZLIB Compressed (default)",cat:"Archives",conf:"exact",offset:0},
{sig:"78DA",ext:"zlib",mime:"application/x-zlib",name:"ZLIB Compressed (best)",cat:"Archives",conf:"exact",offset:0},
{sig:"7820",ext:"zlib",mime:"application/x-zlib",name:"ZLIB Compressed (fastest)",cat:"Archives",conf:"exact",offset:0},
{sig:"785E",ext:"zlib",mime:"application/x-zlib",name:"ZLIB Compressed (fast)",cat:"Archives",conf:"exact",offset:0},
{sig:"4D5A00000000000000000000000000000000000000000000000000000000000000000000",ext:"exe",mime:"application/x-dosexec",name:"NE Executable (Win16)",cat:"Executables",conf:"exact",offset:0},
{sig:"4C00000001140200",ext:"lnk",mime:"application/x-ms-shortcut",name:"Windows Shortcut (LNK)",cat:"Misc",conf:"exact",offset:0},
{sig:"CFAD12FE",ext:"cab",mime:"application/vnd.ms-cab-compressed",name:"Microsoft Cabinet (alt)",cat:"Archives",conf:"exact",offset:0},
{sig:"49536328",ext:"cab",mime:"application/vnd.ms-cab-compressed",name:"MS Compound Document",cat:"Archives",conf:"exact",offset:0},
{sig:"377ABCAF271C",ext:"7z",mime:"application/x-7z-compressed",name:"7-Zip Archive (full sig)",cat:"Archives",conf:"exact",offset:0},
{sig:"B56F4D45",ext:"cab",mime:"application/vnd.ms-cab-compressed",name:"Microsoft Cabinet (LZX)",cat:"Archives",conf:"exact",offset:0},
{sig:"47616D65424F592056303031",ext:"nes",mime:"application/x-nes-rom",name:"Nintendo NES ROM",cat:"Misc",conf:"exact",offset:0},
{sig:"53454741",ext:"sg",mime:"application/x-sg",name:"Sega Genesis ROM",cat:"Misc",conf:"exact",offset:0},
{sig:"504B030414000600",ext:"jar",mime:"application/java-archive",name:"Java Archive (signed)",cat:"Archives",conf:"exact",offset:0},
{sig:"3026B2758E66CF119DD01100900D809F",ext:"wmf",mime:"image/x-wmf",name:"Windows Metafile (WMF)",cat:"Images",conf:"exact",offset:0},
{sig:"41564736206C6F61646572",ext:"avi",mime:"video/avi",name:"AVI (AVG6 loader)",cat:"Video",conf:"exact",offset:0},
{sig:"00010000",ext:"ttf",mime:"font/ttf",name:"TrueType Font (Outline)",cat:"Misc",conf:"exact",offset:0},
{sig:"4F54544F000C",ext:"otf",mime:"font/otf",name:"OpenType Font (CFF)",cat:"Misc",conf:"exact",offset:0},
{sig:"02000000",ext:"eot",mime:"application/vnd.ms-fontobject",name:"Embedded OpenType",cat:"Misc",conf:"check",offset:0,
 check:function(b){return b.length>=8}},
{sig:"48544D4C20",ext:"html",mime:"text/html",name:"HTML (HTM prefix)",cat:"Code",conf:"exact",offset:0},
{sig:"4D6963726F736F667420457863656C20",ext:"xls",mime:"application/vnd.ms-excel",name:"Microsoft Excel (BIFF)",cat:"Documents",conf:"exact",offset:0},
{sig:"237E44454249414E205041434B4147452D",ext:"deb",mime:"application/x-debian-package",name:"Debian Package (text)",cat:"Archives",conf:"exact",offset:0},
{sig:"213C746172",ext:"tar",mime:"application/x-tar",name:"Tape Archive (tar header)",cat:"Archives",conf:"exact",offset:0},
{sig:"42494D",ext:"psd",mime:"image/vnd.adobe.photoshop",name:"Photoshop BIM marker",cat:"Images",conf:"heuristic",offset:0},
{sig:"706F727461626C65206461746162617365",ext:"pdb",mime:"application/x-palm-database",name:"PalmOS Database",cat:"Database",conf:"exact",offset:0},
{sig:"4D6963726F736F667420432F432B2B",ext:"pdb",mime:"application/x-pdb",name:"Visual C++ PDB",cat:"Misc",conf:"exact",offset:0},
{sig:"48434D4150",ext:"palm",mime:"application/x-palm-os",name:"Handspring COM Map",cat:"Misc",conf:"exact",offset:0},
{sig:"4D5020",ext:"mp",mime:"audio/mpeg",name:"MPEG Audio (MP )",cat:"Audio",conf:"exact",offset:0},
{sig:"FF4F",ext:"j2k",mime:"image/jp2",name:"JPEG 2000 Codestream",cat:"Images",conf:"exact",offset:0},
{sig:"0000000C6A584C200D0A870A",ext:"jxl",mime:"image/jxl",name:"JPEG XL (Codestream)",cat:"Images",conf:"exact",offset:0}
];

function shortStr(b,off){
    off=off||0;
    var s='';
    for(var i=off;i<Math.min(b.length,off+512);i++){
        if(b[i]>=32&&b[i]<127)s+=String.fromCharCode(b[i]);
        else s+=' ';
    }
    return s;
}

function isOOXML(b){
    if(b[0]!==0x50||b[1]!==0x4B||b[2]!==0x03||b[3]!==0x04)return false;
    return shortStr(b).indexOf("[Content_Types]")!==-1||shortStr(b).indexOf("META-INF")!==-1;
}

function hasEntry(b,name){
    return shortStr(b).indexOf(name)!==-1;
}

function bytesToHex(arr,len){
    len=len||arr.length;
    var h='';
    for(var i=0;i<len;i++)h+=('0'+arr[i].toString(16)).slice(-2);
    return h.toUpperCase();
}

function detectType(bytes){
    var hex=bytesToHex(bytes);
    var exact=[],check=[],heuristic=[];
    for(var i=0;i<DB.length;i++){
        var e=DB[i];
        var sigHex=e.sig;
        var off=(e.offset||0)*2;
        if(hex.indexOf(sigHex,off)===off){
            if(e.conf==='exact')exact.push(e);
            else if(e.conf==='check'){
                if(typeof e.check==='function'){
                    if(e.check(bytes))check.push(e);
                }else check.push(e);
            }else heuristic.push(e);
        }
    }
    if(exact.length>0)return{entry:exact[0],level:'exact'};
    if(check.length>0)return{entry:check[0],level:'primary'};
    if(heuristic.length>0)return{entry:heuristic[0],level:'heuristic'};
    return null;
}

function formatSize(bytes){
    if(bytes<1024)return bytes+' B';
    if(bytes<1048576)return(bytes/1024).toFixed(1)+' KB';
    return(bytes/1048576).toFixed(2)+' MB';
}

function buildHexDump(bytes,perRow){
    perRow=perRow||16;
    var html='';
    for(var off=0;off<bytes.length;off+=perRow){
        var hexPart='',asciiPart='';
        for(var i=0;i<perRow;i++){
            var idx=off+i;
            if(idx>=bytes.length){
                hexPart+='<span class="hex-space">   </span>';
                asciiPart+='<span class="ascii-dot"> </span>';
            }else{
                var hex=bytes[idx].toString(16).padStart(2,'0').toUpperCase();
                var isPrint=bytes[idx]>=32&&bytes[idx]<127;
                var asciiChar=isPrint?String.fromCharCode(bytes[idx]):'.';
                var cls=isPrint?'ascii-printable':'ascii-dot';
                hexPart+='<span class="hex-byte">'+hex+'</span><span class="hex-space"> </span>';
                asciiPart+='<span class="'+cls+'">'+asciiChar+'</span>';
            }
            if(i===7)hexPart+='<span class="hex-space"> </span>';
        }
        html+='<span class="offset">'+off.toString(16).padStart(8,'0')+'</span>  '+hexPart+' '+asciiPart+'\n';
    }
    return html;
}

function hexStringToBytes(hexStr){
    hexStr=hexStr.replace(/[^0-9a-fA-F]/g,'');
    if(hexStr.length%2!==0)hexStr='0'+hexStr;
    var bytes=[];
    for(var i=0;i<hexStr.length;i+=2){
        bytes.push(parseInt(hexStr.substr(i,2),16));
    }
    return bytes;
}

function showResults(bytes,fileName,fileSize){
    var hex=bytesToHex(bytes,Math.min(bytes.length,32));
    var result=detectType(bytes);

    var badgeEl=document.getElementById('ft-badge');
    var mimeEl=document.getElementById('ft-mime');
    var infoEl=document.getElementById('ft-file-info');
    var magicEl=document.getElementById('ft-magic-hex');
    var dumpEl=document.getElementById('ft-hex-dump');
    var dumpLabel=document.getElementById('ft-hex-dump-label');

    if(result){
        var e=result.entry;
        badgeEl.textContent=e.name;
        badgeEl.className='badge badge-type '+(result.level==='exact'?'bg-success':result.level==='primary'?'bg-primary':'bg-warning text-dark');
        mimeEl.innerHTML='<span class="'+('confidence-'+result.level)+'">'+result.level.toUpperCase()+' MATCH</span> &middot; '+e.mime+(e.ext?' &middot; .'+e.ext:'');

        var rows='';
        if(fileName){rows+='<div class="result-row"><span class="result-label">File name</span><span class="result-value">'+fileName+'</span></div>';}
        if(fileSize!==undefined){rows+='<div class="result-row"><span class="result-label">File size</span><span class="result-value">'+formatSize(fileSize)+'</span></div>';}
        rows+='<div class="result-row"><span class="result-label">Detected type</span><span class="result-value"><strong>'+e.name+'</strong></span></div>';
        rows+='<div class="result-row"><span class="result-label">MIME type</span><span class="result-value" style="font-family:monospace;">'+e.mime+'</span></div>';
        rows+='<div class="result-row"><span class="result-label">Extension</span><span class="result-value">.'+e.ext+'</span></div>';
        rows+='<div class="result-row"><span class="result-label">Category</span><span class="result-value">'+e.cat+'</span></div>';
        rows+='<div class="result-row"><span class="result-label">Magic bytes</span><span class="result-value"><span class="magic-bytes-display">'+e.sig.match(/.{1,2}/g).join(' ')+'</span></span></div>';
        rows+='<div class="result-row"><span class="result-label">Confidence</span><span class="result-value"><span class="confidence-'+result.level+'">'+(result.level==='exact'?'High — exact magic match':result.level==='primary'?'Medium — validated with context check':'Low — heuristic / text signature')+'</span></span></div>';
        infoEl.innerHTML=rows;
    }else{
        badgeEl.textContent='Unknown';
        badgeEl.className='badge bg-secondary badge-type';
        mimeEl.innerHTML='<span class="confidence-heuristic">NO MATCH</span> — the first bytes did not match any known signature in the database.';
        var rows='';
        if(fileName){rows+='<div class="result-row"><span class="result-label">File name</span><span class="result-value">'+fileName+'</span></div>';}
        if(fileSize!==undefined){rows+='<div class="result-row"><span class="result-label">File size</span><span class="result-value">'+formatSize(fileSize)+'</span></div>';}
        rows+='<div class="result-row"><span class="result-label">Status</span><span class="result-value text-warning">Unrecognized — may be encrypted, corrupted or an uncommon format.</span></div>';
        infoEl.innerHTML=rows;
    }

    var hexDisplay=hex.match(/.{1,2}/g).join(' ');
    magicEl.innerHTML='<span class="result-label">Header hex:</span> <span class="magic-bytes-display">'+hexDisplay+'</span>';
    dumpLabel.textContent='Hex dump of header ('+Math.min(bytes.length,64)+' bytes):';
    dumpEl.innerHTML=buildHexDump(bytes,16);

    document.getElementById('ft-results').style.display='block';
    document.getElementById('ft-results').scrollIntoView({behavior:'smooth',block:'start'});
}

function handleFile(files){
    if(!files||!files.length)return;
    var file=files[0];
    var reader=new FileReader();
    reader.onload=function(e){
        var buf=e.target.result;
        var bytes=new Uint8Array(buf.slice(0,64));
        showResults(bytes,file.name,file.size);
    };
    reader.readAsArrayBuffer(file);
}

function detectFromHex(){
    var raw=document.getElementById('ft-hex-in').value.trim();
    if(!raw)return;
    var looksHex=/^[0-9a-fA-F\s]+$/.test(raw);
    if(!looksHex)return;
    var bytes=hexStringToBytes(raw);
    if(bytes.length===0)return;
    showResults(bytes,'Pasted hex ('+bytes.length+' bytes)',bytes.length);
}

function clearAll(){
    document.getElementById('ft-hex-in').value='';
    document.getElementById('ft-file').value='';
    document.getElementById('ft-results').style.display='none';
}

var dropZone=document.getElementById('drop-zone');
dropZone.addEventListener('dragover',function(e){e.preventDefault();e.stopPropagation();dropZone.classList.add('dragover');});
dropZone.addEventListener('dragleave',function(e){e.preventDefault();e.stopPropagation();dropZone.classList.remove('dragover');});
dropZone.addEventListener('drop',function(e){
    e.preventDefault();
    e.stopPropagation();
    dropZone.classList.remove('dragover');
    if(e.dataTransfer.files.length)handleFile(e.dataTransfer.files);
});

var catCounts={};
DB.forEach(function(e){catCounts[e.cat]=(catCounts[e.cat]||0)+1;});
var tagEl=document.getElementById('ft-category-tags');
var cats=Object.keys(catCounts).sort();
cats.forEach(function(c){
    tagEl.innerHTML+='<span class="badge bg-secondary me-1 mb-1">'+c+' ('+catCounts[c]+')</span>';
});
})();
</script>
<?php page_footer(); ?>