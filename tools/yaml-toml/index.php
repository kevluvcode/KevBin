<?php
require_once '../../functions.php';
start_session();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>YAML / TOML / JSON Converter</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#1a1a2e;color:#e0e0e0;padding:20px;min-height:100vh}
.container{max-width:1200px;margin:0 auto}
h1{text-align:center;margin-bottom:6px;color:#00d4ff;font-size:1.6rem}
.subtitle{text-align:center;color:#888;margin-bottom:20px;font-size:.85rem}
.panel{background:#16213e;border:1px solid #0f3460;border-radius:8px;padding:14px;margin-bottom:16px}
.panel-header{display:flex;align-items:center;gap:10px;margin-bottom:10px;flex-wrap:wrap}
.panel-label{font-weight:600;color:#00d4ff;font-size:.9rem}
.panel select,.panel textarea{background:#1a1a2e;border:1px solid #0f3460;color:#e0e0e0;border-radius:5px;padding:8px;font-family:Consolas,'Courier New',monospace;font-size:13px}
.panel select{padding:6px 10px;border-radius:5px;cursor:pointer;min-width:120px}
.panel textarea{width:100%;min-height:160px;resize:vertical;line-height:1.4}
.btn-row{display:flex;gap:8px;margin-top:10px;flex-wrap:wrap;justify-content:center}
.btn{padding:7px 16px;border:none;border-radius:5px;cursor:pointer;font-size:13px;font-weight:600;transition:all .2s}
.btn-primary{background:#00d4ff;color:#1a1a2e}
.btn-primary:hover{background:#00b8d4;transform:translateY(-1px)}
.btn-secondary{background:#533483;color:#fff}
.btn-secondary:hover{background:#6a3da0;transform:translateY(-1px)}
.btn-copy{background:#0f3460;color:#00d4ff;border:1px solid #00d4ff}
.btn-copy:hover{background:#00d4ff;color:#1a1a2e}
.btn-swap{background:#e94560;color:#fff}
.btn-swap:hover{background:#d63050;transform:translateY(-1px)}
.output-panels{display:grid;grid-template-columns:1fr;gap:12px}
.output-block{background:#0f1a35;border:1px solid #0f3460;border-radius:6px;padding:10px}
.output-block-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px}
.output-block-header span{font-weight:600;color:#f5a623;font-size:.85rem}
.output-block textarea{width:100%;min-height:100px;resize:vertical;background:#1a1a2e;border:1px solid #0f3460;color:#c8e6c9;border-radius:4px;padding:8px;font-family:Consolas,'Courier New',monospace;font-size:12px;line-height:1.3}
.reveal{display:none}
.reveal.show{display:block;animation:fadeIn .3s ease}
@keyframes fadeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
.error-msg{color:#e94560;font-size:13px;padding:8px;background:rgba(233,69,96,.1);border-radius:4px;margin-top:6px;display:none}
.error-msg.show{display:block}
.toast{position:fixed;top:20px;right:20px;background:#00d4ff;color:#1a1a2e;padding:10px 18px;border-radius:6px;font-size:13px;font-weight:600;z-index:9999;animation:slideIn .3s ease,slideOut .3s ease 1.5s forwards}
@keyframes slideIn{from{transform:translateX(100%);opacity:0}to{transform:translateX(0);opacity:1}}
@keyframes slideOut{from{transform:translateX(0);opacity:1}to{transform:translateX(100%);opacity:0}}
@media(max-width:768px){.container{padding:10px}h1{font-size:1.2rem}.panel textarea{min-height:120px}}
</style>
</head>
<body>
<div class="container">
<h1>YAML / TOML / JSON Converter</h1>
<p class="subtitle">Convert between YAML, TOML, and JSON formats instantly</p>

<div class="panel reveal show">
<div class="panel-header">
<span class="panel-label">Input</span>
<select id="inputFormat">
<option value="yaml">YAML</option>
<option value="toml">TOML</option>
<option value="json" selected>JSON</option>
</select>
</div>
<textarea id="inputData" placeholder="Paste your YAML, TOML, or JSON here..."></textarea>
<div class="btn-row">
<button class="btn btn-primary" onclick="doConvert()">Convert</button>
<button class="btn btn-swap" onclick="swapInputOutput()">Swap Input / Output</button>
</div>
<div id="inputError" class="error-msg"></div>
</div>

<h2 style="text-align:center;margin:10px 0;color:#888;font-size:1rem">Outputs</h2>

<div class="output-panels" id="outputPanels">
<div class="output-block reveal show" id="outYamlBlock">
<div class="output-block-header">
<span>YAML</span>
<button class="btn btn-copy" onclick="copyOutput('outputYaml')">Copy</button>
</div>
<textarea id="outputYaml" readonly placeholder="YAML output..."></textarea>
</div>
<div class="output-block reveal show" id="outTomlBlock">
<div class="output-block-header">
<span>TOML</span>
<button class="btn btn-copy" onclick="copyOutput('outputToml')">Copy</button>
</div>
<textarea id="outputToml" readonly placeholder="TOML output..."></textarea>
</div>
<div class="output-block reveal show" id="outJsonBlock">
<div class="output-block-header">
<span>JSON</span>
<button class="btn btn-copy" onclick="copyOutput('outputJson')">Copy</button>
</div>
<textarea id="outputJson" readonly placeholder="JSON output..."></textarea>
</div>
</div>
</div>

<script>
(function(){
var inputFormat=document.getElementById('inputFormat');
var inputData=document.getElementById('inputData');
var inputError=document.getElementById('inputError');
var blockYaml=document.getElementById('outYamlBlock');
var blockToml=document.getElementById('outTomlBlock');
var blockJson=document.getElementById('outJsonBlock');
var outputYaml=document.getElementById('outputYaml');
var outputToml=document.getElementById('outputToml');
var outputJson=document.getElementById('outputJson');
var lastConverted=null;

function showError(msg){
inputError.textContent=msg;
inputError.classList.add('show');
}
function hideError(){
inputError.classList.remove('show');
}
function showToast(msg){
var t=document.createElement('div');
t.className='toast';
t.textContent=msg;
document.body.appendChild(t);
setTimeout(function(){t.remove()},2000);
}

function parseJson(str){
return JSON.parse(str);
}

function parseYaml(str){
var lines=str.split('\n');
var root={};
var stack=[{indent:-1,obj:root}];
var lastIndent=0;

function detectType(v){
v=v.trim();
if(v===''||v==='null'||v==='~')return null;
if(v==='true')return true;
if(v==='false')return false;
if(/^-?\d+$/.test(v))return parseInt(v,10);
if(/^-?\d+\.\d+$/.test(v))return parseFloat(v);
if(/^-?\d*\.\d+([eE][+-]?\d+)?$/.test(v))return parseFloat(v);
if(/^-?\d+[eE][+-]?\d+$/.test(v))return parseFloat(v);
if((v.startsWith('"')&&v.endsWith('"'))||(v.startsWith("'")&&v.endsWith("'"))){
return v.slice(1,-1);
}
if(v.startsWith('[')&&v.endsWith(']')){
return parseInlineArray(v.slice(1,-1));
}
if(v.startsWith('{')&&v.endsWith('}')){
return parseInlineObject(v.slice(1,-1));
}
return v;
}

function parseInlineArray(s){
s=s.trim();
if(s==='')return[];
var result=[];
var depth=0;
var current='';
var inStr=false;
var strChar='';
for(var i=0;i<s.length;i++){
var c=s[i];
if(inStr){
current+=c;
if(c===strChar&&s[i-1]!=='\\'){inStr=false;}
continue;
}
if(c==='"'||c==="'"){inStr=true;strChar=c;current+=c;continue;}
if(c==='('||c==='['||c==='{')depth++;
if(c===')'||c===']'||c==='}')depth--;
if(c===','&&depth===0){
result.push(detectType(current));
current='';
}else{
current+=c;
}
}
if(current.trim()!=='')result.push(detectType(current));
return result;
}

function parseInlineObject(s){
s=s.trim();
if(s==='')return{};
var result={};
var depth=0;
var current='';
var inStr=false;
var strChar='';
var parts=[];
for(var i=0;i<s.length;i++){
var c=s[i];
if(inStr){
current+=c;
if(c===strChar&&s[i-1]!=='\\'){inStr=false;}
continue;
}
if(c==='"'||c==="'"){inStr=true;strChar=c;current+=c;continue;}
if(c==='('||c==='['||c==='{')depth++;
if(c===')'||c===']'||c==='}')depth--;
if(c===','&&depth===0){
parts.push(current);
current='';
}else{
current+=c;
}
}
if(current.trim()!=='')parts.push(current);
parts.forEach(function(p){
var eq=p.indexOf(':');
if(eq>-1){
var k=p.substring(0,eq).trim();
var v=p.substring(eq+1).trim();
if((k.startsWith('"')&&k.endsWith('"'))||(k.startsWith("'")&&k.endsWith("'"))){k=k.slice(1,-1);}
result[k]=detectType(v);
}
});
return result;
}

function parseValue(v){
v=v.trim();
if(v.startsWith('|')||v.startsWith('>')){
var lines=v.split('\n');
lines.shift();
return lines.join('\n').trimEnd();
}
return detectType(v);
}

var idx=0;
while(idx<lines.length){
var line=lines[idx];
if(line.trim()===''||line.trim().startsWith('#')){idx++;continue;}
var indent=line.length-line.replace(/^(\s*)/,'').length;
var trimmed=line.trim();
while(stack.length>1&&stack[stack.length-1].indent>=indent){
stack.pop();
}
var parent=stack[stack.length-1].obj;
if(trimmed.startsWith('- ')){
var itemVal=trimmed.substring(2).trim();
var container=parent;
var lastKey=stack[stack.length-1].lastKey;
if(lastKey&&Array.isArray(parent[lastKey])){
container=parent[lastKey];
}else if(lastKey&&typeof parent[lastKey]==='object'&&parent[lastKey]!==null&&!Array.isArray(parent[lastKey])){
container=parent[lastKey];
}else{
if(Array.isArray(container)){}
else{
var arr=[];
if(lastKey){parent[lastKey]=arr;}
container=arr;
}
}
if(itemVal.includes(':')&&!itemVal.startsWith('"')&&!itemVal.startsWith("'")){
var subObj={};
var subParts=itemVal.split(':');
var subK=subParts.slice(0,-1).join(':').trim();
var subV=subParts[subParts.length-1].trim();
subObj[subK]=detectType(subV);
container.push(subObj);
stack.push({indent:indent+2,obj:subObj,lastKey:subK});
}else{
container.push(detectType(itemVal));
}
if(parent!==container&&lastKey&&!Array.isArray(parent[lastKey])){parent[lastKey]=container;}
idx++;continue;
}
var colonIdx=trimmed.indexOf(':');
if(colonIdx>-1){
var key=trimmed.substring(0,colonIdx).trim();
var val=trimmed.substring(colonIdx+1).trim();
if((key.startsWith('"')&&key.endsWith('"'))||(key.startsWith("'")&&key.endsWith("'"))){key=key.slice(1,-1);}
if(val===''||val==='#'){
idx++;
var childIndent=-1;
var childLines=[];
while(idx<lines.length){
var cl=lines[idx];
if(cl.trim()===''||cl.trim().startsWith('#')){idx++;continue;}
var ci=cl.length-cl.replace(/^(\s*)/,'').length;
if(ci<=indent){break;}
if(childIndent===-1)childIndent=ci;
childLines.push(cl);
idx++;
}
if(childLines.length>0){
var subStr=childLines.map(function(l){return l.substring(childIndent);}).join('\n');
parent[key]=parseYamlObject(subStr);
}else{
parent[key]=null;
}
stack.push({indent:childIndent>0?childIndent:indent+2,obj:parent[key],lastKey:key});
}else if(val.startsWith('[')||val.startsWith('{')){
parent[key]=detectType(val);
stack[stack.length-1].lastKey=key;
}else{
parent[key]=parseValue(val);
stack[stack.length-1].lastKey=key;
}
idx++;continue;
}
idx++;
}
return root;
}

function parseYamlObject(str){
var lines=str.split('\n');
var root={};
var stack=[{indent:-1,obj:root}];
var idx=0;
while(idx<lines.length){
var line=lines[idx];
if(line.trim()===''||line.trim().startsWith('#')){idx++;continue;}
var indent=line.length-line.replace(/^(\s*)/,'').length;
var trimmed=line.trim();
while(stack.length>1&&stack[stack.length-1].indent>=indent){stack.pop();}
var parent=stack[stack.length-1].obj;
if(trimmed.startsWith('- ')){
var itemVal=trimmed.substring(2).trim();
var lastKey=stack[stack.length-1].lastKey;
if(lastKey&&parent[lastKey]!==undefined&&Array.isArray(parent[lastKey])){
}else if(lastKey&&parent[lastKey]!==undefined&&typeof parent[lastKey]==='object'&&!Array.isArray(parent[lastKey])){
}else if(lastKey){
parent[lastKey]=[];
}
if(itemVal.includes(':')&&!itemVal.startsWith('"')&&!itemVal.startsWith("'")&&!itemVal.startsWith('[')){
var subParts=itemVal.split(':');
var subK=subParts.slice(0,-1).join(':').trim();
var subV=subParts.slice(-1)[0].trim();
var subObj={};
subObj[subK]=detectType(subV);
parent[lastKey].push(subObj);
stack.push({indent:indent+2,obj:subObj,lastKey:subK});
}else{
parent[lastKey].push(detectType(itemVal));
}
idx++;continue;
}
var colonIdx=trimmed.indexOf(':');
if(colonIdx>-1){
var key=trimmed.substring(0,colonIdx).trim();
var val=trimmed.substring(colonIdx+1).trim();
if((key.startsWith('"')&&key.endsWith('"'))||(key.startsWith("'")&&key.endsWith("'"))){key=key.slice(1,-1);}
if(val===''||val==='#'){
idx++;
var childIndent=-1;
var childLines=[];
while(idx<lines.length){
var cl=lines[idx];
if(cl.trim()===''||cl.trim().startsWith('#')){idx++;continue;}
var ci=cl.length-cl.replace(/^(\s*)/,'').length;
if(ci<=indent){break;}
if(childIndent===-1)childIndent=ci;
childLines.push(cl);
idx++;
}
if(childLines.length>0){
var subStr=childLines.map(function(l){return l.substring(childIndent);}).join('\n');
parent[key]=parseYamlObject(subStr);
}else{
parent[key]=null;
}
stack.push({indent:childIndent>0?childIndent:indent+2,obj:parent[key],lastKey:key});
}else if(val.startsWith('[')||val.startsWith('{')){
parent[key]=detectType(val);
stack[stack.length-1].lastKey=key;
}else{
parent[key]=parseValue(val);
stack[stack.length-1].lastKey=key;
}
idx++;continue;
}
idx++;
}
return root;
}

function parseToml(str){
var lines=str.split('\n');
var root={};
var current=root;
var currentPath=[];
function resolvePath(path){
var obj=root;
for(var i=0;i<path.length;i++){
var key=path[i];
if(key.startsWith('"')&&key.endsWith('"'))key=key.slice(1,-1);
if(key.startsWith("'")&&key.endsWith("'"))key=key.slice(1,-1);
if(obj[key]===undefined||typeof obj[key]!=='object'){
obj[key]={};
}
obj=obj[key];
}
return obj;
}
for(var i=0;i<lines.length;i++){
var line=lines[i].trim();
if(line===''||line.startsWith('#'))continue;
if(line.match(/^\[([^\[\]]+)\]$/)){
var tableName=line.slice(1,-1).trim();
var parts=tableName.split('.');
currentPath=[];
var tempPath=[];
for(var j=0;j<parts.length;j++){
var p=parts[j].trim();
if((p.startsWith('"')&&p.endsWith('"'))||(p.startsWith("'")&&p.endsWith("'"))){p=p.slice(1,-1);}
tempPath.push(p);
}
currentPath=tempPath;
current=resolvePath(currentPath);
continue;
}
if(line.match(/^\[\[([^\[\]]+)\]\]$/)){
var arrTableName=line.slice(2,-2).trim();
var arrParts=arrTableName.split('.');
var arrPath=[];
for(var j=0;j<arrParts.length;j++){
var p=arrParts[j].trim();
if((p.startsWith('"')&&p.endsWith('"'))||(p.startsWith("'")&&p.endsWith("'"))){p=p.slice(1,-1);}
arrPath.push(p);
}
current=resolvePath(arrPath.slice(0,-1));
var arrKey=arrPath[arrPath.length-1];
if(!Array.isArray(current[arrKey])){current[arrKey]=[];}
var newObj={};
current[arrKey].push(newObj);
current=newObj;
currentPath=arrPath.slice(0,-1);
continue;
}
var eqIdx=line.indexOf('=');
if(eqIdx>-1){
var key=line.substring(0,eqIdx).trim();
var val=line.substring(eqIdx+1).trim();
if((key.startsWith('"')&&key.endsWith('"'))||(key.startsWith("'")&&key.endsWith("'"))){key=key.slice(1,-1);}
current[key]=parseTomlValue(val);
}
}
return root;
}

function parseTomlValue(v){
v=v.trim();
if(v===''||v==='null'||v==='NULL')return null;
if(v==='true'||v==='True'||v==='TRUE')return true;
if(v==='false'||v==='False'||v==='FALSE')return false;
if(v.match(/^\d{4}-\d{2}-\d{2}/))return v;
if(v.startsWith('"')&&v.endsWith('"')){
var s=v.slice(1,-1);
s=s.replace(/\\n/g,'\n').replace(/\\t/g,'\t').replace(/\\\\/g,'\\').replace(/\\"/g,'"');
return s;
}
if(v.startsWith("'")&&v.endsWith("'")){return v.slice(1,-1);}
if(v.startsWith('"""')&&v.endsWith('"""')){return v.slice(3,-3).trim();}
if(v.startsWith("'''")&&v.endsWith("'''")){return v.slice(3,-3).trim();}
if(v.match(/^-?\d+$/))return parseInt(v,10);
if(v.match(/^-?\d+\.\d+$/))return parseFloat(v);
if(v.startsWith('[')&&v.endsWith(']')&&!v.startsWith('[[')){
var inner=v.slice(1,-1).trim();
return parseTomlArray(inner);
}
if(v.startsWith('{')&&v.endsWith('}')){
var inner=v.slice(1,-1).trim();
return parseTomlInline(inner);
}
return v;
}

function parseTomlArray(s){
s=s.trim();
if(s==='')return[];
var result=[];
var depth=0;
var current='';
var inStr=false;
var strChar='';
for(var i=0;i<s.length;i++){
var c=s[i];
if(inStr){
current+=c;
if(c===strChar&&s[i-1]!=='\\'){inStr=false;}
continue;
}
if(c==='"'||c==="'"){inStr=true;strChar=c;current+=c;continue;}
if(c==='('||c==='['||c==='{')depth++;
if(c===')'||c===']'||c==='}')depth--;
if(c===','&&depth===0){
result.push(parseTomlValue(current.trim()));
current='';
}else{
current+=c;
}
}
if(current.trim()!=='')result.push(parseTomlValue(current.trim()));
return result;
}

function parseTomlInline(s){
s=s.trim();
if(s==='')return{};
var result={};
var depth=0;
var current='';
var inStr=false;
var strChar='';
var parts=[];
for(var i=0;i<s.length;i++){
var c=s[i];
if(inStr){
current+=c;
if(c===strChar&&s[i-1]!=='\\'){inStr=false;}
continue;
}
if(c==='"'||c==="'"){inStr=true;strChar=c;current+=c;continue;}
if(c==='('||c==='['||c==='{')depth++;
if(c===')'||c===']'||c==='}')depth--;
if(c===','&&depth===0){
parts.push(current);
current='';
}else{
current+=c;
}
}
if(current.trim()!=='')parts.push(current);
parts.forEach(function(p){
var eq=p.indexOf('=');
if(eq>-1){
var k=p.substring(0,eq).trim();
var v=p.substring(eq+1).trim();
if((k.startsWith('"')&&k.endsWith('"'))||(k.startsWith("'")&&k.endsWith("'"))){k=k.slice(1,-1);}
result[k]=parseTomlValue(v);
}
});
return result;
}

function jsonToYaml(obj,indent){
indent=indent||0;
var prefix='';
for(var i=0;i<indent;i++)prefix+='  ';
var out='';
if(obj===null)return prefix+'null\n';
if(typeof obj==='boolean')return prefix+obj.toString()+'\n';
if(typeof obj==='number')return prefix+obj.toString()+'\n';
if(typeof obj==='string'){
if(obj.indexOf('\n')>-1){
return prefix+'|\n'+obj.split('\n').map(function(l){return prefix+'  '+l;}).join('\n')+'\n';
}
if(obj.match(/^[{[\d\-]/)||obj.includes(': ')||obj.includes('#')||obj.includes('"')||obj.includes("'")||obj.trim()!==obj){
return prefix+'"'+obj.replace(/\\/g,'\\\\').replace(/"/g,'\\"')+'"\n';
}
return prefix+obj+'\n';
}
if(Array.isArray(obj)){
if(obj.length===0)return prefix+'[]\n';
var arrOut='';
obj.forEach(function(item){
if(typeof item==='object'&&item!==null&&!Array.isArray(item)){
var keys=Object.keys(item);
if(keys.length===1){
var k=keys[0];
arrOut+=prefix+'- '+k+': '+formatInline(item[k])+'\n';
}else{
arrOut+=prefix+'-\n';
arrOut+=jsonToYaml(item,indent+1);
}
}else{
arrOut+=prefix+'- '+formatInline(item)+'\n';
}
});
return arrOut;
}
var keys=Object.keys(obj);
if(keys.length===0)return prefix+'{}\n';
var out2='';
keys.forEach(function(k){
var val=obj[k];
if(typeof val==='object'&&val!==null&&!Array.isArray(val)&&Object.keys(val).length>0){
out2+=prefix+k+':\n'+jsonToYaml(val,indent+1);
}else if(Array.isArray(val)&&val.length>0){
out2+=prefix+k+':\n'+jsonToYaml(val,indent+1);
}else{
out2+=prefix+k+': '+formatInline(val)+'\n';
}
});
return out2;
}

function formatInline(val){
if(val===null)return 'null';
if(typeof val==='boolean')return val.toString();
if(typeof val==='number')return val.toString();
if(typeof val==='string'){
if(val.indexOf('\n')>-1){
return '|\n'+val.split('\n').map(function(l){return '  '+l;}).join('\n');
}
return JSON.stringify(val);
}
if(Array.isArray(val)){
if(val.length===0)return '[]';
return '['+val.map(function(v){return formatInline(v);}).join(', ')+']';
}
if(typeof val==='object'&&val!==null){
var keys=Object.keys(val);
if(keys.length===0)return '{}';
return '{'+keys.map(function(k){return k+': '+formatInline(val[k]);}).join(', ')+'}';
}
return String(val);
}

function jsonToToml(obj,sectionPath){
sectionPath=sectionPath||[];
var out='';
var simpleKeys={};
var nestedKeys={};
var arrayTableKeys={};
Object.keys(obj).forEach(function(k){
var v=obj[k];
if(v!==null&&typeof v==='object'&&!Array.isArray(v)){
nestedKeys[k]=v;
}else if(Array.isArray(v)&&v.length>0&&typeof v[0]==='object'&&!Array.isArray(v[0])&&v[0]!==null){
arrayTableKeys[k]=v;
}else{
simpleKeys[k]=v;
}
});
Object.keys(simpleKeys).forEach(function(k){
out+=k+' = '+tomlValue(simpleKeys[k])+'\n';
});
Object.keys(nestedKeys).forEach(function(k){
var fullPath=sectionPath.concat([k]);
if(fullPath.length===1){
out+='\n['+k+']\n';
}else{
out+='\n['+fullPath.map(function(s){return s.indexOf('.')>-1||s.indexOf(' ')>-1?'"'+s+'"':s;}).join('.')+']\n';
}
out+=jsonToToml(nestedKeys[k],fullPath);
});
Object.keys(arrayTableKeys).forEach(function(k){
var fullPath=sectionPath.concat([k]);
arrayTableKeys[k].forEach(function(item){
if(fullPath.length===1){
out+='\n[['+k+']]\n';
}else{
out+='\n[['+fullPath.map(function(s){return s.indexOf('.')>-1||s.indexOf(' ')>-1?'"'+s+'"':s;}).join('.')+']]\n';
}
out+=jsonToToml(item,fullPath);
});
});
return out;
}

function tomlValue(val){
if(val===null||val===undefined)return '""';
if(typeof val==='boolean')return val.toString();
if(typeof val==='number')return val.toString();
if(typeof val==='string'){
if(val.includes('\n')){
return '"""\n'+val+'"""';
}
return JSON.stringify(val);
}
if(Array.isArray(val)){
if(val.length===0)return '[]';
var items=val.map(function(v){return tomlValue(v);});
return '['+items.join(', ')+']';
}
if(typeof val==='object'){
var keys=Object.keys(val);
if(keys.length===0)return '{}';
var items=keys.map(function(k){return k+' = '+tomlValue(val[k]);});
return '{'+items.join(', ')+'}';
}
return JSON.stringify(val);
}

function jsonToYamlStr(json){return jsonToYaml(JSON.parse(json)).trimEnd();}
function jsonToTomlStr(json){return jsonToToml(JSON.parse(json)).trimEnd();}
function yamlToJson(yaml){return JSON.stringify(parseYaml(yaml),null,2);}
function tomlToJson(toml){return JSON.stringify(parseToml(toml),null,2);}

window.doConvert=function(){
hideError();
var fmt=inputFormat.value;
var raw=inputData.value.trim();
if(!raw){showError('Please enter some data to convert.');return;}
var obj=null;
try{
if(fmt==='json'){obj=parseJson(raw);}
else if(fmt==='yaml'){obj=parseYaml(raw);}
else if(fmt==='toml'){obj=parseToml(raw);}
}catch(e){
showError('Parse error ('+fmt.toUpperCase()+'): '+e.message);
return;
}
lastConverted=obj;
var jsonStr=JSON.stringify(obj,null,2);
outputJson.value=jsonStr;
if(fmt!=='yaml'){
try{outputYaml.value=jsonToYamlStr(jsonStr);}catch(e){outputYaml.value='Error generating YAML: '+e.message;}
}else{
outputYaml.value='';
}
if(fmt!=='toml'){
try{outputToml.value=jsonToTomlStr(jsonStr);}catch(e){outputToml.value='Error generating TOML: '+e.message;}
}else{
outputToml.value='';
}
};

window.copyOutput=function(id){
var el=document.getElementById(id);
if(!el.value){showToast('Nothing to copy');return;}
navigator.clipboard.writeText(el.value).then(function(){
showToast('Copied!');
}).catch(function(){
el.select();document.execCommand('copy');
showToast('Copied!');
});
};

window.swapInputOutput=function(){
var fmt=inputFormat.value;
var outputVal='';
var newFmt='';
if(fmt==='json'){
if(outputYaml.value){outputVal=outputYaml.value;newFmt='yaml';}
else if(outputToml.value){outputVal=outputToml.value;newFmt='toml';}
}else if(fmt==='yaml'){
if(outputJson.value){outputVal=outputJson.value;newFmt='json';}
else if(outputToml.value){outputVal=outputToml.value;newFmt='toml';}
}else if(fmt==='toml'){
if(outputJson.value){outputVal=outputJson.value;newFmt='json';}
else if(outputYaml.value){outputVal=outputYaml.value;newFmt='yaml';}
}
if(!outputVal){showToast('No output to swap');return;}
inputData.value=outputVal;
inputFormat.value=newFmt;
outputJson.value='';
outputYaml.value='';
outputToml.value='';
hideError();
showToast('Swapped '+newFmt.toUpperCase()+' to input');
};

inputFormat.addEventListener('change',function(){
var fmt=inputFormat.value;
blockYaml.style.display=fmt==='yaml'?'none':'';
blockToml.style.display=fmt==='toml'?'none':'';
blockJson.style.display=fmt==='json'?'none':'';
});

inputFormat.dispatchEvent(new Event('change'));
})();
</script>
<?php page_footer(); ?>
</body>
</html>