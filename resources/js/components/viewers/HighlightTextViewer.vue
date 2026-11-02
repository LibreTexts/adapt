<template>
  <div id="highlight-text-viewer">
    <span v-if="!studentResponse" v-html="highlightedText"/>
    <HighlightResponseFeedback v-if="studentResponse"
                               :key="`highlighted-text-${highlightedTextIndex}`"
                               :highlighted-text="highlightedText"
                               :responses="qtiJson.responses"
                               :check-marks="qtiJson.check_marks"
                               :show-response-feedback="showResponseFeedback"
    />
    <GeneralFeedback
      :feedback="qtiJson.feedback"
      :feedback-type="feedbackType"
    />
  </div>
</template>

<script>
import $ from 'jquery'
import { addHighlights, toggleSelected } from '~/helpers/Highlighter'
import HighlightResponseFeedback from '../feedback/HighlightResponseFeedback'
import GeneralFeedback from '../feedback/GeneralFeedback'
import { formatQuestionMediaPlayer } from '../../helpers/Questions'

export default {
  name: 'HighlightTextViewer',
  components: {
    HighlightResponseFeedback,
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
  data: () => ({
    highlightedText: '',
    studentResponse: [],
    highlightedTextIndex: 0,
    feedbackType: ''
  }),
  mounted () {
    this.studentResponse = this.qtiJson.responses.find(response => response.selected)
    this.feedbackType = 'correct'
    for (let i = 0; i < this.qtiJson.responses.length; i++) {
      let response = this.qtiJson.responses[i]
      if (response.correctResponse !== response.selected) {
        this.feedbackType = 'incorrect'
      }
    }
    // not sure why but quotes are being replaced.  Maybe CKeditor? See storing the question serverside and HighlightText.vue
    this.qtiJson.prompt = this.qtiJson.prompt.replaceAll('&quot;', '"').replaceAll('&#39;', '\'')
    console.log(this.qtiJson.prompt)
    this.highlightedText = addHighlights(this.qtiJson.prompt, this.qtiJson.responses)
    this.highlightedText = this.formatQuestionMediaPlayer(this.highlightedText, this.mediaUploads)
    this.highlightedTextIndex++
    $(document).ready(function () {
      toggleSelected()
      $('.response').hover(function () {
        if (!($(this).hasClass('selected'))) {
          $(this).css({ 'border-color': 'black', 'border-width': '2px', 'border-style': 'dotted' })
        }
      }, function () {
        if (!($(this).hasClass('selected'))) {
          $(this).css('border-style', 'none')
        }
      })
      $('li').css('margin-bottom', '12px')
    })
  },
  methods: {
    formatQuestionMediaPlayer
  }
}
</script>
<style scoped>
#highlight-text-viewer {
  line-height: 25px;
}
</style>

