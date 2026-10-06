import './init.mjs'
import { spawn } from 'node:child_process'
const p=spawn('docker',['compose','up','--build','-d'],{stdio:'inherit',windowsHide:true})
p.on('error',e=>{console.error(e.message);process.exitCode=1})
p.on('exit',code=>{process.exitCode=code || 0;if(code===0) console.log('Bakalorea : http://localhost:8088')})
