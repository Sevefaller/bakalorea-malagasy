import './init.mjs'
import { spawn, spawnSync } from 'node:child_process'
import { existsSync } from 'node:fs'
import { resolve, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'
const root = resolve(dirname(fileURLToPath(import.meta.url)),'..')
const win = process.platform === 'win32'
const phpFlags = win ? ['-d','extension=pdo_sqlite','-d','extension=sqlite3'] : []
function run(command, args, cwd=root) { const r=spawnSync(command,args,{cwd,stdio:'inherit',shell:win && /\.(cmd|bat)$/.test(command)}); if(r.status!==0) process.exit(r.status || 1) }
if(!existsSync(resolve(root,'backend/vendor/autoload.php'))) run(win?'composer.bat':'composer',['install','--no-interaction'],resolve(root,'backend'))
if(!existsSync(resolve(root,'frontend/node_modules'))) run(win?'npm.cmd':'npm',['ci'],resolve(root,'frontend'))
run('php',[...phpFlags,'artisan','migrate','--force'],resolve(root,'backend'))
const children=[]
function service(command,args,cwd) { const p=spawn(command,args,{cwd,stdio:'inherit',shell:win && command.endsWith('.cmd'),windowsHide:true}); children.push(p); p.on('error',e=>console.error(e.message)); return p }
service('php',[...phpFlags,'-S','127.0.0.1:8000',resolve(root,'backend/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],resolve(root,'backend/public'))
service('php',[...phpFlags,'artisan','reverb:start','--host=0.0.0.0','--port=8080'],resolve(root,'backend'))
service(win?'npm.cmd':'npm',['run','dev'],resolve(root,'frontend'))
console.log('\nBakalorea : http://localhost:5173\nCtrl+C pour arrêter.\n')
for(const signal of ['SIGINT','SIGTERM']) process.on(signal,()=>{children.forEach(p=>p.kill());process.exit()})
