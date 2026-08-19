<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$GLOBALS['_meta'] = [
    'description' => 'Free online emoji picker and lookup tool. Browse 300+ emojis by category, search by name, copy any emoji or its Unicode/HTML/JS code. Includes skin tone selector, recently used history, and one-click copy.',
    'keywords' => 'emoji picker, emoji search, unicode emoji, copy emoji, emoji codes, emoji list, emoji lookup, smiley face, emoji keyboard',
];
page_header('Emoji Picker &amp; Lookup - Browse, Search &amp; Copy 300+ Emoji');
?>
<style>
.emoji-grid{display:grid;grid-template-columns:repeat(8,1fr);gap:4px;margin-top:12px}
.emoji-cell{position:relative;aspect-ratio:1;display:flex;align-items:center;justify-content:center;font-size:2rem;cursor:pointer;border-radius:8px;border:1px solid transparent;transition:all .15s}
.emoji-cell:hover{background:rgba(88,101,242,.15);border-color:rgba(88,101,242,.3);transform:scale(1.15)}
.emoji-cell .tt{position:absolute;bottom:calc(100% + 6px);left:50%;transform:translateX(-50%);background:#111;color:#ddd;padding:3px 8px;border-radius:6px;font-size:.7rem;white-space:nowrap;pointer-events:none;opacity:0;transition:opacity .15s;z-index:10;border:1px solid #333}
.emoji-cell:hover .tt{opacity:1}
.cat-tabs{display:flex;flex-wrap:wrap;gap:4px;margin-top:10px}
.cat-tab{padding:5px 12px;border-radius:6px;border:1px solid #333;background:transparent;color:#bbb;cursor:pointer;font-size:.8rem;transition:all .15s}
.cat-tab:hover{background:rgba(88,101,242,.15);color:#fff;border-color:rgba(88,101,242,.4)}
.cat-tab.active{background:#5865f2;color:#fff;border-color:#5865f2}
.detail-panel{background:rgba(255,255,255,.04);border:1px solid #333;border-radius:12px;padding:20px;margin-top:16px;display:none}
.detail-panel.show{display:block}
.detail-big{font-size:5rem;line-height:1.1;margin-bottom:12px}
.detail-row{display:flex;align-items:center;gap:8px;margin-bottom:8px;flex-wrap:wrap}
.detail-label{font-size:.75rem;color:#888;min-width:100px;flex-shrink:0}
.detail-val{font-family:'JetBrains Mono',monospace;font-size:.85rem;background:rgba(0,0,0,.3);padding:4px 10px;border-radius:6px;flex:1;min-width:0;word-break:break-all;color:#ccc;border:1px solid #333}
.detail-val:hover{border-color:#5865f2}
.copy-btn{padding:4px 12px;border-radius:6px;border:1px solid #444;background:rgba(255,255,255,.06);color:#ccc;cursor:pointer;font-size:.75rem;transition:all .15s;white-space:nowrap}
.copy-btn:hover{background:#5865f2;color:#fff;border-color:#5865f2}
.copy-btn.copied{background:#43b581;color:#fff;border-color:#43b581}
.skin-tone-bar{display:flex;gap:4px;align-items:center;margin-top:8px;margin-bottom:4px}
.skin-swatch{width:28px;height:28px;border-radius:50%;cursor:pointer;border:2px solid transparent;display:flex;align-items:center;justify-content:center;font-size:1rem;transition:all .15s}
.skin-swatch:hover{transform:scale(1.15)}
.skin-swatch.active{border-color:#5865f2;box-shadow:0 0 0 2px rgba(88,101,242,.4)}
.recent-emojis{display:flex;gap:4px;flex-wrap:wrap;margin-top:8px}
.recent-item{width:38px;height:38px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;cursor:pointer;border-radius:8px;border:1px solid #333;transition:all .15s}
.recent-item:hover{background:rgba(88,101,242,.15);border-color:rgba(88,101,242,.4);transform:scale(1.1)}
#emoji-search{background:rgba(0,0,0,.3);border:1px solid #444;color:#eee;border-radius:8px;padding:10px 14px;font-size:.95rem;width:100%;transition:border-color .15s}
#emoji-search:focus{outline:none;border-color:#5865f2}
#emoji-search::placeholder{color:#666}
.no-results{color:#888;text-align:center;padding:40px 0;font-size:.9rem;display:none}
@media(max-width:768px){.emoji-grid{grid-template-columns:repeat(5,1fr)}.emoji-cell{font-size:1.6rem}.detail-big{font-size:3.5rem}}
.copy-toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#43b581;color:#fff;padding:8px 20px;border-radius:8px;font-size:.85rem;z-index:9999;opacity:0;transition:opacity .2s;pointer-events:none}
.copy-toast.show{opacity:1}
</style>

<div class="container" style="max-width:960px;">
    <h1 class="h4 mb-2 reveal in-view">Emoji Picker &amp; Lookup</h1>
    <p class="text-secondary mb-1 reveal in-view">Browse, search and copy <strong>300+ emoji</strong> organised by category. Click any emoji to see its <strong>Unicode codepoint</strong>, <strong>HTML entity</strong>, <strong>JavaScript escape</strong>, and more — with one-click copy for every format.</p>
    <p class="text-secondary mb-4 reveal in-view">A skin tone selector lets you modify the next picked emoji. Your recently used emojis are saved locally so they are always at hand.</p>

    <div class="card reveal in-view">
        <div class="card-body">
            <input type="text" id="emoji-search" placeholder="Search emojis by name or keyword..." autocomplete="off">
            <div style="margin-top:10px;display:flex;align-items:center;flex-wrap:wrap;gap:10px">
                <span style="font-size:.75rem;color:#888">Skin tone:</span>
                <div class="skin-tone-bar" id="skin-tone-bar"></div>
            </div>
            <div class="cat-tabs" id="cat-tabs"></div>
            <div class="emoji-grid" id="emoji-grid"></div>
            <div class="no-results" id="no-results">No emojis found matching your search.</div>
        </div>
    </div>

    <div class="detail-panel reveal in-view" id="detail-panel">
        <div style="display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap">
            <div><div class="detail-big" id="detail-emoji"></div></div>
            <div style="flex:1;min-width:280px">
                <div style="font-size:1.1rem;font-weight:600;margin-bottom:4px" id="detail-name"></div>
                <div class="detail-row"><span class="detail-label">Unicode</span><span class="detail-val" id="detail-unicode"></span><button class="copy-btn" onclick="copyField('detail-unicode')">Copy</button></div>
                <div class="detail-row"><span class="detail-label">HTML (dec)</span><span class="detail-val" id="detail-html-dec"></span><button class="copy-btn" onclick="copyField('detail-html-dec')">Copy</button></div>
                <div class="detail-row"><span class="detail-label">HTML (hex)</span><span class="detail-val" id="detail-html-hex"></span><button class="copy-btn" onclick="copyField('detail-html-hex')">Copy</button></div>
                <div class="detail-row"><span class="detail-label">JS escape</span><span class="detail-val" id="detail-js"></span><button class="copy-btn" onclick="copyField('detail-js')">Copy</button></div>
                <div class="detail-row"><span class="detail-label">Short name</span><span class="detail-val" id="detail-short"></span><button class="copy-btn" onclick="copyField('detail-short')">Copy</button></div>
                <div style="margin-top:12px"><button class="copy-btn" style="padding:8px 20px;font-size:.9rem" onclick="copyEmoji()">Copy Emoji</button></div>
            </div>
        </div>
    </div>

    <div class="card reveal in-view" style="margin-top:16px">
        <div class="card-body">
            <div style="font-size:.85rem;color:#888;margin-bottom:2px">Recently Used</div>
            <div class="recent-emojis" id="recent-emojis"><span style="color:#555;font-size:.8rem">No emojis used yet</span></div>
        </div>
    </div>

    <h2 class="h6 mt-4 reveal in-view" style="border-bottom:1px solid var(--line);padding-bottom:.5rem;">How to use</h2>
    <p class="text-secondary small reveal in-view">Browse categories or type a keyword in the search box to filter emojis live. Click an emoji to reveal its full details — Unicode codepoint, HTML entities, JavaScript escape string, and short name. Use the skin tone selector before picking an emoji to apply a tone modifier. All copy operations happen instantly in your browser; nothing is sent to any server.</p>
</div>

<div class="copy-toast" id="copy-toast">Copied!</div>

<script>
(function(){
var skinTone=0;
var skinMod=['\u{1F3FB}','\u{1F3FC}','\u{1F3FD}','\u{1F3FE}','\u{1F3FF}'];
var toneNames=['Default','Light','Medium-Light','Medium','Medium-Dark','Dark'];
var modifiable=new Set(['\u{1F44D}','\u{1F44E}','\u{1F44B}','\u{1F44F}','\u{1F64C}','\u{1F446}','\u{1F447}','\u{1F449}','\u{1F448}','\u{1F44C}','\u{1F91F}','\u{1F918}','\u{1F44A}','\u{270B}','\u{1F90F}','\u{270A}','\u{1F91A}','\u{1F590}','\u{1FAF6}','\u{1F9E1}','\u{1F485}','\u{1F483}','\u{1F481}','\u{1F646}','\u{1F647}','\u{1F645}','\u{1F64B}','\u{1F64D}','\u{1F64E}','\u{1F47C}','\u{1F478}','\u{1F9D4}','\u{1F471}','\u{1F469}','\u{1F468}','\u{1F467}','\u{1F466}','\u{1F9D1}','\u{1F9D2}']);
var catNames=['Smileys','People','Animals','Food','Travel','Activities','Objects','Symbols','Flags'];
var catIcons=['\u{1F600}','\u{1F476}','\u{1F436}','\u{1F34E}','\u{1F697}','\u{26BD}','\u{1F4A1}','\u{2764}\u{FE0F}','\u{1F3F3}\u{FE0F}'];
var catLabels=['Smileys &amp; Face','People &amp; Body','Animals &amp; Nature','Food &amp; Drink','Travel &amp; Places','Activities','Objects','Symbols','Flags'];
var db=[{c:"Smileys",n:"Grinning Face",s:":grinning:",e:"😀"},{c:"Smileys",n:"Beaming Face",s:":beaming:",e:"😄"},{c:"Smileys",n:"Grinning Squinting",s:":grinning_squinting:",e:"😆"},{c:"Smileys",n:"Grinning with Sweat",s:":grinning_sweat:",e:"😅"},{c:"Smileys",n:"Open Mouth Smile",s:":open_mouth:",e:"😃"},{c:"Smileys",n:"Heart-Eyes",s:":heart_eyes:",e:"😍"},{c:"Smileys",n:"Star-Struck",s:":star_struck:",e:"🤩"},{c:"Smileys",n:"Blowing Kiss",s:":kissing_heart:",e:"😘"},{c:"Smileys",n:"Kissing Closed Eyes",s:":kissing_closed:",e:"😚"},{c:"Smileys",n:"Kissing",s:":kissing:",e:"😗"},{c:"Smileys",n:"Winking Face",s:":wink:",e:"😉"},{c:"Smileys",n:"Smiling Eyes",s:":blush:",e:"😊"},{c:"Smileys",n:"Halo Smile",s:":innocent:",e:"😇"},{c:"Smileys",n:"Sunglasses Smile",s:":sunglasses:",e:"😎"},{c:"Smileys",n:"Smiling with Hearts",s:":smiling_hearts:",e:"🥰"},{c:"Smileys",n:"Smiling with Tear",s:":smiling_tear:",e:"🥲"},{c:"Smileys",n:"Savouring Food",s:":yum:",e:"😋"},{c:"Smileys",n:"Tongue Out",s:":stuck_out_tongue:",e:"😛"},{c:"Smileys",n:"Winking Tongue",s:":stuck_out_tongue_wink:",e:"😜"},{c:"Smileys",n:"Medical Mask",s:":mask:",e:"😷"},{c:"Smileys",n:"Thermometer Face",s:":thermometer_face:",e:"🤒"},{c:"Smileys",n:"Head-Bandage",s:":bandage_face:",e:"🤕"},{c:"Smileys",n:"Nauseated",s:":nauseated:",e:"🤢"},{c:"Smileys",n:"Vomiting",s:":vomiting:",e:"🤮"},{c:"Smileys",n:"Sneezing",s:":sneezing:",e:"🤧"},{c:"Smileys",n:"Hot Face",s:":hot_face:",e:"🥵"},{c:"Smileys",n:"Cold Face",s:":cold_face:",e:"🥶"},{c:"Smileys",n:"Woozy Face",s:":woozy_face:",e:"🥴"},{c:"Smileys",n:"Rolling Eyes",s:":roll_eyes:",e:"🙄"},{c:"Smileys",n:"Thinking",s:":thinking:",e:"🤔"},{c:"Smileys",n:"Monocle",s:":monocle:",e:"🧐"},{c:"Smileys",n:"Zipper Mouth",s:":zipper_mouth:",e:"🤐"},{c:"Smileys",n:"Raised Eyebrow",s:":raised_eyebrow:",e:"🤨"},{c:"Smileys",n:"Shushing",s:":shushing:",e:"🤫"},{c:"Smileys",n:"Symbols on Mouth",s:":symbols_mouth:",e:"🤬"},{c:"Smileys",n:"Angry Face",s:":angry:",e:"😠"},{c:"Smileys",n:"Pouting",s:":rage:",e:"😡"},{c:"Smileys",n:"Crying",s:":cry:",e:"😢"},{c:"Smileys",n:"Loudly Crying",s:":sob:",e:"😭"},{c:"Smileys",n:"Confounded",s:":confounded:",e:"😖"},{c:"Smileys",n:"Disappointed",s:":disappointed:",e:"😞"},{c:"Smileys",n:"Worried",s:":worried:",e:"😟"},{c:"Smileys",n:"Frowning Open",s:":frowning_open:",e:"😦"},{c:"Smileys",n:"Anguished",s:":anguished:",e:"😧"},{c:"Smileys",n:"Fearful",s:":fearful:",e:"😨"},{c:"Smileys",n:"Weary",s:":weary:",e:"😩"},{c:"Smileys",n:"Sleepy",s:":sleepy:",e:"😪"},{c:"Smileys",n:"Tired",s:":tired:",e:"😫"},{c:"Smileys",n:"Grimacing",s:":grimacing:",e:"😬"},{c:"Smileys",n:"Exploding Head",s:":exploding_head:",e:"🤯"},{c:"Smileys",n:"Astonished",s:":astonished:",e:"😲"},{c:"Smileys",n:"Sleeping",s:":sleeping:",e:"😴"},{c:"Smileys",n:"Dizzy",s:":dizzy_face:",e:"😵"},{c:"Smileys",n:"Dummy",s:":dummy:",e:"🤭"},{c:"Smileys",n:"Money-Mouth",s:":money_mouth:",e:"🤑"},{c:"Smileys",n:"Cowboy Hat",s:":cowboy:",e:"🤠"},{c:"Smileys",n:"Partying",s:":partying:",e:"🥳"},{c:"Smileys",n:"Disguised",s:":disguised:",e:"🥸"},{c:"Smileys",n:"Nerd Face",s:":nerd:",e:"🤓"},{c:"Smileys",n:"Pleading",s:":pleading:",e:"🥺"},{c:"Smileys",n:"Clown Face",s:":clown:",e:"🤡"},{c:"Smileys",n:"Lying",s:":lying:",e:"🤥"},{c:"Smileys",n:"Skull",s:":skull:",e:"💀"},{c:"Smileys",n:"Ghost",s:":ghost:",e:"👻"},{c:"Smileys",n:"Alien",s:":alien:",e:"👽"},{c:"Smileys",n:"Alien Monster",s:":alien_monster:",e:"👾"},{c:"Smileys",n:"Robot",s:":robot:",e:"🤖"},{c:"Smileys",n:"Pile of Poo",s:":poop:",e:"💩"},{c:"Smileys",n:"Red Heart",s:":heart:",e:"❤️"},{c:"Smileys",n:"Orange Heart",s:":orange_heart:",e:"🧡"},{c:"Smileys",n:"Yellow Heart",s:":yellow_heart:",e:"💛"},{c:"Smileys",n:"Green Heart",s:":green_heart:",e:"💚"},{c:"Smileys",n:"Blue Heart",s:":blue_heart:",e:"💙"},{c:"Smileys",n:"Purple Heart",s:":purple_heart:",e:"💜"},{c:"Smileys",n:"Black Heart",s:":black_heart:",e:"🖤"},{c:"Smileys",n:"Broken Heart",s:":broken_heart:",e:"💔"},{c:"Smileys",n:"Two Hearts",s:":two_hearts:",e:"💕"},{c:"Smileys",n:"Revolving Hearts",s:":revolving_hearts:",e:"💞"},{c:"Smileys",n:"Sparkling Heart",s:":sparkling_heart:",e:"💖"},{c:"Smileys",n:"Beating Heart",s:":beating_heart:",e:"💓"},{c:"Smileys",n:"Hundred Points",s:":100:",e:"💯"},{c:"Smileys",n:"Anger Symbol",s:":anger:",e:"💢"},{c:"Smileys",n:"Zzz",s:":zzz:",e:"💤"},{c:"Smileys",n:"Fire",s:":fire:",e:"🔥"},{c:"Smileys",n:"Sparkles",s:":sparkles:",e:"✨"},{c:"Smileys",n:"Star",s:":star:",e:"⭐"},{c:"Smileys",n:"Rainbow",s:":rainbow:",e:"🌈"},{c:"Smileys",n:"Cloud",s:":cloud:",e:"☁️"},{c:"Smileys",n:"Speech Balloon",s:":speech_balloon:",e:"💬"},{c:"Smileys",n:"Jack-O-Lantern",s:":jack_o_lantern:",e:"🎃"},{c:"Smileys",n:"Droplet",s:":droplet:",e:"💧"},{c:"Smileys",n:"Ocean Wave",s:":ocean:",e:"🌊"},{c:"Smileys",n:"Glowing Star",s:":glowing_star:",e:"🌟"},{c:"Smileys",n:"Thought Balloon",s:":thought_balloon:",e:"💭"},{c:"Smileys",n:"Collision",s:":collision:",e:"💥"},{c:"Smileys",n:"Sweat Droplets",s:":sweat_droplets:",e:"💦"},{c:"Smileys",n:"Dash",s:":dash:",e:"💨"},{c:"Smileys",n:"Kiss Mark",s:":kiss_mark:",e:"💋"},{c:"Smileys",n:"Love Letter",s:":love_letter:",e:"💌"},{c:"Smileys",n:"Growing Heart",s:":growing_heart:",e:"💗"},{c:"Smileys",n:"Heart with Arrow",s:":heart_arrow:",e:"💘"},{c:"Smileys",n:"Heart with Ribbon",s:":heart_ribbon:",e:"💝"},{c:"Smileys",n:"Heart Decoration",s:":heart_decoration:",e:"💟"},{c:"Smileys",n:"Heart Exclamation",s:":heart_exclamation:",e:"❣️"},{c:"Smileys",n:"Brown Heart",s:":brown_heart:",e:"🤎"},{c:"Smileys",n:"White Heart",s:":white_heart:",e:"🤍"},{c:"Smileys",n:"Crying Cat",s:":crying_cat:",e:"😿"},{c:"Smileys",n:"Weary Cat",s:":weary_cat:",e:"🙀"},{c:"Smileys",n:"Cat Wry Smile",s:":cat_wry_smile:",e:"😼"},{c:"Smileys",n:"Joy Cat",s:":joy_cat:",e:"😹"},{c:"Smileys",n:"Heart Eyes Cat",s:":heart_eyes_cat:",e:"😻"},{c:"Smileys",n:"Pouting Cat",s:":pouting_cat:",e:"😾"},{c:"Smileys",n:"See-No-Evil",s:":see_no_evil:",e:"🙈"},{c:"Smileys",n:"Hear-No-Evil",s:":hear_no_evil:",e:"🙉"},{c:"Smileys",n:"Speak-No-Evil",s:":speak_no_evil:",e:"🙊"},{c:"Smileys",n:"Face with Steam",s:":face_steam:",e:"😤"},{c:"Smileys",n:"Neutral Face",s:":neutral_face:",e:"😐"},{c:"Smileys",n:"Expressionless",s:":expressionless:",e:"😑"},{c:"Smileys",n:"Unamused",s:":unamused:",e:"😒"},{c:"People",n:"Baby",s:":baby:",e:"👶"},{c:"People",n:"Child",s:":child:",e:"🧒"},{c:"People",n:"Boy",s:":boy:",e:"👦"},{c:"People",n:"Girl",s:":girl:",e:"👧"},{c:"People",n:"Man",s:":man:",e:"👨"},{c:"People",n:"Woman",s:":woman:",e:"👩"},{c:"People",n:"Old Man",s:":old_man:",e:"👴"},{c:"People",n:"Old Woman",s:":old_woman:",e:"👵"},{c:"People",n:"Blond Hair",s:":blond_hair:",e:"👱"},{c:"People",n:"Man with Beard",s:":bearded:",e:"🧔"},{c:"People",n:"Headscarf",s:":headscarf:",e:"🧕"},{c:"People",n:"Pregnant",s:":pregnant:",e:"🤰"},{c:"People",n:"Breast-Feeding",s:":breast_feeding:",e:"🤱"},{c:"People",n:"Person Pouting",s:":person_pouting:",e:"🙎"},{c:"People",n:"No Good",s:":no_good:",e:"🙅"},{c:"People",n:"OK Person",s:":ok_person:",e:"🙆"},{c:"People",n:"Raising Hand",s:":raising_hand:",e:"🙋"},{c:"People",n:"Bow",s:":bow:",e:"🙇"},{c:"People",n:"Facepalm",s:":facepalm:",e:"🤦"},{c:"People",n:"Shrug",s:":shrug:",e:"🤷"},{c:"People",n:"Tuxedo",s:":tuxedo:",e:"🤵"},{c:"People",n:"Veil",s:":veil:",e:"🤶"},{c:"People",n:"Massage",s:":massage:",e:"💆"},{c:"People",n:"Haircut",s:":haircut:",e:"💇"},{c:"People",n:"Walking",s:":walking:",e:"🚶"},{c:"People",n:"Running",s:":running:",e:"🏃"},{c:"People",n:"Dancer",s:":dancer:",e:"💃"},{c:"People",n:"Man Dancing",s:":man_dancing:",e:"🕺"},{c:"People",n:"Lotus Position",s:":lotus:",e:"🧘"},{c:"People",n:"Bath",s:":bath:",e:"🚿"},{c:"People",n:"In Bed",s:":sleeping_bed:",e:"🛌"},{c:"People",n:"Fist",s:":fist:",e:"✊"},{c:"People",n:"Wave",s:":wave:",e:"👋"},{c:"People",n:"OK Hand",s:":ok_hand:",e:"👌"},{c:"People",n:"Thumbs Up",s:":thumbsup:",e:"👍"},{c:"People",n:"Thumbs Down",s:":thumbsdown:",e:"👎"},{c:"People",n:"Clap",s:":clap:",e:"👏"},{c:"People",n:"Open Hands",s:":open_hands:",e:"👐"},{c:"People",n:"Pray",s:":pray:",e:"🙏"},{c:"People",n:"Writing Hand",s:":writing_hand:",e:"✍️"},{c:"People",n:"Muscle",s:":muscle:",e:"💪"},{c:"People",n:"Selfie",s:":selfie:",e:"🤳"},{c:"People",n:"Ring",s:":ring:",e:"💍"},{c:"People",n:"Crown",s:":crown:",e:"👑"},{c:"People",n:"T-Shirt",s:":tshirt:",e:"👕"},{c:"People",n:"Glasses",s:":glasses:",e:"👓"},{c:"People",n:"Womans Hat",s:":womans_hat:",e:"👒"},{c:"People",n:"High Heel",s:":high_heel:",e:"👠"},{c:"People",n:"Lipstick",s:":lipstick:",e:"💄"},{c:"People",n:"Purse",s:":purse:",e:"👜"},{c:"People",n:"Handbag",s:":handbag:",e:"👝"},{c:"People",n:"Mans Shoe",s:":mans_shoe:",e:"👞"},{c:"People",n:"Athletic Shoe",s:":athletic_shoe:",e:"👟"},{c:"People",n:"Sandal",s:":sandal:",e:"👡"},{c:"People",n:"Boot",s:":boot:",e:"👢"},{c:"People",n:"Dress",s:":dress:",e:"👗"},{c:"People",n:"Kimono",s:":kimono:",e:"👘"},{c:"People",n:"Bikini",s:":bikini:",e:"👙"},{c:"People",n:"Top Hat",s:":tophat:",e:"🎩"},{c:"People",n:"Graduation Cap",s:":mortar_board:",e:"🎓"},{c:"People",n:"Lipstick Kiss",s:":lipstick_kiss:",e:"💋"},{c:"People",n:"Brain",s:":brain:",e:"🧠"},{c:"People",n:"Eye",s:":eye:",e:"👁"},{c:"People",n:"Tongue",s:":tongue:",e:"👅"},{c:"People",n:"Mouth",s:":mouth:",e:"👄"},{c:"People",n:"Ear",s:":ear:",e:"👂"},{c:"People",n:"Nose",s:":nose:",e:"👃"},{c:"Animals",n:"Dog",s:":dog:",e:"🐶"},{c:"Animals",n:"Cat",s:":cat:",e:"🐱"},{c:"Animals",n:"Mouse",s:":mouse:",e:"🐭"},{c:"Animals",n:"Hamster",s:":hamster:",e:"🐹"},{c:"Animals",n:"Rabbit",s:":rabbit:",e:"🐰"},{c:"Animals",n:"Fox",s:":fox:",e:"🦊"},{c:"Animals",n:"Bear",s:":bear:",e:"🐻"},{c:"Animals",n:"Panda",s:":panda:",e:"🐼"},{c:"Animals",n:"Koala",s:":koala:",e:"🐸"},{c:"Animals",n:"Tiger",s:":tiger:",e:"🐯"},{c:"Animals",n:"Lion",s:":lion:",e:"🦁"},{c:"Animals",n:"Leopard",s:":leopard:",e:"🐆"},{c:"Animals",n:"Cow",s:":cow:",e:"🐮"},{c:"Animals",n:"Pig",s:":pig:",e:"🐷"},{c:"Animals",n:"Pig Nose",s:":pig_nose:",e:"🐺"},{c:"Animals",n:"Frog",s:":frog:",e:"🐸"},{c:"Animals",n:"Monkey Face",s:":monkey_face:",e:"🐵"},{c:"Animals",n:"Monkey",s:":monkey:",e:"🐒"},{c:"Animals",n:"Chicken",s:":chicken:",e:"🐔"},{c:"Animals",n:"Rooster",s:":rooster:",e:"🐓"},{c:"Animals",n:"Baby Chick",s:":baby_chick:",e:"🐤"},{c:"Animals",n:"Hatched Chick",s:":hatched_chick:",e:"🐥"},{c:"Animals",n:"Bird",s:":bird:",e:"🐦"},{c:"Animals",n:"Penguin",s:":penguin:",e:"🐧"},{c:"Animals",n:"Dove",s:":dove:",e:"🕊️"},{c:"Animals",n:"Eagle",s:":eagle:",e:"🦅"},{c:"Animals",n:"Duck",s:":duck:",e:"🦆"},{c:"Animals",n:"Owl",s:":owl:",e:"🦉"},{c:"Animals",n:"Crocodile",s:":crocodile:",e:"🐊"},{c:"Animals",n:"Turtle",s:":turtle:",e:"🐢"},{c:"Animals",n:"Lizard",s:":lizard:",e:"🦎"},{c:"Animals",n:"Snake",s:":snake:",e:"🐍"},{c:"Animals",n:"Dragon Face",s:":dragon_face:",e:"🐲"},{c:"Animals",n:"Dragon",s:":dragon:",e:"🐉"},{c:"Animals",n:"Dinosaur",s:":dinosaur:",e:"🦕"},{c:"Animals",n:"T-Rex",s:":t_rex:",e:"🦖"},{c:"Animals",n:"Whale",s:":whale:",e:"🐋"},{c:"Animals",n:"Dolphin",s:":dolphin:",e:"🐬"},{c:"Animals",n:"Fish",s:":fish:",e:"🐟"},{c:"Animals",n:"Tropical Fish",s:":tropical_fish:",e:"🐠"},{c:"Animals",n:"Blowfish",s:":blowfish:",e:"🐡"},{c:"Animals",n:"Shark",s:":shark:",e:"🦈"},{c:"Animals",n:"Octopus",s:":octopus:",e:"🐙"},{c:"Animals",n:"Shell",s:":shell:",e:"🐚"},{c:"Animals",n:"Crab",s:":crab:",e:"🦀"},{c:"Animals",n:"Shrimp",s:":shrimp:",e:"🦐"},{c:"Animals",n:"Squid",s:":squid:",e:"🦑"},{c:"Animals",n:"Snail",s:":snail:",e:"🐌"},{c:"Animals",n:"Bug",s:":bug:",e:"🐛"},{c:"Animals",n:"Ant",s:":ant:",e:"🐜"},{c:"Animals",n:"Bee",s:":bee:",e:"🐝"},{c:"Animals",n:"Lady Beetle",s:":lady_beetle:",e:"🐞"},{c:"Animals",n:"Cricket",s:":cricket:",e:"🦗"},{c:"Animals",n:"Spider",s:":spider:",e:"🕷️"},{c:"Animals",n:"Spider Web",s:":spider_web:",e:"🕸️"},{c:"Animals",n:"Scorpion",s:":scorpion:",e:"🦂"},{c:"Animals",n:"Bouquet",s:":bouquet:",e:"💐"},{c:"Animals",n:"Cherry Blossom",s:":cherry_blossom:",e:"🌸"},{c:"Animals",n:"White Flower",s:":white_flower:",e:"💮"},{c:"Animals",n:"Rose",s:":rose:",e:"🌹"},{c:"Animals",n:"Wilted Flower",s:":wilted_flower:",e:"🥀"},{c:"Animals",n:"Hibiscus",s:":hibiscus:",e:"🌺"},{c:"Animals",n:"Sunflower",s:":sunflower:",e:"🌻"},{c:"Animals",n:"Blossom",s:":blossom:",e:"🌼"},{c:"Animals",n:"Tulip",s:":tulip:",e:"🌷"},{c:"Animals",n:"Seedling",s:":seedling:",e:"🌱"},{c:"Animals",n:"Evergreen Tree",s:":evergreen:",e:"🌲"},{c:"Animals",n:"Deciduous Tree",s:":deciduous:",e:"🌳"},{c:"Animals",n:"Palm Tree",s:":palm_tree:",e:"🌴"},{c:"Animals",n:"Cactus",s:":cactus:",e:"🌵"},{c:"Animals",n:"Ear of Rice",s:":ear_of_rice:",e:"🌾"},{c:"Animals",n:"Four Leaf Clover",s:":clover:",e:"🍀"},{c:"Animals",n:"Maple Leaf",s:":maple_leaf:",e:"🍁"},{c:"Animals",n:"Fallen Leaf",s:":fallen_leaf:",e:"🍂"},{c:"Animals",n:"Mushroom",s:":mushroom:",e:"🍄"},{c:"Food",n:"Green Apple",s:":green_apple:",e:"🍏"},{c:"Food",n:"Red Apple",s:":apple:",e:"🍎"},{c:"Food",n:"Pear",s:":pear:",e:"🍐"},{c:"Food",n:"Tangerine",s:":tangerine:",e:"🍊"},{c:"Food",n:"Lemon",s:":lemon:",e:"🍋"},{c:"Food",n:"Banana",s:":banana:",e:"🍌"},{c:"Food",n:"Watermelon",s:":watermelon:",e:"🍉"},{c:"Food",n:"Grapes",s:":grapes:",e:"🍇"},{c:"Food",n:"Strawberry",s:":strawberry:",e:"🍓"},{c:"Food",n:"Melon",s:":melon:",e:"🍈"},{c:"Food",n:"Peach",s:":peach:",e:"🍑"},{c:"Food",n:"Cherries",s:":cherries:",e:"🍒"},{c:"Food",n:"Pineapple",s:":pineapple:",e:"🍍"},{c:"Food",n:"Kiwi Fruit",s:":kiwi:",e:"🥝"},{c:"Food",n:"Mango",s:":mango:",e:"🥭"},{c:"Food",n:"Avocado",s:":avocado:",e:"🥑"},{c:"Food",n:"Tomato",s:":tomato:",e:"🍅"},{c:"Food",n:"Coconut",s:":coconut:",e:"🥥"},{c:"Food",n:"Eggplant",s:":eggplant:",e:"🍆"},{c:"Food",n:"Potato",s:":potato:",e:"🥔"},{c:"Food",n:"Carrot",s:":carrot:",e:"🥕"},{c:"Food",n:"Corn",s:":corn:",e:"🌽"},{c:"Food",n:"Hot Pepper",s:":hot_pepper:",e:"🌶️"},{c:"Food",n:"Cucumber",s:":cucumber:",e:"🥒"},{c:"Food",n:"Broccoli",s:":broccoli:",e:"🥦"},{c:"Food",n:"Peanuts",s:":peanuts:",e:"🥜"},{c:"Food",n:"Bread",s:":bread:",e:"🍞"},{c:"Food",n:"Croissant",s:":croissant:",e:"🥐"},{c:"Food",n:"Baguette",s:":baguette:",e:"🥖"},{c:"Food",n:"Pretzel",s:":pretzel:",e:"🥨"},{c:"Food",n:"Cheese",s:":cheese:",e:"🧀"},{c:"Food",n:"Egg",s:":egg:",e:"🥚"},{c:"Food",n:"Cooking",s:":cooking:",e:"🍳"},{c:"Food",n:"Bacon",s:":bacon:",e:"🥓"},{c:"Food",n:"Pancakes",s:":pancakes:",e:"🥞"},{c:"Food",n:"Meat on Bone",s:":meat_bone:",e:"🍖"},{c:"Food",n:"Poultry Leg",s:":poultry_leg:",e:"🍗"},{c:"Food",n:"Hamburger",s:":hamburger:",e:"🍔"},{c:"Food",n:"French Fries",s:":fries:",e:"🍟"},{c:"Food",n:"Pizza",s:":pizza:",e:"🍕"},{c:"Food",n:"Hot Dog",s:":hot_dog:",e:"🌭"},{c:"Food",n:"Sandwich",s:":sandwich:",e:"🥪"},{c:"Food",n:"Taco",s:":taco:",e:"🌮"},{c:"Food",n:"Burrito",s:":burrito:",e:"🌯"},{c:"Food",n:"Salad",s:":salad:",e:"🥗"},{c:"Food",n:"Popcorn",s:":popcorn:",e:"🍿"},{c:"Food",n:"Salt",s:":salt:",e:"🧂"},{c:"Food",n:"Bento Box",s:":bento:",e:"🍱"},{c:"Food",n:"Rice Ball",s:":rice_ball:",e:"🍙"},{c:"Food",n:"Rice",s:":rice:",e:"🍚"},{c:"Food",n:"Curry",s:":curry:",e:"🍛"},{c:"Food",n:"Ramen",s:":ramen:",e:"🍜"},{c:"Food",n:"Spaghetti",s:":spaghetti:",e:"🍝"},{c:"Food",n:"Sushi",s:":sushi:",e:"🍣"},{c:"Food",n:"Shrimp Tempura",s:":tempura:",e:"🍤"},{c:"Food",n:"Dango",s:":dango:",e:"🍡"},{c:"Food",n:"Ice Cream",s:":ice_cream:",e:"🍦"},{c:"Food",n:"Shaved Ice",s:":shaved_ice:",e:"🍧"},{c:"Food",n:"Doughnut",s:":doughnut:",e:"🍩"},{c:"Food",n:"Cookie",s:":cookie:",e:"🍪"},{c:"Food",n:"Birthday Cake",s:":birthday_cake:",e:"🎂"},{c:"Food",n:"Shortcake",s:":shortcake:",e:"🍰"},{c:"Food",n:"Cupcake",s:":cupcake:",e:"🧁"},{c:"Food",n:"Pie",s:":pie:",e:"🥧"},{c:"Food",n:"Chocolate Bar",s:":chocolate:",e:"🍫"},{c:"Food",n:"Candy",s:":candy:",e:"🍬"},{c:"Food",n:"Lollipop",s:":lollipop:",e:"🍭"},{c:"Food",n:"Custard",s:":custard:",e:"🍮"},{c:"Food",n:"Honey Pot",s:":honey_pot:",e:"🍯"},{c:"Food",n:"Baby Bottle",s:":baby_bottle:",e:"🍼"},{c:"Food",n:"Coffee",s:":coffee:",e:"☕"},{c:"Food",n:"Tea",s:":tea:",e:"🍵"},{c:"Food",n:"Sake",s:":sake:",e:"🍶"},{c:"Food",n:"Champagne",s:":champagne:",e:"🍾"},{c:"Food",n:"Wine Glass",s:":wine_glass:",e:"🍷"},{c:"Food",n:"Cocktail",s:":cocktail:",e:"🍸"},{c:"Food",n:"Tropical Drink",s:":tropical_drink:",e:"🍹"},{c:"Food",n:"Beer",s:":beer:",e:"🍺"},{c:"Food",n:"Clinking Beers",s:":beers:",e:"🍻"},{c:"Food",n:"Clinking Glasses",s:":clinking_glasses:",e:"🥂"},{c:"Food",n:"Tumbler Glass",s:":tumbler_glass:",e:"🥃"},{c:"Travel",n:"Automobile",s:":car:",e:"🚗"},{c:"Travel",n:"Taxi",s:":taxi:",e:"🚕"},{c:"Travel",n:"Blue Car",s:":blue_car:",e:"🚙"},{c:"Travel",n:"Bus",s:":bus:",e:"🚌"},{c:"Travel",n:"Trolleybus",s:":trolleybus:",e:"🚎"},{c:"Travel",n:"Race Car",s:":race_car:",e:"🏎️"},{c:"Travel",n:"Police Car",s:":police_car:",e:"🚓"},{c:"Travel",n:"Ambulance",s:":ambulance:",e:"🚑"},{c:"Travel",n:"Fire Engine",s:":fire_engine:",e:"🚒"},{c:"Travel",n:"Minibus",s:":minibus:",e:"🚐"},{c:"Travel",n:"Truck",s:":truck:",e:"🚚"},{c:"Travel",n:"Lorry",s:":lorry:",e:"🚛"},{c:"Travel",n:"Tractor",s:":tractor:",e:"🚜"},{c:"Travel",n:"Bicycle",s:":bike:",e:"🚲"},{c:"Travel",n:"Motor Scooter",s:":motor_scooter:",e:"🛵"},{c:"Travel",n:"Motorcycle",s:":motorcycle:",e:"🏍️"},{c:"Travel",n:"Rotating Light",s:":rotating_light:",e:"🚨"},{c:"Travel",n:"Tram",s:":tram:",e:"🚊"},{c:"Travel",n:"Monorail",s:":monorail:",e:"🚝"},{c:"Travel",n:"Mountain Railway",s:":mountain_railway:",e:"🚞"},{c:"Travel",n:"Bullet Train",s:":bullet_train:",e:"🚄"},{c:"Travel",n:"Light Rail",s:":light_rail:",e:"🚈"},{c:"Travel",n:"Station",s:":station:",e:"🚉"},{c:"Travel",n:"Locomotive",s:":locomotive:",e:"🚂"},{c:"Travel",n:"Train",s:":train2:",e:"🚆"},{c:"Travel",n:"Metro",s:":metro:",e:"🚇"},{c:"Travel",n:"Railway Car",s:":railway_car:",e:"🚃"},{c:"Travel",n:"Streetcar",s:":streetcar:",e:"🚋"},{c:"Travel",n:"Airplane",s:":airplane:",e:"✈️"},{c:"Travel",n:"Small Airplane",s:":small_airplane:",e:"🛩️"},{c:"Travel",n:"Departure",s:":airplane_departure:",e:"🛫"},{c:"Travel",n:"Arrival",s:":airplane_arrival:",e:"🛬"},{c:"Travel",n:"Rocket",s:":rocket:",e:"🚀"},{c:"Travel",n:"Satellite",s:":satellite:",e:"🛰️"},{c:"Travel",n:"Helicopter",s:":helicopter:",e:"🚁"},{c:"Travel",n:"Canoe",s:":canoe:",e:"🛶"},{c:"Travel",n:"Speedboat",s:":speedboat:",e:"🚤"},{c:"Travel",n:"Passenger Ship",s:":passenger_ship:",e:"🛳️"},{c:"Travel",n:"Ferry",s:":ferry:",e:"⛴️"},{c:"Travel",n:"Ship",s:":ship:",e:"🚢"},{c:"Travel",n:"Anchor",s:":anchor:",e:"⚓"},{c:"Travel",n:"Fuel Pump",s:":fuelpump:",e:"⛽"},{c:"Travel",n:"Traffic Light",s:":traffic_light:",e:"🚥"},{c:"Travel",n:"Bus Stop",s:":busstop:",e:"🚏"},{c:"Travel",n:"Construction",s:":construction:",e:"🚧"},{c:"Travel",n:"World Map",s:":world_map:",e:"🗺️"},{c:"Travel",n:"Statue of Liberty",s:":liberty:",e:"🗽"},{c:"Travel",n:"Fountain",s:":fountain:",e:"⛲"},{c:"Travel",n:"Tokyo Tower",s:":tokyo_tower:",e:"🗼"},{c:"Travel",n:"Mount Fuji",s:":mount_fuji:",e:"🗯️"},{c:"Travel",n:"Sunset",s:":sunset:",e:"🌄"},{c:"Travel",n:"Sunrise",s:":sunrise:",e:"🌅"},{c:"Travel",n:"Milky Way",s:":milky_way:",e:"🌌"},{c:"Travel",n:"Night Stars",s:":night_stars:",e:"🌃"},{c:"Travel",n:"Bridge at Night",s:":bridge_night:",e:"🌉"},{c:"Travel",n:"Camping",s:":camping:",e:"🏕️"},{c:"Travel",n:"Tent",s:":tent:",e:"⛺"},{c:"Travel",n:"Beach",s:":beach:",e:"🏖️"},{c:"Travel",n:"Desert",s:":desert:",e:"🏜️"},{c:"Travel",n:"Island",s:":island:",e:"🏝️"},{c:"Travel",n:"National Park",s:":national_park:",e:"🏞️"},{c:"Travel",n:"Stadium",s:":stadium:",e:"🏟️"},{c:"Travel",n:"Classical Building",s:":classical:",e:"🏛️"},{c:"Travel",n:"Construction Site",s:":construction_site:",e:"🏗️"},{c:"Travel",n:"House",s:":house:",e:"🏠"},{c:"Travel",n:"House Garden",s:":house_garden:",e:"🏡"},{c:"Travel",n:"Office",s:":office:",e:"🏢"},{c:"Travel",n:"Hospital",s:":hospital:",e:"🏥"},{c:"Travel",n:"Bank",s:":bank:",e:"🏦"},{c:"Travel",n:"Hotel",s:":hotel:",e:"🏨"},{c:"Travel",n:"Love Hotel",s:":love_hotel:",e:"🏩"},{c:"Travel",n:"Convenience Store",s:":convenience_store:",e:"🏪"},{c:"Travel",n:"School",s:":school:",e:"🏫"},{c:"Travel",n:"Church",s:":church:",e:"🏪"},{c:"Travel",n:"Mosque",s:":mosque:",e:"🕌"},{c:"Travel",n:"Synagogue",s:":synagogue:",e:"🕍"},{c:"Travel",n:"Kaaba",s:":kaaba:",e:"🕋"},{c:"Travel",n:"Shinto Shrine",s:":shinto_shrine:",e:"⛩️"},{c:"Travel",n:"Ferris Wheel",s:":ferris_wheel:",e:"🎡"},{c:"Travel",n:"Roller Coaster",s:":roller_coaster:",e:"🎢"},{c:"Travel",n:"Barber Pole",s:":barber:",e:"💈"},{c:"Activities",n:"Soccer Ball",s:":soccer:",e:"⚽"},{c:"Activities",n:"Basketball",s:":basketball:",e:"🏀"},{c:"Activities",n:"American Football",s:":football:",e:"🏈"},{c:"Activities",n:"Baseball",s:":baseball:",e:"⚾"},{c:"Activities",n:"Tennis",s:":tennis:",e:"🎾"},{c:"Activities",n:"Volleyball",s:":volleyball:",e:"🏐"},{c:"Activities",n:"Rugby",s:":rugby:",e:"🏉"},{c:"Activities",n:"Golf",s:":golf:",e:"⛳"},{c:"Activities",n:"Billiards",s:":8ball:",e:"🎱"},{c:"Activities",n:"Ping Pong",s:":ping_pong:",e:"🏓"},{c:"Activities",n:"Badminton",s:":badminton:",e:"🏸"},{c:"Activities",n:"Ice Hockey",s:":hockey:",e:"🏒"},{c:"Activities",n:"Cricket Game",s:":cricket_game:",e:"🏏"},{c:"Activities",n:"Skier",s:":skier:",e:"⛷️"},{c:"Activities",n:"Ice Skate",s:":ice_skate:",e:"⛸️"},{c:"Activities",n:"Snowboarder",s:":snowboarder:",e:"🏂"},{c:"Activities",n:"Weight Lifter",s:":weight_lifter:",e:"🏋️"},{c:"Activities",n:"Surfer",s:":surfer:",e:"🏄"},{c:"Activities",n:"Swimmer",s:":swimmer:",e:"🏊"},{c:"Activities",n:"Person Playing Polo",s:":polo:",e:"🏓"},{c:"Activities",n:"Person Bouncing Ball",s:":bouncing_ball:",e:"⛹️"},{c:"Activities",n:"Person Golfing",s:":golfing:",e:"🏌️"},{c:"Activities",n:"Horse Racing",s:":horse_racing:",e:"🏇"},{c:"Activities",n:"Boxing Glove",s:":boxing:",e:"🥊"},{c:"Activities",n:"Martial Arts",s:":martial_arts:",e:"🥋"},{c:"Activities",n:"Trophy",s:":trophy:",e:"🏆"},{c:"Activities",n:"Sports Medal",s:":medal:",e:"🏅"},{c:"Activities",n:"First Place",s:":first_place:",e:"🥇"},{c:"Activities",n:"Second Place",s:":second_place:",e:"🥈"},{c:"Activities",n:"Third Place",s:":third_place:",e:"🥉"},{c:"Activities",n:"Microphone",s:":microphone:",e:"🎤"},{c:"Activities",n:"Headphones",s:":headphones:",e:"🎧"},{c:"Activities",n:"Musical Score",s:":musical_score:",e:"🎼"},{c:"Activities",n:"Musical Keyboard",s:":musical_keyboard:",e:"🎹"},{c:"Activities",n:"Drum",s:":drum:",e:"🥁"},{c:"Activities",n:"Saxophone",s:":saxophone:",e:"🎷"},{c:"Activities",n:"Trumpet",s:":trumpet:",e:"🎺"},{c:"Activities",n:"Guitar",s:":guitar:",e:"🎸"},{c:"Activities",n:"Violin",s:":violin:",e:"🎻"},{c:"Activities",n:"Game Die",s:":game_die:",e:"🎲"},{c:"Activities",n:"Chess Pawn",s:":chess:",e:"♟️"},{c:"Activities",n:"Bowling",s:":bowling:",e:"🎳"},{c:"Activities",n:"Video Game",s:":video_game:",e:"🎮"},{c:"Activities",n:"Slot Machine",s:":slot_machine:",e:"🎰"},{c:"Activities",n:"Joker",s:":joker:",e:"🃏"},{c:"Activities",n:"Mahjong",s:":mahjong:",e:"🀄"},{c:"Activities",n:"Fireworks",s:":fireworks:",e:"🎆"},{c:"Activities",n:"Sparkler",s:":sparkler:",e:"🎇"},{c:"Activities",n:"Balloon",s:":balloon:",e:"🎈"},{c:"Activities",n:"Party Popper",s:":party_popper:",e:"🎉"},{c:"Activities",n:"Confetti Ball",s:":confetti:",e:"🎊"},{c:"Activities",n:"Christmas Tree",s:":christmas_tree:",e:"🎄"},{c:"Activities",n:"Tanabata Tree",s:":tanabata:",e:"🎋"},{c:"Activities",n:"Pine Decoration",s:":pine_decoration:",e:"🎍"},{c:"Activities",n:"Japanese Dolls",s:":dolls:",e:"🎎"},{c:"Activities",n:"Carp Streamer",s:":carp_streamer:",e:"🎏"},{c:"Activities",n:"Wind Chime",s:":wind_chime:",e:"🎐"},{c:"Activities",n:"Moon Ceremony",s:":moon_ceremony:",e:"🎑"},{c:"Activities",n:"Red Envelope",s:":red_envelope:",e:"🧧"},{c:"Activities",n:"Ribbon",s:":ribbon:",e:"🎀"},{c:"Activities",n:"Gift",s:":gift:",e:"🎁"},{c:"Activities",n:"Ticket",s:":ticket:",e:"🎟️"},{c:"Activities",n:"Framed Picture",s:":framed_picture:",e:"🖼️"},{c:"Objects",n:"Watch",s:":watch:",e:"⌚"},{c:"Objects",n:"Alarm Clock",s:":alarm_clock:",e:"⏰"},{c:"Objects",n:"Stopwatch",s:":stopwatch:",e:"⏱️"},{c:"Objects",n:"Timer Clock",s:":timer_clock:",e:"⏲️"},{c:"Objects",n:"Mantelpiece Clock",s:":mantel_clock:",e:"⏳"},{c:"Objects",n:"Hourglass",s:":hourglass:",e:"⌛"},{c:"Objects",n:"Hourglass Flowing",s:":hourglass_flowing:",e:"⏳"},{c:"Objects",n:"Telephone",s:":telephone:",e:"☎️"},{c:"Objects",n:"Telephone Receiver",s:":telephone_receiver:",e:"📞"},{c:"Objects",n:"Mobile Phone",s:":iphone:",e:"📱"},{c:"Objects",n:"Mobile Arrow",s:":calling:",e:"📲"},{c:"Objects",n:"Pager",s:":pager:",e:"📟"},{c:"Objects",n:"Fax",s:":fax:",e:"📠"},{c:"Objects",n:"Battery",s:":battery:",e:"🔋"},{c:"Objects",n:"Electric Plug",s:":plug:",e:"🔌"},{c:"Objects",n:"Computer",s:":computer:",e:"💻"},{c:"Objects",n:"Desktop Computer",s:":desktop:",e:"🖥️"},{c:"Objects",n:"Printer",s:":printer:",e:"🖨️"},{c:"Objects",n:"Keyboard",s:":keyboard:",e:"⌨"},{c:"Objects",n:"Mouse Computer",s:":computer_mouse:",e:"🖱️"},{c:"Objects",n:"Trackball",s:":trackball:",e:"🖲️"},{c:"Objects",n:"Floppy Disk",s:":floppy_disk:",e:"💾"},{c:"Objects",n:"Optical Disc",s:":cd:",e:"💿"},{c:"Objects",n:"DVD",s:":dvd:",e:"📀"},{c:"Objects",n:"Camera",s:":camera:",e:"📷"},{c:"Objects",n:"Camera Flash",s:":camera_flash:",e:"📸"},{c:"Objects",n:"Video Camera",s:":video_camera:",e:"📹"},{c:"Objects",n:"Movie Camera",s:":movie_camera:",e:"🎬"},{c:"Objects",n:"Television",s:":tv:",e:"📺"},{c:"Objects",n:"Film Projector",s:":film_projector:",e:"📽️"},{c:"Objects",n:"Magnifying Glass",s:":mag:",e:"🔍"},{c:"Objects",n:"Light Bulb",s:":bulb:",e:"💡"},{c:"Objects",n:"Flashlight",s:":flashlight:",e:"🔦"},{c:"Objects",n:"Lantern",s:":izakaya_lantern:",e:"🏮"},{c:"Objects",n:"Candle",s:":candle:",e:"🕯️"},{c:"Objects",n:"Fire Extinguisher",s:":fire_extinguisher:",e:"🧯"},{c:"Objects",n:"Wastebasket",s:":wastebasket:",e:"🗑️"},{c:"Objects",n:"Money Bag",s:":moneybag:",e:"💰"},{c:"Objects",n:"Yen Banknote",s:":yen:",e:"💴"},{c:"Objects",n:"Dollar Banknote",s:":dollar:",e:"💵"},{c:"Objects",n:"Euro Banknote",s:":euro:",e:"💶"},{c:"Objects",n:"Pound Banknote",s:":pound:",e:"💷"},{c:"Objects",n:"Money Wings",s:":money_wings:",e:"💸"},{c:"Objects",n:"Credit Card",s:":credit_card:",e:"💳"},{c:"Objects",n:"Gem Stone",s:":gem:",e:"💎"},{c:"Objects",n:"Balance Scale",s:":balance_scale:",e:"⚖️"},{c:"Objects",n:"Wrench",s:":wrench:",e:"🔧"},{c:"Objects",n:"Hammer",s:":hammer:",e:"🔨"},{c:"Objects",n:"Hammer and Pick",s:":hammer_pick:",e:"⚒️"},{c:"Objects",n:"Hammer Wrench",s:":tools:",e:"🛠️"},{c:"Objects",n:"Pick",s:":pick:",e:"⛏️"},{c:"Objects",n:"Nut and Bolt",s:":nut_bolt:",e:"🔩"},{c:"Objects",n:"Gear",s:":gear:",e:"⚙️"},{c:"Objects",n:"Link",s:":link:",e:"🔗"},{c:"Objects",n:"Chains",s:":chains:",e:"⛓️"},{c:"Objects",n:"Magnet",s:":magnet:",e:"🧲"},{c:"Objects",n:"Scissors",s:":scissors:",e:"✂️"},{c:"Objects",n:"Sword",s:":sword:",e:"🗡️"},{c:"Objects",n:"Shield",s:":shield:",e:"🛡️"},{c:"Objects",n:"Smoking",s:":smoking:",e:"🚬"},{c:"Objects",n:"Coffin",s:":coffin:",e:"⚰️"},{c:"Objects",n:"Funeral Urn",s:":funeral_urn:",e:"⚱️"},{c:"Objects",n:"Crystal Ball",s:":crystal_ball:",e:"🔮"},{c:"Objects",n:"Prayer Beads",s:":prayer_beads:",e:"📿"},{c:"Objects",n:"Nazar Amulet",s:":nazar:",e:"🧿"},{c:"Objects",n:"Moyai",s:":moyai:",e:"🗿"},{c:"Objects",n:"Amphora",s:":amphora:",e:"🏺"},{c:"Objects",n:"Clutched Bag",s:":pouch:",e:"👝"},{c:"Objects",n:"Shopping Bags",s:":shopping:",e:"🛍️"},{c:"Objects",n:"School Satchel",s:":school_satchel:",e:"🎒"},{c:"Objects",n:"Jeans",s:":jeans:",e:"👖"},{c:"Objects",n:"Necktie",s:":necktie:",e:"👔"},{c:"Objects",n:"Womans Clothes",s:":womans_clothes:",e:"👚"},{c:"Objects",n:"Gloves",s:":gloves:",e:"🧤"},{c:"Objects",n:"Scarf",s:":scarf:",e:"🧣"},{c:"Objects",n:"Socks",s:":socks:",e:"🧦"},{c:"Objects",n:"Billed Cap",s:":billed_cap:",e:"🧢"},{c:"Symbols",n:"Red Circle",s:":red_circle:",e:"🔴"},{c:"Symbols",n:"Orange Circle",s:":orange_circle:",e:"🟠"},{c:"Symbols",n:"Yellow Circle",s:":yellow_circle:",e:"🟡"},{c:"Symbols",n:"Green Circle",s:":green_circle:",e:"🟢"},{c:"Symbols",n:"Blue Circle",s:":blue_circle:",e:"🔵"},{c:"Symbols",n:"Purple Circle",s:":purple_circle:",e:"🟣"},{c:"Symbols",n:"Brown Circle",s:":brown_circle:",e:"🟤"},{c:"Symbols",n:"Black Circle",s:":black_circle:",e:"⚫"},{c:"Symbols",n:"White Circle",s:":white_circle:",e:"⚪"},{c:"Symbols",n:"Red Square",s:":red_square:",e:"🟥"},{c:"Symbols",n:"Blue Square",s:":blue_square:",e:"🟦"},{c:"Symbols",n:"Orange Square",s:":orange_square:",e:"🟧"},{c:"Symbols",n:"Yellow Square",s:":yellow_square:",e:"🟨"},{c:"Symbols",n:"Green Square",s:":green_square:",e:"🟩"},{c:"Symbols",n:"Black Square",s:":black_square:",e:"⬛"},{c:"Symbols",n:"White Square",s:":white_square:",e:"⬜"},{c:"Symbols",n:"Large Orange Diamond",s:":large_orange_diamond:",e:"🔶"},{c:"Symbols",n:"Large Blue Diamond",s:":large_blue_diamond:",e:"🔷"},{c:"Symbols",n:"Small Orange Diamond",s:":small_orange_diamond:",e:"🔸"},{c:"Symbols",n:"Small Blue Diamond",s:":small_blue_diamond:",e:"🔹"},{c:"Symbols",n:"Red Triangle Up",s:":red_triangle:",e:"🔺"},{c:"Symbols",n:"Red Triangle Down",s:":red_triangle_down:",e:"🔻"},{c:"Symbols",n:"Diamond Shape Dot",s:":diamond_shape_dot:",e:"🔸"},{c:"Symbols",n:"Radio Button",s:":radio_button:",e:"🔘"},{c:"Symbols",n:"White Square Button",s:":white_square_button:",e:"🔳"},{c:"Symbols",n:"Black Square Button",s:":black_square_button:",e:"🔲"},{c:"Symbols",n:"Check Mark",s:":check_mark:",e:"✔️"},{c:"Symbols",n:"Check Box Check",s:":ballot_check:",e:"☑️"},{c:"Symbols",n:"Cross Mark",s:":cross_mark:",e:"❌"},{c:"Symbols",n:"Cross Mark Button",s:":cross_mark_btn:",e:"❎"},{c:"Symbols",n:"Question Mark",s:":question_mark:",e:"❓"},{c:"Symbols",n:"White Question",s:":white_question:",e:"❔"},{c:"Symbols",n:"Exclamation Mark",s:":exclamation:",e:"❗"},{c:"Symbols",n:"White Exclamation",s:":white_exclamation:",e:"❕"},{c:"Symbols",n:"Warning",s:":warning:",e:"⚠️"},{c:"Symbols",n:"Hot Springs",s:":hotsprings:",e:"♨️"},{c:"Symbols",n:"Recycle",s:":recycle:",e:"♻️"},{c:"Symbols",n:"Eight Spoked Asterisk",s:":eight_spoked:",e:"✳️"},{c:"Symbols",n:"Sparkle",s:":sparkle:",e:"❇️"},{c:"Symbols",n:"Globe Meridians",s:":globe:",e:"🌐"},{c:"Symbols",n:"Wheel of Dharma",s:":dharma:",e:"☸️"},{c:"Symbols",n:"Star of David",s:":star_of_david:",e:"✡️"},{c:"Symbols",n:"Orthodox Cross",s:":orthodox_cross:",e:"☦️"},{c:"Symbols",n:"Peace Symbol",s:":peace_symbol:",e:"☮️"},{c:"Symbols",n:"Menorah",s:":menorah:",e:"🕎"},{c:"Symbols",n:"Yin Yang",s:":yin_yang:",e:"☯️"},{c:"Symbols",n:"Atomic",s:":atom:",e:"⚛️"},{c:"Symbols",n:"Biohazard",s:":biohazard:",e:"☣️"},{c:"Symbols",n:"Radiation",s:":radiation:",e:"☢️"},{c:"Symbols",n:"High Voltage",s:":zap:",e:"⚡"},{c:"Symbols",n:"Star Crescent",s:":star_crescent:",e:"☪️"},{c:"Symbols",n:"Infinity",s:":infinity:",e:"♾️"},{c:"Symbols",n:"Ophiuchus",s:":ophiuchus:",e:"⛎"},{c:"Symbols",n:"Arrows Clockwise",s:":arrows_clockwise:",e:"🔃"},{c:"Symbols",n:"Counterclockwise",s:":counterclockwise:",e:"🔄"},{c:"Symbols",n:"A Button",s:":a_button:",e:"🅰️"},{c:"Symbols",n:"AB Button",s:":ab_button:",e:"🆎"},{c:"Symbols",n:"B Button",s:":b_button:",e:"🅱️"},{c:"Symbols",n:"CL Button",s:":cl_button:",e:"🆑"},{c:"Symbols",n:"COOL Button",s:":cool_button:",e:"🆒"},{c:"Symbols",n:"FREE Button",s:":free_button:",e:"🆓"},{c:"Symbols",n:"Information",s:":info:",e:"ℹ️"},{c:"Symbols",n:"ID Button",s:":id_button:",e:"🆔"},{c:"Symbols",n:"NG Button",s:":ng_button:",e:"🆕"},{c:"Symbols",n:"OK Button",s:":ok_button:",e:"🆖"},{c:"Symbols",n:"P Button",s:":p_button:",e:"🅿️"},{c:"Symbols",n:"SOS Button",s:":sos_button:",e:"🆘"},{c:"Symbols",n:"UP Button",s:":up_button:",e:"🆙"},{c:"Symbols",n:"VS Button",s:":vs_button:",e:"🆚"},{c:"Symbols",n:"Input Latin Upper",s:":abc:",e:"🔤"},{c:"Symbols",n:"Input Latin Lower",s:":abcd:",e:"🔡"},{c:"Symbols",n:"Input Symbols",s:":symbols:",e:"🔣"},{c:"Flags",n:"Checkered Flag",s:":checkered_flag:",e:"🏁"},{c:"Flags",n:"Triangular Flag",s:":triangular_flag:",e:"🚩"},{c:"Flags",n:"Crossed Flags",s:":crossed_flags:",e:"🎌"},{c:"Flags",n:"Black Flag",s:":black_flag:",e:"🏴"},{c:"Flags",n:"White Flag",s:":white_flag:",e:"🏳️"},{c:"Flags",n:"Rainbow Flag",s:":rainbow_flag:",e:"🏳️‍🌈"},{c:"Flags",n:"Pirate Flag",s:":pirate_flag:",e:"🏴‍☠️"},{c:"Flags",n:"US Flag",s:":flag_us:",e:"🇺🇸"},{c:"Flags",n:"UK Flag",s:":flag_gb:",e:"🇬🇧"},{c:"Flags",n:"Canada Flag",s:":flag_ca:",e:"🇨🇦"},{c:"Flags",n:"Germany Flag",s:":flag_de:",e:"🇩🇪"},{c:"Flags",n:"France Flag",s:":flag_fr:",e:"🇫🇷"},{c:"Flags",n:"Japan Flag",s:":flag_jp:",e:"🇯🇵"},{c:"Flags",n:"China Flag",s:":flag_cn:",e:"🇨🇳"},{c:"Flags",n:"South Korea",s:":flag_kr:",e:"🇰🇷"},{c:"Flags",n:"Brazil Flag",s:":flag_br:",e:"🇧🇷"},{c:"Flags",n:"India Flag",s:":flag_in:",e:"🇮🇳"},{c:"Flags",n:"Australia Flag",s:":flag_au:",e:"🇦🇺"},{c:"Flags",n:"Russia Flag",s:":flag_ru:",e:"🇷🇺"},{c:"Flags",n:"Italy Flag",s:":flag_it:",e:"🇮🇹"},{c:"Flags",n:"Spain Flag",s:":flag_es:",e:"🇪🇸"},{c:"Flags",n:"Mexico Flag",s:":flag_mx:",e:"🇲🇽"},{c:"Flags",n:"Argentina Flag",s:":flag_ar:",e:"🇦🇷"},{c:"Flags",n:"Sweden Flag",s:":flag_se:",e:"🇸🇪"},{c:"Flags",n:"Netherlands",s:":flag_nl:",e:"🇳🇱"},{c:"Flags",n:"Switzerland",s:":flag_ch:",e:"🇨🇭"},{c:"Flags",n:"Norway Flag",s:":flag_no:",e:"🇳🇴"},{c:"Flags",n:"Poland Flag",s:":flag_pl:",e:"🇵🇱"},{c:"Flags",n:"Portugal Flag",s:":flag_pt:",e:"🇵🇹"},{c:"Flags",n:"Turkey Flag",s:":flag_tr:",e:"🇹🇷"},{c:"Flags",n:"Saudi Arabia",s:":flag_sa:",e:"🇸🇦"},{c:"Flags",n:"South Africa",s:":flag_za:",e:"🇿🇦"},{c:"Flags",n:"Nigeria Flag",s:":flag_ng:",e:"🇳🇬"},{c:"Flags",n:"Egypt Flag",s:":flag_eg:",e:"🇪🇬"},{c:"Flags",n:"Thailand Flag",s:":flag_th:",e:"🇹🇭"},{c:"Flags",n:"Vietnam Flag",s:":flag_vn:",e:"🇻🇳"},{c:"Flags",n:"Indonesia",s:":flag_id:",e:"🇮🇩"},{c:"Flags",n:"Philippines",s:":flag_ph:",e:"🇵🇭"},{c:"Flags",n:"Singapore",s:":flag_sg:",e:"🇸🇬"},{c:"Flags",n:"Malaysia",s:":flag_my:",e:"🇲🇾"},{c:"Flags",n:"New Zealand",s:":flag_nz:",e:"🇳🇿"},{c:"Flags",n:"Ireland Flag",s:":flag_ie:",e:"🇮🇪"},{c:"Flags",n:"Greece Flag",s:":flag_gr:",e:"🇬🇷"},{c:"Flags",n:"Israel Flag",s:":flag_il:",e:"🇮🇱"},{c:"Flags",n:"Ukraine Flag",s:":flag_ua:",e:"🇺🇦"},{c:"Flags",n:"Morocco Flag",s:":flag_ma:",e:"🇲🇦"},{c:"Flags",n:"Pakistan",s:":flag_pk:",e:"🇵🇰"},{c:"Flags",n:"Bangladesh",s:":flag_bd:",e:"🇧🇩"},{c:"Flags",n:"Kenya Flag",s:":flag_ke:",e:"🇰🇪"},{c:"Flags",n:"Ethiopia",s:":flag_et:",e:"🇪🇹"},{c:"Flags",n:"Chile Flag",s:":flag_cl:",e:"🇨🇱"},{c:"Flags",n:"Colombia",s:":flag_co:",e:"🇨🇴"},{c:"Flags",n:"Peru Flag",s:":flag_pe:",e:"🇵🇪"},{c:"Flags",n:"Cuba Flag",s:":flag_cu:",e:"🇨🇺"},{c:"Flags",n:"Jamaica Flag",s:":flag_jm:",e:"🇯🇲"},{c:"Flags",n:"EU Flag",s:":flag_eu:",e:"🇪🇺"},{c:"Flags",n:"UN Flag",s:":flag_un:",e:"🇺🇳"},{c:"Flags",n:"England Flag",s:":flag_eng:",e:"🇬🇧"},{c:"Flags",n:"Scotland Flag",s:":flag_sct:",e:"🇸🇬"},{c:"Flags",n:"Wales Flag",s:":flag_wls:",e:"🇼🇧"},{c:"Flags",n:"Czech Republic",s:":flag_cz:",e:"🇨🇿"},{c:"Flags",n:"Denmark Flag",s:":flag_dk:",e:"🇩🇰"},{c:"Flags",n:"Finland Flag",s:":flag_fi:",e:"🇫🇮"},{c:"Flags",n:"Belgium Flag",s:":flag_be:",e:"🇧🇪"},{c:"Flags",n:"Austria Flag",s:":flag_at:",e:"🇦🇹"}];
var activeCat='All';
var currentEmoji=null;

function $(id){return document.getElementById(id);}

function buildSkinToneBar(){
    var bar=$('skin-tone-bar');
    var def=document.createElement('div');
    def.className='skin-swatch active';
    def.dataset.tone='0';
    def.title='Default';
    def.textContent='\u{1F600}';
    def.onclick=function(){setSkinTone(0);};
    bar.appendChild(def);
    var modEmojis=['\u{1F44D}','\u{1F44B}','\u{1F44F}','\u{1F44C}','\u{1F446}'];
    for(var i=0;i<5;i++){
        var sw=document.createElement('div');
        sw.className='skin-swatch';
        sw.dataset.tone=String(i+1);
        sw.title=toneNames[i+1];
        sw.textContent=modEmojis[0]+skinMod[i];
        sw.onclick=(function(idx){return function(){setSkinTone(idx+1);};})(i);
        bar.appendChild(sw);
    }
}

function setSkinTone(t){
    skinTone=t;
    var swatches=document.querySelectorAll('.skin-swatch');
    swatches.forEach(function(s){s.classList.remove('active');});
    swatches[t].classList.add('active');
}

function buildTabs(){
    var tabs=$('cat-tabs');
    var allTab=document.createElement('span');
    allTab.className='cat-tab active';
    allTab.textContent='All ('+db.length+')';
    allTab.onclick=function(){setCat('All');};
    tabs.appendChild(allTab);
    for(var i=0;i<catNames.length;i++){
        var count=db.filter(function(e){return e.c===catNames[i];}).length;
        var tab=document.createElement('span');
        tab.className='cat-tab';
        tab.dataset.cat=catNames[i];
        tab.innerHTML=catIcons[i]+' '+catLabels[i]+' ('+count+')';
        tab.onclick=(function(cn){return function(){setCat(cn);};})(catNames[i]);
        tabs.appendChild(tab);
    }
}

function setCat(cat){
    activeCat=cat;
    var tabs=document.querySelectorAll('.cat-tab');
    tabs.forEach(function(t){
        t.classList.remove('active');
        if((cat==='All'&&!t.dataset.cat)||(t.dataset.cat===cat)) t.classList.add('active');
    });
    renderGrid();
}

function getFiltered(){
    var q=($('emoji-search').value||'').trim().toLowerCase();
    return db.filter(function(e){
        if(activeCat!=='All'&&e.c!==activeCat) return false;
        if(q&&e.n.toLowerCase().indexOf(q)===-1&&e.s.toLowerCase().indexOf(q)===-1&&e.c.toLowerCase().indexOf(q)===-1) return false;
        return true;
    });
}

function renderGrid(){
    var grid=$('emoji-grid');
    var noRes=$('no-results');
    var filtered=getFiltered();
    grid.innerHTML='';
    if(filtered.length===0){noRes.style.display='block';return;}
    noRes.style.display='none';
    filtered.forEach(function(e){
        var cell=document.createElement('div');
        cell.className='emoji-cell';
        cell.setAttribute('data-emoji',e.e);
        cell.setAttribute('data-name',e.n);
        cell.setAttribute('data-short',e.s);
        cell.setAttribute('data-cat',e.c);
        var disp=e.e;
        if(skinTone>0&&modifiable.has(e.e)){
            disp=e.e+skinMod[skinTone-1];
        }
        cell.textContent=disp;
        var tip=document.createElement('span');
        tip.className='tt';
        tip.textContent=e.n;
        cell.appendChild(tip);
        cell.onclick=function(){
            var finalE=e.e;
            if(skinTone>0&&modifiable.has(e.e)){
                finalE=e.e+skinMod[skinTone-1];
            }
            showDetail(finalE,e);
            addRecent(e.e);
        };
        grid.appendChild(cell);
    });
}

function emojiToCodePoints(emojiStr){
    var cps=[];
    for(var i=0;i<emojiStr.length;i++){
        var code=emojiStr.charCodeAt(i);
        if(code>=0xD800&&code<=0xDBFF&&i+1<emojiStr.length){
            var lo=emojiStr.charCodeAt(i+1);
            if(lo>=0xDC00&&lo<=0xDFFF){
                cps.push(0x10000+((code-0xD800)<<10)+(lo-0xDC0));
                i++;
                continue;
            }
        }
        cps.push(code);
    }
    return cps;
}

function cpsToHex(cps){
    return cps.map(function(c){return 'U+'+c.toString(16).toUpperCase().padStart(4,'0');}).join(' ');
}

function cpsToHtmlDec(cps){
    return cps.map(function(c){return '&#'+c+';';}).join('');
}

function cpsToHtmlHex(cps){
    return cps.map(function(c){return '&#x'+c.toString(16).toUpperCase()+';';}).join('');
}

function cpsToJs(cps){
    if(cps.length===1&&cps[0]<=0xFFFF){
        return '\u'+cps[0].toString(16).toUpperCase().padStart(4,'0');
    }
    return cps.map(function(c){return '\u{'+c.toString(16).toUpperCase()+'}';}).join('');
}

function showDetail(emojiStr,entry){
    var panel=$('detail-panel');
    panel.classList.add('show');
    currentEmoji=emojiStr;
    $('detail-emoji').textContent=emojiStr;
    $('detail-name').textContent=entry.n;
    var cps=emojiToCodePoints(emojiStr);
    $('detail-unicode').textContent=cpsToHex(cps);
    $('detail-html-dec').textContent=cpsToHtmlDec(cps);
    $('detail-html-hex').textContent=cpsToHtmlHex(cps);
    $('detail-js').textContent=cpsToJs(cps);
    $('detail-short').textContent=entry.s;
    panel.scrollIntoView({behavior:'smooth',block:'nearest'});
}

function copyText(txt){
    if(navigator.clipboard&&navigator.clipboard.writeText){
        navigator.clipboard.writeText(txt);
    }else{
        var ta=document.createElement('textarea');
        ta.value=txt;
        ta.style.cssText='position:fixed;left:-9999px';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
    }
    var toast=$('copy-toast');
    toast.classList.add('show');
    clearTimeout(toast._t);
    toast._t=setTimeout(function(){toast.classList.remove('show');},1500);
}

window.copyField=function(id){
    var el=$(id);
    if(!el) return;
    copyText(el.textContent);
    var btn=el.nextElementSibling;
    if(btn&&btn.classList.contains('copy-btn')){
        btn.classList.add('copied');
        btn.textContent='Copied!';
        setTimeout(function(){btn.classList.remove('copied');btn.textContent='Copy';},1500);
    }
};

window.copyEmoji=function(){
    if(!currentEmoji) return;
    copyText(currentEmoji);
};

function addRecent(emojiStr){
    var key='emoji_recent';
    var list=[];
    try{list=JSON.parse(localStorage.getItem(key))||[]}catch(e){}
    list=list.filter(function(e){return e!==emojiStr;});
    list.unshift(emojiStr);
    if(list.length>30) list=list.slice(0,30);
    try{localStorage.setItem(key,JSON.stringify(list));}catch(e){}
    renderRecent();
}

function renderRecent(){
    var container=$('recent-emojis');
    var key='emoji_recent';
    var list=[];
    try{list=JSON.parse(localStorage.getItem(key))||[]}catch(e){}
    container.innerHTML='';
    if(list.length===0){
        container.innerHTML='<span style="color:#555;font-size:.8rem">No emojis used yet</span>';
        return;
    }
    list.forEach(function(eStr){
        var entry=db.find(function(d){return d.e===eStr;});
        var item=document.createElement('div');
        item.className='recent-item';
        item.textContent=eStr;
        item.title=entry?entry.n:eStr;
        item.onclick=function(){
            if(entry){
                var disp=eStr;
                if(skinTone>0&&modifiable.has(eStr)){
                    disp=eStr+skinMod[skinTone-1];
                }
                showDetail(disp,entry);
            }else{
                copyText(eStr);
            }
        };
        container.appendChild(item);
    });
}

$('emoji-search').addEventListener('input',renderGrid);

buildSkinToneBar();
buildTabs();
renderGrid();
renderRecent();
})();
</script>
<?php page_footer(); ?>