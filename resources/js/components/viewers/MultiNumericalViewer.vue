<template>
  <div class="pb-2">
    <form class="form-inline">
      <div v-html="renderedPrompt"/>
    </form>
    <!-- Legacy feedback support for old questions that have it -->
    <GeneralFeedback
      v-if="qtiJson.jsonType === 'question_json' && qtiJson.feedback && !Array.isArray(qtiJson.feedback)"
      :feedback="qtiJson.feedback"
      :feedback-type="feedbackType"
    />
  </div>
</template>

<script>
import GeneralFeedback from '../feedback/GeneralFeedback'
import { successIcon, failureIcon } from '~/helpers/SuccessFailureIcons'
import { formatQuestionMediaPlayer } from '~/helpers/Questions'
import { mapGetters } from 'vuex'
import $ from 'jquery'

export default {
  name: 'MultiNumericalViewer',
  components: {
    GeneralFeedback
  },
  props: {
    qtiJson: {
      type: Object,
      default: () => {
      }
    },
    showResponseFeedback: {
      type: Boolean,
      default: true
    },
    mediaUploads: {
      type: Array,
      default: () => []
    }
  },
  data () {
    return {
      feedbackType: 'incorrect'
    }
  },
  computed: {
    ...mapGetters({
      user: 'auth/user'
    }),
    renderedPrompt () {
      if (!this.qtiJson.prompt) return ''

      const placeholders = this.qtiJson.placeholders
      const answers = this.qtiJson.studentResponse?.answers ?? []

      const regex = /(<u>.*?<\/u>)/
      const parts = String(this.qtiJson.prompt).split(regex).filter(Boolean)

      let html = ''
      let blankIndex = 0

      for (const part of parts) {
        if (part.includes('<u>') && part.includes('</u>')) {
          const studentAnswer = answers[blankIndex] ?? null
          const studentValue = studentAnswer?.response ?? ''

          html += `<input type="text"
                     class="numerical-blank form-control form-control-sm response_${blankIndex + 1}"
                     style="width:120px"
                     value="${studentValue}" />`

          if (studentAnswer && studentAnswer.hasOwnProperty('answeredCorrectly') && this.showResponseFeedback) {
            html += `<span class="pl-1">${studentAnswer.answeredCorrectly ? successIcon : failureIcon}</span>`
          }

          // Show accepted range hint after submission if tolerance > 0
          console.error('a')
          console.error(studentAnswer && this.qtiJson.jsonType === 'answer_json' && placeholders[blankIndex])
          console.error(this.qtiJson.jsonType === 'answer_json')
          console.error(placeholders[blankIndex])
          console.error('b')
          if (studentAnswer && this.qtiJson.jsonType === 'answer_json' && placeholders[blankIndex]) {
            const hint = this.rangeHint(placeholders[blankIndex], studentAnswer.response)
            if (hint) {
              html += `<span class="text-muted small ml-1">${hint}</span>`
            }
          }

          blankIndex++
        } else {
          html += part
        }
      }
      return this.formatQuestionMediaPlayer(html, this.mediaUploads)
    }
  },
  mounted () {
    // Restore legacy feedback type
    if (this.qtiJson.studentResponse?.answeredCorrectly) {
      this.feedbackType = 'correct'
    }
    $(document).on('keydown', 'input.numerical-blank', function () {
      $(this).removeClass('is-invalid-border')
    })
  },
  methods: {
    formatQuestionMediaPlayer,
    rangeHint (placeholder, value) {
      const val = parseFloat(value ?? placeholder.value)
      if (isNaN(val)) return null
      if (placeholder.toleranceType === 'relative') {
        const pct = parseFloat(placeholder.relativeTolerance)
        if (isNaN(pct) || pct <= 0) return null
        const tol = Math.abs(val) * pct / 100
        return `(${+(val - tol).toFixed(4)} to ${+(val + tol).toFixed(4)}, ±${pct}%)`
      } else {
        const tol = parseFloat(placeholder.absoluteTolerance)
        if (isNaN(tol) || tol <= 0) return null
        return `(${+(val - tol).toFixed(4)} to ${+(val + tol).toFixed(4)})`
      }
    }
  }
}
</script>
