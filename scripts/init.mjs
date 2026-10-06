import { existsSync, readFileSync, writeFileSync, mkdirSync } from 'node:fs'
import { randomBytes } from 'node:crypto'
import { fileURLToPath } from 'node:url'
import { resolve, dirname } from 'node:path'
const root = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const key = () => randomBytes(32).toString('hex')
if (!existsSync(resolve(root,'.env'))) writeFileSync(resolve(root,'.env'), `APP_KEY=base64:${randomBytes(32).toString('base64')}\nDB_PASSWORD=${key()}\nREVERB_APP_ID=bakalorea\nREVERB_APP_KEY=${key()}\nREVERB_APP_SECRET=${key()}\nSITE_ADDRESS=:80\nHTTP_PORT=8088\nREVERB_PUBLIC_PORT=8088\nREVERB_PUBLIC_SCHEME=http\n`)
if (!existsSync(resolve(root,'backend/.env'))) {
  let env = readFileSync(resolve(root,'backend/.env.example'),'utf8')
  env = env.replace(/^APP_KEY=.*$/m, `APP_KEY=base64:${randomBytes(32).toString('base64')}`)
  env += `\nREVERB_APP_ID=bakalorea-local\nREVERB_APP_KEY=${key()}\nREVERB_APP_SECRET=${key()}\n`
  writeFileSync(resolve(root,'backend/.env'),env)
}
mkdirSync(resolve(root,'backend/database'),{recursive:true})
if(!existsSync(resolve(root,'backend/database/database.sqlite'))) writeFileSync(resolve(root,'backend/database/database.sqlite'),'')
mkdirSync(resolve(root,'.runtime'),{recursive:true})
console.log('Configuration prête. Les secrets existants ont été conservés.')
