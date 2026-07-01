<template>
  <div>
    <div v-if="false">{{ qtiJson }}</div>

    <iframe
      :id="`threeDModel-viewer-${activeIndex}`"
      v-resize="{ log: false, checkOrigin: false }"
      class="threeDModelViewer"
      width="100%"
      :src="src"
      frameborder="0"
      @load="init3DModel(`threeDModel-viewer-${activeIndex}`)"
    />
    <div v-if="qtiJson.feedback && typeof qtiJson.feedback === 'string'">
      <b-alert :key="feedbackType"
               show
               :variant="feedbackType === 'correct' ? 'success' : 'danger'"
               class="text-center"
      >
        <div v-html="formatFeedback(qtiJson.feedback)"/>
      </b-alert>
    </div>
  </div>
</template>

<script>
import { mapGetters } from 'vuex'
import { v4 as uuidv4 } from 'uuid'

export default {
  name: 'ThreeDModelViewer',
  props: {
    qtiJson: {
      type: Object,
      default: () => {
      }
    },
    submitButtonActive: {
      type: Boolean,
      default: true
    }
  },
  data: () => ({
    activeIndex: '',
    feedbackKey: 0,
    src: '',
    isReady: {}
  }),
  computed: {
    ...mapGetters({
      user: 'auth/user'
    }),
    isAnswerView () {
      return this.qtiJson.jsonType === 'answer_json'
    },
    // Works for both instructor (solutionStructure) and student (isCorrect)
    responseIsCorrect () {
      if (typeof this.qtiJson.isCorrect !== 'undefined') {
        return this.qtiJson.isCorrect
      }
      if (this.qtiJson.solutionStructure && this.qtiJson.studentResponse) {
        return this.qtiJson.studentResponse.selectedIndex === this.qtiJson.solutionStructure.selectedIndex
      }
      return null
    },
    // CHANGED: computed so it's always in sync with qtiJson, never reset by re-renders
    feedbackType () {
      if (this.responseIsCorrect === null || this.qtiJson.jsonType !== 'question_json') return ''
      return this.responseIsCorrect ? 'correct' : 'incorrect'
    }
  },
  watch: {},
  created () {
    window.addEventListener('message', this.receiveMessage, false)
  },
  beforeDestroy () {
    window.removeEventListener('message', this.receiveMessage)
  },
  mounted () {
    this.activeIndex = uuidv4()
    const params = { ...this.qtiJson.parameters }
    if (this.isAnswerView) {
      // Answer view: always green, no interaction
      params.selectionColor = '008600'
      this.src = this.buildSrc(params) + '&allowSelection=false&hideControlsButton=true'
    } else {
      // Use responseIsCorrect which handles both instructor and student cases
      if (this.qtiJson.studentResponse && this.responseIsCorrect !== null) {
        params.selectionColor = this.responseIsCorrect ? '008600' : 'dc3545'
      } else {
        params.selectionColor = params.selectionColor || '0058E6'
      }
      this.src = this.buildSrc(params) + (!this.submitButtonActive ? '&allowSelection=false' : '')
    }
  },
  methods: {
    buildSrc (params) {
      let src = 'https://devapp02.libretexts.org/?'
      if (params.modelID) {
        src += 'modelID=' + params.modelID
      } else {
        src += '&hideModel=1'
      }
      if (params.annotations) src += '&annotations=' + params.annotations
      src += '&mode=' + (params.mode || 'selection')
      if (params.BGColor) src += '&BGColor=' + params.BGColor
      if (params.modelOffset) src += '&modelOffset=' + params.modelOffset
      if (params.selectionColor) src += '&selectionColor=' + params.selectionColor
      if (params.panel === 'no') src += '&panel=hide'
      if (params.autospin === 'no') src += '&autospin=no'
      if (params.STLmatCol) src += '&STLmatCol=' + params.STLmatCol
      if (params.hideDistance) src += '&hideDistance=' + params.hideDistance
      src += '&v=' + Date.now()
      return src
    },
    formatFeedback (feedback) {
      if (typeof feedback === 'string' && feedback.includes('<p>')) {
        return feedback
          .replace(/<\/p>/g, '<br>')
          .replace(/<p>/g, '')
      }
      return typeof feedback === 'string' ? feedback : ''
    },
    receiveMessage (event) {
      if (event.data.info === 'isReady') {
        const id = event.data.id
        const threeDModelView = document.getElementById(id)
        if (event.data.status === true && id === `threeDModel-viewer-${this.activeIndex}`) {
          this.isReady[id] = true
          if (this.qtiJson && this.qtiJson.studentResponse) {
            threeDModelView.contentWindow.postMessage({
              type: 'load3DModel',
              modelInfo: {
                selectedIndex: this.qtiJson.studentResponse.selectedIndex
              }
            }, '*')

            // Switch to blue after load so subsequent clicks are blue
            if (!this.isAnswerView) {
              const color = this.qtiJson.parameters?.selectionColor?.trim()
                ? parseInt(this.qtiJson.parameters.selectionColor, 16)
                : 0x0058E6
              threeDModelView.contentWindow.postMessage({
                message: 'setSelectionColor',
                color
              }, '*')
            }
          }
        } else {
          console.log('model not quite ready yet')
        }
      }
      if (event.data.info === 'selectedPiece') {
        if (this.isAnswerView) return
        const viewerIframe = document.getElementById(`threeDModel-viewer-${this.activeIndex}`)
        if (viewerIframe && event.source === viewerIframe.contentWindow) {
          const color = this.qtiJson.parameters?.selectionColor?.trim()
            ? parseInt(this.qtiJson.parameters.selectionColor, 16)
            : 0x0058E6
          viewerIframe.contentWindow.postMessage({
            message: 'setSelectionColor',
            color
          }, '*')
        }
      }
    },
    init3DModel (id) {
      this.isReady[id] = false
      const interval = setInterval(() => {
        const threeDModelView = document.getElementById(id)
        if (this.isReady[id] === false && threeDModelView) {
          threeDModelView.contentWindow.postMessage({ message: 'ready', id: id }, '*')
        } else {
          clearInterval(interval)
        }
      }, 50)
    }
  }
}
</script>
