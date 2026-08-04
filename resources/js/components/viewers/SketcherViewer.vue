<template>
  <div>
    <iframe
      :id="sketcherViewerId || generatedId"
      ref="sketcherViewer"
      v-resize="{ log: false }"
      width="100%"
      :src="src"
      frameborder="0"
      @load="loadStructure"
    />
  </div>
</template>

<script>
import { v4 as uuidv4 } from 'uuid'

export default {
  name: 'SketcherViewer',
  props: {
    configuration: {
      type: String,
      default: ''
    },
    sketcherViewerId: {
      type: String,
      default: null
    },
    qtiJson: {
      type: Object,
      default: () => {
      }
    },
    studentResponse: {
      type: String,
      default: ''
    },
    readOnly: {
      type: Boolean,
      default: false
    }
  },
  data () {
    return {
      src: '',
      // Falls back to a per-instance unique id so that having more than one
      // SketcherViewer on the same page (e.g. an interactive one plus a
      // read-only solution view) never collide on DOM id lookups.
      generatedId: `sketcherViewer-${uuidv4()}`
    }
  },
  created () {
    window.addEventListener('message', this.receiveMessage, false)
  },
  destroyed () {
    window.removeEventListener('message', this.receiveMessage)
  },
  mounted () {
    this.src = '/api/sketcher'
    if (this.readOnly) {
      this.src = '/api/sketcher/readonly'
    } else if (this.configuration) {
      this.src = `/api/sketcher/${this.configuration}`
    }
    this.loadStructure()
  },
  methods: {
    uuidv4,
    loadStructure () {
      console.log('loading solutionStructure')
      if (!this.$refs.sketcherViewer) {
        // Guard against calls before the iframe ref exists (e.g. very early in the lifecycle).
        return
      }
      const structure = this.studentResponse ? JSON.parse(this.studentResponse) : this.qtiJson.solutionStructure
      this.$refs.sketcherViewer.contentWindow.postMessage({
        method: 'load',
        structure: structure
      }, '*')
    },
    receiveMessage (event) {
      if (event.data.submissionResults) {
        console.log(event)
        const type = event.data.correct ? 'success' : 'error'
        const message = type === 'success' ? 'That is correct.' : 'That is incorrect.'
        this.$noty[type](message)
      }
    },
    postMessage (id) {
      let method
      let origin
      switch (id) {
        case ('sketcher'):
          method = 'checkSketcher'
          origin = 'someOrigin'
          break
        case ('bio'):
          method = 'someMethod'
          origin = 'someOrigin'
          break
        default:
          alert('not a valid id')
          return false
      }
      const iframe = document.getElementById(id)

      iframe.contentWindow.postMessage(method, '*')
      iframe.contentWindow.postMessage('save', '*')
    }
  }
}
</script>
