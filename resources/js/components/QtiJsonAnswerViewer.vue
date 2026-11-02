<template>
  <b-modal :id="`qti-answer-${modalId}`"
           size="lg"
           hide-header
           @shown="onModalShown"
  >
    <div v-if="qtiJson && ['forge_iteration','forge'].includes(JSON.parse(qtiJson).questionType)"
         v-html="JSON.parse(qtiJson).solution_html"
    />
    <div v-else>
      <h2 class="editable">
        Answer
      </h2>
      <QtiJsonQuestionViewer :qti-json="qtiJson"
                             :media-uploads="mediaUploads"
                             :show-qti-answer="true"
                             :show-submit="false"
                             :show-response-feedback="false"
                             :preview-or-solution="true"
      />
    </div>
    <template #modal-footer="{ ok }">
      <b-button size="sm"
                class="float-right"
                variant="primary"
                @click="$bvModal.hide(`qti-answer-${modalId}`)"
      >
        OK
      </b-button>
    </template>
  </b-modal>
</template>

<script>
import QtiJsonQuestionViewer from '~/components/QtiJsonQuestionViewer'
import { fitModalToContent } from '~/helpers/fitModalToContent'

export default {
  name: 'QtiJsonAnswerViewer',
  components: {
    QtiJsonQuestionViewer
  },
  props: {
    qtiJson: {
      type: String,
      default: ''
    },
    modalId: {
      type: Number,
      default: 0
    },
    mediaUploads: {
      type: Array,
      default: () => []
    }
  },
  beforeDestroy () {
    window.removeEventListener('resize', this.onWindowResize)
  },
  methods: {
    async onModalShown (bvEvent) {
      // This viewer can be rendered more than once with the same id on the
      // questions page, so use the modal that fired the event, not an id lookup.
      const modalEl = (bvEvent && bvEvent.target) || document.getElementById(`qti-answer-${this.modalId}`)
      if (!modalEl) return
      this.modalEl = modalEl
      this.convertMathJaxV2ToV3(modalEl)
      // typesetMath may or may not return a promise; await either way so we
      // measure the rendered math, not the raw TeX.
      try {
        await Promise.resolve(this.typesetMath(modalEl))
      } catch (e) {
        console.error(e)
      }
      // Give the browser one frame to lay out the typeset output.
      requestAnimationFrame(() => fitModalToContent(modalEl))
      // Images that finish loading later can change the content's width.
      modalEl.querySelectorAll('img').forEach(img => {
        if (!img.complete) {
          img.addEventListener('load', () => fitModalToContent(modalEl), { once: true })
        }
      })
      window.removeEventListener('resize', this.onWindowResize)
      window.addEventListener('resize', this.onWindowResize)
    },
    onWindowResize () {
      if (this.modalEl && this.modalEl.offsetParent !== null) {
        fitModalToContent(this.modalEl)
      }
    }
  }
}
</script>

<style scoped>

</style>
