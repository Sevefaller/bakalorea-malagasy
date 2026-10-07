import { ref } from 'vue'
export const soundOn = ref(localStorage.getItem('bakalorea.sound') !== 'off')
export const soundBlocked = ref(false)
let context: AudioContext | undefined
export async function unlockSound() {
  if (!soundOn.value) return
  try { context ||= new AudioContext(); await context.resume(); soundBlocked.value = context.state !== 'running' } catch { soundBlocked.value = true }
}
export function playSound(kind: 'tick' | 'stop' | 'start' | 'win') {
  if (!soundOn.value || !context || context.state !== 'running') return
  const notes = kind === 'start' ? [392, 523, 659, 784, 1047] : kind === 'win' ? [523,659,784,1047] : kind === 'stop' ? [220,165] : [880]
  const spacing = kind === 'start' ? 0.2 : 0.13
  const length = kind === 'start' ? 0.19 : 0.13
  notes.forEach((frequency, i) => { const osc = context!.createOscillator(), gain = context!.createGain(), start = context!.currentTime + i * spacing; osc.frequency.value = frequency; gain.gain.setValueAtTime(kind === 'start' ? 0.09 : 0.07, start); gain.gain.exponentialRampToValueAtTime(0.001,start + length); osc.connect(gain); gain.connect(context!.destination); osc.start(start); osc.stop(start + length + 0.02) })
}
export async function toggleSound() { soundOn.value = !soundOn.value; localStorage.setItem('bakalorea.sound', soundOn.value ? 'on' : 'off'); await unlockSound(); if(soundOn.value) playSound('tick') }
