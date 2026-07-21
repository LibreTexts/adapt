<template>
  <div class="flashcard-root">
    <!-- ─── Flashcard Settings Modal (Instructor only) ─────────────────────── -->
    <b-modal
      :id="`modal-flashcard-card-settings-${uuid}`"
      title="Card Settings"
      size="md"
      no-close-on-backdrop
      @shown="initSettings"
      @hidden="resetSettingsForm"
    >
      <b-container>
        <b-form-group v-if="flashcardSettings" label-cols-sm="6" label-size="sm" label-align="right">
          <template #label>
            Show Hint
            <QuestionCircleTooltip :id="'fc-card-show-hint-tooltip'" />
            <b-tooltip target="fc-card-show-hint-tooltip" delay="250" triggers="hover focus">
              Override whether the hint button is shown for this card.
            </b-tooltip>
          </template>
          <b-form-select v-model="settingsForm.show_hint"
                         :options="[{ value: true, text: 'On' }, { value: false, text: 'Off' }]" size="sm"
                         style="width:130px"
          />
        </b-form-group>
        <b-form-group v-if="flashcardSettings" label-cols-sm="6" label-size="sm" label-align="right">
          <template #label>
            Text-to-Speech
            <QuestionCircleTooltip :id="'fc-card-tts-tooltip'" />
            <b-tooltip target="fc-card-tts-tooltip" delay="250" triggers="hover focus">
              Override whether text-to-speech is available for this card.
            </b-tooltip>
          </template>
          <b-form-select v-model="settingsForm.text_to_speech"
                         :options="[{ value: true, text: 'On' }, { value: false, text: 'Off' }]" size="sm"
                         style="width:130px"
          />
        </b-form-group>
        <b-form-group v-if="flashcardSettings" label-cols-sm="6" label-size="sm" label-align="right">
          <template #label>
            Captions
            <QuestionCircleTooltip :id="'fc-card-captions-tooltip'" />
            <b-tooltip target="fc-card-captions-tooltip" delay="250" triggers="hover focus">
              Override whether captions are shown for this card.
            </b-tooltip>
          </template>
          <b-form-select v-model="settingsForm.captions"
                         :options="[{ value: true, text: 'On' }, { value: false, text: 'Off' }]" size="sm"
                         style="width:130px"
          />
        </b-form-group>
      </b-container>
      <template #modal-footer>
        <b-button size="sm" @click="$bvModal.hide(`modal-flashcard-card-settings-${uuid}`)">
          Cancel
        </b-button>
        <b-button size="sm" variant="primary" @click="saveSettings">
          Save
        </b-button>
      </template>
    </b-modal>

    <!-- ─── Student Settings Modal ────────────────────────────────────────── -->
    <b-modal
      :id="`modal-flashcard-student-settings-${uuid}`"
      title="Flashcard Settings"
      size="md"
      no-close-on-backdrop
      :hide-header-close="!initialized"
      @shown="openStudentSettings"
      @hidden="initialize"
    >
      <b-container>
        <b-form-group v-if="flashcardSettings && flashcardSettings.autoplay.student_override" label-cols-sm="4"
                      label-size="sm" label-align="right"
        >
          <template #label>
            Autoplay
            <QuestionCircleTooltip :id="`fc-student-autoplay-tooltip-${uuid}`" />
            <b-tooltip :target="`fc-student-autoplay-tooltip-${uuid}`" delay="250" triggers="hover focus">
              Automatically flips each card and advances to the next after the set number of seconds.
            </b-tooltip>
          </template>
          <div class="d-flex align-items-center">
            <b-form-radio-group v-model="studentSettingsForm.autoplay" name="student_autoplay_enabled"
                                class="d-inline-flex align-items-center mr-3"
            >
              <b-form-radio :value="true">
                On
              </b-form-radio>
              <b-form-radio :value="false">
                Off
              </b-form-radio>
            </b-form-radio-group>
            <span v-if="studentSettingsForm.autoplay" class="d-inline-flex align-items-center">
              <b-form-input v-model="studentSettingsForm.autoplay_seconds" type="text" style="width:46px"
                            class="mr-1 no-spinner" @keydown="onAutoplaySecondsKeydown" @paste.prevent
                            @input="onAutoplaySecondsInput"
              />
              <span class="text-muted small">sec/side</span>
            </span>
          </div>
        </b-form-group>
        <b-form-group v-if="flashcardSettings && flashcardSettings.random_shuffle.student_override" label-cols-sm="4"
                      label-size="sm" label-align="right"
        >
          <template #label>
            Random Shuffle
            <QuestionCircleTooltip :id="`fc-student-shuffle-tooltip-${uuid}`" />
            <b-tooltip :target="`fc-student-shuffle-tooltip-${uuid}`" delay="250" triggers="hover focus">
              Randomizes the order of cards each time you start or restart the deck.
            </b-tooltip>
          </template>
          <b-form-radio-group v-model="studentSettingsForm.random_shuffle"
                              :options="[{value: true, text: 'On'}, {value: false, text: 'Off'}]" size="sm"
          />
        </b-form-group>
        <b-form-group v-if="flashcardSettings && flashcardSettings.show_hint.student_override" label-cols-sm="4"
                      label-size="sm" label-align="right"
        >
          <template #label>
            Show Hint
            <QuestionCircleTooltip :id="`fc-student-hint-tooltip-${uuid}`" />
            <b-tooltip :target="`fc-student-hint-tooltip-${uuid}`" delay="250" triggers="hover focus">
              Shows a hint before flipping the card.
            </b-tooltip>
          </template>
          <b-form-radio-group v-model="studentSettingsForm.show_hint"
                              :options="[{value: true, text: 'On'}, {value: false, text: 'Off'}]" size="sm"
          />
        </b-form-group>
        <b-form-group v-if="flashcardSettings && flashcardSettings.text_to_speech.student_override" label-cols-sm="4"
                      label-size="sm" label-align="right"
        >
          <template #label>
            Text-to-Speech
            <QuestionCircleTooltip :id="`fc-student-tts-tooltip-${uuid}`" />
            <b-tooltip :target="`fc-student-tts-tooltip-${uuid}`" delay="250" triggers="hover focus">
              Plays an audio pronunciation of the card content.
            </b-tooltip>
          </template>
          <b-form-radio-group v-model="studentSettingsForm.text_to_speech"
                              :options="[{value: true, text: 'On'}, {value: false, text: 'Off'}]" size="sm"
          />
        </b-form-group>
        <b-form-group v-if="flashcardSettings && flashcardSettings.captions.student_override" label-cols-sm="4"
                      label-size="sm" label-align="right"
        >
          <template #label>
            Captions
            <QuestionCircleTooltip :id="`fc-student-captions-tooltip-${uuid}`" />
            <b-tooltip :target="`fc-student-captions-tooltip-${uuid}`" delay="250" triggers="hover focus">
              Shows captions for audio and video content.
            </b-tooltip>
          </template>
          <b-form-radio-group v-model="studentSettingsForm.captions"
                              :options="[{value: true, text: 'On'}, {value: false, text: 'Off'}]" size="sm"
          />
        </b-form-group>
        <hr>
        <div class="fc-keyboard-shortcuts">
          <div class="fc-keyboard-shortcuts-title">
            Keyboard Shortcuts
          </div>
          <table class="fc-keyboard-table">
            <tbody>
            <tr>
              <td><kbd>Space</kbd> / <kbd>Enter</kbd></td>
              <td>Flip card</td>
            </tr>
            <tr>
              <td><kbd>&#8594;</kbd></td>
              <td>Next card</td>
            </tr>
            <tr>
              <td><kbd>&#8592;</kbd></td>
              <td>Previous card</td>
            </tr>
            <tr>
              <td><kbd>&#8593;</kbd></td>
              <td>Mark Correct (after flip)</td>
            </tr>
            <tr>
              <td><kbd>&#8595;</kbd></td>
              <td>Mark Incorrect (after flip)</td>
            </tr>
            </tbody>
          </table>
        </div>
      </b-container>
      <template #modal-footer>
        <b-button v-if="initialized" size="sm" @click="$bvModal.hide(`modal-flashcard-student-settings-${uuid}`)">
          Cancel
        </b-button>
        <b-button size="sm" variant="primary" @click="saveStudentSettings">
          {{ initialized ? 'Save' : 'Start' }}
        </b-button>
      </template>
    </b-modal>

    <!-- ─── Main session layout ─────────────────────────────────────────────── -->
    <div class="fc-session">
      <div v-if="orderedCards.length > 1" class="fc-progress-wrap">
        <div class="fc-progress-bar" :style="{ width: progressPct + '%' }" />
      </div>

      <div v-if="orderedCards.length > 1" class="fc-header">
        <span class="fc-counter">Card {{ currentIndex + 1 }} <span class="fc-counter-of">of {{
            cards.length
          }}</span></span>
      </div>

      <div v-if="orderedCards.length > 1" aria-live="polite" aria-atomic="true" class="sr-only">
        Card {{ currentIndex + 1 }} of {{ orderedCards.length }}.
      </div>
      <div aria-live="assertive" aria-atomic="true" class="sr-only">
        {{
          isFlipped ? 'Answer: ' + (currentCard.answer || currentCard.back || '') : 'Question: ' + (currentCard.term || currentCard.front || '')
        }}
      </div>
      <audio v-if="currentCard.frontTtsUrl || currentCard.backTtsUrl" ref="ttsAudio" :key="`tts-${currentIndex}`"
             preload="none" aria-hidden="true" @ended="ttsPlayingSide = null"
      />

      <!-- ─── Card ──────────────────────────────────────────────────────── -->
      <div
        class="fc-scene"
        :class="{
          'is-flipped': isFlipped,
          'has-video': currentCard.frontMediaType === 'video' || currentCard.backMediaType === 'video'
            || currentCard.frontMediaType === 'youtube' || currentCard.backMediaType === 'youtube'
        }"
        :aria-label="isFlipped ? 'Answer side — press Space or Enter to flip back' : 'Question side — press Space or Enter to reveal answer'"
        role="button" tabindex="0"
        @click="handleCardClick"
        @keydown.enter.space.prevent="handleCardClick"
      >
        <!-- FRONT -->
        <div ref="frontFace" class="fc-face fc-front" :aria-hidden="isFlipped ? 'true' : 'false'">
          <div class="fc-top-actions">
            <button v-if="(assessmentType !== 'flashcard' || canShowHint) && !isFlipped" type="button"
                    class="fc-icon-btn fc-hint-icon" :class="{ 'fc-icon-btn--active': hintVisible }"
                    :aria-label="hintVisible ? 'Hide hint' : 'Show hint'"
                    :aria-expanded="hintVisible ? 'true' : 'false'" aria-controls="fc-hint-bubble" tabindex="0"
                    @click.stop="toggleHint"
            >
              <span aria-hidden="true">💡</span>
            </button>
            <button v-if="ttsEnabled && effectiveFrontTtsUrl" type="button" class="fc-icon-btn"
                    :class="{ 'fc-icon-btn--active': ttsPlayingSide === 'front' }"
                    :aria-label="ttsPlayingSide === 'front' ? 'Stop audio' : 'Play pronunciation'" tabindex="0"
                    @click.stop="toggleTts('front')"
            >
              <b-icon :icon="ttsPlayingSide === 'front' ? 'stop-circle-fill' : 'mic-fill'" aria-hidden="true" />
            </button>
          </div>
          <div class="fc-face-content">
            <template v-if="currentCard.frontType === 'free_form'">
              <div class="fc-rich math-tex" v-html="currentCard.front" />
            </template>
            <template v-else-if="currentCard.frontType === 'text_only'">
              <div class="fc-term math-tex">
                {{ currentCard.term }}
              </div>
            </template>
            <template v-else-if="currentCard.frontType === 'text_media'">
              <div class="fc-two-col">
                <div class="fc-term math-tex">
                  {{ currentCard.term }}
                </div>
                <div :class="currentCard.frontMediaType !== 'audio' ? 'fc-media' : ''">
                  <CardMedia :card="currentCard" side="front"  />
                </div>
              </div>
            </template>
            <template v-else-if="currentCard.frontType === 'media'">
              <div class="fc-media-center">
                <CardMedia :card="currentCard" side="front"  />
              </div>
            </template>
          </div>
          <transition name="fc-hint-fade">
            <div v-if="hintVisible" id="fc-hint-bubble" class="fc-hint-bubble" role="status" aria-live="polite"
                 aria-atomic="true" @click.stop
            >
              <button type="button" class="fc-hint-close" aria-label="Close hint" @click.stop="hintVisible = false">
                <b-icon icon="x" aria-hidden="true" />
              </button>
              <span class="fc-hint-label" aria-hidden="true">Hint</span>
              <span class="fc-hint-text math-tex" aria-hidden="true">{{ effectiveHintText }}</span>
              <span class="sr-only">{{ effectiveHintSrText }}</span>
            </div>
          </transition>
        </div>

        <!-- BACK -->
        <div ref="backFace" class="fc-face fc-back" :aria-hidden="!isFlipped ? 'true' : 'false'">
          <div v-if="ttsEnabled && effectiveBackTtsUrl" class="fc-top-actions">
            <button type="button" class="fc-icon-btn" :class="{ 'fc-icon-btn--active': ttsPlayingSide === 'back' }"
                    :aria-label="ttsPlayingSide === 'back' ? 'Stop audio' : 'Play pronunciation'" tabindex="0"
                    @click.stop="toggleTts('back')"
            >
              <b-icon :icon="ttsPlayingSide === 'back' ? 'stop-circle-fill' : 'mic-fill'" aria-hidden="true" />
            </button>
          </div>
          <div class="fc-face-content">
            <template v-if="currentCard.backType === 'free_form'">
              <div class="fc-rich math-tex" v-html="currentCard.back" />
            </template>
            <template v-else-if="currentCard.backType === 'text_only'">
              <div class="fc-answer math-tex">
                {{ currentCard.answer }}
              </div>
            </template>
            <template v-else-if="currentCard.backType === 'text_media'">
              <div class="fc-two-col">
                <div class="fc-answer math-tex">
                  {{ currentCard.answer }}
                </div>
                <div :class="currentCard.backMediaType !== 'audio' ? 'fc-media' : ''">
                  <CardMedia :card="currentCard" side="back"  />
                </div>
              </div>
            </template>
            <template v-else-if="currentCard.backType === 'media'">
              <div class="fc-media-center">
                <CardMedia :card="currentCard" side="back"  />
              </div>
            </template>
          </div>
        </div>
      </div>
      <!-- ─── Navigation + Self-report ───────────────────────────────────── -->
      <div class="fc-nav">
        <b-button v-if="orderedCards.length > 1" variant="outline-secondary" :disabled="currentIndex === 0"
                  class="fc-nav-btn" :aria-label="`Previous card, card ${currentIndex} of ${orderedCards.length}`"
                  @click="goTo(currentIndex - 1)"
        >
          <b-icon icon="chevron-left" aria-hidden="true" />
          Prev
        </b-button>

        <transition name="fc-report-slide">
          <div v-if="isFlipped && !previewingQuestion" class="fc-self-report-buttons"
               :style="orderedCards.length === 1 ? 'width:100%;justify-content:center' : ''" role="group"
               aria-label="Did you get it?"
          >
            <b-button :variant="selfReport === 'correct' ? 'success' : 'outline-success'" class="fc-report-btn"
                      :disabled="submitting" :aria-pressed="selfReport === 'correct' ? 'true' : 'false'"
                      @click="submitSelfReport('correct')"
            >
              <b-spinner v-if="submitting && selfReport === 'correct'" small class="mr-1" aria-hidden="true" />
              <b-icon v-else icon="check-circle-fill" class="mr-1" aria-hidden="true" />
              Correct
            </b-button>
            <b-button :variant="selfReport === 'incorrect' ? 'danger' : 'outline-danger'" class="fc-report-btn"
                      :disabled="submitting" :aria-pressed="selfReport === 'incorrect' ? 'true' : 'false'"
                      @click="submitSelfReport('incorrect')"
            >
              <b-spinner v-if="submitting && selfReport === 'incorrect'" small class="mr-1" aria-hidden="true" />
              <b-icon v-else icon="x-circle-fill" class="mr-1" aria-hidden="true" />
              Incorrect
            </b-button>
          </div>
        </transition>

        <template v-if="orderedCards.length > 1 && !isFlipped">
          <div v-if="orderedCards.length <= 20" class="fc-dots" role="group" aria-label="Card navigation">
            <button v-for="(_, i) in orderedCards.length" :key="i" type="button" class="fc-dot"
                    :class="{ 'fc-dot--current': i === currentIndex, 'fc-dot--correct': cardResults[i] === 'correct', 'fc-dot--incorrect': cardResults[i] === 'incorrect' }"
                    :aria-label="`Card ${i + 1}${cardResults[i] ? ', ' + cardResults[i] : ''}`"
                    :aria-current="i === currentIndex ? 'true' : 'false'" @click="goTo(i)"
            />
          </div>
          <div v-else class="fc-strip" role="group" aria-label="Card navigation">
            <button v-for="(_, i) in orderedCards.length" :key="i" type="button" class="fc-strip-seg"
                    :class="{ 'fc-strip-seg--current': i === currentIndex, 'fc-strip-seg--correct': cardResults[i] === 'correct', 'fc-strip-seg--incorrect': cardResults[i] === 'incorrect' }"
                    :aria-label="`Card ${i + 1}${cardResults[i] ? ', ' + cardResults[i] : ''}`"
                    :aria-current="i === currentIndex ? 'true' : 'false'"
                    :style="{ width: `calc(100% / ${orderedCards.length})` }" @click="goTo(i)"
            />
          </div>
        </template>
        <div v-else-if="orderedCards.length <= 1" class="fc-dots" />

        <b-button v-if="orderedCards.length > 1" variant="outline-secondary"
                  :disabled="currentIndex === orderedCards.length - 1" class="fc-nav-btn"
                  :aria-label="`Next card, card ${currentIndex + 2} of ${orderedCards.length}`"
                  @click="goTo(currentIndex + 1)"
        >
          Next
          <b-icon icon="chevron-right" aria-hidden="true" />
        </b-button>
      </div>

      <!-- ─── End-of-deck summary ──────────────────────────────────────────── -->
      <transition name="fc-summary-fade">
        <div v-if="showSummary" ref="summaryDialog" class="fc-summary" role="dialog" aria-modal="true" tabindex="-1"
             aria-labelledby="fc-summary-title" aria-describedby="fc-summary-stats"
        >
          <div id="fc-summary-title" class="fc-summary-title">
            Session Complete
          </div>
          <div id="fc-summary-stats" class="fc-summary-stats">
            <div class="fc-stat fc-stat--correct" :aria-label="`${correctCount} correct`">
              <span class="fc-stat-num" aria-hidden="true">{{ correctCount }}</span><span class="fc-stat-lbl"
                                                                                          aria-hidden="true"
            >Correct</span>
            </div>
            <div class="fc-stat fc-stat--incorrect" :aria-label="`${incorrectCount} incorrect`">
              <span class="fc-stat-num" aria-hidden="true">{{ incorrectCount }}</span><span class="fc-stat-lbl"
                                                                                            aria-hidden="true"
            >Incorrect</span>
            </div>
            <div class="fc-stat fc-stat--unanswered" :aria-label="`${unansweredCount} skipped`">
              <span class="fc-stat-num" aria-hidden="true">{{ unansweredCount }}</span><span class="fc-stat-lbl"
                                                                                             aria-hidden="true"
            >Skipped</span>
            </div>
          </div>
          <div class="fc-summary-actions">
            <b-button variant="outline-danger" size="sm" :disabled="incorrectCount === 0" @click="reviewMissed">
              <b-icon icon="arrow-repeat" aria-hidden="true" />
              Review Incorrect
            </b-button>
            <b-button variant="primary" size="sm" @click="showSummary = false">
              Done
            </b-button>
          </div>
        </div>
      </transition>
      <div v-if="canOverrideAudio && currentCard.question_id && hasTtsEligibleSide" class="fc-recorder-panel">
        <div class="fc-recorder-panel-title">
          <b-icon icon="mic-fill" class="mr-1" aria-hidden="true" />
          Card Audio
        </div>
        <div class="fc-recorder-sides">
          <FlashcardAudioRecorder
            v-if="isTtsEligible(currentCard.frontType)"
            side="front"
            :question-id="questionId"
            :text="currentCard.term || ''"
            :tts-url="effectiveFrontTtsUrl"
            @updated="onTtsUpdated"
          />
          <FlashcardAudioRecorder
            v-if="isTtsEligible(currentCard.backType)"
            side="back"
            :question-id="questionId"
            :text="currentCard.answer || ''"
            :tts-url="effectiveBackTtsUrl"
            @updated="onTtsUpdated"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'
import FlashcardAudioRecorder from '../FlashcardAudioRecorder.vue'
import { mapGetters } from 'vuex'
import { v4 as uuidv4 } from 'uuid'
import { canEdit } from '../../helpers/Questions'

// ── CardMedia sub-component ────────────────────────────────────────────────
const CardMedia = {
  name: 'CardMedia',
  props: { card: Object, side: String },
  data: () => ({ longDescOpen: false }),
  computed: {
    mediaType () {
      return this.card[`${this.side}MediaType`]
    },
    mediaUrl () {
      return this.card[`${this.side}MediaUrl`]
    },
    mediaAlt () {
      return this.card[`${this.side}MediaAlt`] || ''
    },
    decorative () {
      return this.card[`${this.side}MediaDecorative`]
    },
    caption () {
      return this.card[`${this.side}MediaCaption`]
    },
    longDesc () {
      return this.card[`${this.side}MediaLongDesc`]
    },
    vttUrl () {
      return this.card[`${this.side}MediaVttUrl`] || ''
    },
    youtubeId () {
      return this.card[`${this.side}YoutubeId`] || ''
    },
    mediaPlayerUrl () {
      if (this.mediaType === 'youtube') {
        return `/flashcard-media?type=youtube&youtube_id=${this.youtubeId}`
      }
      if (!this.mediaUrl) return ''
      const params = new URLSearchParams({
        url: this.mediaUrl,
        type: this.mediaType,
        ...(this.vttUrl ? { vtt_url: this.vttUrl } : {})
      })
      return `/flashcard-media?${params.toString()}`
    }
  },
  template: `
    <div :style="mediaType !== 'audio' ? 'width:100%' : ''">
    <figure v-if="mediaType === 'image' && mediaUrl" class="fc-figure">
      <img :src="mediaUrl" :alt="decorative ? '' : mediaAlt" :role="decorative ? 'presentation' : undefined"
           class="fc-img"
      />
      <figcaption v-if="caption" class="fc-figcaption" style="text-align:center" v-html="caption"/>
      <div v-if="longDesc" class="fc-longdesc-wrap">
        <b-link class="fc-longdesc-toggle" @click.stop="longDescOpen = !longDescOpen">
          <b-icon :icon="longDescOpen ? 'chevron-up' : 'chevron-down'" class="mr-1"/>
          <small>{{ longDescOpen ? 'Hide' : 'Show' }} long description</small>
        </b-link>
        <div v-show="longDescOpen" class="fc-longdesc" v-html="longDesc"/>
      </div>
    </figure>
    <audio v-else-if="mediaType === 'audio' && mediaUrl" class="fc-audio fc-audio-wrap" @click.stop :src="mediaUrl"
           controls
    />
    <iframe v-else-if="mediaType === 'video' && mediaUrl" :key="mediaUrl" :src="mediaPlayerUrl" class="fc-media-iframe"
            width="100%" height="380" allowfullscreen allow="autoplay; fullscreen" frameborder="0" scrolling="no"
            title="Media player"
    />
    <iframe v-else-if="mediaType === 'youtube' && youtubeId" :key="'yt-' + youtubeId" :src="mediaPlayerUrl"
            class="fc-media-iframe" width="100%" height="380" allowfullscreen allow="autoplay; fullscreen"
            frameborder="0" title="YouTube video"
    />
    <span v-else class="fc-media-placeholder">No media uploaded</span>
    </div>
  `
}

export default {
  name: 'FlashcardViewer',
  components: { CardMedia, FlashcardAudioRecorder },
  props: {
    assessmentType: { type: String, default: '' },
    questionId: { type: Number, default: 0 },
    initialQuestionId: { type: Number, default: 0 },
    card: { type: Object, default: null },
    cards: { type: Array, default: () => [] },
    assignmentId: { type: Number, default: 0 },
    previewingQuestion: { type: Boolean, default: false },
    flashcardSettings: {
      type: Object,
      default: () => ({
        autoplay: { enabled: false, seconds: 4, student_override: false },
        random_shuffle: { enabled: false, student_override: false },
        show_hint: { enabled: true, student_override: false },
        text_to_speech: { enabled: false, student_override: false },
        captions: { enabled: false, student_override: false }
      })
    }
  },

  data () {
    return {
      canOverrideAudio: false,
      currentCardQuestionId: 0,
      initialized: false,
      questionUsageFlashcardSettings: [],
      currentIndex: -1,
      orderedCards: [],
      isFlipped: false,
      hintVisible: false,
      showSummary: false,
      uuid: uuidv4(),
      cardResults: {},
      selfReport: null,
      submitting: false,
      ttsPlayingSide: null,
      autoplayActive: false,
      autoplayCountdown: 0,
      autoplayTimer: null,
      autoplayPhase: 'front',
      shuffleOn: false,
      settingsForm: { show_hint: false, captions: false, text_to_speech: false, autoplay_seconds: null },
      studentOverrides: {
        autoplay: null,
        autoplay_seconds: null,
        random_shuffle: null,
        show_hint: null,
        text_to_speech: null,
        captions: null
      },
      studentSettingsForm: {
        autoplay: null,
        autoplay_seconds: null,
        random_shuffle: null,
        show_hint: null,
        text_to_speech: null,
        captions: null
      },
      // Locally overridden TTS URLs after a manual recording, keyed by question_id → { front, back }
      localTtsUrlsMap: {}
    }
  },

  computed: {
    ...mapGetters({ user: 'auth/user' }),
    isInstructor () {
      return this.user && [2, 5].includes(this.user.role)
    },
    isAdmin: () => window.config.isAdmin,
    effectiveCards () {
      if (this.card && (!this.cards || !this.cards.length)) return [this.card]
      return this.cards
    },
    currentCard () {
      return this.orderedCards[this.currentIndex] || {}
    },

    // Effective TTS URLs — local override takes precedence over card data
    effectiveFrontTtsUrl () {
      const id = this.currentCard.question_id
      return (id && this.localTtsUrlsMap[id] && this.localTtsUrlsMap[id].front) || this.currentCard.frontTtsUrl || ''
    },
    effectiveBackTtsUrl () {
      const id = this.currentCard.question_id
      return (id && this.localTtsUrlsMap[id] && this.localTtsUrlsMap[id].back) || this.currentCard.backTtsUrl || ''
    },

    hasTtsEligibleSide () {
      return this.isTtsEligible(this.currentCard.frontType) || this.isTtsEligible(this.currentCard.backType)
    },

    resolvedSettings () {
      if (!this.flashcardSettings) return this.flashcardSettings
      const s = this.flashcardSettings
      const o = this.studentOverrides
      return {
        autoplay: {
          ...s.autoplay,
          enabled: (s.autoplay.student_override && o.autoplay !== null) ? o.autoplay : s.autoplay.enabled,
          seconds: (s.autoplay.student_override && o.autoplay_seconds !== null) ? o.autoplay_seconds : s.autoplay.seconds
        },
        random_shuffle: {
          ...s.random_shuffle,
          enabled: (s.random_shuffle.student_override && o.random_shuffle !== null) ? o.random_shuffle : s.random_shuffle.enabled
        },
        show_hint: {
          ...s.show_hint,
          enabled: (s.show_hint.student_override && o.show_hint !== null) ? o.show_hint : s.show_hint.enabled
        },
        text_to_speech: {
          ...s.text_to_speech,
          enabled: (s.text_to_speech.student_override && o.text_to_speech !== null) ? o.text_to_speech : s.text_to_speech.enabled
        },
        captions: {
          ...s.captions,
          enabled: (s.captions.student_override && o.captions !== null) ? o.captions : s.captions.enabled
        }
      }
    },

    hasStudentOverridableSettings () {
      if (!this.flashcardSettings || this.isInstructor || this.previewingQuestion) return false
      const s = this.flashcardSettings
      return (s.autoplay.student_override && s.autoplay.enabled) || s.show_hint.student_override || s.text_to_speech.student_override || s.captions.student_override
    },

    ttsEnabled () {
      if (this.assessmentType !== 'flashcard') {
        return true
      }
      const o = this.currentCard.settings_override
      if (o && o.text_to_speech !== null && o.text_to_speech !== undefined) return o.text_to_speech
      return !!(this.resolvedSettings && this.resolvedSettings.text_to_speech && this.resolvedSettings.text_to_speech.enabled)
    },

    captionsEnabled () {
      const o = this.currentCard.settings_override
      if (o && o.captions !== null && o.captions !== undefined) return o.captions
      return !!(this.resolvedSettings && this.resolvedSettings.captions && this.resolvedSettings.captions.enabled)
    },

    progressPct () {
      if (!this.orderedCards.length) return 0
      return ((this.currentIndex + 1) / this.orderedCards.length) * 100
    },

    canShowHint () {
      const o = this.currentCard.settings_override
      if (o && o.show_hint !== null && o.show_hint !== undefined) return o.show_hint
      if (!(this.resolvedSettings && this.resolvedSettings.show_hint && this.resolvedSettings.show_hint.enabled)) return false
      if ((o && o.hint_text) || this.currentCard.hint) return true
      return !!(this.currentCard.backType && ['text_only', 'text_media'].includes(this.currentCard.backType))
    },

    effectiveHintText () {
      const o = this.currentCard.settings_override
      if (o && o.hint_text) return o.hint_text
      if (this.currentCard.hint) return this.currentCard.hint
      const answer = (this.currentCard.answer || '').trim()
      if (!answer) return ''
      return answer.split(/\s+/).map(w => w.length ? w[0] + '_'.repeat(Math.max(0, w.length - 1)) : '').join(' ')
    },

    effectiveHintSrText () {
      const o = this.currentCard.settings_override
      if ((o && o.hint_text) || this.currentCard.hint) return this.effectiveHintText
      const answer = (this.currentCard.answer || '').trim()
      if (!answer) return ''
      const words = answer.split(/\s+/)
      return `Hint: the answer has ${words.length} word${words.length === 1 ? '' : 's'} starting with ${words.map(w => w[0]).join(', ')}`
    },

    effectiveAutoplaySeconds () {
      const o = this.currentCard.settings_override
      if (o && o.autoplay_seconds) return +o.autoplay_seconds
      return this.resolvedSettings.autoplay && this.resolvedSettings.autoplay.seconds ? +this.resolvedSettings.autoplay.seconds : 4
    },

    correctCount () {
      return Object.values(this.cardResults).filter(v => v === 'correct').length
    },
    incorrectCount () {
      return Object.values(this.cardResults).filter(v => v === 'incorrect').length
    },
    unansweredCount () {
      return this.orderedCards.length - Object.keys(this.cardResults).length
    }
  },

  watch: {
    async currentCard (card) {
      this.canOverrideAudio = false
      this.currentCardQuestionId = card.question_id
      this.canOverrideAudio = false
      if (this.questionId && !this.user.fake_student) {
        this.canOverrideAudio = await this.canEdit(this.isAdmin, this.user, { id: this.questionId })
      }
    },
    card () {
      this.initDeck()
      this.typesetCurrentCard()
    },
    cards (newCards, oldCards) {
      const sameCards = newCards.length === oldCards.length && newCards.every((c, i) => c.question_id === oldCards[i].question_id)
      if (!sameCards) {
        this.initDeck()
        this.typesetCurrentCard()
        return
      }
      newCards.forEach((card, i) => {
        if ((card.student_response === 'correct' || card.student_response === 'incorrect') && !this.cardResults[i]) {
          this.$set(this.cardResults, i, card.student_response)
        }
      })
    },
    currentIndex () {
      this.hintVisible = false
      this.ttsPlayingSide = null
      this.selfReport = this.cardResults[this.currentIndex] || null
      if (this.assignmentId) this.getQuestionUsageFlashcardSettings()
      this.typesetCurrentCard().then(() => this.syncCardHeight())
    },
    showSummary (val) {
      if (val) {
        this.$nextTick(() => {
          if (this.$refs.summaryDialog) this.$refs.summaryDialog.focus()
        })
      } else {
        this.$nextTick(() => {
          const s = this.$el.querySelector('.fc-scene')
          if (s) s.focus()
        })
      }
    }
  },
  async mounted () {
    window.addEventListener('keydown', this.handleKeyNav)
    if (typeof this.effectiveCards[0] === 'undefined') return
    this.initialized = false
    if (this.user.role === 3 && this.assessmentType === 'flashcard') {
      this.loadStudentOverrides()
    }
    this.initialize()
    this.typesetCurrentCard()
  },

  beforeDestroy () {
    this.stopAutoplay()
    window.removeEventListener('keydown', this.handleKeyNav)
  },
  methods: {
    canEdit,
    // Re-run MathJax typesetting scoped to the visible card faces only.
    // Called whenever the DOM content of frontFace/backFace changes
    // (card navigation, deck reload, TTS recording swap, etc.)
    typesetCurrentCard () {
      return this.$nextTick().then(() => {
        if (!window.MathJax) {
          console.warn('[FlashcardViewer] MathJax not found on window — check that the MathJax script has loaded before this component mounts.')
          return
        }
        if (!window.MathJax.typesetPromise) {
          console.warn('[FlashcardViewer] window.MathJax exists but has no typesetPromise — MathJax may still be starting up.')
          return
        }
        const els = [this.$refs.frontFace, this.$refs.backFace].filter(Boolean)
        if (!els.length) return
        return MathJax.typesetPromise(els).catch(err => {
          console.error('[FlashcardViewer] MathJax typesetPromise failed:', err)
        })
      })
    },
    syncCardHeight () {
      this.$nextTick(() => {
        const front = this.$refs.frontFace
        const back = this.$refs.backFace
        if (!front || !back) return
        const isFreeForm = this.currentCard.frontType === 'free_form' || this.currentCard.backType === 'free_form'
        const hasMedia = ['text_media', 'media'].includes(this.currentCard.frontType) || ['text_media', 'media'].includes(this.currentCard.backType)

        const scene = this.$el.querySelector('.fc-scene')
        if (!scene) return
        if (isFreeForm || hasMedia) {
          const imgs = this.$el.querySelectorAll('img')
          if (imgs.length > 0) {
            Promise.all([...imgs].map(img => img.complete ? Promise.resolve() : new Promise(resolve => {
              img.addEventListener('load', resolve, { once: true })
              img.addEventListener('error', resolve, { once: true })
            }))).then(() => this.applyHeight(front, back, scene))
          } else {
            this.applyHeight(front, back, scene)
          }
        } else {
          scene.style.minHeight = ''
        }
      })
    },

    applyHeight (front, back, scene) {
      scene.style.minHeight = Math.max(front.scrollHeight, back.scrollHeight) + 'px'
    },
    isTtsEligible (type) {
      return ['text_only', 'text_media'].includes(type)
    },

    onTtsUpdated ({ side, ttsUrl }) {
      const id = this.currentCard.question_id
      if (!id) return
      if (!this.localTtsUrlsMap[id]) this.$set(this.localTtsUrlsMap, id, {})
      this.$set(this.localTtsUrlsMap[id], side, ttsUrl)
      // Also patch orderedCards so the mic button icon reflects the new URL immediately
      if (this.orderedCards[this.currentIndex]) {
        this.$set(this.orderedCards[this.currentIndex], side === 'front' ? 'frontTtsUrl' : 'backTtsUrl', ttsUrl)
      }
    },

    onAutoplaySecondsKeydown (e) {
      if (['Backspace', 'Delete', 'Tab', 'ArrowLeft', 'ArrowRight'].includes(e.key)) return
      if (!/^[0-9]$/.test(e.key)) {
        e.preventDefault()
        return
      }
      const input = e.target
      const hasSelection = input.selectionStart !== input.selectionEnd
      if (!hasSelection && String(this.studentSettingsForm.autoplay_seconds || '').length >= 2) e.preventDefault()
    },

    onAutoplaySecondsInput (val) {
      if (val === '' || val === null) return
      const n = parseInt(val, 10)
      if (isNaN(n)) {
        this.$nextTick(() => {
          this.studentSettingsForm.autoplay_seconds = 1
        })
      } else if (n > 99) {
        this.$nextTick(() => {
          this.studentSettingsForm.autoplay_seconds = 99
        })
      }
    },

    loadStudentOverrides () {
      if (!this.assignmentId) return
      try {
        const stored = localStorage.getItem(this.localStorageKey())
        if (stored) {
          const parsed = JSON.parse(stored)
          const s = this.flashcardSettings
          if (!s) return
          if (s.autoplay.student_override && parsed.autoplay !== undefined) this.studentOverrides.autoplay = parsed.autoplay
          if (s.autoplay.student_override && parsed.autoplay_seconds !== undefined) this.studentOverrides.autoplay_seconds = parsed.autoplay_seconds
          if (s.random_shuffle.student_override && parsed.random_shuffle !== undefined) this.studentOverrides.random_shuffle = parsed.random_shuffle
          if (s.show_hint.student_override && parsed.show_hint !== undefined) this.studentOverrides.show_hint = parsed.show_hint
          if (s.text_to_speech.student_override && parsed.text_to_speech !== undefined) this.studentOverrides.text_to_speech = parsed.text_to_speech
          if (s.captions.student_override && parsed.captions !== undefined) this.studentOverrides.captions = parsed.captions
        }
      } catch (e) { /* ignore */
      }
    },

    initialize () {
      if (!this.initialized) this.initDeck()
      if (this.initialQuestionId) {
        const idx = this.orderedCards.findIndex(c => c.question_id === this.initialQuestionId)
        if (idx !== -1) this.currentIndex = idx
      }
      if (this.resolvedSettings && this.resolvedSettings.autoplay.enabled && this.user.role === 3) this.toggleAutoplay()
      if (this.resolvedSettings && this.resolvedSettings.random_shuffle.enabled && this.user.role === 3) {
        this.shuffleOn = true
        this.applyOrder()
      }
      this.$nextTick(() => this.emitControlsState())
      this.initialized = true
    },

    async getQuestionUsageFlashcardSettings () {
      if (!this.currentCardQuestionId) return
      try {
        const { data } = await axios.get(`/api/assignments/${this.assignmentId}/questions/${this.currentCardQuestionId}/flashcard-card-settings`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return
        }
        if (!data.flashcard_card_settings) return
        const s = data.flashcard_card_settings
        console.error(this.flashcardSettings)
        this.settingsForm = {
          show_hint: s.show_hint !== null ? s.show_hint : this.flashcardSettings.show_hint.enabled,
          text_to_speech: s.text_to_speech !== null ? s.text_to_speech : this.flashcardSettings.text_to_speech.enabled,
          captions: s.captions !== null ? s.captions : this.flashcardSettings.captions.enabled,
          autoplay_seconds: s.autoplay_seconds ?? null
        }
        if (this.orderedCards[this.currentIndex]) {
          this.$set(this.orderedCards[this.currentIndex], 'settings_override', {
            show_hint: s.show_hint,
            text_to_speech: s.text_to_speech,
            captions: s.captions,
            autoplay_seconds: s.autoplay_seconds
          })
        }
      } catch (error) {
        console.error(error)
        this.$noty.error(error.message)
      }
    },
    initDeck () {
      this.orderedCards = [...this.effectiveCards]
      console.error(this.orderedCards)
      if (this.assessmentType !== 'flashcard') {
        this.orderedCards[0].question_id = this.initialQuestionId
      }

      // aaaaaaaaa Do an await to get the array of the ones that can be edited in the deck.....

      this.currentIndex = 0
      this.isFlipped = false
      this.selfReport = null
      this.showSummary = false
      this.hintVisible = false
      const results = {}
      this.orderedCards.forEach((card, i) => {
        if (card.student_response === 'correct' || card.student_response === 'incorrect') results[i] = card.student_response
      })
      this.cardResults = results
      this.selfReport = this.cardResults[0] || null
      if (this.shuffleOn && this.user.role !== 2) this.applyOrder()
    },

    applyOrder () {
      if (this.shuffleOn) {
        const arr = this.effectiveCards.map((c, i) => ({ ...c, _deckIndex: i }))
        for (let i = arr.length - 1; i > 0; i--) {
          const j = Math.floor(Math.random() * (i + 1));
          [arr[i], arr[j]] = [arr[j], arr[i]]
        }
        this.orderedCards = arr
      } else {
        this.orderedCards = this.effectiveCards.map((c, i) => ({ ...c, _deckIndex: i }))
      }
    },

    toggleShuffle () {
      this.shuffleOn = !this.shuffleOn
      this.applyOrder()
      this.currentIndex = 0
      this.isFlipped = false
      this.emitControlsState()
    },

    goTo (index) {
      if (index < 0 || index >= this.orderedCards.length) return
      this.stopTts()
      this.stopAutoplay()
      const scene = this.$el.querySelector('.fc-scene')
      if (scene) scene.style.minHeight = ''
      this.currentIndex = index
      this.isFlipped = false
      this.selfReport = this.cardResults[index] || null
      if (this.autoplayActive) this.resumeAutoplay()
      if (this.orderedCards[index].question_id) this.$emit('cardChanged', this.orderedCards[index].question_id)
    },

    handleKeyNav (e) {
      const tag = document.activeElement && document.activeElement.tagName
      if (['INPUT', 'TEXTAREA', 'SELECT'].includes(tag)) return
      if (document.activeElement && document.activeElement.closest('.modal')) return
      if ((e.key === ' ' || e.key === 'Enter') && !this.showSummary) {
        e.preventDefault()
        this.handleCardClick()
        return
      }
      if (e.key === 'ArrowRight') {
        e.preventDefault()
        this.goTo(this.currentIndex + 1)
      }
      if (e.key === 'ArrowLeft') {
        e.preventDefault()
        this.goTo(this.currentIndex - 1)
      }
      if (e.key === 'ArrowUp' && this.isFlipped && !this.submitting && !this.isInstructor && !this.previewingQuestion) {
        e.preventDefault()
        this.submitSelfReport('correct')
      }
      if (e.key === 'ArrowDown' && this.isFlipped && !this.submitting && !this.isInstructor && !this.previewingQuestion) {
        e.preventDefault()
        this.submitSelfReport('incorrect')
      }
    },

    toggleTts (side) {
      const audio = this.$refs.ttsAudio
      if (!audio) return
      if (this.ttsPlayingSide === side) {
        audio.pause()
        audio.currentTime = 0
        this.ttsPlayingSide = null
        return
      }
      audio.pause()
      audio.currentTime = 0
      const url = side === 'front' ? this.effectiveFrontTtsUrl : this.effectiveBackTtsUrl
      if (!url) return
      audio.src = url
      audio.play().catch(() => {
        this.ttsPlayingSide = null
      })
      this.ttsPlayingSide = side
    },

    stopTts () {
      const a = this.$refs.ttsAudio
      if (a) {
        a.pause()
        a.currentTime = 0
      }
      this.ttsPlayingSide = null
    },
    localStorageKey () {
      return `flashcard_settings_${this.assignmentId}`
    },

    handleCardClick () {
      this.isFlipped = !this.isFlipped
      this.hintVisible = false
      this.stopTts()
      if (this.autoplayActive) {
        this.stopAutoplay()
        this.autoplayActive = false
        this.emitControlsState()
      } else {
        this.stopAutoplay()
      }
    },

    toggleHint () {
      if (!this.effectiveHintText) return
      this.hintVisible = !this.hintVisible
      if (this.hintVisible) this.typesetCurrentCard()
    },

    toggleAutoplay () {
      if (this.autoplayActive) {
        this.stopAutoplay()
        this.autoplayActive = false
      } else {
        this.autoplayActive = true
        this.resumeAutoplay()
      }
      this.emitControlsState()
    },

    resumeAutoplay () {
      this.stopAutoplay()
      const o = this.currentCard.settings_override
      if (o && o.skip_autoplay) {
        this.autoplayTimer = setTimeout(() => this.autoAdvance(), 500)
        return
      }
      this.autoplayCountdown = this.effectiveAutoplaySeconds
      this.tickAutoplay()
    },

    tickAutoplay () {
      this.autoplayTimer = setTimeout(() => {
        if (this.autoplayCountdown > 1) {
          this.autoplayCountdown--
          this.emitControlsState()
          this.tickAutoplay()
        } else if (!this.isFlipped) {
          this.isFlipped = true
          this.autoplayCountdown = this.effectiveAutoplaySeconds
          this.emitControlsState()
          this.tickAutoplay()
        } else {
          this.autoAdvance()
        }
      }, 1000)
    },

    autoAdvance () {
      if (this.currentIndex < this.orderedCards.length - 1) {
        this.currentIndex++
        this.isFlipped = false
        this.selfReport = this.cardResults[this.currentIndex] || null
        this.autoplayCountdown = this.effectiveAutoplaySeconds
        this.tickAutoplay()
      } else {
        this.stopAutoplay()
        this.autoplayActive = false
        if (!this.isInstructor && !this.previewingQuestion) this.showSummary = true
      }
    },

    startAutoplay () {
      this.autoplayActive = true
      this.resumeAutoplay()
    },
    stopAutoplay () {
      if (this.autoplayTimer) {
        clearTimeout(this.autoplayTimer)
        this.autoplayTimer = null
      }
    },

    submitSelfReport (result) {
      this.stopAutoplay()
      this.autoplayActive = false
      this.emitControlsState()
      this.selfReport = result
      this.$set(this.cardResults, this.currentIndex, result)
      this.submitting = true
      this.$emit('selfReported', { result, questionId: this.currentCard.question_id })
    },

    onSubmitResult (success) {
      this.submitting = false
      if (!success) {
        this.$delete(this.cardResults, this.currentIndex)
        this.selfReport = null
        this.$bvModal.show('modal-submission-error')
        return
      }
      if (this.unansweredCount === 0) {
        this.stopTts()
        this.showSummary = true
      } else if (this.currentIndex < this.orderedCards.length - 1) {
        this.goTo(this.currentIndex + 1)
      }
    },

    getStudentResponse () {
      return this.selfReport
    },

    reviewMissed () {
      this.showSummary = false
      const missed = this.orderedCards.map((c, i) => ({
        c,
        i
      })).filter(({ i }) => this.cardResults[i] === 'incorrect').map(({ c }) => c)
      if (!missed.length) return
      this.orderedCards = missed
      this.currentIndex = 0
      this.isFlipped = false
      this.cardResults = {}
      this.selfReport = null
    },

    async openSettings () {
      await this.getQuestionUsageFlashcardSettings()
      this.$bvModal.show(`modal-flashcard-card-settings-${this.uuid}`)
    },
    initSettings () {
    },
    resetSettingsForm () {
      this.settingsForm = { show_hint: false, captions: false, text_to_speech: false, autoplay_seconds: null }
    },
    async saveSettings () {
      const payload = { ...this.settingsForm }
      if (!payload.autoplay_seconds) payload.autoplay_seconds = null
      try {
        const { data } = await axios.patch(`/api/assignments/${this.assignmentId}/question/${this.currentCard.question_id}/flashcard-card-settings`, payload)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return
        }
        this.$set(this.orderedCards[this.currentIndex], 'settings_override', payload)
        this.$noty.success('Card settings saved.')
        this.$bvModal.hide(`modal-flashcard-card-settings-${this.uuid}`)
      } catch (error) {
        this.$noty.error(error.message)
      }
    },

    emitControlsState () {
      this.$emit('controlsStateChanged', {
        autoplayEnabled: this.resolvedSettings && this.resolvedSettings.autoplay && this.resolvedSettings.autoplay.enabled,
        autoplayActive: this.autoplayActive,
        autoplayCountdown: this.autoplayCountdown,
        shuffleOn: this.shuffleOn,
        showShuffle: !!(this.resolvedSettings && this.resolvedSettings.random_shuffle && (this.resolvedSettings.random_shuffle.student_override || this.isInstructor)),
        showSettings: this.isInstructor || this.hasStudentOverridableSettings,
        isInstructor: this.isInstructor,
        hasStudentOverridableSettings: this.hasStudentOverridableSettings
      })
    },

    externalToggleAutoplay () {
      this.toggleAutoplay()
    },
    externalToggleShuffle () {
      this.toggleShuffle()
    },
    externalOpenSettings () {
      this.isInstructor ? this.openSettings() : this.openStudentSettings()
    },

    openStudentSettings () {
      const r = this.resolvedSettings
      this.studentSettingsForm = {
        autoplay: r.autoplay.enabled,
        autoplay_seconds: r.autoplay.seconds,
        random_shuffle: r.random_shuffle.enabled,
        show_hint: r.show_hint.enabled,
        text_to_speech: r.text_to_speech.enabled,
        captions: r.captions.enabled
      }
      this.$bvModal.show(`modal-flashcard-student-settings-${this.uuid}`)
    },

    saveStudentSettings () {
      const s = this.flashcardSettings
      const toSave = {}
      if (s.autoplay.student_override && this.studentSettingsForm.autoplay) {
        const seconds = +this.studentSettingsForm.autoplay_seconds
        if (!seconds || seconds < 1) {
          this.studentSettingsForm.autoplay_seconds = 1
          this.$noty.error('Autoplay seconds must be at least 1.')
          return
        }
      }
      if (s.autoplay.student_override) {
        toSave.autoplay = this.studentSettingsForm.autoplay
        toSave.autoplay_seconds = this.studentSettingsForm.autoplay_seconds
        this.studentOverrides.autoplay = this.studentSettingsForm.autoplay
        this.studentOverrides.autoplay_seconds = this.studentSettingsForm.autoplay_seconds
      }
      if (s.random_shuffle.student_override) {
        toSave.random_shuffle = this.studentSettingsForm.random_shuffle
        this.studentOverrides.random_shuffle = this.studentSettingsForm.random_shuffle
      }
      if (s.show_hint.student_override) {
        toSave.show_hint = this.studentSettingsForm.show_hint
        this.studentOverrides.show_hint = this.studentSettingsForm.show_hint
      }
      if (s.text_to_speech.student_override) {
        toSave.text_to_speech = this.studentSettingsForm.text_to_speech
        this.studentOverrides.text_to_speech = this.studentSettingsForm.text_to_speech
      }
      if (s.captions.student_override) {
        toSave.captions = this.studentSettingsForm.captions
        this.studentOverrides.captions = this.studentSettingsForm.captions
      }
      try {
        localStorage.setItem(this.localStorageKey(), JSON.stringify(toSave))
      } catch (e) { /* ignore */
      }
      this.$bvModal.hide(`modal-flashcard-student-settings-${this.uuid}`)
    }
  }
}
</script>

<style scoped>
.flashcard-root {
  width: 100%;
}

.fc-session {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 16px 0 24px;
  gap: 12px;
  width: 100%;
}

.fc-progress-wrap {
  width: 100%;
  max-width: 700px;
  height: 5px;
  background: #e9ecef;
  border-radius: 3px;
  overflow: hidden;
}

.fc-progress-bar {
  height: 100%;
  background: linear-gradient(90deg, #4dabf7, #339af0);
  border-radius: 3px;
  transition: width 0.4s ease;
}

.fc-header {
  width: 100%;
  max-width: 700px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.fc-counter {
  font-size: 0.85rem;
  font-weight: 600;
  color: #495057;
}

.fc-counter-of {
  font-weight: 400;
  color: #adb5bd;
}

.fc-scene {
  width: 100%;
  max-width: 700px;
  min-height: 340px;
  position: relative;
  cursor: pointer;
  perspective: 1200px;
  transform-style: preserve-3d;
  outline: none;
}

.fc-scene.has-video {
  min-height: 460px;
}

.fc-scene.is-flipped .fc-front {
  transform: rotateY(-180deg);
}

.fc-scene.is-flipped .fc-back {
  transform: rotateY(0deg);
}

.fc-top-actions {
  position: absolute;
  top: 10px;
  right: 10px;
  z-index: 10;
  display: flex;
  gap: 4px;
}

.fc-icon-btn {
  background: none;
  border: none;
  padding: 4px 6px;
  border-radius: 6px;
  color: #6c757d;
  cursor: pointer;
  font-size: 1.1rem;
  line-height: 1;
  transition: color 0.15s, background 0.15s;
}

.fc-icon-btn:hover, .fc-icon-btn:focus {
  color: #0d6efd;
  background: rgba(13, 110, 253, 0.08);
  outline: 2px solid #0d6efd;
  outline-offset: 2px;
}

.fc-icon-btn--active {
  color: #0d6efd;
}

.fc-icon-btn.fc-hint-icon {
  color: #f59f00;
}

.fc-icon-btn.fc-hint-icon.fc-icon-btn--active {
  color: #e67700;
}

.fc-keyboard-shortcuts {
  font-size: 0.85rem;
  color: #555;
}

.fc-keyboard-shortcuts-title {
  font-weight: 600;
  margin-bottom: 6px;
}

.fc-keyboard-table {
  width: 100%;
  border-collapse: collapse;
}

.fc-keyboard-table td {
  padding: 3px 8px 3px 0;
  vertical-align: middle;
}

.fc-keyboard-table td:first-child {
  white-space: nowrap;
  width: 160px;
}

kbd {
  display: inline-block;
  padding: 2px 6px;
  font-size: 0.78rem;
  font-family: monospace;
  color: #212529;
  background: #f4f4f4;
  border: 1px solid #ccc;
  border-radius: 4px;
  box-shadow: 0 1px 0 #bbb;
}

.fc-face {
  position: absolute;
  width: 100%;
  min-height: 340px;
  height: 100%;
  top: 0;
  left: 0;
  border-radius: 14px;
  backface-visibility: hidden;
  -webkit-backface-visibility: hidden;
  transition: transform 0.55s cubic-bezier(0.45, 0.05, 0.55, 0.95);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 32px 32px 16px;
  box-sizing: border-box;
  box-shadow: 0 4px 24px rgba(0, 0, 0, 0.09);
  user-select: none;
}

.fc-media-center,
.fc-media-center *,
.fc-figure,
.fc-figure * {
  pointer-events: none;
}

.fc-front {
  background: #ffffff;
  border: 2px solid #dee2e6;
  transform: rotateY(0deg);
}

.fc-back {
  background: #f0f4ff;
  border: 2px solid #b8c8ff;
  transform: rotateY(180deg);
}

.fc-face-content {
  flex: 1;
  display: flex;
  justify-content: center;
  align-items: center;
  width: 100%;
}

.fc-rich {
  width: 100%;
  font-size: 1.05rem;
  line-height: 1.6;
  color: #212529;
  text-align: center;
}

.fc-term {
  flex: 1;
  font-size: 1.35rem;
  font-weight: 600;
  color: #212529;
  text-align: center;
  padding-right: 20px;
  word-break: break-word;
}

.fc-answer {
  flex: 1;
  font-size: 1.35rem;
  font-weight: 600;
  color: #212529;
  text-align: center;
  padding-right: 20px;
  word-break: break-word;
}

.fc-two-col {
  display: flex;
  flex-direction: row;
  align-items: center;
  width: 100%;
  min-width: 0;
}

.fc-media {
  flex: 1;
  min-width: 0;
  display: flex;
  justify-content: center;
  align-items: center;
  padding-left: 20px;
}

.fc-media-center {
  display: flex;
  justify-content: center;
  align-items: center;
  width: 100%;
}

.fc-hint-bubble {
  position: absolute;
  top: 46px;
  right: 10px;
  background: #fff9db;
  border: 1px solid #ffe066;
  border-radius: 8px;
  padding: 8px 28px 8px 12px;
  max-width: 240px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  z-index: 10;
}

.fc-hint-close {
  position: absolute;
  top: 4px;
  right: 4px;
  background: none;
  border: none;
  padding: 0 2px;
  line-height: 1;
  color: #adb5bd;
  cursor: pointer;
  font-size: 1rem;
  border-radius: 4px;
}

.fc-hint-close:hover {
  color: #495057;
  background: rgba(0, 0, 0, 0.06);
}

.fc-hint-label {
  display: block;
  font-size: 0.7rem;
  font-weight: 700;
  color: #e67700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 3px;
}

.fc-hint-text {
  font-size: 0.85rem;
  color: #495057;
  line-height: 1.4;
}

.fc-hint-fade-enter-active, .fc-hint-fade-leave-active {
  transition: opacity 0.2s;
}

.fc-hint-fade-enter, .fc-hint-fade-leave-to {
  opacity: 0;
}

/* ── Audio Recorder Panel ─────────────────────────────────────────────── */
.fc-recorder-panel {
  width: 100%;
  max-width: 700px;
  background: #f8f9fa;
  border: 1px solid #dee2e6;
  border-radius: 10px;
  padding: 14px 18px;
}

.fc-recorder-panel-title {
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #6c757d;
  margin-bottom: 12px;
  display: flex;
  align-items: center;
}

.fc-recorder-sides {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
}

/* ── Self-report ────────────────────────────────────────────────────────── */
.fc-self-report-buttons {
  display: flex;
  gap: 12px;
}

.fc-report-btn {
  min-width: 120px;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
}

.fc-report-slide-enter-active {
  transition: all 0.3s ease;
}

.fc-report-slide-enter {
  opacity: 0;
  transform: translateY(8px);
}

/* ── Navigation ─────────────────────────────────────────────────────────── */
.fc-nav {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  max-width: 700px;
  margin-top: 4px;
}

.fc-nav-btn {
  min-width: 80px;
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 4px;
}

.fc-dots {
  display: flex;
  gap: 6px;
  align-items: center;
  flex-wrap: wrap;
  justify-content: center;
}

.fc-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  border: none;
  background: #dee2e6;
  cursor: pointer;
  padding: 0;
  transition: background 0.2s, transform 0.15s;
}

.fc-dot:hover {
  transform: scale(1.3);
}

.fc-dot--current {
  background: #339af0;
  transform: scale(1.25);
}

.fc-dot--correct {
  background: #51cf66;
}

.fc-dot--incorrect {
  background: #ff6b6b;
}

.fc-dot--current.fc-dot--correct {
  background: #2f9e44;
}

.fc-dot--current.fc-dot--incorrect {
  background: #e03131;
}

/* ── Summary ─────────────────────────────────────────────────────────────── */
.fc-summary {
  width: 100%;
  max-width: 700px;
  background: #fff;
  border: 2px solid #dee2e6;
  border-radius: 14px;
  padding: 28px 32px;
  text-align: center;
  box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
}

.fc-summary-title {
  font-size: 1.1rem;
  font-weight: 700;
  color: #212529;
  margin-bottom: 18px;
}

.fc-summary-stats {
  display: flex;
  justify-content: center;
  gap: 24px;
  margin-bottom: 20px;
}

.fc-stat {
  display: flex;
  flex-direction: column;
  align-items: center;
  min-width: 72px;
}

.fc-stat-num {
  font-size: 2rem;
  font-weight: 800;
  line-height: 1;
}

.fc-stat-lbl {
  font-size: 0.78rem;
  color: #868e96;
  margin-top: 4px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.fc-stat--correct .fc-stat-num {
  color: #2f9e44;
}

.fc-stat--incorrect .fc-stat-num {
  color: #e03131;
}

.fc-stat--unanswered .fc-stat-num {
  color: #adb5bd;
}

.fc-summary-actions {
  display: flex;
  justify-content: center;
  gap: 10px;
  flex-wrap: wrap;
}

.fc-summary-fade-enter-active, .fc-summary-fade-leave-active {
  transition: opacity 0.3s;
}

.fc-summary-fade-enter, .fc-summary-fade-leave-to {
  opacity: 0;
}

/* ── Media sub-component ──────────────────────────────────────────────────── */
.fc-figure {
  margin: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.fc-img {
  max-width: 100%;
  max-height: 220px;
  border-radius: 8px;
  object-fit: contain;
}

.fc-media-iframe {
  width: 100%;
  height: 380px;
  border: none;
  display: block;
}

.fc-figcaption {
  margin-top: 6px;
  font-size: 0.85rem;
  color: #495057;
  text-align: center;
  font-style: italic;
}

.fc-longdesc-wrap {
  margin-top: 6px;
  text-align: center;
}

.fc-longdesc-toggle {
  color: #adb5bd;
  font-size: 0.78rem;
  text-decoration: none;
}

.fc-longdesc-toggle:hover {
  color: #6c757d;
  text-decoration: none;
}

.fc-longdesc {
  margin-top: 6px;
  font-size: 0.85rem;
  color: #495057;
  text-align: left;
  background: #f8f9fa;
  border-radius: 6px;
  padding: 8px 12px;
  line-height: 1.5;
}

.fc-media-placeholder {
  color: #adb5bd;
  font-style: italic;
  font-size: 0.9rem;
}

/* ── Progress strip ────────────────────────────────────────────────────── */
.fc-strip {
  display: flex;
  width: 100%;
  height: 16px;
  border-radius: 8px;
  overflow: hidden;
  gap: 2px;
  background: #dee2e6;
  padding: 0;
  margin: 0 12px;
  align-self: center;
}

.fc-strip-seg {
  flex: 1;
  height: 100%;
  border: none;
  background: #dee2e6;
  cursor: pointer;
  padding: 0;
  transition: background 0.2s;
  min-width: 4px;
  border-radius: 2px;
}

.fc-strip-seg:hover {
  filter: brightness(0.85);
}

.fc-strip-seg--current {
  background: #339af0;
}

.fc-strip-seg--correct {
  background: #51cf66;
}

.fc-strip-seg--incorrect {
  background: #ff6b6b;
}

.fc-strip-seg--current.fc-strip-seg--correct {
  background: #2f9e44;
}

.fc-strip-seg--current.fc-strip-seg--incorrect {
  background: #e03131;
}
</style>
