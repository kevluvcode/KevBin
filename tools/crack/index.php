<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$result = null;
$inputHashes = '';
$mode = 'common';
$algo = 'auto';
$maxSeconds = 6;

function crack_algos(): array
{
    return [
        'md5' => ['label' => 'MD5', 'len' => 32, 'hex' => true, 'fn' => function (string $s) { return hash('md5', $s); }],
        'sha1' => ['label' => 'SHA-1', 'len' => 40, 'hex' => true, 'fn' => function (string $s) { return hash('sha1', $s); }],
        'sha224' => ['label' => 'SHA-224', 'len' => 56, 'hex' => true, 'fn' => function (string $s) { return hash('sha224', $s); }],
        'sha256' => ['label' => 'SHA-256', 'len' => 64, 'hex' => true, 'fn' => function (string $s) { return hash('sha256', $s); }],
        'sha384' => ['label' => 'SHA-384', 'len' => 96, 'hex' => true, 'fn' => function (string $s) { return hash('sha384', $s); }],
        'sha512' => ['label' => 'SHA-512', 'len' => 128, 'hex' => true, 'fn' => function (string $s) { return hash('sha512', $s); }],
        'ntlm' => ['label' => 'NTLM', 'len' => 32, 'hex' => true, 'fn' => function (string $s) { return strtoupper(hash('md4', mb_convert_encoding($s, 'UTF-16LE', 'UTF-8'))); }],
        'crc32' => ['label' => 'CRC32', 'len' => 8, 'hex' => true, 'fn' => function (string $s) { return str_pad(dechex(crc32($s)), 8, '0', STR_PAD_LEFT); }],
    ];
}

function detect_crack_algo(string $hash): ?string
{
    $h = strtolower($hash);
    foreach (crack_algos() as $key => $a) {
        if (strlen($h) === $a['len'] && preg_match('/^[a-f0-9]{' . $a['len'] . '}$/', $h)) return $key;
    }
    return null;
}

function crack_words(): array
{
    return [
        'password','123456','12345678','123456789','1234567890','1234567','1234567891','qwerty','abc123','letmein','admin','welcome','monkey','dragon','master','login','princess','football','shadow','sunshine','iloveyou','trustno1','batman','superman','pokemon','baseball','whatever','starwars','freedom','hello','hunter2','passw0rd','zaq1zaq1','qazwsx','1q2w3e4r','asdfgh','zxcvbn','000000','111111','112233','121212','123123','131313','654321','666666','696969','777777','888888','999999','aaaaaa','abcabc','abcdef','a1b2c3','1qaz2wsx','qwertyuiop','qwerty123','password1','password123','admin123','root','toor','test','test123','guest','changeme','default','killer','jordan','michelle','jennifer','ginger','pepper','soccer','hockey','ranger','buster','thomas','george','harley','love','secret','dallas','austin','andrew','matrix','computer','internet','monica','cowboy','redwings','mississippi','michael','robert','hunter','shadow1','maggie','charlie','summer','winter','spring','autumn','orange','purple','yellow','green','blue','red','silver','golden','money','million','tennis','guitar','chicago','atlanta','phoenix','denver','seattle','boston','houston','miami','orlando','tampa','angels','yankees','cubs','raiders','packers','cheese','cookie','monkey1','bigdog','panther','mustang','corvette','camaro','jackson','jasmine','ashley','bailey','bubbles','cooper','diamond','fluffy','harley1','jackson1','lucky','midnight','oliver','princess1','rocky','snoopy','sparky','tigger','whiskey','william','winston','spongebob','computer','internet','liverpool','chelsea','arsenal','manchester','arsenal','chelsea1','wolverine','deadpool','xd','razzle','dazzle','butterfly','amsterdam','vladivostok','barcelona','galaxy','universe','cosmos','samsung','iphone','apple','android','microsoft','windows','linux','ubuntu','google','facebook','instagram','twitter','youtube','netflix','hulu','spotify','pandora','beans','quack','beach','sand','wave','surf','dolphin','shark','whale','octopus','lion','tiger','bear','eagle','falcon','hawk','penguin','panda','koala','giraffe','zebra','elephant','rhino','hippo','leopard','cheetah','wolf','fox','deer','moose','squirrel','rabbit','hamster','guinea','gerbil','parrot','canary','budgie','lovebirds','dragonfly','bumblebee','ladybug','butterfly1','moth','ant','bee','wasp','hornet','beetle','cricket','grasshopper','spider','scorpion','tarantula','snake','viper','python','cobra','mamba','anaconda','boa','rattlesnake','lizard','gecko','iguana','chameleon','tortoise','turtle','frog','toad','newt','salamander','axolotl','goldfish','betta','guppy','tetra','angelfish','clownfish','starfish','seahorse','jellyfish','crab','lobster','shrimp','oyster','clam','mussel','scallop','snail','slug','earthworm','centipede','millipede','silkworm','caterpillar','chrysalis','cocoon','pupa','larva','tadpole','fry','egg','nest','den','burrow','hive','colony','swarm','pack','pride','herd','flock','school','pod','gaggle','murder','parliament','unkindness','charm','clowder','kindle','litter','litter1','team','troop','gang','crew','squad','posse','family','clan','tribe','kingdom','realm','empire','nation','world','earth','mars','jupiter','saturn','uranus','neptune','pluto','venus','mercury','moon','sun','star','sol','luna','aurora','nova','comet','asteroid','meteor','nebula','galaxy1','quasar','supernova','blackhole','wormhole','tesseract','infinity','eternity','forever','always','never','sometimes','often','rarely','never1','always1','together','forever1','together1','apart','alone','lonely','happy','sad','angry','scared','brave','fearless','lucky1','unlucky','furious','calm','zen','peace','war','battle','fight','win','lose','victory','defeat','triumph','success','failure','prosper','poverty','wealth','rich','poor','king','queen','prince','princess2','duke','duchess','lord','lady','knight','soldier','warrior','samurai','ninja','shogun','emperor','empress','royal','crown','throne','castle','palace','fortress','tower','bridge','river','lake','ocean','sea','pond','stream','creek','brook','canal','waterfall','glacier','iceberg','volcano','mountain','hill','valley','canyon','desert','oasis','plain','plateau','meadow','field','forest','forest1','jungle','rainforest','swamp','marsh','bayou','tundra','savanna','prairie','grove','thicket','wood','woods','bush','scrub','hedge','garden','orchard','vineyard','farm','ranch','barn','stable','coop','pen','field1','meadow1','hillside','cliff','ridge','peak','summit','base','slope','foothill','hinterland','frontier','territory','province','region','district','county','parish','borough','ward','precinct','zone','sector','quadrant','hamlet','village','town','city','metropolis','megalopolis','capital','downtown','suburb','exurb','neighbor','community','society','civilization','culture','custom','tradition','folklore','legend','myth','epic','saga','tale','story','fable','parable','allegory','metaphor','simile','idiom','proverb','saying','quote','phrase','word','term','vocabulary','lexicon','dictionary','thesaurus','glossary','encyclopedia','almanac','atlas','gazetteer','directory','catalog','index','register','roster','ledger','journal','diary','log','chronicle','annals','record','archive','vault','museum','gallery','studio','workshop','laboratory','observatory','planetarium','aquarium','zoo','aviary','safari','expedition','adventure','quest','journey','voyage','trek','hike','climb','swim','dive','jump','run','walk','sprint','jog','dash','gallop','trot','canter','amble','saunter','stroll','wander','roam','travel','commute','visit','trip','excursion','detour','circuit','route','path','trail','track','lane','avenue','boulevard','street','road','highway','freeway','motorway','expressway','thruway','turnpike','tollway','bypass','bridge1','tunnel','viaduct','overpass','underpass','crosswalk','intersection','roundabout','traffic','signal','sign','billboard','poster','notice','advert','commercial','campaign','propaganda','publicity','promotion','marketing','advertising','branding','merchandising','retail','wholesale','commerce','market','bazaar','mall','outlet','store','shop','boutique','emporium','supermarket','grocery','butcher','baker','candlestick','blacksmith','carpenter','mason','plumber','electrician','welder','mechanic','engineer','architect','designer','artist','sculptor','painter','drawer','sketcher','calligrapher','typographer','printer','publisher','editor','writer','author','poet','playwright','novelist','essayist','journalist','columnist','reporter','correspondent','anchor','broadcaster','commentator','analyst','pundit','critic','reviewer','blogger','vlogger','podcaster','streamer','gamer','player','athlete','coach','trainer','instructor','teacher','professor','lecturer','tutor','mentor','counselor','advisor','consultant','specialist','expert','authority','guru','master1','sensei','sifu','guru1','pandit','rabbi','priest','pastor','minister','preacher','vicar','rector','deacon','monk','nun','abbot','prior','reverend','father','mother','brother','sister','uncle','aunt','grandpa','granny','nanna','papa','mama','chacha','tata','bhai','didi','bade','chote','chhoti','motu','chintu','pintu','montu','golu','pikachu','charizard','blastoise','raichu','jigglypuff','meowth','snorlax','mewtwo','mew','celebi','jirachi','deoxys','manaphy','lucario','greninja','pikachu1','eevee','vaporeon','jolteon','flareon','espeon','umbreon','leafeon','glaceon','sylveon','umbreon1','cash','dough','bread','bucks','dinos','kaching','cha-ching','ka-ching','cha-cha','ching','ching-a-ling','greenback','mozzarella','cheddar','guacamole','avocado','fajita','burrito','taco','enchilada','quesadilla','nachos','chips','salsa','guacamo','tortilla','tortellini','spaghetti','lasagna','fettuccine','penne','rigatoni','macaroni','ravioli','gnocchi','polenta','risotto','paella','tapas','empanada','arepa','arepa1','tamale','pozole','menudo','ceviche','ceviche1','pupusa','plantain','mofongo','yucca','cassava','taro','yam','sweetpotato','potato','fries','fries1','fried','grilled','roasted','baked','boiled','steamed','sautéed','pan-fried','deep-fried','air-fried','oven','stove','grill','bbq','smoker','pit','hearth','fireplace','chimney','flue','smoke','ash','cinder','ember','fire','flame','blaze','inferno','conflagration','wildfire','brushfire','backdraft','explosion','blast','boom','bang','pop','snap','crackle','sizzle','hiss','squeak','creak','groan','grunt','growl','howl','shriek','screech','yelp','whimper','snivel','sob','cry','weep','wail','lament','mourn','grieve','sorrow','anguish','agony','torment','torture','tribulation','affliction','calamity','catastrophe','disaster','mishap','misfortune','mischance','misadventure','accident','incident','event','occurrence','happening','phenomenon','marvel','wonder','miracle','blessing','boon','gift','present','prize','reward','bounty','bonus','perk','benefit','advantage','edge','upperhand','leverage','momentum','impetus','thrust','drive','push','pull','lift','raise','lower','elevate','depress','press','squeeze','pinch','grip','grasp','clutch','clasp','embrace','hug','cuddle','snuggle','nestle','nuzzle','kiss','peck','smooch','makeout','flirt','romance','woo','court','date','dating','crush','infatuation','obsession','fetish','kink','obsess','obsessive','obsessivecompulsive','compulsive','addict','addiction','dependent','dependency','reliance','dependence','rust','corrosion','oxidation','erosion','degradation','breakdown','failure1','malfunction','glitch','bug','virus','malware','spyware','adware','ransomware','trojan','worm','phish','scam','fraud','hoax','con','scheme','plot','conspiracy','intrigue','machination','mayhem','chaos','disorder','turmoil','unrest','upheaval','revolution','rebellion','revolt','uprising','protest','demonstration','rally','riot','strike','lockout','pickett','sit-in','occupation','takeover','coup','putsch','junta','militia','paramilitary','insurgency','guerrilla','terror','violence','bloodshed','carnage','slaughter','massacre','genocide','holocaust','atrocity','warfare','combat','hostility','antagonism','rivalry','competition','contest','tournament','match','game','round','set','point','score','goal','touchdown','homerun','strikeout','hattrick','grandslam','ace','birdie','eagle1','par','bogey','holeinone','checkmate','stalemate','draw1','tie','forfeit','concede','surrender','yield','submit','comply','obey','follow','conform','adapt','adjust','fine-tune','optimize','refine','polish','perfect','flawless','impeccable','superb','superior','supreme','ultimate','utmost','pinnacle','zenith','apex','acme','peak1','summit1','height','expanse','sweep','breadth','width','length','depth','volume','capacity','magnitude','size','scale','extent','degree','measure','quantity','amount','number','figure','statistic','data','information','knowledge','wisdom','insight','understanding','comprehension','cognition','perception','awareness','consciousness','mindfulness','presence','focus','concentration','attention','aware','alert','vigilant','watchful','observant','perceptive','acute','sharp','keen','eagle-eyed','hawk-eyed','lynx-eyed','all-seeing','omniscient','omnipresent','omnipotent','infinite','eternal','immortal','everlasting','undying','timeless','ageless','perpetual','ceaseless','endless','boundless','limitless','unlimited1','unrestricted','untamed','unbridled','unfettered','unchained','unshackled','liberated','freed','emancipated','released','unchained1','wild','savage','feral','untamed1','rugged','tough','hardy','resilient','robust','sturdy','durable','indestructible','invincible','unbeatable','unconquerable','determined','resolute','steadfast','unwavering','tenacious','persistent','relentless','tireless','indefatigable','unflagging','untiring','vigorous','energetic','dynamic','lively','vibrant','vivid','colorful','radiant','glowing','shining','sparkling','glittering','shimmering','twinkling','flickering','fluorescent','neon','fluorescent1','bright','brilliant','luminous','lucid','clear','transparent','translucent','opaque','solid','liquid','gaseous','plasma','matter','energy','force','power','strength','might','muscle','brawn','mojo','juice','sauce','gravy','drippings','skillet','wok','saucepan','stockpot','griddle','tawa','tandoor','kazan','pit1','brazier','charcoal','lignite','coal','peat','coke','anthracite','bituminous','subbituminous','lignite1','graphite','diamond1','crystal','quartz','amethyst','citrine','tourmaline','topaz','sapphire','ruby','emerald','jade','amber','opal','pearl','mother-of-pearl','abalone','conch','scallop1','coral','sponge','anemone','jellyfish1','kraken','leviathan','serpent','wyrm','drakaina','wyvern','lindworm','basilisk','cockatrice','manticore','chimera','griffin','hippogriff','pegasus','unicorn','alicorn','sphinx','minotaur','centaur','satyr','faun','troll','ogre1','giant','cyclops','harpy','gorgon','medusa','nymph','siren','mermaid','selkie','kitsune','tanuki','yokai','oni','kappa','tengu','naga','yaksha','rakshasa','asura','deva','daeva','goblin','orc','hobgoblin','bugbear','boggart','poltergeist','specter','phantasm','apparition','ghost','spirit','phantom','wraith','banshee','doppelganger','shade','revenant','zombie','vampire','werewolf','lycanthrope','shapeshifter','metamorph','doppelganger1','polymorph','transmogrify','transfigure','transform','morph','mutate','evolve','devolve','regress','progress','advance','proceed','continue','persist','prevail','endure','survive','withstand','weather','bear1','abide','tolerate','endure1','persevere','stickwith','hangon','holdon','behave','conduct','manage','handle','dealwith','cope','reconcile','settle','resolve','decide','determine1','conclude','infer','deduce','surmise','assume','presume','postulate','hypothesize','theorize','speculate','conjecture','ruminate','ponder','muse','contemplate','brood','mull','deliberate','cogitate','masticate','chew','gnaw','nibble','munch','crunch','chomp','mouthful','bite','swallow','gulp','sip','slurp','sup','quaff','drain','swig','down','belt','chug','knockback','tossback','throwback','flashback','callback','recall','recollect','remind','evoke','invoke','conjure','summon','call','namend','called','referred','designated','termed','titled','entitled','dubbed','christened','baptized','anointed','initiated','inducted','inaugurated','installed','installed1','appointed','assigned','delegated','mandated','commissioned','authorized','sanctioned','approved','endorsed','ratified','validated','certified','accredited','credentialed','verified','authenticated','legitimized','justified','vindicated','warranted','merited','deserved','earned','won1','achieved','attained','built','crafted','forged','molded','shaped','carved','sculpted','chiseled','etched','engraved','incised','inscribed','imprinted','embossed','stamped','branded','marked','labeled','tagged','badged','bannered','bannered1','crowned','celebrated','honored','commemorated','remembered','memorialize','immortalize','eterialize','canonize','saint','venerate','revere','adore','cherish','treasure','value','prize1','appreciate','admire','respect','esteem','regard','consider','deem','judge','pronounce','declare','assert','affirm','maintain','insist','contend','argue','debate','discuss','converse','communicate','connect','link','relate','associate','affiliate','ally','partner','collaborate','cooperate','contribute','participate','engage','involve','immerse','absorb','assimilate','incorporate','integrate','synthesize','combine','merge','unite','coalesce','converge','merge1','amalgamate','fuse','bond','attach','affix','fasten','secure','clamp','pin','bolt','screw','nail','rivet','weld','solder','braze','glue','paste','tape','strap','buckle','zip','snap1','button','fasten1','bind','tie','knot','lace','cord','rope','chain','tether','hitch','yoke','harness','bridle','saddle','mount','ride','drive1','pilot','navigate','steer','guide','lead','direct','conduct1','escort','accompany','convey','transport','carry','lug','haul','tote','shoulder','bear2','buckle1','browse','peruse','scan','skim','leaf','flip','thumb','check','review1','evaluate','assess','appraise','measure1','quantify','itemize','enumerate','list1','inventory','audit','census','tabulate','tally','count1','recount','compute','calculate','reckon','chart','graph','plot1','diagram','map1','sketch','draft','blueprint','schematics','layout','arrangement','design1','composition','structure','framework','system','method','procedure','protocol','process','workflow','pipeline','algorithm','formula','equation','constant','coefficient','variable','parameter','argument','premise','axiom','postulate1','theorem','lemma','corollary','proposition','hypothesis1','conjecture1','supposition','assumption1','precondition','prerequisite','requirement','condition','stipulation','clause','provision','term1','covenant','contract','agreement','accord','compact','pact','treaty','convention','charter','constitution','statute','ordinance','regulation','rule','guideline','standard','benchmark','criteria','measure2','yardstick','gauge','index1','indicator','signal1','pointer','marker','landmark','milestone','waypoint','checkpoint','gateway','portal','doorway','entrance','exit1','entry','access','approach','avenue1','corridor','hallway','passage','passageway','walkway','esplanade','promenade','boardwalk','pier','wharf','dock','marina','harbor','port1','quay','mooring','buoy','anchor1','rudder','helm','wheel','mast','sail','bow','stern','keel','hull','deck','cabin','galley','bridge1','engine','crank','piston','valve1','pump','hose','pipe','conduit','channel','trench','ditch','sluice','dam','levee','dyke','weir','reservoir','cistern','tank','tub','vat','barrel','cask','keg','drum','cylinder','canister','flask','bottle','jar','jug','pitcher','carafe','decanter','goblet','chalice','grail','crown1','scepter','orb','sequin','glitter','tinsel','bunting','garland','wreath','festoon','ribbon','bow1','curl','frill','flounce','ruffle','pleat','gather','tuck','dart','seam','stitch','purl','knit','crochet','weave','spindle','loom','warp','weft','thread','yarn','fiber','filament','strand','ply','strand1','braid','plait','queue','ponytail','bun','chignon','updo','bob','buzz','crewcut','flattop','shag','mullet','mohawk','fauxhawk','perm','relaxer','straightener','curling','session','trim','shave','touchup','fade','caesar','spike','comb-over','conerves','sidepart','disheveled','bedhead','mussed','tousled','rumpled','tangled','knotted','matted','spiked','gelled','moussed','sprayed','clipped','buzzed','shorn','sheared','cropped','cropped1','bobbed','layered','feathered','stacked','inverted','graduated','undercut','fringed','swept','side-swept','center-parted','subway','metro','underground','railway','rail','tram','trolley','cablecar','funicular','cogwheel','monorail','maglev','bullet','express','regional','intercity','freight','cargo','container','lorry','truck','pickup','van','suv','sedan','coupe','hatchback','convertible','roadster','sports','luxury','muscle','classic','antique','vintage','retro','modern','futuristic','cyberpunk','neopunk','steampunk','dieselpunk','atom-punk','clockpunk','biopunk','solarpunk','nanopunk','post-cyberpunk','raypunk','cassettepunk','cottagecore','goblincore','frogcore','witchcore','dark','light','shadow1','dusk','dawn','twilight','noon','midday','morning','afternoon','evening','night','midnight1','sunset','sunrise','dusk1','darkness','gloom','murk','haze','fog','mist','smog','smoke1','soot','dust','grime','filth','muck','sludge','ooze','goop','gunk','dirt','soil','earth1','mud','clay','silt','sand1','gravel','pebble','stone','rock','boulder','gully','ravine','chasm','gorge','canyon1','abyss','precipice','cliff1','escarpment','mesa','butte','pinnacle1','spire','steeple','turret','minaret','obelisk','column','pillar','caryatid','atlante','triumphal-arch','arch','arcade','portico','colonnade','peristyle','atrium','courtyard','plaza','forum','agora','marketplace','square','piazza','esplande','rotunda','dome','cupola','lantern','amber1','beacon','lighthouse','signal-fire','bonfire','campfire','hearthfire','candle','lantern1','torch','flare','flashlight','headlight','spotlight','searchlight','laser','ray','beam','pulse','wave1','surge','ripple','tide','undertow','current','stream1','brooklet','rivulet','runnel','trickle','seep','drip','dribble','ooze1','wash','rinse','soak','steep1','marinate','brine','pickle','cure','smoke-cure','salt','season','spice','flavor','essence','scent','aroma','fragrance','perfume','bouquet1','nose','oak','vanilla','caramel','toffee','butterscotch','maple','honey','sugar','agave','stevia','molasses','sorghum','dextrose','fructose','glucose','lactose','sucrose','maltose','mannose','galactose','xylitol','erythritol','aspartame','saccharin','sucralose','acesulfame','neotame','advantame','steviol','glucoside','monkfruit','date1','raisin','sultana','currant','cranberry','blueberry','raspberry','blackberry','strawberry','cherry','plum1','apricot','peach','nectarine','mango','papaya','pineapple','melon','watermelon','cantaloupe','honeydew','fig','persimmon','pomegranate','kiwi','guava','lychee','durian','rambutan','longan','mangosteen','jackfruit','starfruit','dragonfruit','passionfruit','coconut','banana','apple1','pear','quince','medlar','navel','tangerine','clementine','mandarin','citron','pomelo','grapefruit','lemon','lime','kumquat','yuzu','bergamot','calamondin','bloodorange','valencia','navel1','temple','murcott','tangor','tangelo','ugli','jabuticaba','baclava','turkish','greek','gyro','souvlaki','felafel','shawarma','kebab','kofta','pide','lahmacun','borek','moussaka','pastitsio','spanakopita','tiropita','baklava1','loukoum','halva','kataifi','cash1','mastercard','visa','amex','discover','maestro','cirrus','jcb','unionpay','rupay','paytm','phonepe','gpay','applepay','googlepay','samsungpay','swift','sepa','fedwire','chaps','ach','rtgs','neft','imps','upi','p-cards','chargeback','refund','dispute','reversal','settlement','clearing','netting','nostro','vostro','float','liquidity','solvency','capital1','equity','asset','liability','balance','cashflow','revenue','income','turnover','gross','net1','ebitda','margin','markup','markdown','discount','rebate','kickback','commission','royalty','dividend','interest1','apr','principal','amortization','depreciation','impairment','goodwill','subsidiary','holding','conglomerate','syndicate','cartel','monopoly','oligopoly','duopoly','trust','bust','boom1','bubble','crash','correction','downturn','recession','depression1','stagnation','hyperinflation','deflation','reflation','stagflation','meltdown','bankruptcy','insolvency','liquidation','receivership','foreclosure','seizure','confiscation','expropriation','reprisal','retaliation','sanction','embargo','boycott','blockade','siege','besiege','beleaguer','mauling','pummel','hammer','batter','pound','thrash','whack','belt1','slug1','clobber','smash','crush1','squash','flatten','pulverize','pound1','bash','bang1','rape','pillage','plunder','loot','ravage','despoil','strip','maraud','raid','foray','incursion','invasion','offensive','assault','onslaught','charge1','sally','sortie','counterattack','ambush','flank','outflank','encircle','envelop','surround','trap','corner','pin-down','suppress','quell','subdue','overpower','overwhelm','outnumber','outgun','outmaneuver','outwit','outsmart','outplay','outclass','outshine','eclipse','overshadow','dominate','command','rule1','govern','control1','manage1','administer','supervise','oversee','direct1','head','run1','operate','conduct2','execute','perform','carry-out','accomplish','complete','finalize','conclude1','wrapup','close','seal','sign1','ink','stamp1','execute1','implement','enforce','administer1','officiate','preside','chair','premiere','debut','open1','launch','release1','unveil','reveal1','disclose','expose','unmask','uncover','surface','emerge','materialize','appear','manifest','show','display','exhibit','present1','offer','proffer','extend','grant','confer','bestow','award','give','donate','contribute1','endow','fund','finance','sponsor','underwrite','guarantee','endorse1','vouch','attest','certify1','confirm','corroborate','substantiate','verify1','validate1','authenticate1','fathom','grasp1','grok','comprehend1','understand1','fathom1','discern','distinguish','differentiate','separate','divide','partition','split','bisect','halve','quarter','segment','slice1','chunk','hunk','gob','glob','clump','lump','mass1','heap','pile','stack1','bank1','mound','hillock','knoll','rise','bump','hummock','callus','corn','bunion','blister','wart','mole1','freckle','birthmark','scar1','blemish','imperfection','flaw1','defect','fault','shortcoming','weakness','vulnerability','exploit','abuse','misuse','malpractice','corruption1','bribery','graft','fraud1','embezzlement','peculation','spoliation','rapacity','avarice','cupidity','greed','covetousness','miserliness','parsimony','stinginess','frugality','thrift','economy','prudence','temperance','moderation','restraint1','discipline','self-control','willpower','resolve1','fortitude','courage','valor','bravery','heroism','gallantry','chivalry','nobility','integrity','honesty','truthfulness','veracity','honor1','repute','reputation','fame','renown','prestige','status1','standing','rank1','position1','title1','designation','office','post1','capacity1','function1','role1','part1','pageant','parade','cavalcade','motorcade','procession1','march','walk1','stroll1','promenade1','amble1','saunter1','trudge','plod','tramp','trek1','hike1','scramble','traverse','breach','ford','wade','paddle1','swim1','float1','drift','glide','coast','soar','hover','levitate','ascend','descend','plummet','plunge','swoop','dive1','submerge','immerse1','surface1','resurface','rise1','elevation','lift1','hoist','heave','boost','uprise','surge1','upswell','overflow','brim','spill','slosh','slop','sluice1','flush','gush','spurt','erupt','spew','vomit','regurgitate','belch','burp','hiccup','sneeze','cough','wheeze','pant','gasp','choke','gag','retch','heave1','puke','barf','honk','kaflooey','klaxon','blare','blast1','bellow','roar1','shout','yell','holler','scream','shriek1','cry1','yowl','bawl','squall','wail1','moan','groan1','whine','snivel1','blubber','sob1','sniffle','snuffle','whimper1','weep1','cry2','tear','tearful','lachrymose','weepy','mournful','sorrowful','woeful','rueful','doleful','melancholy','blue1','depressed','gloomy','forlorn','despondent','desolate','wretched','dismal','grim','bleak','somber','austere','severe','stern1','harsh','grave','serious','earnest','solemn','funereal','deathly','ghostly','spectral','ethereal','celestial','heavenly','divine1','sacred','holy','blessed1','beatific','seraphic','cherubic','angelic','saintly','pious','devout','religious','spiritual','faithful','loyal1','devoted','dedicated','committed','true1','constant1','steadfast1','staunch','firm','unflinching','immovable','unyielding','stubborn','obstinate','headstrong','willful','determined1','purposeful','resolute1','expectant','hopeful','optimistic','positive','confident','assured','certain','sure1','convinced','persuaded','swayed','won-over','converted','reformed','changed','transformed1','altered','modified','adjusted1','revised','amended','corrected','rectified','remedied','fixed','repaired','restored','renewed','revived','rejuvenated','refreshed','recharged','rebooted','reinitialized','reset','default2','factory','baseline','cleared','emptied','drained','exhausted','depleted','spent','finished','done1','completed1','concluded2','terminated','ended','stopped','halted','ceased','paused','suspended','interrupted','discontinued','disbanded','dissolved','liquidated1','wound','wounddown','closed1','shut','firmly','slamming','latch','bolt1','catch','clasp1','hasp','lock1','padlock','combination','deadbolt','rimlock','mortice','latchkey','passkey','keycard','fob','smartcard','proximity','biometric','fingerprint','iris','retinal','palm','vein','voiceprint','signature1','hologram','watermark','serial','license','registration','documentation','paperwork','redtape','bureaucracy','bureaucrat','officious','pedantic','fastidious','meticulous','scrupulous','punctilious','conscientious','dutiful','responsible','accountable','liable','answerable','bound1','obligated','indebted','beholden','obliged','grateful','thankful','appreciative1','acknowledging','recognizing','honoring1','celebrating1','observing','marking','commemorating1','noting','recording','filing','logging1','documenting','chronicling','recording1','narrating','relating1','recounting','describing','detailing','expounding','elucidating','explaining1','clarifying','unraveling','unfolding','unpacking','disentangling','disentering','disentrail','unentangling','unsnarling','untangling','unraveling1','unknot','undo','loosen','slacken','unfasten','unclasp','unhook','unzip','unbutton','unlace','untie','undo1','disentwine','unwind','uncork','unscrew','unplug','unmount','detach1','separate1','disconnect','disengage','dissove','disassemble','dismantle','breakdown1','takeapart','knockdown','demolish','destroy','ruin1','wreck','sabotage','vandalize','defile','desecrate','spoil','taint','corrupt1','poison1','contaminate','infect1','blight','scourge','plague1','pestilence','epidemic','pandemic','outbreak','flare-up','quot','quarantine','isolation','lockdown','curfew','martiallaw','emergency','contingency','crisis','critical','acute1','chronic','severe1','grave1','terminal','fatal','lethal','deadly','mortal','venomous','poisonous','toxic','noxious','hazardous','dangerous','perilous','risky','precarious','uncertain','unpredictable','volatile','explosive1','combustible','flammable','inflammable','incendiary','pyrotechnic','ostraka','shale','marl','loess','alluvium','detritus','sediment','deposit','stratum','layer1','bed1','vein1','seam1','fissure','fault1','crack1','crevice','split1','rift','cleft','chink','chap','nick','notch','groove','furrow','crease','wrinkle','fold','pleat1','buckle1','crumple','wad','ball1','wad1','crumple1','scrunch','wrinkle1','rumply','tousle','mess','disarray','clutter','confusion1','jumble','hodgepodge','mishmash','gallimaufry','olla','potpourri','medley','potpourri1','miscellany','assortment','variety','range','gamut','spectrum1','continua','array','slew','bevy','covey','host1','multitude','myriad','plethora','profusion','abundance','plenty','wealth1','richer','prosperity','opulence','lavishness','luxury1','plush','sumptuous','grandiose','ostentatious','showy','flashy','gaudy','garish','tawdry','crass','vulgar','common1','average','mediocre','garden-variety','run-of-the-mill','standard2','regular','ordinary','typical','usual','customary','habitual','familiar','known','recognized','acknowledged1','accepted','established','entrenched','ingrained','rooted','fixed1','settled','stable','steady','even1','level1','flat1','smooth','sleek','glossy','burnished','polished1','shiny','gleaming','glinting','glimmering','glistening','glittering1','shimmering1','French','Spanish','Italian','German','Portuguese','Arabic','Hebrew','Yiddish','Russian','Polish','Ukrainian','Czech','Slovak','Hungarian','Romanian','Bulgarian','Serbian','Croatian','Bosnian','Slovenian','Macedonian','Greek1','Turkish','Swedish','Norwegian','Danish','Finnish','Icelandic','Estonian','Latvian','Lithuanian','Irish','Welsh','Scottish','Breton','Basque','Catalan','Galician','Occitan','Provençal','Corsican','Sardinian','Sicilian','Neapolitan','Venetian','Lombard','Piedmontese','Sicilian','Maltese','Albanian','Armenian','Georgian','Azeri','Kurdish','Persian','Dari','Pashto','Urdu','Hindi','Punjabi','Gujarati','Marathi','Bengali','Assamese','Oriya','Telugu','Tamil','Kannada','Malayalam','Sinhala','Nepali','Somalia','Amharic','Swahili','Hausa','Igbo','Yoruba','Fula','Wolof','Bambara','Mandinka','Zulu','Xhosa','Sotho','Tswana','Chichewa','Shona','Kikuyu','Luo','Luganda','Afrikaans','Afrikaans1','Dutch','Flemish','Javanese','Sundanese','Malay','Indonesian','Tagalog','Cebuano','Ilocano','Bicol','Waray','Kapampangan','Pangasinan','Iloko','Chavacano','Hiligaynon','Bikol','Maranao','Bangsa','Hmong','Lao','Thai','Khmer','Burmese','Vietnamese','Cantonese','Mandarin1','Hokkien','Teochew','Hakka','Hokkien1','Taiwanese','Cantonese1','Shanghainese','Yue','Putonghua','Guoyu','Huayu','Zhongwen','Hanzi','Pinyin','Kana','Romaji','Rōmaji','Kanji','Hiragana','Katakana','Bopomofo','Zhuyin','Wade-Giles','Yale','Cuntlish','Cantrill','SpanSh','SinWa','chive','dieux','Che','Nhe','phuto','xeo','mien','nong','sape','douko','moro','kino','neroli','nan','yeti','trolltunga','Yggdrasil','Mjolnir','Odin','Thor','Loki','Freya','Frigg','Baldur','Heimdall','Tyr','Njord','Skadi','Idunn','Foseti','Jormungandr','Fenrir','Hel','Valkyrie','Einherjar','Valhalla','Bifrost','Nifheim','Muspelheim','Vanaheim','Alfheim','Midgard','Asgard','Jotunheim','Helheim','Svartalfheim','Niflheim2','Ymir','Audhumla','Ginnungagap','Ragnarok','Mjölnir','Gungnir','Mimisbrunnr','Eivar','runecraft','seidhr','volva','galdr','loki1','skaði','víkingg','Draugr','Eldritch','cthulhu','Azathoth','Nyarlathotep','Yog-Sothoth','Shub-Niggurath','Hastur','Dagon','Cthaeh','Nyarl','Nyb','Nab','algernon','blipbloop','Lorem','Ipsum','Dolor','Sit','Amet','Consectetur','Adipiscing','Elit','SedDozzle','Praesent','Commodo','Cursus','Magnam','Dolorem','Ipsum-1','Ipsum-2','Dolor1','Dolor2','Sit1','Amet1'];
}

function crack_mutations(): array
{
    return [
        '00','01','02','03','04','05','06','07','08','09','10','11','12','13','14','15','16','17','18','19','20','21','22','23','24','25','26','27','28','29','30','69','96',
        '123','321','007','420','111','222','333','444','555','666','777','888','999','000','101','202','1010','69!','69!!','420!',
        '!','!!','!!!','@','##','$','%','^','&','*','()','_','-','+','=','?','!?!','!$',
        '1','12','123','1234','12345','123456','1!','1!2!','!1','!2!3!',
        '2020','2021','2022','2023','2024','2025','2026','19','20','2010','2011','2012','2013','2014','2015','2016','2017','2018','2019',
        '0101','0202','0303','0404','0505','0606','0707','0808','0909','1010','1111','1212','1313','1414','1515','1616','1717','1818','1919',
        'today','now','2024!','2025!',
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    if (!rate_limit_check('tool_crack', 15, 300)) {
        friendly_error('Too many crack attempts from your IP. Try again in 5 minutes.', 429);
    }
    $inputHashes = strtolower(trim((string)($_POST['hashes'] ?? '')));
    $inputAlgo = (string)($_POST['algo'] ?? '');
    $mode = in_array(($_POST['mode'] ?? 'common'), ['common', 'wordlist', 'digits', 'days', 'keyboard'], true) ? (string)$_POST['mode'] : 'common';
    $maxSeconds = max(2, min(30, (int)($_POST['seconds'] ?? 6)));
    $algos = crack_algos();

    if ($inputAlgo === 'auto' && ($guess = detect_crack_algo($inputHashes)) !== null) {
        $algo = $guess;
    } elseif (isset($algos[$inputAlgo])) {
        $algo = $inputAlgo;
    } elseif (($guess = detect_crack_algo($inputHashes)) !== null) {
        $algo = $guess;
    } else {
        $algo = 'md5';
    }

    $hashes = [];
    foreach (preg_split('/[\s,;]+/', $inputHashes) as $h) {
        $h = strtolower($h);
        if (preg_match('/^[a-f0-9]{' . $algos[$algo]['len'] . '}$/', $h)) $hashes[] = $h;
    }
    if (count($hashes) === 0) {
        flash_set('error', 'Enter at least one valid ' . $algos[$algo]['label'] . ' hash (' . $algos[$algo]['len'] . ' hex characters).');
    } else {
        $result = try_crack($hashes, $mode, $algos[$algo]['fn'], $maxSeconds);
        log_activity('tool_crack', $algo . ':' . $mode . ':' . count($hashes) . ':' . ($result['total_found'] > 0 ? 'found' : 'miss'));
    }
}

function try_crack(array $hashes, string $mode, callable $hashFn, float $limit): array
{
    $t0 = microtime(true);
    $attempts = 0;
    $found = [];
    $checked = [];
    foreach ($hashes as $h) $checked[$h] = false;

    $check = function (string $candidate) use (&$attempts, &$found, $hashes, $hashFn, &$checked) {
        $attempts++;
        $c = $hashFn($candidate);
        if (isset($checked[$c])) {
            $checkKey = $c;
            if (!$checked[$checkKey]) {
                $checked[$checkKey] = true;
                $found[$candidate] = $c;
            }
        }
    };
    $timedOut = function () use ($t0, $limit) { return (microtime(true) - $t0) >= $limit; };

    $words = crack_words();
    $mutations = crack_mutations();
    $leet = ['a' => '4', 'e' => '3', 'i' => '1', 'o' => '0', 's' => '5', 't' => '7'];
    $keys = ['q' => 'was', 'w' => 'qeasd', 'e' => 'wrsdf', 'r' => 'etdfg', 't' => 'ryfgh', 'y' => 'tughj', 'u' => 'yihjk', 'i' => 'uojkl', 'o' => 'ipkl', 'p' => 'ol', 'a' => 'qwsxz', 's' => 'awedxz', 'd' => 'serfcv', 'f' => 'drtgvb', 'g' => 'ftyhnb', 'h' => 'gyujn', 'j' => 'huikm', 'k' => 'jiol', 'l' => 'kop', 'z' => 'asx', 'x' => 'zsdc', 'c' => 'xdfv', 'v' => 'cfgb', 'b' => 'vghn', 'n' => 'bhjm', 'm' => 'njk'];

    try {
        if ($mode === 'wordlist') {
            foreach ($words as $w) {
                $check($w); $check(strtoupper($w)); $check(ucfirst($w));
                if ($timedOut()) break;
            }
        } elseif ($mode === 'common') {
            foreach ($words as $w) {
                $check($w); $check(strtoupper($w)); $check(ucfirst($w));
                $leetw = strtr(strtolower($w), $leet);
                $check($leetw); $check(ucfirst($leetw));
                foreach ($mutations as $m) {
                    $check($w . $m); $check($leetw . $m);
                    if ($timedOut()) break 2;
                }
                if ($timedOut()) break;
            }
            $firsts = ['Kev', 'Bin', 'KevBin', 'kev', 'bin', 'admin', 'root', 'user', 'test', 'demo'];
            foreach (['123', '1234', '12345', '123456', '1', '2024', '2025', '!', '!@#', '!1', '01', '1qwe', 'abc', 'xyz'] as $tail) {
                foreach ($firsts as $f) { $check($f . $tail); }
            }
        } elseif ($mode === 'days') {
            $ml = [31,28,31,30,31,30,31,31,30,31,30,31];
            for ($y = 1950; $y <= 2026; $y++) {
                $ys = (string)$y; $ys2 = substr($ys, 2);
                for ($m = 1; $m <= 12; $m++) {
                    $ms = str_pad((string)$m, 2, '0', STR_PAD_LEFT);
                    for ($d = 1; $d <= $ml[$m-1]; $d++) {
                        $ds = str_pad((string)$d, 2, '0', STR_PAD_LEFT);
                        $check($ms . $ds . $ys2); $check($ds . $ms . $ys2); $check($ms . $ds . $ys);
                        if ($timedOut()) break 3;
                    }
                }
            }
            $weekdays = ['Monday','monday','Sunday','sunday','Friday','friday','January','january','December','december','March','march','summer','winter','spring'];
            foreach ($weekdays as $w) { $check($w); $check($w . '1'); $check($w . '2024'); $check($w . '22'); }
        } elseif ($mode === 'digits') {
            for ($len = 1; $len <= 7; $len++) {
                $max = (int)str_repeat('9', $len);
                for ($i = 0; $i <= $max; $i++) {
                    $check(str_pad((string)$i, $len, '0', STR_PAD_LEFT));
                    if ($timedOut()) break 2;
                }
            }
        } elseif ($mode === 'keyboard') {
            $startKeys = array_keys($keys);
            $seqs = [];
            for ($i = 0; $i < 5000 && !$timedOut(); $i++) {
                $len = 4 + rand(0, 4);
                $s = $startKeys[array_rand($startKeys)];
                $out = $s;
                for ($k = 1; $k < $len; $k++) {
                    $neighbors = $keys[$s];
                    $s = $neighbors[rand(0, strlen($neighbors) - 1)];
                    $out .= $s;
                }
                $check($out); $check($out . '1'); $check(ucfirst($out));
            }
            foreach (['qwerty','qwerty123','qwertyuiop','asdfghjkl','zxcvbnm','1qaz2wsx','1q2w3e4r','qazwsxedc','poiuytrewq','lkjhgfdsa','mnbvcxz','1234qwer','asd123','qwe123','1qaz','2wsx','3edc','4rfv','5tgb','6yhn','7ujm','8ik,','9ol.','0p;/'] as $w) {
                $check($w); $check(ucfirst($w)); $check($w . '1'); $check($w . '!');
            }
        }
    } catch (Throwable $t) {
    }

    $elapsed = microtime(true) - $t0;
    $totalFound = count($found);
    return [
        'total_found' => $totalFound,
        'map' => $found,
        'hashes' => $hashes,
        'attempts' => $attempts,
        'elapsed' => $elapsed,
        'timed_out' => $totalFound === 0 && $elapsed >= $limit - 0.05,
        'rate' => $elapsed > 0 ? (int)round($attempts / $elapsed) : 0,
    ];
}

page_header('Hash Cracker');
?>
<div class="container" style="max-width: 820px;">
    <h1 class="h4 mb-1 reveal in-view">🎯 Hash Cracker</h1>
    <p class="text-secondary mb-3 reveal in-view">Try to recover the original input for MD5, SHA-1, SHA-2 family, NTLM or CRC32 hashes. Supports <strong>one hash per line</strong>, server-side cracking with several attack strategies, and a usage limit. Educational — real hashes can't be reversed, only weak guessable inputs fall.</p>

    <div class="card mb-3 reveal"><div class="card-body">
        <form method="post" action="index.php">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="mb-3">
                <label class="form-label">Hash(es) — one per line</label>
                <textarea class="form-control" name="hashes" rows="3" maxlength="6000" required
                    placeholder="MD5 / SHA-1 / SHA-2 / NTLM / CRC32 hex — one per line" style="font-family:'JetBrains Mono',monospace;"><?= e($inputHashes) ?></textarea>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Hash algorithm</label>
                    <select class="form-select" name="algo">
                        <option value="auto" <?= $algo === 'auto' ? 'selected' : '' ?>>Auto-detect (first line)</option>
                        <?php foreach (crack_algos() as $key => $a): ?>
                            <option value="<?= e($key) ?>" <?= $algo === $key ? 'selected' : '' ?>><?= e($a['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Attack mode</label>
                    <select class="form-select" name="mode">
                        <option value="common" <?= $mode === 'common' ? 'selected' : '' ?>>Common (+mutations)</option>
                        <option value="wordlist" <?= $mode === 'wordlist' ? 'selected' : '' ?>>Wordlist only (fast)</option>
                        <option value="days" <?= $mode === 'days' ? 'selected' : '' ?>>Dates (DDMMYY & MM DD)</option>
                        <option value="digits" <?= $mode === 'digits' ? 'selected' : '' ?>>Digits 1-7 (brute force)</option>
                        <option value="keyboard" <?= $mode === 'keyboard' ? 'selected' : '' ?>>Keyboard walks (qwerty)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Time limit</label>
                    <select class="form-select" name="seconds">
                        <?php foreach ([2, 6, 10] as $s): ?>
                            <option value="<?= $s ?>" <?= $maxSeconds === $s ? 'selected' : '' ?>>~<?= $s ?> seconds</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button class="btn btn-primary mt-3" type="submit">💥 Try to crack</button>
        </form>
    </div></div>

    <?php if ($result !== null): ?>
        <div class="card reveal"><div class="card-body">
            <h2 class="h6 mb-2">Result</h2>
            <?php if ($result['total_found'] > 0): ?>
                <?php foreach ($result['map'] as $plain => $h): ?>
                    <div class="alert alert-success mb-2 d-flex justify-content-between align-items-center">
                        <span>🎉 FOUND <code><?= e($plain) ?></code></span>
                        <code class="small text-success"><?= e($h) ?></code>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-danger mb-2">Not found in <?= number_format($result['attempts']) ?> tries<?= $result['timed_out'] ? ' (hit the time limit)' : '' ?>. Try a different mode.</div>
            <?php endif; ?>
            <div class="text-secondary small">Tried: <?= number_format($result['attempts']) ?> candidates · <?= number_format($result['rate']) ?>/s · Time: <?= number_format($result['elapsed'], 2) ?>s</div>
            <p class="text-secondary small mb-0 mt-2">⚠️ Educational only — this works only for weak, guessable inputs.</p>
        </div></div>
    <?php endif; ?>

    <div class="card mt-3 reveal"><div class="card-body">
        <h2 class="h6 mb-2">Quick test hashes</h2>
        <p class="text-secondary small mb-0">Click to fill:
            <button type="button" class="btn btn-sm btn-outline-light ms-1" data-hash="5f4dcc3b5aa765d61d8327deb882cf99">MD5 "password"</button>
            <button type="button" class="btn btn-sm btn-outline-light ms-1" data-hash="5e884898da28047151d0e56f8dc6292773603d0d6aabbdd62a11ef721d1542d8">SHA-256 "password"</button>
            <button type="button" class="btn btn-sm btn-outline-light ms-1" data-hash="8846f7eaee8fb117ad06bdd830b7586c">NTLM "password"</button>
        </p>
    </div></div>
</div>
<script>
document.querySelectorAll('[data-hash]').forEach(function (b) {
    b.addEventListener('click', function () {
        document.querySelector('textarea[name="hashes"]').value = b.getAttribute('data-hash');
        var algoEl = document.querySelector('select[name="algo"]');
        algoEl.value = 'auto';
    });
});
</script>
<?php page_footer(); ?>