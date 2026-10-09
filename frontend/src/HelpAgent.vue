<script setup lang="ts">
import { nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Bot, Send, X } from 'lucide-vue-next'
import type { Game } from './store'

const props = defineProps<{ contextTip: string; game: Game | null; remainingSeconds: number }>()
const { t } = useI18n()
const open = ref(false)
const draft = ref('')
const input = ref<HTMLInputElement>()
const conversation = ref<HTMLElement>()
type Message = { from: 'user' | 'agent'; text?: string; key?: string; params?: Record<string, string | number> }
const messages = ref<Message[]>([{ from: 'agent', key: 'help.welcome' }])
const topics = ['start', 'join', 'answer', 'vote', 'score'] as const
type Topic = typeof topics[number]

function intent(question: string): Topic | 'connection' | 'host' | 'unknown' | 'roomCode' | 'myScore' | 'leader' | 'players' | 'round' | 'time' | 'settings' {
  const words = question.toLocaleLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
  if (/connect|connexion|reseau|internet|offline|tsy mandeha|tapaka|error|erreur/.test(words)) return 'connection'
  if (/kaody|code/.test(words) && props.game) return 'roomCode'
  if (/(point|score|isa)/.test(words) && /(mon|mes|my|isako|ahy|combien|firy|how many)/.test(words)) return 'myScore'
  if (/gagn|winner|mpandresy|premier|first|leader|mitarika/.test(words)) return 'leader'
  if (/combien de joueur|how many player|firy ny mpilalao|nombre de joueur/.test(words)) return 'players'
  if (/temps|time left|remaining time|chrono|segondra|secondes restantes|fotoana/.test(words)) return 'time'
  if (/objectif|target|tanjona|duree|duration|antitriche|anti-triche/.test(words)) return 'settings'
  if (/(quelle|quel|what|inona|current|actuel|amin.izao)/.test(words) && /(lettre|litera|categorie|category|sokajy|manche|round|fihodinana)/.test(words)) return 'round'
  if (/code|join|rejoin|rejoind|entrer|miditra|hiditra|salle|efitra|invitation/.test(words)) return 'join'
  if (/point|score|isa|classement|filaharana|gagn|win|resul|vokatra/.test(words)) return 'score'
  if (/vote|vot|valid|invalid|incorrect|mitsara|jug|judge|tsara|diso|egalit|tie/.test(words)) return 'vote'
  if (/repons|answer|valin|mot|teny|lettre|litera|stop|sais|enregistr|save|hitahiry/.test(words)) return 'answer'
  if (/maitre|hote|host|mpitarika|lancer|manomboka/.test(words)) return 'host'
  if (/creer|create|commenc|start|jouer|filalao|lalao|regle|rule|fonctionn|marche|aide|help|ahoana|how/.test(words)) return 'start'
  return 'unknown'
}

function answerFor(kind: ReturnType<typeof intent>): Pick<Message, 'key' | 'params'> {
  const game = props.game
  if (['roomCode', 'myScore', 'leader', 'players', 'round', 'time', 'settings'].includes(kind) && !game) return { key: 'help.answer.noGame' }
  if (!game) return { key: `help.answer.${kind}` }
  if (kind === 'roomCode') return { key: 'help.answer.roomCode', params: { code: game.code } }
  if (kind === 'myScore') return { key: 'help.answer.myScore', params: { score: game.players.find(player => player.id === game.me_id)?.score ?? 0, target: game.target_score } }
  if (kind === 'players') return { key: 'help.answer.players', params: { count: game.players.filter(player => player.online && !player.left).length } }
  if (kind === 'settings') return { key: 'help.answer.settings', params: { target: game.target_score, duration: game.answer_duration, unique: game.unique_points, duplicate: game.duplicate_points } }
  if (kind === 'round') return game.round ? { key: 'help.answer.round', params: { number: game.round.number, category: t(`category.${game.round.category}`), letter: game.round.letter } } : { key: 'help.answer.noRound' }
  if (kind === 'time') return game.round?.status === 'answering' ? { key: 'help.answer.time', params: { seconds: props.remainingSeconds } } : { key: 'help.answer.noTimer' }
  if (kind === 'leader') {
    const leaders = [...game.players].sort((a, b) => b.score - a.score)
    if (game.status === 'finished') return { key: 'help.answer.winner', params: { name: leaders.find(player => player.id === game.winner_id)?.nickname ?? leaders[0]?.nickname ?? '', score: leaders[0]?.score ?? 0 } }
    if (leaders.length > 1 && leaders[0].score === leaders[1].score) return { key: 'help.answer.leaderTied', params: { score: leaders[0].score } }
    return { key: 'help.answer.leader', params: { name: leaders[0]?.nickname ?? '', score: leaders[0]?.score ?? 0 } }
  }
  return { key: `help.answer.${kind}` }
}

function send(topic?: Topic) {
  const question = topic ? t(`help.topic.${topic}`) : draft.value.trim()
  if (!question) return
  messages.value.push({ from: 'user', text: question })
  messages.value.push({ from: 'agent', ...answerFor(topic || intent(question)) })
  draft.value = ''
  nextTick(() => { if (conversation.value) conversation.value.scrollTop = conversation.value.scrollHeight; input.value?.focus() })
}

function toggle() {
  open.value = !open.value
  if (open.value) nextTick(() => input.value?.focus())
}

watch(open, value => { if (!value) draft.value = '' })
</script>

<template>
  <div class="help-agent">
    <section v-if="open" id="help-agent-panel" class="help-panel" role="dialog" :aria-label="t('help.title')" @keydown.esc="toggle">
      <div class="help-heading"><span class="help-heading-icon"><Bot :size="22" /></span><div><strong>{{ t('help.title') }}</strong><small>{{ t('help.subtitle') }}</small></div><button type="button" class="help-close" :aria-label="t('close')" @click="toggle"><X :size="20" /></button></div>
      <div ref="conversation" class="help-conversation" role="log" aria-live="polite">
        <p v-for="(message, index) in messages" :key="index" :class="['help-message', message.from]">{{ message.key ? t(message.key, message.params || {}) : message.text }}</p>
        <div class="help-context"><strong>{{ t('help.currentStep') }}</strong><p>{{ contextTip }}</p></div>
      </div>
      <div class="help-topics" :aria-label="t('help.suggestions')"><button v-for="topic in topics" :key="topic" type="button" @click="send(topic)">{{ t(`help.topic.${topic}`) }}</button></div>
      <form class="help-form" @submit.prevent="send()"><label class="sr-only" for="help-question">{{ t('help.placeholder') }}</label><input id="help-question" ref="input" v-model="draft" maxlength="250" :placeholder="t('help.placeholder')" autocomplete="off"><button type="submit" :disabled="!draft.trim()" :aria-label="t('help.send')"><Send :size="18" /></button></form>
    </section>
    <button type="button" class="help-launcher" :aria-label="t('help.title')" :aria-expanded="open" aria-controls="help-agent-panel" @click="toggle"><X v-if="open" :size="25"/><Bot v-else :size="30"/></button>
  </div>
</template>

<style scoped>
.help-agent{position:fixed;right:20px;bottom:20px;z-index:100;display:flex;flex-direction:column;align-items:flex-end;gap:12px;font-family:'Inter',system-ui,sans-serif}
.help-launcher{display:grid;place-items:center;width:58px;height:58px;border:0;border-radius:50%;background:#082b49;color:#fff;box-shadow:0 5px 16px #09263d47;cursor:pointer}
.help-launcher:hover{background:#124269}.help-launcher:focus-visible,.help-close:focus-visible,.help-topics button:focus-visible,.help-form button:focus-visible{outline:3px solid #83bcb3;outline-offset:3px}
.help-panel{display:flex;flex-direction:column;width:min(360px,calc(100vw - 32px));height:min(530px,calc(100dvh - 100px));overflow:hidden;border:1px solid #dce7eb;border-radius:16px;background:#fff;box-shadow:0 14px 40px #102c4140}
.help-heading{display:flex;flex-shrink:0;align-items:center;gap:10px;padding:14px 16px;background:#082b49;color:#fff}.help-heading-icon{display:grid;place-items:center;width:36px;height:36px;border:1px solid #ffffff70;border-radius:50%}.help-heading>div{display:flex;flex:1;flex-direction:column;gap:2px}.help-heading strong{font-size:.9rem}.help-heading small{font-size:.7rem;color:#c7e1ef}.help-close{display:grid;place-items:center;width:32px;height:32px;border:0;border-radius:8px;background:transparent;color:#fff;cursor:pointer}.help-close:hover{background:#ffffff23}
.help-conversation{display:flex;flex:1;flex-direction:column;gap:9px;min-height:0;overflow:auto;padding:16px;background:#f7fafb}.help-message{flex-shrink:0;align-self:flex-start;max-width:90%;padding:9px 12px;border-radius:12px 12px 12px 3px;background:#fff;color:#243844;font-size:.82rem;line-height:1.45;box-shadow:0 1px 4px #112d4114;white-space:pre-wrap}.help-message.user{align-self:flex-end;border-radius:12px 12px 3px 12px;background:#dff0e8;color:#174c39}.help-context{flex-shrink:0;margin-top:5px;padding:10px 12px;border-left:3px solid #2e8a7d;border-radius:6px;background:#edf7f2;color:#285a4b;font-size:.78rem}.help-context strong{display:block;margin-bottom:3px;font-size:.73rem}.help-context p{line-height:1.45}
.help-topics{display:flex;flex-shrink:0;flex-wrap:wrap;gap:6px;padding:10px 12px;border-top:1px solid #e8eef0}.help-topics button{padding:7px 10px;border:1px solid #b9d7ce;border-radius:20px;background:#fff;color:#226d59;font-size:.72rem;cursor:pointer}.help-topics button:hover{background:#edf7f2}
.help-form{display:flex;flex-shrink:0;gap:7px;padding:10px 12px 12px;border-top:1px solid #e8eef0}.help-form input{flex:1;min-width:0;padding:10px 12px;border:1px solid #cddbe0;border-radius:9px;font:inherit;font-size:.78rem}.help-form button{display:grid;place-items:center;width:39px;border:0;border-radius:9px;background:#2e8a7d;color:#fff;cursor:pointer}.help-form button:disabled{opacity:.45;cursor:not-allowed}
@media(max-width:460px){.help-agent{right:12px;bottom:12px}.help-panel{height:min(530px,calc(100dvh - 88px))}.help-launcher{width:54px;height:54px}}
</style>
