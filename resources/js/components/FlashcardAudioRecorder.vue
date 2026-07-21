<template>
  <div class="fc-recorder">
    <div class="fc-recorder-label">
      {{ label }}
    </div>
    <div v-if="text" ref="recorderText" class="fc-recorder-text math-tex">
      {{ text }}
    </div>

    <!-- Idle: show existing audio + record button -->
    <div v-if="recorderState === 'idle'" class="fc-recorder-controls">
      <audio v-if="ttsUrl" :src="ttsUrl" controls class="fc-recorder-audio" preload="none" />
      <span v-else class="fc-recorder-no-audio text-muted small">No audio recorded yet</span>
      <b-button
        size="sm"
        variant="outline-primary"
        class="fc-recorder-btn mt-2"
        @click="startRecording"
      >
        <b-icon icon="mic-fill" class="mr-1" aria-hidden="true" />
        {{ ttsUrl ? 'Re-record' : 'Record' }}
      </b-button>
    </div>

    <!-- Recording -->
    <div v-else-if="recorderState === 'recording'" class="fc-recorder-controls">
      <div class="fc-recorder-indicator">
        <span class="fc-recorder-dot" aria-hidden="true" />
        Recording...
      </div>
      <b-button size="sm" variant="danger" class="fc-recorder-btn mt-2" @click="stopRecording">
        <b-icon icon="stop-fill" class="mr-1" aria-hidden="true" />
        Stop
      </b-button>
    </div>

    <!-- Reviewing -->
    <div v-else-if="recorderState === 'reviewing'" class="fc-recorder-controls">
      <audio :src="reviewUrl" controls class="fc-recorder-audio" preload="auto" />
      <div class="fc-recorder-review-actions mt-2">
        <b-button size="sm" variant="outline-secondary" @click="reRecord">
          <b-icon icon="arrow-repeat" class="mr-1" aria-hidden="true" />
          Re-record
        </b-button>
        <b-button size="sm" variant="primary" @click="saveRecording">
          <b-icon icon="check2" class="mr-1" aria-hidden="true" />
          Save
        </b-button>
      </div>
    </div>

    <!-- Saving -->
    <div v-else-if="recorderState === 'saving'" class="fc-recorder-controls fc-recorder-saving">
      <b-spinner small aria-hidden="true" />
      <span class="text-muted small">Saving...</span>
    </div>

    <div v-if="errorMessage" class="fc-recorder-error mt-1">
      {{ errorMessage }}
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'FlashcardAudioRecorder',

  props: {
    side: { type: String, required: true }, // 'front' | 'back'
    questionId: { type: Number, required: true },
    text: { type: String, default: '' },
    ttsUrl: { type: String, default: '' }
  },

  data () {
    return {
      // 'idle' | 'recording' | 'reviewing' | 'saving'
      recorderState: 'idle',
      mediaRecorder: null,
      chunks: [],
      reviewBlob: null,
      reviewUrl: null,
      errorMessage: ''
    }
  },

  computed: {
    label () {
      return this.side === 'front' ? 'Front' : 'Back'
    }
  },

  watch: {
    text () {
      this.typesetText()
    }
  },

  mounted () {
    this.typesetText()
  },

  beforeDestroy () {
    this.cleanup()
  },

  methods: {
    typesetText () {
      return this.$nextTick().then(() => {
        if (!window.MathJax?.typesetPromise) return
        const el = this.$refs.recorderText
        if (!el) return
        return MathJax.typesetPromise([el]).catch(err => {
          console.error('[FlashcardAudioRecorder] MathJax typesetPromise failed:', err)
        })
      })
    },
    async startRecording () {
      this.errorMessage = ''
      try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
        this.chunks = []
        this.mediaRecorder = new MediaRecorder(stream)

        this.mediaRecorder.ondataavailable = e => {
          if (e.data && e.data.size > 0) this.chunks.push(e.data)
        }

        this.mediaRecorder.onstop = () => {
          this.reviewBlob = new Blob(this.chunks, { type: 'audio/webm' })
          if (this.reviewUrl) URL.revokeObjectURL(this.reviewUrl)
          this.reviewUrl = URL.createObjectURL(this.reviewBlob)
          this.recorderState = 'reviewing'
          // Release the mic indicator
          stream.getTracks().forEach(t => t.stop())
        }

        this.mediaRecorder.start()
        this.recorderState = 'recording'
      } catch (e) {
        this.errorMessage = 'Microphone access denied. Please allow microphone access and try again.'
      }
    },

    stopRecording () {
      if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
        this.mediaRecorder.stop()
      }
    },

    reRecord () {
      this.cleanup()
      this.recorderState = 'idle'
    },

    async saveRecording () {
      if (!this.reviewBlob) return
      this.recorderState = 'saving'
      this.errorMessage = ''

      try {
        const formData = new FormData()
        formData.append('audio', this.reviewBlob, `${this.side}.webm`)
        formData.append('side', this.side)

        const { data } = await axios.post(
          `/api/questions/${this.questionId}/flashcard-tts-override`,
          formData,
          { headers: { 'Content-Type': 'multipart/form-data' } }
        )

        if (data.type === 'error') {
          this.errorMessage = data.message
          this.recorderState = 'reviewing'
          return
        }

        this.$emit('updated', { side: this.side, ttsUrl: data.tts_url })
        this.cleanup()
        this.recorderState = 'idle'
      } catch (e) {
        this.errorMessage = 'Failed to save recording. Please try again.'
        this.recorderState = 'reviewing'
      }
    },

    cleanup () {
      if (this.reviewUrl) {
        URL.revokeObjectURL(this.reviewUrl)
        this.reviewUrl = null
      }
      this.reviewBlob = null
      this.chunks = []
      if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
        this.mediaRecorder.stop()
      }
      this.mediaRecorder = null
    }
  }
}
</script>

<style scoped>
.fc-recorder {
  flex: 1;
  min-width: 220px;
  background: #fff;
  border: 1px solid #e9ecef;
  border-radius: 8px;
  padding: 12px 14px;
}

.fc-recorder-label {
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #6c757d;
  margin-bottom: 4px;
}

.fc-recorder-text {
  font-size: 0.9rem;
  color: #212529;
  margin-bottom: 8px;
  line-height: 1.4;
}

.fc-recorder-controls {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
}

.fc-recorder-audio {
  width: 100%;
  height: 36px;
}

.fc-recorder-no-audio {
  display: block;
  margin-bottom: 2px;
}

.fc-recorder-btn {
  min-width: 110px;
}

.fc-recorder-review-actions {
  display: flex;
  gap: 8px;
}

.fc-recorder-saving {
  flex-direction: row;
  align-items: center;
  gap: 8px;
}

.fc-recorder-indicator {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.9rem;
  color: #dc3545;
  font-weight: 600;
  padding: 4px 0;
}

.fc-recorder-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #dc3545;
  flex-shrink: 0;
  animation: fc-pulse 1s ease-in-out infinite;
}

@keyframes fc-pulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50%       { opacity: 0.4; transform: scale(0.8); }
}

.fc-recorder-error {
  font-size: 0.8rem;
  color: #dc3545;
}
</style>
