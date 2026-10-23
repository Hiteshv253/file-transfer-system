// Opt-in live test: native directory picker, folder creation/browsing, and cleanup.
// Set FILEVAULT_TEST_URL, FILEVAULT_TEST_EMAIL, FILEVAULT_TEST_PASSWORD.
const {spawn}=require('node:child_process');
const fs=require('node:fs');
const os=require('node:os');
const path=require('node:path');
const assert=require('node:assert/strict');
const base=process.env.FILEVAULT_TEST_URL;
const email=process.env.FILEVAULT_TEST_EMAIL;
const password=process.env.FILEVAULT_TEST_PASSWORD;
assert.ok(base&&email&&password,'Set live test URL and credentials');
const prefix=`filevault-folder-qa-${Date.now()}`;
const temp=fs.mkdtempSync(path.join(os.tmpdir(),'filevault-folder-live-'));
const directory=path.join(temp,`${prefix}-bundle`);
for(const part of ['Quarter 1','Quarter 2'])fs.mkdirSync(path.join(directory,part),{recursive:true});
fs.writeFileSync(path.join(directory,'Quarter 1','report.txt'),'First folder report');
fs.writeFileSync(path.join(directory,'Quarter 2','report.txt'),'Second folder report');
const browser=spawn(process.env.BROWSER_EXE||'C:/Program Files/Google/Chrome/Application/chrome.exe',
    ['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0',`--user-data-dir=${path.join(temp,'chrome')}`,'about:blank'],{windowsHide:true,stdio:'ignore'});
let socket,sequence=0,rootId=0;
const pending=new Map();
const exceptions=[];
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
function send(method,params={}){return new Promise((resolve,reject)=>{
    const id=++sequence;const timer=setTimeout(()=>{pending.delete(id);reject(Error(`Timed out: ${method}`));},30000);
    pending.set(id,{resolve,reject,timer});socket.send(JSON.stringify({id,method,params}));
});}
async function evaluate(expression){const data=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(data.exceptionDetails)throw Error(data.exceptionDetails.exception?.description||data.exceptionDetails.text);return data.result.value;}
async function waitFor(expression){for(let n=0;n<150;n++){if(await evaluate(expression))return;await delay(200);}throw Error(`Page state not reached: ${expression}`);}
async function cleanup(){
    if(!rootId||!socket)return;
    const result=await evaluate(`(async()=>{
        history.replaceState(null,'','/files');
        const csrf=document.querySelector('[name="_token"]').value;
        const folders=[], documents=new Set();
        async function scan(id){
            const response=await fetch('/files?folder='+id);if(!response.ok)throw Error('Could not inspect QA folder '+id);
            const page=new DOMParser().parseFromString(await response.text(),'text/html');
            folders.push(id);
            page.querySelectorAll('.file-card .file-actions a[href$="/download"]').forEach(link=>documents.add(Number(link.getAttribute('href').split('/')[2])));
            for(const link of page.querySelectorAll('.folder-open'))await scan(Number(new URL(link.href,location.href).searchParams.get('folder')));
        }
        await scan(${rootId});
        for(const id of documents){const response=await fetch('/files/'+id+'/delete',{method:'POST',headers:{'X-CSRF-TOKEN':csrf}});if(!response.ok)throw Error('Could not delete QA file '+id);}
        for(const id of folders.reverse()){const response=await fetch('/files/folders/'+id+'/delete',{method:'POST',headers:{'X-CSRF-TOKEN':csrf}});if(!response.ok)throw Error('Could not delete empty QA folder '+id);}
        return {folders:folders.length,files:documents.size};
    })()`);
    rootId=0;console.log(`PASS: QA cleanup removed ${result.files} files and ${result.folders} folders`);
}
(async()=>{
    try{
        const portFile=path.join(temp,'chrome','DevToolsActivePort');
        for(let n=0;!fs.existsSync(portFile)&&n<100;n++)await delay(100);
        const port=Number(fs.readFileSync(portFile,'utf8').split('\n')[0]);
        const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();
        socket=new WebSocket(tabs.find(tab=>tab.type==='page').webSocketDebuggerUrl);
        await new Promise((resolve,reject)=>{socket.addEventListener('open',resolve,{once:true});socket.addEventListener('error',reject,{once:true});});
        socket.addEventListener('message',event=>{const data=JSON.parse(event.data);if(data.method==='Runtime.exceptionThrown')exceptions.push(data.params.exceptionDetails.exception?.description||data.params.exceptionDetails.text);const entry=pending.get(data.id);if(!entry)return;clearTimeout(entry.timer);pending.delete(data.id);data.error?entry.reject(Error(data.error.message)):entry.resolve(data.result);});
        await send('Page.enable');await send('Runtime.enable');
        await send('Page.navigate',{url:base+'/files/login'});
        await waitFor('!!document.getElementById("email")');
        await evaluate(`document.getElementById('email').value=${JSON.stringify(email)};document.getElementById('password').value=${JSON.stringify(password)};document.querySelector('.login form').requestSubmit()`);
        await waitFor('!!document.getElementById("folder-input") && document.readyState === "complete"');
        await evaluate(`window.__qaUploader=document.getElementById('upload-form');document.querySelector('.new-folder').open=true;document.querySelector('.new-folder input[name="name"]').value=${JSON.stringify(prefix+'-destination')};document.querySelector('.new-folder form').requestSubmit()`);
        await waitFor(`document.querySelector('.folder-breadcrumbs').textContent.includes(${JSON.stringify(prefix+'-destination')}) && document.querySelector('.drive-main').getAttribute('aria-busy')!=='true'`);
        rootId=await evaluate('Number(document.getElementById("upload-form").dataset.folderId)');
        assert.ok(rootId>0);assert.equal(await evaluate('window.__qaUploader===document.getElementById("upload-form")'),true);
        console.log('PASS: New folder creates and opens an empty destination without reloading uploader');
        await evaluate('document.getElementById("tab-upload").click()');
        const documentNode=await send('DOM.getDocument');
        const inputNode=await send('DOM.querySelector',{nodeId:documentNode.root.nodeId,selector:'#folder-input'});
        await send('DOM.setFileInputFiles',{nodeId:inputNode.nodeId,files:[directory]});
        await waitFor('document.getElementById("upload-queue").children.length===2');
        const selected=await evaluate(`JSON.parse(localStorage.getItem('file-vault-upload-batch-'+document.getElementById('upload-form').dataset.userId))`);
        assert.deepEqual(selected.map(item=>item.relativePath).sort(),[`${prefix}-bundle/Quarter 1/report.txt`,`${prefix}-bundle/Quarter 2/report.txt`]);
        assert.ok(selected.every(item=>item.parentFolderId===rootId));
        console.log('PASS: native folder picker retains root name and subfolder paths');
        await evaluate('document.getElementById("upload-form").requestSubmit()');
        await waitFor('document.getElementById("upload-feedback").textContent.includes("2 file(s) uploaded.")');
        const receipts=await evaluate(`JSON.parse(localStorage.getItem('file-vault-upload-batch-'+document.getElementById('upload-form').dataset.userId))`);
        assert.equal(receipts.length,2);assert.ok(receipts.every(item=>item.state==='done'&&item.documentId));
        const checked=await evaluate(`(async()=>{
            const receipts=${JSON.stringify(receipts)};
            const result=[];
            for(const receipt of receipts){
                const response=await fetch('/files/'+receipt.documentId+'/download');const content=await response.text();
                result.push({path:receipt.relativePath,content,status:response.status});
            }
            const response=await fetch('/files?folder=${rootId}');
            const page=new DOMParser().parseFromString(await response.text(),'text/html');
            const link=[...page.querySelectorAll('.folder-open')].find(link=>link.querySelector('strong').textContent===${JSON.stringify(prefix+'-bundle')});
            if(!link)throw Error('Uploaded root folder is missing');
            const bundle=Number(new URL(link.href,location.href).searchParams.get('folder'));
            const childrenPage=new DOMParser().parseFromString(await(await fetch('/files?folder='+bundle)).text(),'text/html');
            const children=[...childrenPage.querySelectorAll('.folder-open')].map(link=>({id:Number(new URL(link.href,location.href).searchParams.get('folder')),name:link.querySelector('strong').textContent}));
            for(const child of children){const page=new DOMParser().parseFromString(await(await fetch('/files?folder='+child.id)).text(),'text/html');if(page.querySelectorAll('.file-card').length!==1)throw Error('Incorrect file count in '+child.name);}
            const csrf=document.querySelector('[name="_token"]').value;
            const empty=await fetch('/files/folders',{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify({name:'Empty child',parent_id:bundle})});
            if(empty.status!==201)throw Error('Empty folder creation failed');
            return {files:result,children:children.map(child=>child.name)};
        })()`);
        assert.deepEqual(checked.children.sort(),['Quarter 1','Quarter 2']);
        for(const file of checked.files){assert.equal(file.status,200);assert.equal(file.content,file.path.includes('Quarter 1')?'First folder report':'Second folder report');}
        console.log('PASS: uploaded folder hierarchy, same-name files, exact bytes, and empty child folder');
        await send('Emulation.setDeviceMetricsOverride',{width:320,height:1000,deviceScaleFactor:1,mobile:false});
        assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'),true);
        assert.deepEqual(exceptions,[]);console.log('PASS: 320px layout and no browser JavaScript exceptions');
        await cleanup();
    }catch(error){console.error(error.message);process.exitCode=1;}
    finally{try{await cleanup();}catch(error){console.error('QA cleanup failed:',error.message);process.exitCode=1;}if(socket)socket.close();browser.kill();for(const entry of pending.values())clearTimeout(entry.timer);}
})();
